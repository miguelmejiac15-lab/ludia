<?php
/**
 * Descarga de la plantilla de Excel para cargar actividades en bloque.
 * Se genera al vuelo: siempre baja con los tipos y ejemplos al día.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Plantilla.php';

auth_requerir();

$archivo = tempnam(sys_get_temp_dir(), 'ludia-plantilla-');

try {
    plantilla_generar($archivo);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla-ludia.xlsx"');
    header('Content-Length: ' . filesize($archivo));
    header('Cache-Control: no-store');
    readfile($archivo);
} finally {
    @unlink($archivo);
}
