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
 * El cobro es POR ADELANTADO con Wompi (2026-10-08): cada fila es un pago de un
 * mes o un año. No hay nada que cancelar; si no renueva, la fecha pasa sola.
 *
 * `usuarios.plan` se conserva porque un administrador puede regalar un plan a
 * mano desde el panel, sin que medie ningún cobro.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Wompi.php';

/** Días de margen tras un cobro, para que un retraso del banco no corte el acceso. */
const SUSCRIPCION_GRACIA_DIAS = 3;

/** Con cuántos días de antelación se avisa de que el plan va a vencer. */
const SUSCRIPCION_AVISO_DIAS = 7;

/* ---------- Consultas ---------- */

/**
 * La suscripción que rige para una cuenta: la PAGADA que llega más lejos.
 *
 * Antes era "la más reciente", y eso tenía una trampa: cada vez que alguien
 * pulsa "pagar" se crea una fila pendiente, así que con solo abrir el pago y
 * cerrarlo la cuenta perdía el plan que ya tenía pagado. Las pendientes solo
 * salen si nunca se pagó ninguna.
 */
function suscripcion_de_usuario(int $usuarioId): ?array
{
    $consulta = db()->prepare(
        'SELECT * FROM suscripciones WHERE usuario_id = ?
          ORDER BY pagado_hasta IS NULL, pagado_hasta DESC, created_at DESC LIMIT 1'
    );
    $consulta->execute([$usuarioId]);
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

function suscripcion_por_referencia(string $referencia): ?array
{
    $consulta = db()->prepare('SELECT * FROM suscripciones WHERE referencia = ?');
    $consulta->execute([$referencia]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

/** ¿Está al día? Vale mientras la fecha pagada no haya pasado. */
function suscripcion_vigente(?array $suscripcion): bool
{
    if (!$suscripcion || empty($suscripcion['pagado_hasta'])) {
        return false;
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

    // Sin ningún pago (o solo intentos sin pagar): es un plan puesto a mano por
    // un administrador. Vale.
    if (!$suscripcion || empty($suscripcion['pagado_hasta'])) {
        return $planGuardado;
    }

    return suscripcion_vigente($suscripcion) ? $planGuardado : 'gratis';
}

/** Días que le quedan a lo pagado; null si no hay nada pagado vigente. */
function suscripcion_dias_restantes(?array $suscripcion): ?int
{
    if (!suscripcion_vigente($suscripcion)) {
        return null;
    }
    return (int) ceil((strtotime((string) $suscripcion['pagado_hasta']) - time()) / 86400);
}

/* ---------- Altas y cobros ---------- */

/**
 * Crea la fila 'pendiente' antes de mandar al usuario a Wompi, con la
 * referencia que identificará el pago cuando Wompi avise.
 *
 * El periodo se guarda AQUÍ y no se deduce después del monto: los precios de la
 * tabla `planes` cambian, y lo pagado debe dar lo que se contrató.
 *
 * @return array{id:int, referencia:string}
 */
function suscripcion_crear(int $usuarioId, string $plan, int $monto, string $periodo = 'mensual'): array
{
    $periodo = $periodo === 'anual' ? 'anual' : 'mensual';

    db()->prepare(
        'INSERT INTO suscripciones (usuario_id, plan, periodo, estado, moneda, monto) VALUES (?, ?, ?, "pendiente", ?, ?)'
    )->execute([$usuarioId, $plan, $periodo, wompi_moneda(), $monto]);
    $id = (int) db()->lastInsertId();

    // Wompi exige una referencia única por pago. El trozo al azar impide
    // adivinar la de otra persona a partir del id.
    $referencia = 'LUDIA-' . $id . '-' . bin2hex(random_bytes(4));
    db()->prepare('UPDATE suscripciones SET referencia = ? WHERE id = ?')->execute([$referencia, $id]);

    return ['id' => $id, 'referencia' => $referencia];
}

/**
 * Da por bueno un cobro: activa la suscripción, corre la fecha y sube el plan de
 * la cuenta. Es lo único que otorga acceso de pago.
 *
 * OJO con el periodo. Aquí había un `P1M` fijo, y con el cobro anual eso
 * significaba aceptar $141.960 y conceder treinta días: un fallo que no lanza
 * ningún error y que solo se descubre cuando alguien reclama. El tiempo que se
 * añade sale de lo que se contrató (`suscripciones.periodo`), no del monto.
 *
 * Si aún le quedaba tiempo pagado (en CUALQUIER fila: al renovar, el pago nuevo
 * va en una fila nueva), lo nuevo se suma encima en vez de pisarlo.
 */
function suscripcion_registrar_pago(int $suscripcionId, ?string $correoPagador = null): void
{
    $suscripcion = suscripcion_por_id($suscripcionId);
    if (!$suscripcion) {
        return;
    }

    $consulta = db()->prepare(
        'SELECT MAX(pagado_hasta) FROM suscripciones WHERE usuario_id = ? AND pagado_hasta > NOW()'
    );
    $consulta->execute([(int) $suscripcion['usuario_id']]);
    $desde = (string) ($consulta->fetchColumn() ?: 'now');

    $cuanto = ($suscripcion['periodo'] ?? 'mensual') === 'anual' ? 'P1Y' : 'P1M';

    // La gracia se da una sola vez, no en cada renovación encadenada.
    $nuevo = (new DateTimeImmutable($desde))->add(new DateInterval($cuanto));
    if ($desde === 'now') {
        $nuevo = $nuevo->add(new DateInterval('P' . SUSCRIPCION_GRACIA_DIAS . 'D'));
    }

    db()->prepare(
        'UPDATE suscripciones
            SET estado = "activa", pagado_hasta = ?, ultimo_pago_at = NOW(),
                payer_email = COALESCE(?, payer_email), cancelada_at = NULL
          WHERE id = ?'
    )->execute([$nuevo->format('Y-m-d H:i:s'), $correoPagador, $suscripcionId]);

    db()->prepare('UPDATE usuarios SET plan = ? WHERE id = ?')
        ->execute([$suscripcion['plan'], (int) $suscripcion['usuario_id']]);
}

/**
 * Verifica una transacción con Wompi y, si está aprobada y cuadra con lo que
 * se cobró, otorga el plan. La llaman el aviso (webhook) y la página de vuelta.
 *
 * Que la llame la página de vuelta no afloja nada: no se fía de lo que diga la
 * URL, sino que le pregunta a Wompi, igual que el aviso. Lo que impide fiarse
 * del navegador es esa consulta, no quién la dispara.
 *
 * Una transacción se concede UNA sola vez aunque lleguen el aviso y la vuelta a
 * la vez, o el aviso repetido: lo garantiza la clave única de `pagos.pago_id`.
 *
 * @return array{ok:bool, estado:string, concedido:bool, error:string}
 */
function suscripcion_procesar_transaccion(string $transaccionId): array
{
    $r = wompi_ver_transaccion($transaccionId);
    if (!$r['ok']) {
        return ['ok' => false, 'estado' => '', 'concedido' => false, 'error' => $r['error']];
    }

    return suscripcion_aplicar_transaccion($r['datos']);
}

/**
 * La mitad de suscripcion_procesar_transaccion() que no habla con Wompi: recibe
 * la transacción YA consultada. Va aparte para poder probarla sin red; no se
 * debe llamar con datos que vengan del navegador o del cuerpo de un aviso.
 *
 * @param array<string,mixed> $t
 * @return array{ok:bool, estado:string, concedido:bool, error:string}
 */
function suscripcion_aplicar_transaccion(array $t): array
{
    $transaccionId = (string) ($t['id'] ?? '');
    $estado = (string) ($t['status'] ?? '');
    $suscripcion = suscripcion_por_referencia((string) ($t['reference'] ?? ''));

    if (!$suscripcion) {
        // Un pago que no salió de Ludia (otro producto del mismo comercio, un
        // enlace hecho a mano en el panel de Wompi): no es asunto nuestro.
        return ['ok' => true, 'estado' => $estado, 'concedido' => false, 'error' => 'referencia desconocida'];
    }

    // La firma de integridad ya impide cambiar el monto en la URL, pero se
    // comprueba igual: dar un plan por un pago que no lo cubre no tiene arreglo.
    $cuadra = (int) ($t['amount_in_cents'] ?? 0) === (int) $suscripcion['monto'] * 100
        && strtoupper((string) ($t['currency'] ?? '')) === strtoupper((string) $suscripcion['moneda']);

    $guardar = db()->prepare(
        'INSERT IGNORE INTO pagos (suscripcion_id, usuario_id, pago_id, estado, monto, moneda, crudo)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $guardar->execute([
        (int) $suscripcion['id'],
        (int) $suscripcion['usuario_id'],
        (string) ($t['id'] ?? $transaccionId),
        $estado ?: 'desconocido',
        intdiv((int) ($t['amount_in_cents'] ?? 0), 100),
        (string) ($t['currency'] ?? wompi_moneda()),
        json_encode($t, JSON_UNESCAPED_UNICODE),
    ]);
    $esNuevo = $guardar->rowCount() > 0;

    if ($estado !== 'APPROVED') {
        // Un PENDING que luego pasa a APPROVED llega en otro aviso: el estado
        // se actualiza aquí para que el panel no muestre uno viejo.
        if (!$esNuevo && $estado !== '') {
            db()->prepare('UPDATE pagos SET estado = ?, crudo = ? WHERE pago_id = ? AND estado <> "APPROVED"')
                ->execute([$estado, json_encode($t, JSON_UNESCAPED_UNICODE), (string) ($t['id'] ?? $transaccionId)]);
        }
        return ['ok' => true, 'estado' => $estado, 'concedido' => false, 'error' => ''];
    }

    if (!$cuadra) {
        error_log('[Ludia Wompi] transacción ' . $transaccionId . ' aprobada pero con monto/moneda que no cuadra con la suscripción ' . $suscripcion['id']);
        return ['ok' => true, 'estado' => $estado, 'concedido' => false, 'error' => 'el monto no coincide'];
    }

    // Si la fila ya existía (llegó antes como PENDING), pasarla a APPROVED es
    // lo que decide quién concede: solo una de dos peticiones simultáneas
    // consigue cambiarla.
    if (!$esNuevo) {
        $pasar = db()->prepare('UPDATE pagos SET estado = "APPROVED", crudo = ? WHERE pago_id = ? AND estado <> "APPROVED"');
        $pasar->execute([json_encode($t, JSON_UNESCAPED_UNICODE), (string) ($t['id'] ?? $transaccionId)]);
        if ($pasar->rowCount() === 0) {
            return ['ok' => true, 'estado' => $estado, 'concedido' => false, 'error' => ''];   // ya concedido antes
        }
    }

    suscripcion_registrar_pago((int) $suscripcion['id'], (string) ($t['customer_email'] ?? '') ?: null);

    return ['ok' => true, 'estado' => $estado, 'concedido' => true, 'error' => ''];
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

    if ($hasta && suscripcion_vigente($suscripcion)) {
        return 'Pagado hasta el ' . $hasta;
    }

    return match ($suscripcion['estado']) {
        'pendiente' => 'Esperando el pago',
        'activa', 'vencida' => $hasta ? 'Venció el ' . $hasta : 'Vencida',
        'cancelada' => 'Cancelada',
        'pausada'   => 'Pausada',
        default     => (string) $suscripcion['estado'],
    };
}
