<?php
/**
 * POST /api/imagen-subir.php   (multipart/form-data, campo "imagen")
 *
 * Sube una imagen para ilustrar una pregunta. Devuelve la URL para guardarla
 * dentro de la actividad. Exige cuenta: el público que juega nunca sube nada.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Imagenes.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta', 401, ['ingresar' => base_url('app/ingresar.php')]);
}

// Las imágenes son de los planes de pago.
if (!plan_permite($usuario, 'imagenes')) {
    api_error(
        'Ilustrar con imágenes es parte de los planes Estándar y Pro. En el plan gratis puedes usar texto.',
        402,
        ['plan' => plan_de($usuario), 'planes' => base_url('/') . '#planes']
    );
}

if (!extension_loaded('gd')) {
    api_error('El servidor no puede procesar imágenes: falta la extensión GD de PHP.', 503);
}

$resultado = imagen_guardar($_FILES['imagen'] ?? [], (int) $usuario['id']);

if (!$resultado['ok']) {
    api_error($resultado['error'], 422);
}

api_ok(['imagen' => $resultado['imagen'], 'uso' => imagenes_uso((int) $usuario['id'])]);
