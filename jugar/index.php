<?php
/**
 * ludia.click/jugar — la puerta corta para el público, como kahoot.it.
 *
 * El docente proyecta «Entra en ludia.click/jugar» y el código; el estudiante
 * escribe eso en el navegador del celular y llega a poner el código.
 *
 * Es una REDIRECCIÓN y no una copia de la página a propósito: la pantalla del
 * estudiante tiene que seguir siendo app/unirse.php, que vive dentro de /app/
 * y por eso nunca carga Analytics ni el píxel (pueden ser menores). Una página
 * aquí, fuera de /app/, los heredaría.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$codigo = preg_replace('/\D/', '', (string) ($_GET['c'] ?? $_GET['codigo'] ?? ''));
header('Location: ' . base_url('app/unirse.php') . ($codigo !== '' ? '?c=' . $codigo : ''), true, 302);
exit;
