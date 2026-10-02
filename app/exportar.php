<?php
/**
 * Exportar un paquete: HTML autónomo o paquete SCORM para Moodle.
 *
 *   app/exportar.php?p=12&formato=html
 *   app/exportar.php?p=12&formato=scorm
 *
 * Se exporta el PAQUETE (el contenido guardado), no una sesión jugada: por eso
 * el parámetro es el id del paquete y no un código de sala.
 *
 * Es una función del plan Pro. Se comprueba aquí, en el servidor: que el enlace
 * no aparezca en pantalla para los demás planes es cosmética, no seguridad.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Paquetes.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Exportar.php';

$usuario = auth_requerir();

$id      = (int) ($_GET['p'] ?? 0);
$formato = ($_GET['formato'] ?? 'html') === 'scorm' ? 'scorm' : 'html';
$paquete = $id > 0 ? paquete_de($id, (int) $usuario['id']) : null;

/** Sale con un aviso legible en vez de descargar un archivo roto. */
function exportar_cortar(string $mensaje, int $http = 400): never
{
    http_response_code($http);
    $page = ['title' => 'No se pudo exportar — Ludia', 'description' => '', 'home' => false];
    $extraStyles = ['css/cuenta.css'];

    require LUDIA_ROOT . '/includes/partials/header.php';
    echo '<main id="contenido" class="cuenta"><div class="wrap"><div class="panel vacio">'
        . '<b aria-hidden="true">📦</b><h1>No se pudo exportar</h1><p>' . e($mensaje) . '</p>'
        . '<a class="btn btn-primary" href="' . e(base_url('app/mis-sesiones.php')) . '">Volver a mis paquetes</a>'
        . '</div></div></main>';
    require LUDIA_ROOT . '/includes/partials/footer.php';
    exit;
}

// paquete_de() ya filtra por dueño: si no aparece, o no existe o no es suyo.
if (!$paquete) {
    exportar_cortar('Ese paquete no existe o no es tuyo.', 404);
}
// Capacidad, no un plan escrito a mano: Escuela también exporta, y un
// administrador puede concederlo a una cuenta concreta desde el panel.
if (!puede_exportar($usuario)) {
    exportar_cortar(
        'Exportar a SCORM o a HTML descargable es una función del plan Pro. Con ella puedes subir tus paquetes a Moodle o usarlos sin internet.',
        402
    );
}

$contenido = exportar_contenido(paquete_actividades($paquete));

if (!$contenido) {
    exportar_cortar('Este paquete no tiene actividades que se puedan exportar todavía.');
}

$tema = (string) $paquete['nombre'];

/* ---------- HTML autónomo ---------- */
if ($formato === 'html') {
    $html = exportar_html($tema, $contenido, false);

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . exportar_nombre($tema, 'html') . '"');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: no-store');
    echo $html;
    exit;
}

/* ---------- Paquete SCORM ---------- */
$archivo = tempnam(sys_get_temp_dir(), 'ludia-scorm-');

try {
    $resultado = exportar_scorm_zip($archivo, $tema, 'p' . (int) $paquete['id'], $contenido);
    if (!$resultado['ok']) {
        exportar_cortar($resultado['error'], 500);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . exportar_nombre($tema, 'zip') . '"');
    header('Content-Length: ' . filesize($archivo));
    header('Cache-Control: no-store');
    readfile($archivo);
} finally {
    @unlink($archivo);
}
