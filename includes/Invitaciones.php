<?php
/**
 * Cortesías y descuentos (15/10/2026).
 *
 * Dos beneficios, para las reuniones con docentes y las redes:
 *  · CORTESÍA: Pro gratis por N meses. Se guarda como una fila de
 *    `suscripciones` con origen 'cortesia' y monto 0, así que vence sola igual
 *    que un plan pagado y al vencer la cuenta vuelve a gratis sin perder nada.
 *  · DESCUENTO: un porcentaje en UN SOLO pago (mensual o anual). Se aplica al
 *    abrir el checkout y se GASTA cuando Wompi aprueba ese pago; si la persona
 *    abre el pago y no lo termina, lo conserva.
 *
 * Llegan por enlace (tabla `invitaciones`, página app/invitacion.php) o, la
 * cortesía, a mano desde el panel. Cada cuenta recibe como mucho UNA cortesía
 * por enlace y UN descuento en toda su vida: el enlace se comparte en redes y
 * no debe servir para encadenar periodos gratis o rebajados.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Lo que se ofrece si el administrador no cambia nada. */
const INVITACION_MESES_DEFECTO = 3;
const INVITACION_PORCENTAJE_DEFECTO = 30;

/* ---------- Enlaces ---------- */

/** Código corto y legible para la URL: sin 0/O ni 1/l, que se confunden al dictarlos. */
function invitacion_codigo_nuevo(): string
{
    $letras = 'abcdefghjkmnpqrstuvwxyz23456789';
    $codigo = '';
    for ($i = 0; $i < 10; $i++) {
        $codigo .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $codigo;
}

/** @return array{ok:bool, mensaje:string} */
function invitacion_crear(string $tipo, int $meses, int $porcentaje, string $nota, ?int $usosMax, ?string $expira, ?int $creadoPor): array
{
    if (!in_array($tipo, ['cortesia', 'descuento'], true)) {
        return ['ok' => false, 'mensaje' => 'Elige si el enlace es de cortesía o de descuento.'];
    }
    $meses = max(1, min(24, $meses));
    $porcentaje = max(5, min(90, $porcentaje));
    $usosMax = ($usosMax !== null && $usosMax > 0) ? $usosMax : null;
    $expira = ($expira && strtotime($expira)) ? date('Y-m-d 23:59:59', strtotime($expira)) : null;

    db()->prepare(
        'INSERT INTO invitaciones (codigo, tipo, meses, porcentaje, nota, usos_max, expira_at, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([invitacion_codigo_nuevo(), $tipo, $meses, $porcentaje, mb_substr(trim($nota), 0, 120), $usosMax, $expira, $creadoPor]);

    return ['ok' => true, 'mensaje' => 'Enlace creado. Cópialo de la lista y compártelo.'];
}

function invitacion_por_codigo(string $codigo): ?array
{
    $consulta = db()->prepare('SELECT * FROM invitaciones WHERE codigo = ?');
    $consulta->execute([strtolower(trim($codigo))]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

/** Por qué un enlace ya no sirve, o null si sirve. */
function invitacion_problema(?array $inv): ?string
{
    if (!$inv) {
        return 'Este enlace no existe. Revisa que lo hayas copiado completo.';
    }
    if (!(int) $inv['activa']) {
        return 'Este enlace ya no está activo.';
    }
    if (!empty($inv['expira_at']) && strtotime((string) $inv['expira_at']) < time()) {
        return 'Este enlace venció el ' . date('d/m/Y', strtotime((string) $inv['expira_at'])) . '.';
    }
    if ($inv['usos_max'] !== null && (int) $inv['usos'] >= (int) $inv['usos_max']) {
        return 'Este enlace ya se usó todas las veces permitidas.';
    }
    return null;
}

function invitacion_url(string $codigo): string
{
    return url_absoluta('app/invitacion.php') . '?c=' . rawurlencode($codigo);
}

/** Texto de lo que regala el enlace, para la página y el panel. */
function invitacion_descripcion(array $inv): string
{
    return $inv['tipo'] === 'cortesia'
        ? (int) $inv['meses'] . ((int) $inv['meses'] === 1 ? ' mes' : ' meses') . ' del plan Pro, gratis'
        : (int) $inv['porcentaje'] . ' % de descuento en tu primer pago de un plan (mensual o anual)';
}

function invitacion_activar(int $id, bool $activa): array
{
    db()->prepare('UPDATE invitaciones SET activa = ? WHERE id = ?')->execute([$activa ? 1 : 0, $id]);
    return ['ok' => true, 'mensaje' => $activa ? 'Enlace activado.' : 'Enlace desactivado: ya nadie más puede usarlo.'];
}

/** @return list<array> */
function invitaciones_listar(): array
{
    return db()->query('SELECT * FROM invitaciones ORDER BY created_at DESC')->fetchAll();
}

/** Quién recibió qué, lo más reciente primero (para el panel). @return list<array> */
function beneficios_recientes(int $cuantos = 60): array
{
    $consulta = db()->prepare(
        'SELECT b.*, u.email, u.nombre, i.nota, i.codigo, s.pagado_hasta
           FROM beneficios b
           JOIN usuarios u ON u.id = b.usuario_id
      LEFT JOIN invitaciones i ON i.id = b.invitacion_id
      LEFT JOIN suscripciones s ON s.id = b.suscripcion_id
          ORDER BY b.created_at DESC LIMIT ' . max(1, min(500, $cuantos))
    );
    $consulta->execute();
    return $consulta->fetchAll();
}

/* ---------- Canjear un enlace ---------- */

/**
 * Aplica el beneficio de un enlace a una cuenta.
 *
 * El contador de usos se sube con una sola sentencia que vuelve a comprobar
 * las condiciones: si dos personas usan a la vez el último cupo, solo una lo
 * consigue.
 *
 * @return array{ok:bool, mensaje:string}
 */
function invitacion_canjear(int $usuarioId, string $codigo): array
{
    $inv = invitacion_por_codigo($codigo);
    $problema = invitacion_problema($inv);
    if ($problema) {
        return ['ok' => false, 'mensaje' => $problema];
    }

    // Una cortesía por enlace en toda la vida de la cuenta, y un solo descuento.
    $previo = db()->prepare(
        $inv['tipo'] === 'cortesia'
            ? 'SELECT COUNT(*) FROM beneficios WHERE usuario_id = ? AND tipo = "cortesia" AND invitacion_id IS NOT NULL'
            : 'SELECT COUNT(*) FROM beneficios WHERE usuario_id = ? AND tipo = "descuento"'
    );
    $previo->execute([$usuarioId]);
    if ((int) $previo->fetchColumn() > 0) {
        return ['ok' => false, 'mensaje' => $inv['tipo'] === 'cortesia'
            ? 'Tu cuenta ya recibió una cortesía antes.'
            : 'Tu cuenta ya tiene (o ya usó) un descuento.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cupo = $pdo->prepare(
            'UPDATE invitaciones SET usos = usos + 1
              WHERE id = ? AND activa = 1
                AND (usos_max IS NULL OR usos < usos_max)
                AND (expira_at IS NULL OR expira_at > NOW())'
        );
        $cupo->execute([(int) $inv['id']]);
        if ($cupo->rowCount() === 0) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'Este enlace se acaba de agotar.'];
        }

        if ($inv['tipo'] === 'cortesia') {
            $hasta = beneficio_dar_cortesia($usuarioId, (int) $inv['meses'], (int) $inv['id']);
            $mensaje = '¡Listo! Tienes el plan Pro gratis hasta el ' . date('d/m/Y', strtotime($hasta)) . '.';
        } else {
            $pdo->prepare('INSERT INTO beneficios (usuario_id, invitacion_id, tipo, porcentaje) VALUES (?, ?, "descuento", ?)')
                ->execute([$usuarioId, (int) $inv['id'], (int) $inv['porcentaje']]);
            $mensaje = '¡Listo! Tienes ' . (int) $inv['porcentaje'] . ' % de descuento en tu primer pago. Se aplica solo al elegir tu plan.';
        }

        $pdo->commit();
        return ['ok' => true, 'mensaje' => $mensaje];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Cortesía ---------- */

/**
 * Da Pro gratis por N meses. Si a la cuenta aún le quedaba tiempo (pagado o de
 * otra cortesía), los meses se suman encima en vez de pisarlo.
 * Devuelve la nueva fecha de vencimiento ('Y-m-d H:i:s').
 */
function beneficio_dar_cortesia(int $usuarioId, int $meses, ?int $invitacionId = null): string
{
    $meses = max(1, min(24, $meses));

    $consulta = db()->prepare('SELECT MAX(pagado_hasta) FROM suscripciones WHERE usuario_id = ? AND pagado_hasta > NOW()');
    $consulta->execute([$usuarioId]);
    $desde = (string) ($consulta->fetchColumn() ?: 'now');
    $hasta = (new DateTimeImmutable($desde))->add(new DateInterval('P' . $meses . 'M'))->format('Y-m-d H:i:s');

    db()->prepare(
        'INSERT INTO suscripciones (usuario_id, plan, periodo, origen, estado, moneda, monto, pagado_hasta, ultimo_pago_at)
         VALUES (?, "pro", "mensual", "cortesia", "activa", "COP", 0, ?, NOW())'
    )->execute([$usuarioId, $hasta]);
    $suscripcionId = (int) db()->lastInsertId();

    db()->prepare('UPDATE usuarios SET plan = "pro" WHERE id = ?')->execute([$usuarioId]);

    db()->prepare('INSERT INTO beneficios (usuario_id, invitacion_id, tipo, meses, suscripcion_id) VALUES (?, ?, "cortesia", ?, ?)')
        ->execute([$usuarioId, $invitacionId, $meses, $suscripcionId]);

    return $hasta;
}

/** Cortesía puesta a mano desde el panel (no cuenta para el límite de enlaces). */
function admin_dar_cortesia(int $usuarioId, int $meses = INVITACION_MESES_DEFECTO): array
{
    $consulta = db()->prepare('SELECT email FROM usuarios WHERE id = ?');
    $consulta->execute([$usuarioId]);
    $correo = $consulta->fetchColumn();
    if (!$correo) {
        return ['ok' => false, 'mensaje' => 'Esa cuenta no existe.'];
    }
    $hasta = beneficio_dar_cortesia($usuarioId, $meses, null);
    return ['ok' => true, 'mensaje' => 'Cortesía activada: ' . $correo . ' tiene Pro hasta el ' . date('d/m/Y', strtotime($hasta)) . '.'];
}

/* ---------- Descuento ---------- */

/** El descuento sin usar de una cuenta, o null. */
function descuento_disponible(int $usuarioId): ?array
{
    $consulta = db()->prepare(
        'SELECT * FROM beneficios WHERE usuario_id = ? AND tipo = "descuento" AND usado_at IS NULL ORDER BY created_at LIMIT 1'
    );
    $consulta->execute([$usuarioId]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

/** Precio con el descuento aplicado, en pesos enteros. */
function descuento_aplicar(int $monto, int $porcentaje): int
{
    return (int) round($monto * (100 - max(0, min(90, $porcentaje))) / 100);
}

/**
 * Gasta el descuento cuando Wompi aprueba el pago que lo usó. Desde ese momento
 * la cuenta paga precio completo, sea mensual o anual.
 */
function descuento_gastar(int $usuarioId, int $suscripcionId): void
{
    db()->prepare(
        'UPDATE beneficios SET usado_at = NOW(), suscripcion_id = ?
          WHERE usuario_id = ? AND tipo = "descuento" AND usado_at IS NULL
          ORDER BY created_at LIMIT 1'
    )->execute([$suscripcionId, $usuarioId]);
}
