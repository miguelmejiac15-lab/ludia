<?php
/**
 * Suscripciones: qué plan tiene de verdad cada cuenta y hasta cuándo.
 *
 * La fuente de verdad del acceso es `pagado_hasta`, no `usuarios.plan`:
 *  · Mientras esa fecha esté en el futuro, la cuenta disfruta de su plan.
 *  · Cuando pasa, el plan efectivo vuelve a gratis **sin borrar nada**: los
 *    paquetes se quedan guardados pero bloqueados, y si vuelve a pagar los
 *    recupera tal cual (decisión del 2026-09-16).
 *
 * `usuarios.plan` se conserva porque un administrador puede regalar un plan a
 * mano desde el panel, sin que medie ningún cobro.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/MercadoPago.php';

/** Días de margen tras un cobro, para que un retraso del banco no corte el acceso. */
const SUSCRIPCION_GRACIA_DIAS = 3;

/* ---------- Consultas ---------- */

/** La suscripción más reciente de una cuenta (sin importar su estado). */
function suscripcion_de_usuario(int $usuarioId): ?array
{
    $consulta = db()->prepare(
        'SELECT * FROM suscripciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1'
    );
    $consulta->execute([$usuarioId]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

function suscripcion_por_preapproval(string $preapprovalId): ?array
{
    $consulta = db()->prepare('SELECT * FROM suscripciones WHERE preapproval_id = ?');
    $consulta->execute([$preapprovalId]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

function suscripcion_por_id(int $id): ?array
{
    $consulta = db()->prepare('SELECT * FROM suscripciones WHERE id = ?');
    $consulta->execute([$id]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

/** ¿Está al día? Vale mientras la fecha pagada no haya pasado. */
function suscripcion_vigente(?array $suscripcion): bool
{
    if (!$suscripcion || empty($suscripcion['pagado_hasta'])) {
        return false;
    }
    if (in_array($suscripcion['estado'], ['cancelada', 'vencida'], true)) {
        // Una cancelación no corta el acceso a mitad del mes ya pagado.
        return strtotime((string) $suscripcion['pagado_hasta']) > time();
    }
    return strtotime((string) $suscripcion['pagado_hasta']) > time();
}

/**
 * El plan que de verdad tiene una cuenta ahora mismo.
 * Si la suscripción venció, cae a 'gratis' aunque usuarios.plan diga otra cosa.
 */
function suscripcion_plan_efectivo(int $usuarioId, string $planGuardado): string
{
    if ($planGuardado === 'gratis') {
        return 'gratis';
    }

    $suscripcion = suscripcion_de_usuario($usuarioId);

    // Sin suscripción: es un plan puesto a mano por un administrador. Vale.
    if (!$suscripcion) {
        return $planGuardado;
    }

    return suscripcion_vigente($suscripcion) ? $planGuardado : 'gratis';
}

/* ---------- Altas y cobros ---------- */

/**
 * Crea la fila 'pendiente' antes de mandar al usuario a Mercado Pago.
 *
 * El periodo se guarda AQUÍ y no se deduce después del monto: los precios de la
 * tabla `planes` cambian, y una suscripción vieja tiene que seguir renovándose
 * como se contrató.
 */
function suscripcion_crear(int $usuarioId, string $plan, int $monto, string $periodo = 'mensual'): int
{
    $periodo = $periodo === 'anual' ? 'anual' : 'mensual';

    db()->prepare(
        'INSERT INTO suscripciones (usuario_id, plan, periodo, estado, moneda, monto) VALUES (?, ?, ?, "pendiente", ?, ?)'
    )->execute([$usuarioId, $plan, $periodo, mp_moneda(), $monto]);

    return (int) db()->lastInsertId();
}

function suscripcion_guardar_preapproval(int $suscripcionId, string $preapprovalId): void
{
    db()->prepare('UPDATE suscripciones SET preapproval_id = ? WHERE id = ?')
        ->execute([$preapprovalId, $suscripcionId]);
}

/**
 * Da por bueno un cobro: activa la suscripción, corre la fecha y sube el plan de
 * la cuenta. Es lo único que otorga acceso de pago.
 *
 * OJO con el periodo. Aquí había un `P1M` fijo, y con el cobro anual eso
 * significaba aceptar $141.960 y conceder treinta días: un fallo que no lanza
 * ningún error y que solo se descubre cuando alguien reclama. El tiempo que se
 * añade sale de lo que se contrató (`suscripciones.periodo`), no del monto.
 */
function suscripcion_registrar_pago(int $suscripcionId, ?string $correoPagador = null): void
{
    $suscripcion = suscripcion_por_id($suscripcionId);
    if (!$suscripcion) {
        return;
    }

    // Si aún le quedaba tiempo, lo nuevo se suma; si no, cuenta desde hoy.
    $desde = (!empty($suscripcion['pagado_hasta']) && strtotime((string) $suscripcion['pagado_hasta']) > time())
        ? (string) $suscripcion['pagado_hasta']
        : 'now';

    $cuanto = ($suscripcion['periodo'] ?? 'mensual') === 'anual' ? 'P1Y' : 'P1M';

    $hasta = (new DateTimeImmutable($desde))
        ->add(new DateInterval($cuanto))
        ->add(new DateInterval('P' . SUSCRIPCION_GRACIA_DIAS . 'D'))
        ->format('Y-m-d H:i:s');

    db()->prepare(
        'UPDATE suscripciones
            SET estado = "activa", pagado_hasta = ?, ultimo_pago_at = NOW(),
                payer_email = COALESCE(?, payer_email), cancelada_at = NULL
          WHERE id = ?'
    )->execute([$hasta, $correoPagador, $suscripcionId]);

    db()->prepare('UPDATE usuarios SET plan = ? WHERE id = ?')
        ->execute([$suscripcion['plan'], (int) $suscripcion['usuario_id']]);
}

/** Guarda el pago. Devuelve false si ya estaba registrado (aviso repetido). */
function suscripcion_guardar_pago(array $pago, ?int $suscripcionId, ?int $usuarioId): bool
{
    $guardar = db()->prepare(
        'INSERT IGNORE INTO pagos (suscripcion_id, usuario_id, pago_id, estado, monto, moneda, crudo)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $guardar->execute([
        $suscripcionId,
        $usuarioId,
        (string) ($pago['id'] ?? ''),
        (string) ($pago['status'] ?? 'desconocido'),
        (int) round((float) ($pago['transaction_amount'] ?? 0)),
        (string) ($pago['currency_id'] ?? mp_moneda()),
        json_encode($pago, JSON_UNESCAPED_UNICODE),
    ]);

    return $guardar->rowCount() > 0;
}

/**
 * Cancela la suscripción. No baja el plan de golpe: el usuario conserva lo
 * pagado hasta que venza, y entonces cae a gratis solo.
 */
function suscripcion_cancelar(int $suscripcionId): array
{
    $suscripcion = suscripcion_por_id($suscripcionId);
    if (!$suscripcion) {
        return ['ok' => false, 'error' => 'No encontramos esa suscripción'];
    }

    if (!empty($suscripcion['preapproval_id'])) {
        $r = mp_cancelar_suscripcion((string) $suscripcion['preapproval_id']);
        if (!$r['ok'] && $r['http'] !== 404) {
            return ['ok' => false, 'error' => $r['error']];
        }
    }

    db()->prepare('UPDATE suscripciones SET estado = "cancelada", cancelada_at = NOW() WHERE id = ?')
        ->execute([$suscripcionId]);

    return ['ok' => true, 'error' => ''];
}

/** Marca como vencidas las que ya pasaron de fecha y baja esas cuentas a gratis. */
function suscripciones_vencer(): int
{
    db()->exec(
        'UPDATE suscripciones
            SET estado = "vencida"
          WHERE estado IN ("activa", "cancelada")
            AND pagado_hasta IS NOT NULL
            AND pagado_hasta < NOW()'
    );

    // El plan guardado vuelve a gratis; los paquetes NO se tocan.
    $bajadas = db()->exec(
        'UPDATE usuarios u
           JOIN suscripciones s ON s.usuario_id = u.id AND s.estado = "vencida"
            SET u.plan = "gratis"
          WHERE u.plan <> "gratis"
            AND NOT EXISTS (
              SELECT 1 FROM (SELECT * FROM suscripciones) v
               WHERE v.usuario_id = u.id AND v.pagado_hasta > NOW()
            )'
    );

    return (int) $bajadas;
}

/** Texto legible del estado, para el panel y la página de cuenta. */
function suscripcion_estado_legible(?array $suscripcion): string
{
    if (!$suscripcion) {
        return 'Sin suscripción';
    }

    $hasta = !empty($suscripcion['pagado_hasta'])
        ? date('d/m/Y', strtotime((string) $suscripcion['pagado_hasta']))
        : null;

    return match ($suscripcion['estado']) {
        'activa'    => $hasta ? 'Activa hasta el ' . $hasta : 'Activa',
        'pendiente' => 'Esperando el pago',
        'pausada'   => 'Pausada',
        'cancelada' => $hasta && strtotime((string) $suscripcion['pagado_hasta']) > time()
            ? 'Cancelada · activa hasta el ' . $hasta
            : 'Cancelada',
        'vencida'   => $hasta ? 'Vencida el ' . $hasta : 'Vencida',
        default     => (string) $suscripcion['estado'],
    };
}
