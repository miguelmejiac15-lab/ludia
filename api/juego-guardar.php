<?php
/**
 * POST /api/juego-guardar.php     (cabecera X-CSRF)
 * Cuerpo: { id?, titulo, tipo: 'parejas'|'preguntas', plantilla, contenido: { items: [...] } }
 *
 * Crea o actualiza un JUEGO. El servidor valida, convierte el contenido a la
 * plantilla y lo guarda listo para jugar. Ver includes/Juegos.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Juegos.php';

api_iniciar();
api_metodo('POST');

$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta para guardar un juego', 401, ['ingresar' => base_url('app/ingresar.php')]);
}
if (!auth_csrf_valido($_SERVER['HTTP_X_CSRF'] ?? null)) {
    api_error('La página caducó. Recárgala y vuelve a guardar.', 403);
}

$datos = api_cuerpo();
$resultado = juego_guardar(
    $usuario,
    (int) ($datos['id'] ?? 0),
    is_scalar($datos['titulo'] ?? null) ? (string) $datos['titulo'] : '',
    api_opcion($datos, 'tipo', JUEGO_CONTENIDOS, ''),
    is_array($datos['contenido'] ?? null) ? $datos['contenido'] : [],
    is_scalar($datos['plantilla'] ?? null) ? (string) $datos['plantilla'] : ''
);

if (!$resultado['ok']) {
    api_error($resultado['mensaje'], $resultado['http'] ?? 422, ['planes' => base_url('app/suscripcion.php')]);
}

api_ok([
    'id'        => $resultado['id'],
    'plantilla' => $resultado['plantilla'],
    'url'       => base_url('app/juego.php') . '?id=' . $resultado['id'],
]);
