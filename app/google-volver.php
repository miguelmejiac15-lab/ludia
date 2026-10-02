<?php
/**
 * Vuelta desde Google: aquí se decide si entra o no.
 *
 * Orden de las comprobaciones, y ninguna es prescindible:
 *  1. ¿Hay credenciales? (una URL se puede escribir a mano)
 *  2. ¿Google devolvió un error? (el usuario pudo cancelar)
 *  3. ¿El `state` coincide con el que guardamos? Sin esto, otra página podría
 *     provocar un ingreso — CSRF sobre el propio login.
 *  4. El código se canjea EN EL SERVIDOR por el perfil, y solo se acepta si el
 *     correo viene verificado por Google.
 *
 * La sesión se abre con auth_abrir_sesion(), la misma puerta que el ingreso con
 * contraseña: nada de inventar una segunda forma de quedar dentro.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Google.php';

auth_iniciar();

/** Vuelve a la pantalla de ingreso con un motivo legible. */
function google_cortar(string $motivo): never
{
    $_SESSION['ingreso_error'] = $motivo;
    header('Location: ' . base_url('app/ingresar.php'));
    exit;
}

if (!google_configurado()) {
    google_cortar('Entrar con Google todavía no está disponible en este servidor.');
}

// Google avisa así cuando el usuario cancela: no es un fallo, no se registra.
if (!empty($_GET['error'])) {
    google_cortar('No se completó el ingreso con Google.');
}

if (!google_state_valido($_GET['state'] ?? null)) {
    google_cortar('El ingreso con Google caducó o no empezó aquí. Inténtalo otra vez.');
}

$codigo = (string) ($_GET['code'] ?? '');
if ($codigo === '') {
    google_cortar('Google no devolvió el código de acceso.');
}

$r = google_perfil_desde_codigo($codigo);
if (!$r['ok']) {
    google_cortar('No pudimos verificar tu cuenta de Google: ' . $r['error']);
}

// Busca la cuenta por google_id, la enlaza si ya existía con ese correo, o la
// crea. Vive en Auth.php porque es un alta de cuenta como cualquier otra, no
// una puerta aparte.
$cuenta = auth_entrar_con_google($r['perfil']);
if (!$cuenta['ok']) {
    google_cortar($cuenta['error']);
}

header('Location: ' . google_destino());
exit;
