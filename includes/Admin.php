<?php
/**
 * Panel de administración: quién entra y qué datos ve.
 *
 * El rol vive en usuarios.rol ('creador' | 'admin'). Hasta ahora la columna
 * existía pero nadie la comprobaba: esta es la primera puerta que la usa.
 *
 * Todo lo que cambia datos exige POST + CSRF (ver app/admin.php) y vuelve a
 * comprobar el rol aquí: nunca se confía en que la pantalla ya lo hiciera.
 */

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Planes.php';
require_once __DIR__ . '/Sesiones.php';

function admin_es_admin(?array $usuario): bool
{
    return ($usuario['rol'] ?? '') === 'admin';
}

/**
 * Exige cuenta y rol de administrador.
 * Sin cuenta manda a ingresar; con cuenta pero sin permiso corta con 403.
 */
function admin_requerir(): array
{
    $usuario = auth_requerir();
    if (!admin_es_admin($usuario)) {
        http_response_code(403);
        return $usuario;   // la vista muestra el aviso y no pinta nada más
    }
    return $usuario;
}

/* ---------- Cifras de la portada del panel ---------- */

function admin_resumen(): array
{
    sesiones_limpiar_vencidas();

    $cuentas = db()->query(
        "SELECT
            COUNT(*)                                        AS total,
            SUM(plan = 'gratis')                            AS gratis,
            SUM(plan = 'estandar')                          AS estandar,
            SUM(plan = 'pro')                               AS pro,
            SUM(rol = 'admin')                              AS admins,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS nuevas_semana,
            SUM(ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS activas_semana
         FROM usuarios"
    )->fetch();

    // Paquetes y sesiones son cosas distintas desde el 17/09/2026: el paquete es
    // el contenido guardado, la sesión es cada vez que se juega o se envía.
    // Contarlos juntos, como antes, daba una cifra que no significaba nada.
    $paquetes = db()->query(
        'SELECT
            COUNT(*)                                          AS guardados,
            COALESCE(SUM(total_actividades), 0)               AS actividades,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS nuevos_semana
         FROM paquetes'
    )->fetch();

    $sesiones = db()->query(
        "SELECT
            COUNT(*)                     AS vivas,
            SUM(modo = 'vivo')           AS en_vivo,
            SUM(modo = 'enviar')         AS enviadas,
            SUM(estado = 'en_curso')     AS en_curso
         FROM sesiones WHERE expira_at > NOW()"
    )->fetch();

    $informes = db()->query('SELECT COUNT(*) AS guardados FROM sesiones WHERE informe_guardado = 1')->fetch();

    $juego = db()->query(
        'SELECT
            (SELECT COUNT(*) FROM participantes) AS participantes,
            (SELECT COUNT(*) FROM respuestas)    AS respuestas'
    )->fetch();

    $imagenes = db()->query('SELECT COUNT(*) AS archivos, COALESCE(SUM(bytes), 0) AS bytes FROM imagenes')->fetch();

    return [
        'cuentas'  => $cuentas,
        'paquetes' => $paquetes,
        'sesiones' => $sesiones,
        'informes' => $informes,
        'juego'    => $juego,
        'imagenes' => $imagenes,
    ];
}

/* ---------- Listados ---------- */

/** Cuentas, con su contenido guardado y sus sesiones abiertas. La búsqueda va por nombre o correo. */
function admin_usuarios(string $busqueda = '', int $limite = 60): array
{
    $busqueda = trim($busqueda);
    $filtro = $busqueda === '' ? '' : ' WHERE u.nombre LIKE ? OR u.email LIKE ?';

    $consulta = db()->prepare(
        // `tiene_clave` en vez del hash: la plantilla solo necesita saber SI
        // hay contraseña, y sacar hashes de contraseñas hacia una vista es un
        // hábito que no conviene crear aunque en este caso no se pintaran.
        'SELECT u.id, u.nombre, u.email, u.plan, u.rol, u.perfil, u.ultimo_acceso, u.created_at,
                u.google_id, u.permisos, (u.password_hash IS NOT NULL) AS tiene_clave,
                (SELECT COUNT(*) FROM paquetes q WHERE q.usuario_id = u.id) AS paquetes,
                (SELECT COUNT(*) FROM sesiones s WHERE s.usuario_id = u.id AND s.expira_at > NOW()) AS sesiones,
                (SELECT COUNT(*) FROM imagenes i WHERE i.usuario_id = u.id) AS imagenes,
                -- Distingue un plan pagado de uno puesto a mano desde el panel.
                (SELECT x.estado FROM suscripciones x WHERE x.usuario_id = u.id ORDER BY x.created_at DESC LIMIT 1) AS suscripcion_estado,
                (SELECT x.pagado_hasta FROM suscripciones x WHERE x.usuario_id = u.id ORDER BY x.created_at DESC LIMIT 1) AS suscripcion_hasta
         FROM usuarios u' . $filtro . '
         ORDER BY u.created_at DESC
         LIMIT ' . max(1, min(200, $limite))
    );

    $consulta->execute($busqueda === '' ? [] : ['%' . $busqueda . '%', '%' . $busqueda . '%']);
    return $consulta->fetchAll();
}

/**
 * Sesiones abiertas ahora mismo, con su dueño.
 *
 * Son PARTIDAS, no contenido: cada una nació al jugar o enviar un paquete y
 * caduca sola, salvo que se haya conservado como informe.
 */
function admin_sesiones(int $limite = 40): array
{
    sesiones_limpiar_vencidas();

    return db()->query(
        'SELECT s.codigo, s.nombre, s.modo, s.estado, s.total_actividades, s.created_at, s.expira_at,
                u.email AS dueno, u.plan AS dueno_plan,
                (SELECT COUNT(*) FROM participantes p WHERE p.sesion_id = s.id AND p.estado <> "salio") AS participantes,
                (SELECT COUNT(*) FROM respuestas r WHERE r.sesion_id = s.id) AS respuestas
         FROM sesiones s
         LEFT JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.expira_at > NOW()
         ORDER BY s.created_at DESC
         LIMIT ' . max(1, min(200, $limite))
    )->fetchAll();
}

/** Cuántos formatos hay por plan mínimo, para ver el reparto de un vistazo. */
function admin_formatos(): array
{
    return db()->query(
        "SELECT plan_minimo, estado, COUNT(*) AS cuantos
         FROM formatos
         GROUP BY plan_minimo, estado
         ORDER BY FIELD(plan_minimo, 'gratis', 'estandar', 'pro'), estado"
    )->fetchAll();
}

/** El catálogo entero, para poder mover cada actividad de plan. */
function admin_catalogo(): array
{
    return db()->query(
        "SELECT clave, nombre, grupo, estado, plan_minimo, evaluable
         FROM formatos
         WHERE estado = 'activo'
         ORDER BY FIELD(plan_minimo, 'gratis', 'estandar', 'pro'), nombre"
    )->fetchAll();
}

/* ---------- Métricas ---------- */

/**
 * Altas de cuentas y paquetes creados por día, para la gráfica del panel.
 * Devuelve siempre la serie completa (los días sin actividad van en cero), que
 * es lo que permite dibujarla sin huecos.
 */
function admin_metricas_diarias(int $dias = 14): array
{
    $dias = max(7, min(60, $dias));

    $cuentas = db()->prepare(
        'SELECT DATE(created_at) AS dia, COUNT(*) AS n FROM usuarios
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY dia'
    );
    $cuentas->execute([$dias]);

    $paquetes = db()->prepare(
        'SELECT DATE(created_at) AS dia, COUNT(*) AS n FROM sesiones
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY dia'
    );
    $paquetes->execute([$dias]);

    $porDia = static function (array $filas): array {
        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(string) $fila['dia']] = (int) $fila['n'];
        }
        return $mapa;
    };

    $mapaCuentas = $porDia($cuentas->fetchAll());
    $mapaPaquetes = $porDia($paquetes->fetchAll());

    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $dia = date('Y-m-d', strtotime('-' . $i . ' day'));
        $serie[] = [
            'dia'      => $dia,
            'etiqueta' => date('d/m', strtotime($dia)),
            'cuentas'  => $mapaCuentas[$dia] ?? 0,
            'paquetes' => $mapaPaquetes[$dia] ?? 0,
        ];
    }

    return $serie;
}

