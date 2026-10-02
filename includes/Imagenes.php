<?php
/**
 * Imágenes que sube el creador para ilustrar sus preguntas.
 *
 * Reglas de la casa:
 *  · Se comprueba el contenido real del archivo, no su extensión ni lo que diga
 *    el navegador: una foto es una foto o no entra.
 *  · Se vuelve a dibujar con GD y se guarda como JPEG o PNG. Así, si alguien
 *    esconde código dentro de una imagen, ese contenido no sobrevive.
 *  · El nombre del archivo lo pone el servidor (aleatorio), nunca el usuario.
 *  · Se reduce a un tamaño razonable: las fotos de celular pesan demasiado para
 *    proyectarlas y para el plan de datos del público.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const IMAGEN_MAX_BYTES  = 5242880;   // 5 MB de entrada
const IMAGEN_MAX_LADO   = 1280;      // se reduce hasta este lado mayor
const IMAGEN_CALIDAD    = 82;        // calidad JPEG al reguardar
const IMAGEN_CARPETA    = 'uploads';

/** Tipos de imagen aceptados. */
const IMAGEN_TIPOS = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

/**
 * ¿La imagen usa de verdad la transparencia?
 *
 * Importa para elegir formato: un PNG sin transparencia (una foto, por ejemplo)
 * pesa mucho más que el mismo contenido en JPEG, y el público lo descarga con
 * sus datos. Solo se conserva PNG cuando hay píxeles translúcidos.
 */
