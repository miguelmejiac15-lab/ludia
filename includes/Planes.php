<?php
/**
 * Planes: qué incluye cada uno y qué puede hacer una cuenta.
 *
 * Los planes son DATOS, no código: viven en la tabla `planes` para que un
 * administrador pueda cambiar precios y topes desde el panel sin tocar el
 * servidor. Lo mismo con el catálogo: `formatos.plan_minimo` decide qué
 * actividad entra en qué plan, así una actividad nueva se asigna desde el panel.
 *
 * Reglas (decididas por el usuario el 2026-09-16):
 *   · Gratis   → 3 paquetes, medio catálogo, sin imágenes.
 *   · Estándar → muchos más paquetes pero CON TOPE, casi todas las actividades.
 *   · Pro      → paquetes y actividades ILIMITADOS.
 *   · Los dos de pago usan imágenes y reciben las actividades nuevas.
 *   · Escuela  → licencia anual para una institución; da lo mismo que Pro pero
 *                se vende al colegio y NO se cobra con tarjeta (se factura, y
 *                el administrador activa la licencia). Ver Colegios.php.
 *
 * Fuente de verdad ÚNICA: el servidor. El navegador recibe una copia para
 * pintar candados, pero cualquier límite se vuelve a comprobar aquí.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Orden de menor a mayor: sirve para saber si un plan alcanza a otro.
 *
 * «Escuela» comparte nivel con Pro a propósito, no está por encima. No es un
 * plan "más grande": da exactamente lo mismo que Pro, y lo que cambia es quién
 * paga y cuántas cuentas cubre (eso vive en la tabla `colegios`). Ponerlo en un
 * nivel 3 obligaría a decidir qué formato es "solo de escuela", y no hay
 * ninguno. Compartir nivel hace que herede el catálogo completo sin inventar
 * una jerarquía que no existe.
 */
const PLAN_ESCALA = ['gratis' => 0, 'estandar' => 1, 'pro' => 2, 'escuela' => 2];

/** Valores de emergencia si la tabla aún no existe (base sin migrar). */
const PLAN_POR_DEFECTO = [
    'clave' => 'gratis', 'nombre' => 'Gratis', 'resumen' => '', 'precio' => 0, 'moneda' => 'COP',
    'max_paquetes' => 3, 'max_participantes' => 30, 'max_actividades' => 10,
    'imagenes' => false, 'informe' => false, 'guarda' => false, 'exporta' => false, 'destacado' => false, 'orden' => 10,
];

/**
 * Todos los planes, tal como están hoy en la base.
 * Se cachea por petición: se consulta en cada carga del editor.
 */
function planes_todos(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $filas = db()->query('SELECT * FROM planes ORDER BY orden')->fetchAll();
    } catch (PDOException $e) {
        error_log('[Ludia] tabla planes no disponible: ' . $e->getMessage());
        return $cache = ['gratis' => PLAN_POR_DEFECTO];
    }

    $planes = [];
    foreach ($filas as $fila) {
        $planes[$fila['clave']] = [
            'clave'             => $fila['clave'],
            'nombre'            => $fila['nombre'],
            'resumen'           => $fila['resumen'],
            'precio'            => (int) $fila['precio'],
            // 0 = este plan no se vende por año. El descuento NO se guarda: se
            // calcula al mostrarlo, para que no pueda desincronizarse del precio.
            'precio_anual'      => (int) ($fila['precio_anual'] ?? 0),
            'moneda'            => $fila['moneda'],
            'max_paquetes'      => (int) $fila['max_paquetes'],
            'max_participantes' => (int) $fila['max_participantes'],
            'max_actividades'   => (int) $fila['max_actividades'],
            'imagenes'          => (bool) $fila['imagenes'],
            'informe'           => (bool) $fila['informe'],
            'guarda'            => (bool) $fila['guarda'],
            'exporta'           => (bool) ($fila['exporta'] ?? false),
            'destacado'         => (bool) $fila['destacado'],
            'orden'             => (int) $fila['orden'],
        ];
    }

    return $cache = ($planes ?: ['gratis' => PLAN_POR_DEFECTO]);
}

