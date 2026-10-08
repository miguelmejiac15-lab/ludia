<?php
/**
 * POST /api/wompi-webhook.php
 *
 * Aviso de Wompi ("URL de eventos" en su panel). Aquí se desconfía de todo:
 *
 *  1. Se comprueba la firma (SHA-256 con el secreto de eventos).
 *  2. Del cuerpo solo se toma el id de la transacción; el estado, la referencia
 *     y el monto se vuelven a preguntar a Wompi.
 *  3. Un pago ya concedido no se concede dos veces (clave única en `pagos`).
 *
 * Responde 200 salvo firma inválida o fallo al consultar: con cualquier otro
 * código Wompi reintenta (a los 30 min, 3 h y 24 h), que es justo lo que
 * queremos si no pudimos verificar, y lo que NO queremos si ya entendimos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Suscripciones.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

$crudo = file_get_contents('php://input') ?: '';
$aviso = json_decode($crudo, true);
$aviso = is_array($aviso) ? $aviso : [];

if (!wompi_firma_valida($aviso)) {
    error_log('[Ludia Wompi] aviso con firma inválida: ' . substr($crudo, 0, 300));
    api_error('Firma no válida', 401);
}

if (($aviso['event'] ?? '') !== 'transaction.updated') {
    api_responder(['ok' => true, 'nota' => 'evento ignorado: ' . (string) ($aviso['event'] ?? '')]);
}

$transaccionId = (string) ($aviso['data']['transaction']['id'] ?? '');
if ($transaccionId === '') {
    api_responder(['ok' => true, 'nota' => 'aviso sin transacción']);
}

$r = suscripcion_procesar_transaccion($transaccionId);
if (!$r['ok']) {
    // No se pudo verificar con Wompi: un 503 hace que lo reintenten.
    api_error('No se pudo verificar la transacción', 503);
}

api_responder(['ok' => true, 'estado' => $r['estado'], 'concedido' => $r['concedido']]);
