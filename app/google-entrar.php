<?php
/**
 * Manda al usuario a Google para que se identifique.
 *
 * No recibe nada ni decide nada: solo arma la URL con su `state` de un solo uso
 * y redirige. Toda la comprobación ocurre a la vuelta, en google-volver.php.
 *
 * Si no hay credenciales configuradas no se llega aquí (el botón no se pinta),
 * pero se comprueba igual: una URL se puede escribir a mano.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Google.php';

auth_iniciar();

if (!google_configurado()) {
    $_SESSION['ingreso_error'] = 'Entrar con Google todavía no está disponible en este servidor.';
    header('Location: ' . base_url('app/ingresar.php'));
    exit;
}

// Quien ya tiene sesión no necesita identificarse otra vez.
if (auth_hay_sesion()) {
    header('Location: ' . base_url('app/mis-sesiones.php'));
    exit;
}

// A dónde volver después: se acepta solo una ruta interna del propio sitio,
// nunca una URL completa. Si no, este parámetro sería un salto abierto a
// cualquier página que alguien quisiera colar en un enlace.
$volverA = (string) ($_GET['volver'] ?? '');
if ($volverA !== '' && (str_contains($volverA, '://') || !str_starts_with($volverA, base_url('/')))) {
    $volverA = '';
}

header('Location: ' . google_url_autorizacion($volverA));
exit;
