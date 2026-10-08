<?php
/**
 * Cliente de Wompi: enlace al Web Checkout, consulta de transacciones y firma
 * de los avisos (eventos).
 *
 * Con cURL nativo, sin librerías externas, como el resto del proyecto.
 *
 * Wompi NO cobra solo cada mes (decisión del 2026-10-08): cada mes o cada año
 * es un pago suelto por adelantado, y la app avisa cuando se acerca el
 * vencimiento. Así se aceptan PSE, Nequi y Bancolombia, que no se pueden
 * cobrar de forma recurrente, y no se guardan tarjetas.
 *
 * Reglas de la casa:
 *  · La llave privada y los dos secretos NUNCA salen del servidor.
 *  · De un aviso no se cree nada: se toma el id de la transacción y se vuelve
 *    a preguntar a Wompi cuál es su estado real.
 *  · Todo error se registra y se devuelve como array, sin excepciones que
 *    tumben la página de un usuario que está intentando pagar.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const WOMPI_CHECKOUT = 'https://checkout.wompi.co/p/';
const WOMPI_TIMEOUT = 20;

function wompi_configurado(): bool
{
    return wompi_llave_publica() !== '' && trim((string) config('wompi.integrity_secret')) !== '';
}

function wompi_llave_publica(): string
{
    return trim((string) config('wompi.public_key'));
}

function wompi_moneda(): string
{
    return 'COP';
}

/**
 * Sandbox o producción lo dice la propia llave (`pub_test_` / `pub_prod_`):
 * así no hay un interruptor aparte que pueda quedar desalineado con ella.
 */
function wompi_api(): string
{
    return str_starts_with(wompi_llave_publica(), 'pub_test_')
        ? 'https://sandbox.wompi.co/v1'
        : 'https://production.wompi.co/v1';
}

/**
 * Enlace al Web Checkout de Wompi para un pago concreto.
 *
 * La firma de integridad impide que alguien cambie el monto en la URL: Wompi
 * la recalcula con el secreto y rechaza el pago si no coincide.
 */
function wompi_enlace_pago(string $referencia, int $montoPesos, string $correo, string $volverA): string
{
    $centavos = $montoPesos * 100;
    $firma = hash('sha256', $referencia . $centavos . wompi_moneda() . trim((string) config('wompi.integrity_secret')));

    return WOMPI_CHECKOUT . '?' . http_build_query([
        'public-key'          => wompi_llave_publica(),
        'currency'            => wompi_moneda(),
        'amount-in-cents'     => $centavos,
        'reference'           => $referencia,
        'signature:integrity' => $firma,
        'redirect-url'        => $volverA,
        'customer-data:email' => $correo,
    ]);
}

/**
 * Consulta una transacción. Devuelve ['ok'=>bool, 'http'=>int, 'datos'=>array, 'error'=>string];
 * en 'datos' viene la transacción (id, status, reference, amount_in_cents...).
 *
 * El endpoint es público (no pide la llave privada): basta con el id.
 */
function wompi_ver_transaccion(string $transaccionId): array
{
    $ch = curl_init(wompi_api() . '/transactions/' . rawurlencode($transaccionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => WOMPI_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $respuesta = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fallo = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('[Ludia Wompi] GET transacción ' . $transaccionId . ' — ' . $fallo);
        return ['ok' => false, 'http' => 0, 'datos' => [], 'error' => 'No se pudo contactar con Wompi'];
    }

    $cuerpo = json_decode((string) $respuesta, true);
    $cuerpo = is_array($cuerpo) ? $cuerpo : [];

    if ($http >= 400 || !isset($cuerpo['data']) || !is_array($cuerpo['data'])) {
        $mensaje = (string) ($cuerpo['error']['reason'] ?? $cuerpo['error']['type'] ?? 'Wompi respondió ' . $http);
        error_log('[Ludia Wompi] GET transacción ' . $transaccionId . ' — HTTP ' . $http . ' — ' . $mensaje);
        return ['ok' => false, 'http' => $http, 'datos' => [], 'error' => $mensaje];
    }

    return ['ok' => true, 'http' => $http, 'datos' => $cuerpo['data'], 'error' => ''];
}

/**
 * Comprueba que un aviso viene de verdad de Wompi.
 *
 * Firman con SHA-256 sobre los valores de `signature.properties`, en ese
 * orden, más el `timestamp` y el secreto de eventos. Sin secreto configurado
 * se rechaza: a diferencia de un entorno de pruebas sin pagos, aquí aceptar un
 * aviso sin firmar sería dejar que cualquiera se regale un plan.
 *
 * @param array<string,mixed> $aviso
 */
function wompi_firma_valida(array $aviso): bool
{
    $secreto = trim((string) config('wompi.events_secret'));
    if ($secreto === '') {
        error_log('[Ludia Wompi] aviso recibido sin events_secret configurado: rechazado');
        return false;
    }

    $propiedades = $aviso['signature']['properties'] ?? null;
    $recibida = (string) ($aviso['signature']['checksum'] ?? '');
    if (!is_array($propiedades) || $propiedades === [] || $recibida === '' || !isset($aviso['timestamp'])) {
        return false;
    }

    // "transaction.id" → $aviso['data']['transaction']['id']
    $cadena = '';
    foreach ($propiedades as $ruta) {
        $valor = $aviso['data'] ?? null;
        foreach (explode('.', (string) $ruta) as $tramo) {
            $valor = is_array($valor) ? ($valor[$tramo] ?? null) : null;
        }
        if (!is_scalar($valor)) {
            return false;
        }
        $cadena .= (string) $valor;
    }
    $cadena .= (string) $aviso['timestamp'] . $secreto;

    return hash_equals(strtolower(hash('sha256', $cadena)), strtolower($recibida));
}
