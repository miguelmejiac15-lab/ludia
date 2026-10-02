<?php
/**
 * POST /api/mercadopago-webhook.php
 *
 * Aviso de Mercado Pago. Es lo ÚNICO que otorga acceso de pago, así que aquí
 * se desconfía de todo:
 *
 *  1. Se comprueba la firma (HMAC) del aviso.
 *  2. Del cuerpo solo se toma el ID; el estado se vuelve a preguntar a la API.
 *  3. Un pago ya registrado no se procesa dos veces (clave única en `pagos`).
 *
 * Siempre responde 200 salvo firma inválida: si devolviéramos error, Mercado
 * Pago reintentaría en bucle un aviso que ya entendimos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Suscripciones.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

$crudo = file_get_contents('php://input') ?: '';
$aviso = json_decode($crudo, true);
$aviso = is_array($aviso) ? $aviso : [];

$tipo   = (string) ($aviso['type'] ?? $aviso['topic'] ?? '');
$dataId = (string) ($aviso['data']['id'] ?? $aviso['id'] ?? '');

if ($dataId === '') {
    api_responder(['ok' => true, 'nota' => 'aviso sin id']);
}

$firma = $_SERVER['HTTP_X_SIGNATURE'] ?? null;
$requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;

if (!mp_firma_valida($firma, $requestId, $dataId)) {
    error_log('[Ludia MP] webhook con firma inválida · data.id=' . $dataId);
    api_error('Firma no válida', 401);
}

/* ---------- Un cobro ---------- */
if ($tipo === 'payment') {
    $consulta = mp_ver_pago($dataId);
    if (!$consulta['ok']) {
        // No se pudo verificar: se responde 200 y Mercado Pago reintentará.
        api_responder(['ok' => true, 'nota' => 'no se pudo consultar el pago']);
    }

    $pago = $consulta['datos'];
    $referencia = (string) ($pago['external_reference'] ?? '');
    $suscripcionId = str_starts_with($referencia, 'ludia-sus-')
        ? (int) substr($referencia, strlen('ludia-sus-'))
        : 0;

    // Si el pago no trae la referencia, se busca por la suscripción asociada.
    if ($suscripcionId === 0 && !empty($pago['metadata']['preapproval_id'])) {
        $suscripcion = suscripcion_por_preapproval((string) $pago['metadata']['preapproval_id']);
        $suscripcionId = (int) ($suscripcion['id'] ?? 0);
    }

    $suscripcion = $suscripcionId ? suscripcion_por_id($suscripcionId) : null;
    $nuevo = suscripcion_guardar_pago($pago, $suscripcionId ?: null, $suscripcion ? (int) $suscripcion['usuario_id'] : null);

    if ($nuevo && $suscripcion && ($pago['status'] ?? '') === 'approved') {
        suscripcion_registrar_pago($suscripcionId, (string) ($pago['payer']['email'] ?? '') ?: null);
    }

    api_responder(['ok' => true, 'procesado' => $nuevo]);
}

/* ---------- Cambio de estado de la suscripción ---------- */
if ($tipo === 'subscription_preapproval' || $tipo === 'preapproval') {
    $consulta = mp_ver_suscripcion($dataId);
    if (!$consulta['ok']) {
        api_responder(['ok' => true, 'nota' => 'no se pudo consultar la suscripción']);
    }

    $estado = (string) ($consulta['datos']['status'] ?? '');
    $suscripcion = suscripcion_por_preapproval($dataId);

    if ($suscripcion) {
        $mapa = [
            'authorized' => 'activa',
            'paused'     => 'pausada',
            'cancelled'  => 'cancelada',
            'pending'    => 'pendiente',
        ];
        $nuevoEstado = $mapa[$estado] ?? null;

        if ($nuevoEstado) {
            db()->prepare('UPDATE suscripciones SET estado = ?, cancelada_at = IF(? = "cancelada", NOW(), cancelada_at) WHERE id = ?')
                ->execute([$nuevoEstado, $nuevoEstado, (int) $suscripcion['id']]);
        }
    }

    api_responder(['ok' => true, 'estado' => $estado]);
}

api_responder(['ok' => true, 'nota' => 'tipo de aviso ignorado: ' . $tipo]);
