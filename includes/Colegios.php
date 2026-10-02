<?php

declare(strict_types=1);

/**
 * Licencia de institución.
 *
 * Un colegio compra UNA licencia anual y reparte plan Pro entre sus profesores.
 * No es un cuarto plan: un profesor de colegio y un profesor suelto con Pro
 * pueden exactamente lo mismo. Lo que cambia es quién paga y cuántos caben.
 *
 * Dos roles dentro del colegio, independientes de `usuarios.rol` (que es el rol
 * de PLATAFORMA: creador o admin de Ludia):
 *   · coordinador → da de alta profesores y ve todos los cursos.
 *   · profesor    → crea paquetes, arma cursos y envía.
 *
 * Regla que no se afloja: **vencer no borra nada**. Igual que con las
 * suscripciones sueltas, si la licencia caduca los profesores vuelven a gratis
 * y sus paquetes quedan guardados y bloqueados. Al renovar, lo recuperan todo.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Planes.php';

/** Lo que una licencia de colegio otorga a sus profesores. */
const COLEGIO_PLAN = 'pro';

/* ---------- Leer ---------- */

function colegio_de(int $id): ?array
{
    $consulta = db()->prepare('SELECT * FROM colegios WHERE id = ?');
    $consulta->execute([$id]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/** El colegio al que pertenece una cuenta, o null si es una cuenta suelta. */
function colegio_de_usuario(?array $usuario): ?array
{
    $id = (int) ($usuario['colegio_id'] ?? 0);

    return $id > 0 ? colegio_de($id) : null;
}

/**
 * ¿La licencia está al día?
 *
 * `pagado_hasta` nula significa que todavía no se ha activado: la licencia
 * existe (alguien la creó en el panel) pero aún no otorga nada. Es distinto de
 * estar vencida, aunque el efecto para el profesor sea el mismo.
 */
function colegio_vigente(?array $colegio): bool
{
    if (!$colegio || !$colegio['activo']) {
        return false;
    }
    if (empty($colegio['pagado_hasta'])) {
        return false;
    }

    return strtotime((string) $colegio['pagado_hasta']) >= strtotime(date('Y-m-d'));
}

/** Cuántos días le quedan. Negativo = vencida hace tantos días. */
function colegio_dias_restantes(?array $colegio): ?int
{
    if (!$colegio || empty($colegio['pagado_hasta'])) {
        return null;
    }

    $hasta = strtotime((string) $colegio['pagado_hasta']);
    $hoy   = strtotime(date('Y-m-d'));

    return (int) floor(($hasta - $hoy) / 86400);
}

function colegio_profesores(int $colegioId): array
{
    $consulta = db()->prepare(
        'SELECT u.id, u.nombre, u.email, u.plan, u.rol_colegio, u.ultimo_acceso, u.created_at,
                (SELECT COUNT(*) FROM paquetes p WHERE p.usuario_id = u.id) AS paquetes,
                (SELECT COUNT(*) FROM cursos c WHERE c.profesor_id = u.id AND c.archivado = 0) AS cursos
           FROM usuarios u
          WHERE u.colegio_id = ?
       ORDER BY FIELD(u.rol_colegio, "coordinador", "profesor"), u.nombre'
    );
    $consulta->execute([$colegioId]);

    return $consulta->fetchAll();
}

/** Cuántas cuentas ocupa hoy el colegio (coordinador incluido). */
function colegio_cuantos(int $colegioId): int
{
    $consulta = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE colegio_id = ?');
    $consulta->execute([$colegioId]);

    return (int) $consulta->fetchColumn();
}

function colegios_todos(): array
{
    return db()->query(
        'SELECT c.*,
                (SELECT COUNT(*) FROM usuarios u WHERE u.colegio_id = c.id) AS cuentas,
                (SELECT COUNT(*) FROM cursos k WHERE k.colegio_id = c.id AND k.archivado = 0) AS cursos
           FROM colegios c
       ORDER BY c.nombre'
    )->fetchAll();
}

/* ---------- Permisos ---------- */

function es_coordinador(?array $usuario): bool
{
    return ($usuario['rol_colegio'] ?? null) === 'coordinador'
        && (int) ($usuario['colegio_id'] ?? 0) > 0;
}

function es_profesor_de_colegio(?array $usuario): bool
{
    return in_array($usuario['rol_colegio'] ?? null, ['coordinador', 'profesor'], true)
        && (int) ($usuario['colegio_id'] ?? 0) > 0;
}

/* ---------- Escribir ---------- */

/** @return array{ok:bool, mensaje:string, id?:int} */
function colegio_crear(string $nombre, array $campos = []): array
{
    $nombre = trim($nombre);
    if (mb_strlen($nombre) < 3) {
        return ['ok' => false, 'mensaje' => 'El nombre del colegio es muy corto.'];
    }

    db()->prepare(
        'INSERT INTO colegios (nombre, nit, ciudad, contacto_email, cupo_profesores, pagado_hasta, precio_anual)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $nombre,
        trim((string) ($campos['nit'] ?? '')) ?: null,
        trim((string) ($campos['ciudad'] ?? '')) ?: null,
        trim((string) ($campos['contacto_email'] ?? '')) ?: null,
        max(0, (int) ($campos['cupo_profesores'] ?? 30)),
        !empty($campos['pagado_hasta']) ? (string) $campos['pagado_hasta'] : null,
        max(0, (int) ($campos['precio_anual'] ?? 6000000)),
    ]);

    return ['ok' => true, 'mensaje' => 'Colegio «' . $nombre . '» creado.', 'id' => (int) db()->lastInsertId()];
}

/** @return array{ok:bool, mensaje:string} */
function colegio_guardar(int $id, array $campos): array
{
    if (!colegio_de($id)) {
        return ['ok' => false, 'mensaje' => 'Ese colegio no existe.'];
    }

    $nombre = trim((string) ($campos['nombre'] ?? ''));
    if (mb_strlen($nombre) < 3) {
        return ['ok' => false, 'mensaje' => 'El nombre del colegio es muy corto.'];
    }

    db()->prepare(
        'UPDATE colegios
            SET nombre = ?, nit = ?, ciudad = ?, contacto_email = ?,
                cupo_profesores = ?, pagado_hasta = ?, precio_anual = ?, activo = ?
          WHERE id = ?'
    )->execute([
        $nombre,
        trim((string) ($campos['nit'] ?? '')) ?: null,
        trim((string) ($campos['ciudad'] ?? '')) ?: null,
        trim((string) ($campos['contacto_email'] ?? '')) ?: null,
        max(0, (int) ($campos['cupo_profesores'] ?? 30)),
        !empty($campos['pagado_hasta']) ? (string) $campos['pagado_hasta'] : null,
        max(0, (int) ($campos['precio_anual'] ?? 0)),
        !empty($campos['activo']) ? 1 : 0,
        $id,
    ]);

    // El plan de sus cuentas depende de la licencia: si acaban de renovarla (o
    // de suspenderla), hay que reflejarlo ya, no en el próximo ingreso.
    colegio_sincronizar_planes($id);

    return ['ok' => true, 'mensaje' => 'Colegio «' . $nombre . '» actualizado.'];
}

/**
 * Pone a las cuentas del colegio el plan que les toca según la licencia.
 *
 * NO BORRA NADA: una licencia vencida solo devuelve las cuentas a gratis. Sus
 * paquetes siguen guardados y vuelven a estar disponibles al renovar, igual que
 * con las suscripciones individuales.
 *
 * Tampoco toca a quien tenga una suscripción propia pagada: si un profesor
 * pagó su Pro por su cuenta, la licencia del colegio no se la puede quitar.
 */
function colegio_sincronizar_planes(int $colegioId): int
{
    $colegio = colegio_de($colegioId);
    $plan = colegio_vigente($colegio) ? COLEGIO_PLAN : 'gratis';

    $cambiar = db()->prepare(
        'UPDATE usuarios u
            SET u.plan = ?
          WHERE u.colegio_id = ?
            AND u.plan <> ?
            AND NOT EXISTS (
                  SELECT 1 FROM suscripciones s
                   WHERE s.usuario_id = u.id AND s.pagado_hasta > NOW()
                )'
    );
    $cambiar->execute([$plan, $colegioId, $plan]);

    return $cambiar->rowCount();
}

/**
 * El coordinador da de alta a un profesor.
 *
 * Si el correo ya tiene cuenta en Ludia, se ENLAZA al colegio en vez de
 * rechazarlo: un profesor puede haber probado la herramienta por su cuenta
 * antes de que el colegio comprara la licencia, y perder su trabajo sería
 * inaceptable. Lo que no se permite es robarle la cuenta a otro colegio.
 *
 * @return array{ok:bool, mensaje:string, id?:int, enlace?:string}
 */
function colegio_agregar_profesor(int $colegioId, string $nombre, string $email): array
{
    require_once __DIR__ . '/Auth.php';

    $colegio = colegio_de($colegioId);
    if (!$colegio) {
        return ['ok' => false, 'mensaje' => 'Ese colegio no existe.'];
    }

    $email  = mb_strtolower(trim($email));
    $nombre = trim($nombre);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensaje' => 'Ese correo no parece válido.'];
    }
    if (mb_strlen($nombre) < 2) {
        return ['ok' => false, 'mensaje' => 'Escribe el nombre del profesor.'];
    }

    $cupo = (int) $colegio['cupo_profesores'];
    if ($cupo > 0 && colegio_cuantos($colegioId) >= $cupo) {
        return [
            'ok' => false,
            'mensaje' => 'La licencia cubre ' . $cupo . ' cuentas y ya están todas ocupadas. ' .
                'Quita a alguien o pide ampliar el cupo.',
        ];
    }

    $consulta = db()->prepare('SELECT id, nombre, colegio_id FROM usuarios WHERE email = ?');
    $consulta->execute([$email]);
    $existente = $consulta->fetch();

    if ($existente) {
        $suyo = (int) ($existente['colegio_id'] ?? 0);
        if ($suyo === $colegioId) {
            return ['ok' => false, 'mensaje' => 'Esa cuenta ya está en el colegio.'];
        }
        if ($suyo > 0) {
            return ['ok' => false, 'mensaje' => 'Esa cuenta ya pertenece a otro colegio.'];
        }

        db()->prepare("UPDATE usuarios SET colegio_id = ?, rol_colegio = 'profesor' WHERE id = ?")
            ->execute([$colegioId, (int) $existente['id']]);
        colegio_sincronizar_planes($colegioId);

        return [
            'ok' => true,
            'mensaje' => 'Se enlazó la cuenta de ' . $existente['nombre'] . ' al colegio, con lo que ya tenía dentro.',
            'id' => (int) $existente['id'],
        ];
    }

    // Cuenta nueva SIN contraseña: no se le inventa una. Entra por el enlace de
    // restablecimiento, igual que quien la olvida. Así el coordinador nunca
    // conoce la contraseña de su profesor.
    db()->prepare(
        "INSERT INTO usuarios (nombre, email, password_hash, plan, colegio_id, rol_colegio)
         VALUES (?, ?, NULL, 'gratis', ?, 'profesor')"
    )->execute([$nombre, $email, $colegioId]);

    $nuevoId = (int) db()->lastInsertId();
    colegio_sincronizar_planes($colegioId);

    require_once __DIR__ . '/Recuperar.php';
    $recuperar = recuperar_pedir($email);

    return [
        'ok' => true,
        'mensaje' => 'Cuenta creada para ' . $nombre . '. Pásale este enlace para que ponga su contraseña.',
        'id' => $nuevoId,
        'enlace' => $recuperar['enlace'] ?? null,
    ];
}

