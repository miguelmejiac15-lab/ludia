<?php
/**
 * Entrar con Google (OAuth 2.0, flujo de código de autorización).
 *
 * Con cURL nativo y sin librerías, como el resto del proyecto.
 *
 * Reglas de la casa:
 *  · El `client_secret` NUNCA sale del servidor.
 *  · Sin credenciales configuradas, `google_configurado()` devuelve false y el
 *    botón no se pinta: entrar con contraseña sigue funcionando igual. Es
 *    opcional, como los pagos.
 *  · La identidad se ata al `sub` de Google, no al correo: el correo puede
 *    cambiar de dueño con el tiempo, el `sub` no.
 *  · Solo se acepta el perfil si Google dice que el correo está **verificado**.
 *    Sin esa comprobación, cualquiera que registrara una cuenta de Google con
 *    el correo de otra persona podría entrar en su cuenta de Ludia.
 */

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

const GOOGLE_AUTORIZAR = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN     = 'https://oauth2.googleapis.com/token';
const GOOGLE_PERFIL    = 'https://www.googleapis.com/oauth2/v3/userinfo';

function google_config(): array
{
    $config = config('google');
    return is_array($config) ? $config : [];
}

/** ¿Hay credenciales? Sin ellas, el botón ni aparece. */
function google_configurado(): bool
{
    $c = google_config();
    return !empty($c['client_id']) && !empty($c['client_secret']);
}

function google_redirect_uri(): string
{
    $c = google_config();
    return (string) ($c['redirect_uri'] ?? '');
}

/**
 * A dónde mandar al usuario para que Google le pregunte.
 *
 * El `state` es un valor de un solo uso guardado en la sesión: cuando Google
 * devuelva al usuario, tiene que traerlo igual. Sin eso, cualquiera podría
 * provocar un ingreso desde otra página (CSRF sobre el propio login).
 */
function google_url_autorizacion(string $volverA = ''): string
{
    auth_iniciar();

    $state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $state;
    $_SESSION['google_volver'] = $volverA;

    return GOOGLE_AUTORIZAR . '?' . http_build_query([
        'client_id'     => (string) google_config()['client_id'],
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        // Sin esto, quien ya eligió cuenta una vez entra siempre con la misma
        // sin poder cambiarla, y en un salón de clase se comparten equipos.
        'prompt'        => 'select_account',
    ]);
}

/** Comprueba el `state` y lo consume: vale una sola vez. */
function google_state_valido(?string $state): bool
{
    auth_iniciar();
    $esperado = (string) ($_SESSION['google_state'] ?? '');
    unset($_SESSION['google_state']);

    return $esperado !== '' && is_string($state) && hash_equals($esperado, $state);
}

/** A dónde volver tras entrar (lo guardó google_url_autorizacion). */
function google_destino(): string
{
    auth_iniciar();
    $destino = (string) ($_SESSION['google_volver'] ?? '');
    unset($_SESSION['google_volver']);

    return $destino !== '' ? $destino : base_url('app/mis-sesiones.php');
}

/**
 * Cambia el código por el perfil del usuario.
 *
 * @return array{ok:bool, error:string, perfil:array}
 */
function google_perfil_desde_codigo(string $codigo): array
{
    $token = google_peticion(GOOGLE_TOKEN, [
        'code'          => $codigo,
        'client_id'     => (string) google_config()['client_id'],
        'client_secret' => (string) google_config()['client_secret'],
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    if (!$token['ok'] || empty($token['datos']['access_token'])) {
        return ['ok' => false, 'error' => $token['error'] ?: 'Google no entregó el token', 'perfil' => []];
    }

    $perfil = google_peticion(GOOGLE_PERFIL, null, (string) $token['datos']['access_token']);
    if (!$perfil['ok']) {
        return ['ok' => false, 'error' => $perfil['error'], 'perfil' => []];
    }

    $datos = $perfil['datos'];
    $sub   = (string) ($datos['sub'] ?? '');
    $email = mb_strtolower(trim((string) ($datos['email'] ?? '')));

    if ($sub === '' || $email === '') {
        return ['ok' => false, 'error' => 'Google no devolvió el correo de la cuenta', 'perfil' => []];
    }

    // Sin correo verificado no se enlaza con nada: sería la puerta para entrar
    // en la cuenta ajena de quien tenga ese mismo correo en Ludia.
    if (empty($datos['email_verified'])) {
        return ['ok' => false, 'error' => 'Esa cuenta de Google no tiene el correo verificado', 'perfil' => []];
    }

    return ['ok' => true, 'error' => '', 'perfil' => [
        'sub'    => $sub,
        'email'  => $email,
        'nombre' => trim((string) ($datos['name'] ?? '')) ?: mb_strstr($email, '@', true),
    ]];
}

/**
 * Petición a Google. Con $cuerpo hace POST de formulario; con $token, GET
 * autenticado. Devuelve siempre un array: ningún error tumba la página de
 * alguien que solo intentaba entrar.
 *
 * @return array{ok:bool, datos:array, error:string}
 */
function google_peticion(string $url, ?array $cuerpo = null, string $token = ''): array
{
    $ch = curl_init($url);
    $cabeceras = ['Accept: application/json'];

    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($cuerpo));
        $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($token !== '') {
        $cabeceras[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $respuesta = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fallo = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('[Ludia Google] sin conexión: ' . $fallo);
        return ['ok' => false, 'datos' => [], 'error' => 'No se pudo contactar con Google'];
    }

    $datos = json_decode((string) $respuesta, true);
    $datos = is_array($datos) ? $datos : [];

    if ($http >= 400) {
        $mensaje = (string) ($datos['error_description'] ?? $datos['error'] ?? 'Google respondió ' . $http);
        error_log('[Ludia Google] ' . $url . ' — HTTP ' . $http . ' — ' . $mensaje);
        return ['ok' => false, 'datos' => $datos, 'error' => $mensaje];
    }

    return ['ok' => true, 'datos' => $datos, 'error' => ''];
}