/** Ingresos y suscripciones, para saber si el negocio se mueve. */
function admin_metricas_dinero(): array
{
    $pagos = db()->query(
        "SELECT
            COALESCE(SUM(CASE WHEN estado = 'approved' THEN monto ELSE 0 END), 0) AS cobrado,
            COALESCE(SUM(CASE WHEN estado = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN monto ELSE 0 END), 0) AS cobrado_mes,
            COUNT(*) AS intentos,
            COALESCE(SUM(estado = 'approved'), 0) AS aprobados
         FROM pagos"
    )->fetch();

    $suscripciones = db()->query(
        "SELECT
            COALESCE(SUM(estado = 'activa'), 0)    AS activas,
            COALESCE(SUM(estado = 'pendiente'), 0) AS pendientes,
            COALESCE(SUM(estado = 'cancelada'), 0) AS canceladas,
            COALESCE(SUM(estado = 'vencida'), 0)   AS vencidas
         FROM suscripciones"
    )->fetch();

    // Ingreso recurrente: lo que entra cada mes si nadie cancela.
    $recurrente = (int) db()->query(
        "SELECT COALESCE(SUM(p.precio), 0)
           FROM usuarios u JOIN planes p ON p.clave = u.plan
          WHERE u.plan <> 'gratis'"
    )->fetchColumn();

    return [
        'pagos'         => $pagos,
        'suscripciones' => $suscripciones,
        'recurrente'    => $recurrente,
    ];
}