/** Saca a alguien del colegio. No borra la cuenta ni su trabajo. */
function colegio_quitar_profesor(int $colegioId, int $usuarioId): array
{
    $consulta = db()->prepare('SELECT nombre, rol_colegio FROM usuarios WHERE id = ? AND colegio_id = ?');
    $consulta->execute([$usuarioId, $colegioId]);
    $quien = $consulta->fetch();

    if (!$quien) {
        return ['ok' => false, 'mensaje' => 'Esa cuenta no está en el colegio.'];
    }
    if ($quien['rol_colegio'] === 'coordinador') {
        return ['ok' => false, 'mensaje' => 'No puedes quitar al coordinador. Cambia primero su rol.'];
    }

    // La cuenta y sus paquetes SIGUEN EXISTIENDO: vuelve a ser una cuenta
    // suelta en plan gratis. Sus cursos caen con la relación al colegio, así
    // que se avisa de eso en el mensaje.
    db()->prepare("UPDATE usuarios SET colegio_id = NULL, rol_colegio = NULL, plan = 'gratis' WHERE id = ?")
        ->execute([$usuarioId]);

    return ['ok' => true, 'mensaje' => $quien['nombre'] . ' salió del colegio. Su cuenta y sus paquetes siguen ahí.'];
}

/** Cambia el rol dentro del colegio. Siempre debe quedar un coordinador. */
function colegio_cambiar_rol(int $colegioId, int $usuarioId, string $rol): array
{
    if (!in_array($rol, ['coordinador', 'profesor'], true)) {
        return ['ok' => false, 'mensaje' => 'Ese rol no existe.'];
    }

    $consulta = db()->prepare('SELECT nombre, rol_colegio FROM usuarios WHERE id = ? AND colegio_id = ?');
    $consulta->execute([$usuarioId, $colegioId]);
    $quien = $consulta->fetch();

    if (!$quien) {
        return ['ok' => false, 'mensaje' => 'Esa cuenta no está en el colegio.'];
    }

    if ($quien['rol_colegio'] === 'coordinador' && $rol === 'profesor') {
        $cuantos = db()->prepare("SELECT COUNT(*) FROM usuarios WHERE colegio_id = ? AND rol_colegio = 'coordinador'");
        $cuantos->execute([$colegioId]);
        if ((int) $cuantos->fetchColumn() <= 1) {
            return ['ok' => false, 'mensaje' => 'Es el único coordinador: nombra otro antes de cambiarle el rol.'];
        }
    }

    db()->prepare('UPDATE usuarios SET rol_colegio = ? WHERE id = ?')->execute([$rol, $usuarioId]);

    return [
        'ok' => true,
        'mensaje' => $quien['nombre'] . ($rol === 'coordinador' ? ' ahora coordina el colegio.' : ' ahora es profesor.'),
    ];
}
