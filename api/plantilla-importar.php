<?php
/**
 * POST /api/plantilla-importar.php   (multipart/form-data, campo "archivo")
 *
 * Lee la plantilla de Excel llena y devuelve las actividades listas para que
 * el editor las muestre antes de agregarlas. No guarda nada: quien decide es
 * el creador, después de revisar la vista previa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Plantilla.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

if (!auth_usuario()) {
    api_error('Necesitas ingresar con tu cuenta', 401, ['ingresar' => base_url('app/ingresar.php')]);
}

const PLANTILLA_MAX_BYTES = 2097152; // 2 MB: una plantilla llena pesa muy poco

$archivo = $_FILES['archivo'] ?? null;
if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $motivo = match ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
        UPLOAD_ERR_PARTIAL                        => 'La subida se cortó a medias, inténtalo de nuevo.',
        default                                   => 'No llegó ningún archivo.',
    };
    api_error($motivo, 422);
}

if ($archivo['size'] > PLANTILLA_MAX_BYTES) {
    api_error('El archivo pesa más de 2 MB: usa la plantilla de Ludia sin imágenes ni hojas extra.', 413);
}

if (!is_uploaded_file($archivo['tmp_name'])) {
    api_error('No pudimos leer el archivo subido', 400);
}

if (strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
    api_error('El archivo debe ser .xlsx (Excel). Si lo tienes en otro formato, guárdalo como libro de Excel.', 422);
}

try {
    $hojas = excel_leer($archivo['tmp_name']);
} catch (RuntimeException $e) {
    api_error($e->getMessage(), 422);
}

$resultado = plantilla_a_actividades($hojas);

if (!$resultado['actividades']) {
    api_error(
        'No encontramos actividades para importar. Revisa que la hoja «Actividades» tenga filas y que los títulos coincidan.',
        422,
        ['avisos' => $resultado['avisos']]
    );
}

api_ok([
    'actividades' => $resultado['actividades'],
    'avisos'      => $resultado['avisos'],
    'total'       => count($resultado['actividades']),
    'preguntas'   => array_sum(array_map(static fn (array $a): int => count($a['items']), $resultado['actividades'])),
]);