/** Qué formatos se usan de verdad: para decidir qué construir y qué cobrar. */
function admin_formatos_usados(int $limite = 8): array
{
    $filas = db()->query('SELECT contenido FROM sesiones')->fetchAll(PDO::FETCH_COLUMN);

    $cuenta = [];
    foreach ($filas as $json) {
        foreach (json_decode((string) $json, true) ?: [] as $actividad) {
            $tipo = (string) ($actividad['tipo'] ?? '');
            if ($tipo !== '') {
                $cuenta[$tipo] = ($cuenta[$tipo] ?? 0) + 1;
            }
        }
    }

    arsort($cuenta);
    $total = array_sum($cuenta) ?: 1;

    $top = [];
    foreach (array_slice($cuenta, 0, $limite, true) as $tipo => $veces) {
        $top[] = [
            'tipo'       => $tipo,
            'veces'      => $veces,
            'porcentaje' => (int) round(($veces / $total) * 100),
        ];
    }

    return $top;
}

/* ---------- Acciones ---------- */

/** @return array{ok:bool, mensaje:string} */
function admin_cambiar_plan(int $usuarioId, string $plan): array
{
    if (!plan_existe($plan)) {
        return ['ok' => false, 'mensaje' => 'Ese plan no existe.'];
    }

    $actualizar = db()->prepare('UPDATE usuarios SET plan = ? WHERE id = ?');
    $actualizar->execute([$plan, $usuarioId]);

    return $actualizar->rowCount() > 0
        ? ['ok' => true, 'mensaje' => 'Plan cambiado a ' . plan_nombre($plan) . '.']
        : ['ok' => false, 'mensaje' => 'No se encontró esa cuenta (o ya tenía ese plan).'];
}

/**
 * Cambia el rol. No deja que un administrador se quite a sí mismo el permiso:
 * es la forma más fácil de dejar la plataforma sin nadie que pueda administrarla.
 *
 * @return array{ok:bool, mensaje:string}
 */
function admin_cambiar_rol(int $usuarioId, string $rol, int $yoId): array
{
    if (!in_array($rol, ['creador', 'admin'], true)) {
        return ['ok' => false, 'mensaje' => 'Ese rol no existe.'];
    }
    if ($usuarioId === $yoId && $rol !== 'admin') {
        return ['ok' => false, 'mensaje' => 'No puedes quitarte a ti mismo el permiso de administrador.'];
    }
    if ($rol === 'creador' && admin_cuantos_admins() <= 1) {
        return ['ok' => false, 'mensaje' => 'Es el único administrador: deja otro antes de quitarle el permiso.'];
    }

    $actualizar = db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?');
    $actualizar->execute([$rol, $usuarioId]);

    return $actualizar->rowCount() > 0
        ? ['ok' => true, 'mensaje' => $rol === 'admin' ? 'Ahora es administrador.' : 'Ya no es administrador.']
        : ['ok' => false, 'mensaje' => 'No se encontró esa cuenta (o ya tenía ese rol).'];
}

/**
 * Guarda precios y topes de un plan. La comprobación de verdad vive en
 * plan_guardar() (includes/Planes.php): aquí solo se traduce el formulario.
 *
 * @return array{ok:bool, mensaje:string}
 */
function admin_guardar_plan(string $clave, array $post): array
{
    return plan_guardar($clave, [
        'precio'            => (int) ($post['precio'] ?? 0),
        // Sin esta línea el precio anual se guardaba como 0 en CADA cambio
        // hecho desde el panel —incluso al tocar un tope de participantes—, y
        // la venta anual desaparecía de la portada sin que nadie lo notara.
        'precio_anual'      => (int) ($post['precio_anual'] ?? 0),
        'max_paquetes'      => (int) ($post['max_paquetes'] ?? 0),
        'max_participantes' => (int) ($post['max_participantes'] ?? 30),
        'max_actividades'   => (int) ($post['max_actividades'] ?? 0),
        'imagenes'          => !empty($post['imagenes']),
        'informe'           => !empty($post['informe']),
        'guarda'            => !empty($post['guarda']),
    ]);
}

