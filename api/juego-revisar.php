<?php
/**
 * POST /api/juego-revisar.php     (cabecera X-CSRF)
 * Cuerpo: { tipo: 'parejas'|'preguntas', contenido: { items: [...] } }
 *
 * Mientras el profesor escribe: ¿en qué plantillas se puede jugar lo que lleva
 * escrito, y por qué no en las otras? No guarda nada. Las reglas son las
 * mismas que al guardar (includes/Juegos.php), así que el editor no adivina.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Juegos.php';

api_iniciar();
api_metodo('POST');

$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta.', 401);
}
if (!auth_csrf_valido($_SERVER['HTTP_X_CSRF'] ?? null)) {
    api_error('La página caducó. Recárgala.', 403);
}

$datos = api_cuerpo();
$tipo = api_opcion($datos, 'tipo', JUEGO_CONTENIDOS, '');
$validado = juego_validar($tipo, is_array($datos['contenido'] ?? null) ? $datos['contenido'] : [], plan_permite($usuario, 'imagenes'));

$lista = juego_plantillas_para($validado['ok'] ? $tipo : null, $validado['ok'] ? $validado['contenido'] : null, $usuario, 'revisar');

api_ok([
    'valido'     => $validado['ok'],
    'error'      => $validado['ok'] ? '' : $validado['error'],
    'plantillas' => array_values(array_map(static fn (array $p): array => [
        'clave'  => $p['clave'],
        'nombre' => $p['nombre'],
        'icono'  => $p['icono'],
        'estado' => $p['estado'],
        'motivo' => $p['motivo'],
    ], $lista)),
]);
