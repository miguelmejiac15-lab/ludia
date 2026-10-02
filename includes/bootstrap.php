<?php
/**
 * Arranque común: configuración, zona horaria, errores y helpers de vista.
 * Todas las páginas y endpoints empiezan con: require __DIR__ . '/.../includes/bootstrap.php';
 */

declare(strict_types=1);

define('LUDIA_ROOT', dirname(__DIR__));

$configFile = LUDIA_ROOT . '/config/config.php';
$GLOBALS['ludia_config'] = require (is_file($configFile)
    ? $configFile
    : LUDIA_ROOT . '/config/config.example.php');

date_default_timezone_set(config('app.timezone', 'America/Bogota'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/**
 * Lee un valor de configuración con notación de puntos: config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['ludia_config'];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/**
 * Ruta base del sitio sin barra final: '/LUDIRA' en XAMPP, '' en la raíz de un dominio.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configured = config('app.base_url');
    if (is_string($configured)) {
        return $base = rtrim($configured, '/');
    }

    $root    = str_replace('\\', '/', LUDIA_ROOT);
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');

    $base = ($docRoot !== '' && stripos($root, $docRoot) === 0)
        ? substr($root, strlen($docRoot))
        : '';

    return $base = rtrim($base, '/');
}

function base_url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/**
 * Dirección COMPLETA, con esquema y dominio.
 *
 * `base_url()` devuelve una ruta relativa a propósito: dentro de una página eso
 * es lo correcto y sobrevive a cambios de dominio. Pero hay direcciones que
 * salen de la página —un enlace que alguien copia y pega, el que un día irá en
 * un correo— y una ruta suelta ahí no sirve para nada.
 *
 * El orden importa: si `app.base_url` está configurado, manda, porque en
 * producción detrás de un proxy `$_SERVER['HTTP_HOST']` puede mentir. Solo si
 * no hay configuración se mira la petición en curso.
 */
function url_absoluta(string $path = ''): string
{
    $configurado = config('app.base_url');
    if (is_string($configurado) && preg_match('~^https?://~i', $configurado)) {
        return rtrim($configurado, '/') . '/' . ltrim($path, '/');
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

    // Sin petición en curso (línea de comandos, una tarea programada) no hay
    // dominio que valer: se devuelve la ruta relativa en vez de inventar
    // 'localhost', que produciría enlaces rotos sin avisar a nadie.
    if ($host === '') {
        return base_url($path);
    }

    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            ? 'https'
            : 'http';

    return $esquema . '://' . $host . base_url($path);
}

/**
 * URL de un archivo en /assets con versión por fecha de modificación (evita caché vieja).
 */
function asset(string $path): string
{
    $file = LUDIA_ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return base_url('assets/' . ltrim($path, '/')) . $version;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