/**
 * Genera un enlace de restablecimiento para una cuenta, desde el panel.
 *
 * Existe porque HOY NO HAY CORREO SALIENTE (ver el masterplan): sin esto, un
 * profesor que olvide su contraseña no tiene ninguna forma de volver a entrar.
 * El administrador genera el enlace y se lo pasa por donde pueda.
 *
 * No duplica nada de la lógica delicada: llama a `recuperar_pedir()`, que es
 * quien sabe de testigos, caducidad y cuentas de Google. Si algún día se
 * cambia la regla, se cambia en un solo sitio.
 */
function admin_enlace_clave(int $usuarioId): array
{
    require_once __DIR__ . '/Recuperar.php';

    $consulta = db()->prepare('SELECT email FROM usuarios WHERE id = ?');
    $consulta->execute([$usuarioId]);
    $email = $consulta->fetchColumn();

    if ($email === false) {
        return ['ok' => false, 'mensaje' => 'No se encontró esa cuenta.'];
    }

    $resultado = recuperar_pedir((string) $email);

    // Aquí sí se dice el motivo: quien mira esta pantalla ya es administrador
    // y ve la lista de cuentas entera, así que no se le revela nada nuevo. En
    // la pantalla pública la respuesta sigue siendo siempre la misma.
    if (empty($resultado['enlace'])) {
        $motivo = $resultado['motivo'] ?? 'no se pudo generar';
        return ['ok' => false, 'mensaje' => 'No se generó el enlace: ' . $motivo . '.'];
    }

    return [
        'ok'      => true,
        'mensaje' => 'Enlace para ' . $email . ' (vale una hora, un solo uso): ' . $resultado['enlace'],
    ];
}

/* ---------- Permisos por cuenta ---------- */

/**
 * Las capacidades que se pueden conceder o quitar a una cuenta concreta.
 *
 * Cada una tiene TRES estados, no dos: «según el plan», «sí» y «no». Una
 * casilla simple no distingue «quiero que NO pueda» de «que siga a su plan», y
 * esa diferencia importa: una cuenta sin excepciones debe seguir al plan sola
 * cuando el plan cambie, sin que nadie vuelva a tocarla.
 */
const ADMIN_PERMISOS = [
    'exporta'  => 'Exportar a SCORM y HTML',
    'imagenes' => 'Imágenes en las preguntas',
    'informe'  => 'Informe con aciertos y gráficos',
    'guarda'   => 'Sus paquetes se guardan',
];

const ADMIN_TOPES = [
    'max_paquetes'      => 'Paquetes guardados',
    'max_actividades'   => 'Actividades por paquete',
    'max_participantes' => 'Participantes por sesión',
];

/** La cuenta con sus permisos ya decodificados, para pintar el formulario. */
function admin_usuario_con_permisos(int $usuarioId): ?array
{
    require_once __DIR__ . '/Planes.php';

    $consulta = db()->prepare(
        'SELECT id, nombre, email, plan, rol, permisos FROM usuarios WHERE id = ?'
    );
    $consulta->execute([$usuarioId]);
    $fila = $consulta->fetch();

    if (!$fila) {
        return null;
    }

    $fila['sueltos']     = usuario_permisos($fila);
    $fila['del_plan']    = plan_datos(plan_de($fila));
    $fila['efectivo']    = plan_capacidades($fila);
    $fila['formatos']    = count(plan_formatos_de($fila));
    $fila['formatos_pro'] = count(plan_formatos('pro'));

    return $fila;
}

/**
 * Guarda las excepciones de una cuenta.
 *
 * Solo se escribe lo que el administrador marcó como excepción. Todo lo que
 * quede en «según el plan» NO se guarda, para que la cuenta siga al plan si
 * este cambia — guardar el valor actual la congelaría sin que nadie lo note.
 *
 * @return array{ok:bool, mensaje:string}
 */