function plan_existe(string $clave): bool
{
    return isset(planes_todos()[$clave]);
}

function plan_datos(string $clave): array
{
    $planes = planes_todos();
    return $planes[$clave] ?? ($planes['gratis'] ?? PLAN_POR_DEFECTO);
}

function plan_nombre(string $clave): string
{
    return (string) plan_datos($clave)['nombre'];
}

/**
 * El plan que rige AHORA para esta cuenta.
 *
 * No basta con leer `usuarios.plan`: si la suscripción venció, el acceso vuelve
 * a gratis aunque la columna siga diciendo "pro". Los paquetes no se borran —
 * quedan guardados y bloqueados—, así que al volver a pagar se recuperan.
 */
function plan_de(?array $usuario): string
{
    $plan = (string) ($usuario['plan'] ?? 'gratis');
    if (!plan_existe($plan)) {
        return 'gratis';
    }
    if ($plan === 'gratis' || empty($usuario['id'])) {
        return $plan;
    }

    require_once __DIR__ . '/Suscripciones.php';
    return suscripcion_plan_efectivo((int) $usuario['id'], $plan);
}

/**
 * Los permisos SUELTOS de una cuenta: solo las excepciones a su plan.
 *
 * Lo normal es que esto esté vacío y la cuenta se comporte como su plan. Sirve
 * para casos concretos —un profesor al que se le quiere dar exportar sin
 * subirlo de plan, una cuenta de demostración con todo abierto— sin tener que
 * inventar un plan nuevo para cada excepción.
 *
 * @return array<string, mixed>
 */
function usuario_permisos(?array $usuario): array
{
    $crudo = trim((string) ($usuario['permisos'] ?? ''));
    if ($crudo === '') {
        return [];
    }

    $datos = json_decode($crudo, true);

    return is_array($datos) ? $datos : [];
}

/**
 * Qué puede esta cuenta AHORA: lo de su plan, con sus excepciones encima.
 *
 * Este es el punto único por el que pasan `plan_permite()` y todos los topes,
 * así que mezclar aquí hace que el resto del producto respete los permisos
 * sueltos sin tocar nada más.
 */
function plan_capacidades(?array $usuario): array
{
    $delPlan = plan_datos(plan_de($usuario));
    $sueltos = usuario_permisos($usuario);

    foreach (['imagenes', 'informe', 'guarda', 'exporta'] as $clave) {
        if (array_key_exists($clave, $sueltos)) {
            $delPlan[$clave] = (bool) $sueltos[$clave];
        }
    }

    // Los topes se sustituyen enteros, no se suman: 0 significa "sin límite" y
    // sumarlo daría justo lo contrario de lo que el administrador quiso.
    foreach (['max_paquetes', 'max_actividades', 'max_participantes'] as $clave) {
        if (array_key_exists($clave, $sueltos) && $sueltos[$clave] !== '') {
            $delPlan[$clave] = max(0, (int) $sueltos[$clave]);
        }
    }

    return $delPlan;
}

function plan_permite(?array $usuario, string $capacidad): bool
{
    return (bool) (plan_capacidades($usuario)[$capacidad] ?? false);
}

/**
 * ¿Puede esta cuenta exportar a SCORM o a HTML?
 *
 * Estaba escrito como `plan_de($usuario) === 'pro'` en dos sitios, y eso dejaba
 * fuera a las cuentas de Escuela —que deben poder lo mismo que Pro— y obligaba
 * a tocar código para cambiar de idea. Ahora es una capacidad como las demás.
 */
function puede_exportar(?array $usuario): bool
{
    return plan_permite($usuario, 'exporta');
}

/* ---------- Qué formatos alcanza cada plan ---------- */

/**
 * Formatos activos agrupados por el plan mínimo que los incluye.
 * Sale de la base para que el administrador reparta las actividades nuevas.
 *
 * @return array<string, string[]>  ['gratis' => [...], 'estandar' => [...], 'pro' => [...]]
 */
