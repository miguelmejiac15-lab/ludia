<?php
/**
 * Cliente de Mercado Pago: suscripciones (preapproval) y consulta de pagos.
 *
 * Con cURL nativo, sin librerías externas, como el resto del proyecto: así
 * corre igual en XAMPP que en un hosting compartido.
 *
 * Reglas de la casa:
 *  · El `access_token` NUNCA sale del servidor. El navegador jamás lo ve.
 *  · De un aviso (webhook) no se cree nada: se toma el id y se vuelve a
 *    preguntar a Mercado Pago cuál es el estado real.
 *  · Todo error se registra y se devuelve como array, sin excepciones que
 *    tumben la página de un usuario que está intentando pagar.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MP_API = 'https://api.mercadopago.com';
const MP_TIMEOUT = 20;

function mp_configurado(): bool
{
    return trim((string) config('mercadopago.access_token')) !== '';
}

function mp_token(): string
{
    return trim((string) config('mercadopago.access_token'));
}

function mp_moneda(): string
{
    return strtoupper((string) config('mercadopago.moneda', 'COP'));
}

/**
 * Llamada a la API. Devuelve ['ok'=>bool, 'http'=>int, 'datos'=>array, 'error'=>string].
 *
 * @param array<string,mixed>|null $cuerpo
 */
function mp_peticion(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    if (!mp_configurado()) {
        return ['ok' => false, 'http' => 0, 'datos' => [], 'error' => 'Falta configurar el access_token de Mercado Pago'];
    }

    $ch = curl_init(MP_API . $ruta);
    $cabeceras = [
        'Authorization: Bearer ' . mp_token(),
        'Content-Type: application/json',
    ];

    // Evita cobrar dos veces si una petición se reintenta por un corte de red.
    if ($metodo === 'POST') {
        $cabeceras[] = 'X-Idempotency-Key: ' . bin2hex(random_bytes(16));
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => MP_TIMEOUT,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }

    $respuesta = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fallo = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('[Ludia MP] ' . $metodo . ' ' . $ruta . ' — ' . $fallo);
        return ['ok' => false, 'http' => 0, 'datos' => [], 'error' => 'No se pudo contactar con Mercado Pago'];
    }

    $datos = json_decode((string) $respuesta, true);
    $datos = is_array($datos) ? $datos : [];

    if ($http >= 400) {
        $mensaje = (string) ($datos['message'] ?? 'Mercado Pago respondió ' . $http);
        error_log('[Ludia MP] ' . $metodo . ' ' . $ruta . ' — HTTP ' . $http . ' — ' . $mensaje);
        return ['ok' => false, 'http' => $http, 'datos' => $datos, 'error' => $mensaje];
    }

    return ['ok' => true, 'http' => $http, 'datos' => $datos, 'error' => ''];
}

/**
 * Crea una suscripción recurrente y devuelve el enlace al que mandar al usuario.
 *
 * `external_reference` lleva nuestro id de suscripción: es lo que permite
 * reconocer al usuario cuando Mercado Pago avisa del cobro.
 *
 * El periodo decide cada cuánto cobra Mercado Pago. Se expresa siempre en meses
 * (1 o 12) y no en años: la API admite las dos formas, pero mezclarlas complica
 * cuadrar los avisos sin ganar nada.
 */
function mp_crear_suscripcion(
    int $suscripcionId,
    string $correo,
    string $nombrePlan,
    int $monto,
    string $volverA,
    string $periodo = 'mensual'
): array {
    $cadaCuantosMeses = $periodo === 'anual' ? 12 : 1;

    $cuerpo = [
        'reason'             => 'Ludia ' . $nombrePlan . ($periodo === 'anual' ? ' (anual)' : ''),
        'external_reference' => 'ludia-sus-' . $suscripcionId,
        'payer_email'        => $correo,
        'back_url'           => $volverA,
        'status'             => 'pending',
        'auto_recurring'     => [
            'frequency'          => $cadaCuantosMeses,
            'frequency_type'     => 'months',
            'transaction_amount' => $monto,
            'currency_id'        => mp_moneda(),
        ],
    ];

    $r = mp_peticion('POST', '/preapproval', $cuerpo);
    if (!$r['ok']) {
        return $r;
    }

    // init_point es la URL de pago; en credenciales de prueba viene la sandbox.
    $r['enlace'] = (string) ($r['datos']['sandbox_init_point'] ?? $r['datos']['init_point'] ?? '');
    $r['preapproval_id'] = (string) ($r['datos']['id'] ?? '');

    return $r;
}

function mp_ver_suscripcion(string $preapprovalId): array
{
    return mp_peticion('GET', '/preapproval/' . rawurlencode($preapprovalId));
}

function mp_cancelar_suscripcion(string $preapprovalId): array
{
    return mp_peticion('PUT', '/preapproval/' . rawurlencode($preapprovalId), ['status' => 'cancelled']);
}

function mp_ver_pago(string $pagoId): array
{
    return mp_peticion('GET', '/v1/payments/' . rawurlencode($pagoId));
}

/**
 * Comprueba que un aviso viene de verdad de Mercado Pago.
 *
 * Ellos firman con HMAC-SHA256 sobre "id:<data.id>;request-id:<x-request-id>;ts:<ts>;"
 * usando la clave secreta del webhook. Si no hay clave configurada se deja
 * pasar (entorno de pruebas), pero se registra el aviso.
 */
function mp_firma_valida(?string $cabeceraFirma, ?string $requestId, string $dataId): bool
{
    $secreto = trim((string) config('mercadopago.webhook_secret'));
    if ($secreto === '') {
        error_log('[Ludia MP] webhook sin webhook_secret configurado: no se verificó la firma');
        return true;
    }
    if (!$cabeceraFirma) {
        return false;
    }

    // Cabecera: "ts=1700000000,v1=abcdef..."
    $ts = '';
    $v1 = '';
    foreach (explode(',', $cabeceraFirma) as $parte) {
        $trozos = explode('=', trim($parte), 2);
        if (count($trozos) !== 2) {
            continue;
        }
        if ($trozos[0] === 'ts') {
            $ts = $trozos[1];
        } elseif ($trozos[0] === 'v1') {
            $v1 = $trozos[1];
        }
    }

    if ($ts === '' || $v1 === '') {
        return false;
    }

    $plantilla = 'id:' . $dataId . ';request-id:' . (string) $requestId . ';ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $plantilla, $secreto), $v1);
}