function admin_guardar_permisos(int $usuarioId, array $post): array
{
    $consulta = db()->prepare('SELECT nombre, email FROM usuarios WHERE id = ?');
    $consulta->execute([$usuarioId]);
    $quien = $consulta->fetch();

    if (!$quien) {
        return ['ok' => false, 'mensaje' => 'No se encontró esa cuenta.'];
    }

    $permisos = [];

    foreach (array_keys(ADMIN_PERMISOS) as $clave) {
        $valor = (string) ($post[$clave] ?? 'plan');
        if ($valor === 'si') {
            $permisos[$clave] = 1;
        } elseif ($valor === 'no') {
            $permisos[$clave] = 0;
        }
        // 'plan' → no se guarda nada: esa es justamente la ausencia.
    }

    if (($post['formatos'] ?? 'plan') === 'todos') {
        $permisos['formatos'] = 'todos';
    }

    foreach (array_keys(ADMIN_TOPES) as $clave) {
        $valor = trim((string) ($post[$clave] ?? ''));
        if ($valor !== '') {
            $permisos[$clave] = max(0, (int) $valor);
        }
    }

    // Sin excepciones se guarda NULL, no "{}": así la cuenta queda limpia y se
    // ve de un vistazo en la base quién tiene algo especial.
    $json = $permisos ? json_encode($permisos, JSON_UNESCAPED_UNICODE) : null;
    db()->prepare('UPDATE usuarios SET permisos = ? WHERE id = ?')->execute([$json, $usuarioId]);

    return [
        'ok' => true,
        'mensaje' => $permisos
            // «excepción» pierde la tilde al pluralizar: «excepciónes» no existe.
            ? 'Permisos de ' . $quien['nombre'] . ' actualizados (' . count($permisos) . ' ' .
              (count($permisos) === 1 ? 'excepción' : 'excepciones') . ' sobre su plan).'
            : $quien['nombre'] . ' vuelve a seguir su plan, sin excepciones.',
    ];
}

/* ---------- Licencias de institución ---------- */

/**
 * Crear y editar colegios desde el panel.
 *
 * Estas dos funciones son un ENVOLTORIO: las reglas —cupo, vigencia, a quién le
 * toca qué plan— viven en Colegios.php y se aplican en un solo sitio. Aquí solo
 * se traduce lo que llega del formulario.
 */
function admin_colegio_crear(array $post): array
{
    require_once __DIR__ . '/Colegios.php';

    return colegio_crear((string) ($post['nombre'] ?? ''), [
        'nit'             => $post['nit'] ?? '',
        'ciudad'          => $post['ciudad'] ?? '',
        'contacto_email'  => $post['contacto_email'] ?? '',
        'cupo_profesores' => $post['cupo_profesores'] ?? 30,
        'pagado_hasta'    => $post['pagado_hasta'] ?? '',
        'precio_anual'    => $post['precio_anual'] ?? 6000000,
    ]);
}

function admin_colegio_guardar(int $colegioId, array $post): array
{
    require_once __DIR__ . '/Colegios.php';

    if ($colegioId <= 0) {
        return ['ok' => false, 'mensaje' => 'No se indicó qué colegio guardar.'];
    }

    return colegio_guardar($colegioId, [
        'nombre'          => $post['nombre'] ?? '',
        'nit'             => $post['nit'] ?? '',
        'ciudad'          => $post['ciudad'] ?? '',
        'contacto_email'  => $post['contacto_email'] ?? '',
        'cupo_profesores' => $post['cupo_profesores'] ?? 30,
        'pagado_hasta'    => $post['pagado_hasta'] ?? '',
        'precio_anual'    => $post['precio_anual'] ?? 0,
        // Una casilla sin marcar no viaja en el POST: su ausencia ES el "no".
        'activo'          => !empty($post['activo']),
    ]);
}

function admin_cuantos_admins(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
}

/** Borra un paquete vivo, sea de quien sea (sus participantes y respuestas caen con él). */
function admin_eliminar_paquete(string $codigo): array
{
    if (!preg_match('/^\d{6}$/', $codigo)) {
        return ['ok' => false, 'mensaje' => 'Ese código no es válido.'];
    }

    $borrar = db()->prepare('DELETE FROM sesiones WHERE codigo = ?');
    $borrar->execute([$codigo]);

    return $borrar->rowCount() > 0
        ? ['ok' => true, 'mensaje' => 'Paquete ' . $codigo . ' eliminado.']
        : ['ok' => false, 'mensaje' => 'Ese paquete ya no existe.'];
}

/* ---------- Ayudas de presentación ---------- */

function admin_peso(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function admin_cuando(?string $fecha): string
{
    if (!$fecha) {
        return 'nunca';
    }
    $momento = strtotime($fecha);
    $minutos = (int) round((time() - $momento) / 60);

    if ($minutos < 1)    return 'ahora';
    if ($minutos < 60)   return 'hace ' . $minutos . ' min';
    if ($minutos < 1440) return 'hace ' . (int) round($minutos / 60) . ' h';
    if ($minutos < 10080) return 'hace ' . (int) round($minutos / 1440) . ' d';

    return date('d/m/Y', $momento);
}