function plan_formatos_por_plan(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $mapa = ['gratis' => [], 'estandar' => [], 'pro' => []];
    try {
        $filas = db()->query("SELECT clave, plan_minimo FROM formatos WHERE estado = 'activo'")->fetchAll();
        foreach ($filas as $fila) {
            $minimo = (string) $fila['plan_minimo'];
            if (isset($mapa[$minimo])) {
                $mapa[$minimo][] = (string) $fila['clave'];
            }
        }
    } catch (PDOException $e) {
        error_log('[Ludia] no se pudo leer el catálogo por plan: ' . $e->getMessage());
    }

    return $cache = $mapa;
}

/**
 * El nombre legible de un formato: 'sopa_letras' → 'Sopa de letras'.
 *
 * Sale de la tabla `formatos`, que es la fuente de verdad del servidor: el
 * catálogo de nombres vive también en assets/js/formatos.js para el editor,
 * pero el servidor no puede depender de eso.
 *
 * Se usa como RED para el título de una actividad. Desde el 18/09/2026 el
 * editor lo pone solo al crearla, pero todo lo guardado antes lo tiene vacío,
 * y sin esto la proyección mostraba « · pregunta 1 de 3» sin nombre.
 */
function formato_nombre(string $tipo): string
{
    static $nombres = null;

    if ($nombres === null) {
        $nombres = [];
        try {
            foreach (db()->query('SELECT clave, nombre FROM formatos')->fetchAll() as $fila) {
                $nombres[(string) $fila['clave']] = (string) $fila['nombre'];
            }
        } catch (PDOException $e) {
            error_log('[Ludia] no se pudo leer los nombres de formato: ' . $e->getMessage());
        }
    }

    // Sin fila en la tabla, se deja legible el propio tipo antes que vacío.
    return $nombres[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
}

/** El título de una actividad, o el nombre de su formato si no tiene. */
function actividad_titulo(array $actividad): string
{
    $titulo = trim((string) ($actividad['titulo'] ?? ''));

    return $titulo !== '' ? $titulo : formato_nombre((string) ($actividad['tipo'] ?? ''));
}

/** Los formatos que puede usar un plan: los suyos y los de los planes menores. */
function plan_formatos(string $clave): array
{
    $nivel = PLAN_ESCALA[$clave] ?? 0;
    $formatos = [];

    foreach (plan_formatos_por_plan() as $minimo => $lista) {
        if ((PLAN_ESCALA[$minimo] ?? 0) <= $nivel) {
            $formatos = array_merge($formatos, $lista);
        }
    }

    return $formatos;
}

/**
 * Los formatos que alcanza ESTA CUENTA.
 *
 * Normalmente los de su plan. Con el permiso suelto `formatos: "todos"` se le
 * abre el catálogo entero sin cambiarle el plan — que es justo lo que hacía
 * falta para una cuenta de demostración o para un caso pactado.
 */
function plan_formatos_de(?array $usuario): array
{
    $sueltos = usuario_permisos($usuario);

    if (($sueltos['formatos'] ?? '') === 'todos') {
        return plan_formatos('pro');
    }

    return plan_formatos(plan_de($usuario));
}

/** ¿Esta cuenta puede usar este formato? */
function plan_permite_formato(?array $usuario, string $tipo): bool
{
    return in_array($tipo, plan_formatos_de($usuario), true);
}

/* ---------- Topes ---------- */

/** Paquetes vivos a la vez; 0 = sin tope (Pro). */
function plan_max_paquetes(?array $usuario): int
{
    return (int) plan_capacidades($usuario)['max_paquetes'];
}

/** Actividades dentro de un paquete; 0 = sin tope (Pro). */
function plan_max_actividades(?array $usuario): int
{
    return (int) plan_capacidades($usuario)['max_actividades'];
}

function plan_max_participantes(?array $usuario): int
{
    return (int) plan_capacidades($usuario)['max_participantes'];
}

/**
 * Plan del dueño de una sesión, buscándolo por id.
 * Hace falta donde no hay usuario en sesión: el sondeo de estado lo consulta
 * el navegador del anfitrión, que se autoriza con un token, no con la cuenta.
 */
function plan_de_usuario_id(?int $usuarioId): string
{
    if (!$usuarioId) {
        return 'gratis';
    }
    static $cache = [];
    if (!isset($cache[$usuarioId])) {
        $consulta = db()->prepare('SELECT plan FROM usuarios WHERE id = ?');
        $consulta->execute([$usuarioId]);
        $plan = (string) ($consulta->fetchColumn() ?: 'gratis');
        $cache[$usuarioId] = plan_existe($plan) ? $plan : 'gratis';
    }
    return $cache[$usuarioId];
}

/** Lo que necesita el navegador para pintar candados (nunca para autorizar). */
function plan_para_js(?array $usuario): array
{
    $clave = plan_de($usuario);
    // Capacidades YA mezcladas con los permisos sueltos: si no, el editor
    // pintaría candados sobre formatos que la cuenta sí puede usar.
    $c = plan_capacidades($usuario);

    return [
        'clave'           => $clave,
        'nombre'          => $c['nombre'],
        'paquetes'        => $c['max_paquetes'],
        'actividades'     => $c['max_actividades'],
        'participantes'   => $c['max_participantes'],
        'formatos'        => plan_formatos_de($usuario),
        'formatosTotales' => count(plan_formatos('pro')),
        'imagenes'        => $c['imagenes'],
        'informe'         => $c['informe'],
        'guarda'          => $c['guarda'],
        'exporta'         => $c['exporta'],
    ];
}

/* ---------- Edición desde el panel ---------- */

/** @return array{ok:bool, mensaje:string} */
function plan_guardar(string $clave, array $campos): array
{
    if (!plan_existe($clave)) {
        return ['ok' => false, 'mensaje' => 'Ese plan no existe.'];
    }

    $precio = max(0, (int) ($campos['precio'] ?? 0));
    $precioAnual = max(0, (int) ($campos['precio_anual'] ?? 0));
    $paquetes = max(0, min(9999, (int) ($campos['max_paquetes'] ?? 0)));
    $participantes = max(1, min(9999, (int) ($campos['max_participantes'] ?? 30)));
    $actividades = max(0, min(999, (int) ($campos['max_actividades'] ?? 0)));

    if ($clave === 'gratis' && ($precio !== 0 || $precioAnual !== 0)) {
        return ['ok' => false, 'mensaje' => 'El plan gratis no puede tener precio.'];
    }

    // Un año más caro que doce meses sueltos es un error de tecleo, no una
    // oferta: mejor negarlo aquí que publicarlo en la portada.
    if ($precio > 0 && $precioAnual > $precio * 12) {
        return ['ok' => false, 'mensaje' => 'El precio anual no puede superar al de 12 meses sueltos ($' . number_format($precio * 12, 0, ',', '.') . ').'];
    }

    db()->prepare(
        'UPDATE planes
            SET precio = ?, precio_anual = ?, max_paquetes = ?, max_participantes = ?, max_actividades = ?,
                imagenes = ?, informe = ?, guarda = ?, exporta = ?
          WHERE clave = ?'
    )->execute([
        $precio,
        $precioAnual,
        $paquetes,
        $participantes,
        $actividades,
        !empty($campos['imagenes']) ? 1 : 0,
        !empty($campos['informe']) ? 1 : 0,
        !empty($campos['guarda']) ? 1 : 0,
        !empty($campos['exporta']) ? 1 : 0,
        $clave,
    ]);

    return ['ok' => true, 'mensaje' => 'Plan ' . plan_nombre($clave) . ' actualizado.'];
}

/** Cambia a qué plan pertenece una actividad del catálogo. */
function plan_asignar_formato(string $formato, string $planMinimo): array
{
    if (!isset(PLAN_ESCALA[$planMinimo])) {
        return ['ok' => false, 'mensaje' => 'Ese plan no existe.'];
    }

    $actualizar = db()->prepare('UPDATE formatos SET plan_minimo = ? WHERE clave = ?');
    $actualizar->execute([$planMinimo, $formato]);

    return $actualizar->rowCount() > 0
        ? ['ok' => true, 'mensaje' => 'Actividad movida al plan ' . plan_nombre($planMinimo) . '.']
        : ['ok' => false, 'mensaje' => 'No se encontró esa actividad (o ya estaba en ese plan).'];
}
