<?php
/**
 * Configuración para el SERVIDOR, leída de variables de entorno.
 *
 * Por qué existe este archivo además de `config.php` y `config.example.php`:
 * el repositorio es PÚBLICO, así que las credenciales no pueden estar escritas
 * en ningún archivo versionado. En el servidor las pone Coolify como variables
 * de entorno, y este archivo las traduce a la forma que espera `config()`.
 *
 * Cómo entra en juego: el `Dockerfile` lo copia encima de `config/config.php`
 * dentro de la imagen. `bootstrap.php` usa `config/config.php` si existe, así
 * que no hay que cambiar ni una línea del resto del proyecto.
 *
 * En local NO se usa: ahí sigue mandando el `config/config.php` de siempre,
 * que está en `.gitignore` y nunca sale de la máquina.
 */

declare(strict_types=1);

/**
 * Lee una variable de entorno. Devuelve el valor por defecto si no está o si
 * está vacía: en Docker una variable sin definir y una definida como cadena
 * vacía llegan igual, y tratarlas distinto solo da sorpresas.
 */
$env = static function (string $clave, ?string $porDefecto = null): ?string {
    $valor = getenv($clave);
    if ($valor === false || trim((string) $valor) === '') {
        return $porDefecto;
    }
    return trim((string) $valor);
};

$booleano = static fn (?string $valor): bool
    => in_array(strtolower((string) $valor), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);

return [
    'app' => [
        'name'  => $env('APP_NAME', 'Ludia'),
        'env'   => $env('APP_ENV', 'produccion'),

        // En producción va SIEMPRE apagado. Con `display_errors` encendido, un
        // error de base de datos imprime la consulta y la ruta del archivo en
        // la pantalla del visitante.
        'debug' => $booleano($env('APP_DEBUG', 'false')),

        /**
         * Aquí NO se autodetecta.
         *
         * `base_path()` deduce la ruta comparando con DOCUMENT_ROOT, y en el
         * contenedor eso daría la respuesta correcta por casualidad. Pero
         * `url_absoluta()` necesita el dominio COMPLETO para los enlaces que
         * salen de la app, y detrás de Traefik `HTTP_HOST` puede mentir.
         * Poniéndolo explícito, los enlaces que alguien copia y pega apuntan
         * siempre a donde deben.
         */
        'base_url' => $env('APP_BASE_URL', 'https://ludia.click'),

        'timezone' => $env('APP_TIMEZONE', 'America/Bogota'),
    ],

    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'ludia'),
        'user'    => $env('DB_USER', 'ludia'),
        'pass'    => $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'mercadopago' => [
        'access_token'   => $env('MP_ACCESS_TOKEN', ''),
        'public_key'     => $env('MP_PUBLIC_KEY', ''),
        'webhook_secret' => $env('MP_WEBHOOK_SECRET', ''),
    ],

    'google' => [
        'client_id'     => $env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => $env('GOOGLE_CLIENT_SECRET', ''),

        // Tiene que coincidir EXACTAMENTE con la URI autorizada en la consola
        // de Google Cloud, incluido el https.
        'redirect_uri'  => $env('GOOGLE_REDIRECT_URI', 'https://ludia.click/app/google-volver.php'),
    ],

    'legal' => [
        'responsable' => $env('LEGAL_RESPONSABLE', ''),
        'documento'   => $env('LEGAL_DOCUMENTO', ''),
        'ciudad'      => $env('LEGAL_CIUDAD', ''),
        'correo'      => $env('LEGAL_CORREO', ''),
    ],
];
