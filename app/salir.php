<?php
/**
 * Cierra la sesión. Solo por POST con token CSRF, para que nadie pueda
 * sacar a alguien con un enlace.
 */

require_once __DIR__ . '/../includes/Auth.php';

auth_iniciar();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && auth_csrf_valido($_POST['csrf'] ?? null)) {
    auth_salir();
}

header('Location: ' . base_url('/'));
exit;
