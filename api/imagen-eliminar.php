<?php
/**
 * POST /api/imagen-eliminar.php
 * Cuerpo: { archivo }
 *
 * Borra una imagen propia. Solo la puede borrar quien la subió.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Imagenes.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta', 401, ['ingresar' => base_url('app/ingresar.php')]);
}

$datos = api_cuerpo();
$archivo = api_texto($datos, 'archivo', 60);

if (!imagen_eliminar($archivo, (int) $usuario['id'])) {
    api_error('No encontramos esa imagen entre las tuyas', 404);
}

api_ok(['uso' => imagenes_uso((int) $usuario['id'])]);