function imagen_tiene_transparencia($lienzo, int $ancho, int $alto): bool
{
    if (!imageistruecolor($lienzo)) {
        return imagecolortransparent($lienzo) >= 0;
    }

    // Se revisa una rejilla de puntos: basta para detectar transparencia real.
    $pasoX = max(1, (int) ($ancho / 40));
    $pasoY = max(1, (int) ($alto / 40));

    for ($x = 0; $x < $ancho; $x += $pasoX) {
        for ($y = 0; $y < $alto; $y += $pasoY) {
            if (((imagecolorat($lienzo, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }
    }
    return false;
}

function imagenes_carpeta(): string
{
    $ruta = LUDIA_ROOT . '/' . IMAGEN_CARPETA;
    if (!is_dir($ruta)) {
        mkdir($ruta, 0775, true);
    }
    return $ruta;
}

function imagen_url(string $archivo): string
{
    return base_url(IMAGEN_CARPETA . '/' . $archivo);
}

/** Lee el archivo subido con GD, sea cual sea su formato de origen. */
function imagen_abrir(string $ruta, int $tipo)
{
    return match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
        IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
        IMAGETYPE_GIF  => @imagecreatefromgif($ruta),
        IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
        default        => false,
    };
}

/**
 * Valida, reduce y guarda una imagen subida.
 *
 * @param array $archivo     elemento de $_FILES
 * @param bool  $desdeLaWeb  false solo en pruebas por línea de comandos, donde
 *                           is_uploaded_file() no puede confirmar nada
 * @return array{ok:bool, error?:string, imagen?:array}
 */
function imagen_guardar(array $archivo, ?int $usuarioId, bool $desdeLaWeb = true): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $motivo = match ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen es demasiado grande.',
            UPLOAD_ERR_PARTIAL                        => 'La subida se cortó a medias, inténtalo otra vez.',
            default                                   => 'No llegó ninguna imagen.',
        };
        return ['ok' => false, 'error' => $motivo];
    }

    if ($desdeLaWeb && !is_uploaded_file($archivo['tmp_name'])) {
        return ['ok' => false, 'error' => 'No pudimos leer la imagen subida.'];
    }

    if ($archivo['size'] > IMAGEN_MAX_BYTES) {
        return ['ok' => false, 'error' => 'La imagen pesa más de ' . round(IMAGEN_MAX_BYTES / 1048576) . ' MB. Usa una más liviana.'];
    }

    // El contenido manda: si getimagesize no la reconoce, no es una imagen.
    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !in_array($info[2], IMAGEN_TIPOS, true)) {
        return ['ok' => false, 'error' => 'Ese archivo no es una imagen JPG, PNG, GIF o WebP.'];
    }

    [$ancho, $alto, $tipo] = $info;
    if ($ancho < 16 || $alto < 16) {
        return ['ok' => false, 'error' => 'La imagen es demasiado pequeña.'];
    }
    if ($ancho * $alto > 40000000) {   // ~40 megapíxeles: evita agotar la memoria
        return ['ok' => false, 'error' => 'La imagen tiene demasiados píxeles. Redúcela antes de subirla.'];
    }

    $origen = imagen_abrir($archivo['tmp_name'], $tipo);
    if (!$origen) {
        return ['ok' => false, 'error' => 'No pudimos procesar la imagen. Prueba a guardarla de nuevo como JPG o PNG.'];
    }

    // Reducción proporcional solo si hace falta.
    $escala = min(1, IMAGEN_MAX_LADO / max($ancho, $alto));
    $nuevoAncho = max(1, (int) round($ancho * $escala));
    $nuevoAlto = max(1, (int) round($alto * $escala));

    // PNG solo si hace falta: con transparencia se conserva; si no, JPEG pesa menos.
    $extension = imagen_tiene_transparencia($origen, $ancho, $alto) ? 'png' : 'jpg';
    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

    if ($extension === 'png') {        // conserva la transparencia
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
    } else {                            // fondo blanco para lo que era transparente
        $blanco = imagecolorallocate($destino, 255, 255, 255);
        imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);
    }

    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
    imagedestroy($origen);

    $nombre = bin2hex(random_bytes(12)) . '.' . $extension;
    $ruta = imagenes_carpeta() . '/' . $nombre;

    $guardada = $extension === 'png'
        ? imagepng($destino, $ruta, 6)
        : imagejpeg($destino, $ruta, IMAGEN_CALIDAD);
    imagedestroy($destino);

    if (!$guardada) {
        return ['ok' => false, 'error' => 'No pudimos guardar la imagen en el servidor.'];
    }

    $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
    $bytes = (int) filesize($ruta);

    db()->prepare(
        'INSERT INTO imagenes (usuario_id, archivo, nombre_original, mime, ancho, alto, bytes)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $usuarioId,
        $nombre,
        mb_substr((string) ($archivo['name'] ?? ''), 0, 160),
        $mime,
        $nuevoAncho,
        $nuevoAlto,
        $bytes,
    ]);

    return ['ok' => true, 'imagen' => [
        'archivo' => $nombre,
        'url'     => imagen_url($nombre),
        'ancho'   => $nuevoAncho,
        'alto'    => $nuevoAlto,
        'bytes'   => $bytes,
    ]];
}

/** Borra una imagen propia (archivo y registro). */
function imagen_eliminar(string $archivo, ?int $usuarioId): bool
{
    if (!preg_match('/^[a-f0-9]{24}\.(jpg|png)$/', $archivo)) {
        return false;
    }

    $consulta = db()->prepare('SELECT id FROM imagenes WHERE archivo = ? AND usuario_id <=> ?');
    $consulta->execute([$archivo, $usuarioId]);
    if (!$consulta->fetchColumn()) {
        return false;
    }

    @unlink(imagenes_carpeta() . '/' . $archivo);
    db()->prepare('DELETE FROM imagenes WHERE archivo = ?')->execute([$archivo]);
    return true;
}

/** Cuánto ocupa un creador (para cuotas por plan, más adelante). */
function imagenes_uso(int $usuarioId): array
{
    $consulta = db()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(bytes), 0) AS bytes FROM imagenes WHERE usuario_id = ?');
    $consulta->execute([$usuarioId]);
    $fila = $consulta->fetch() ?: ['total' => 0, 'bytes' => 0];
    return ['total' => (int) $fila['total'], 'bytes' => (int) $fila['bytes']];
}
