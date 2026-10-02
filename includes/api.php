<?php
/**
 * Utilidades comunes de los endpoints JSON de /api.
 * Responden siempre JSON, incluso en error, para que el fetch del navegador
 * no tenga que adivinar qué pasó.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const API_MAX_CUERPO = 400000; // 400 KB: suficiente para una sesión larga de texto

function api_responder(array $datos, int $http = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($http);
    }
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok(array $datos = []): void
{
    api_responder(['ok' => true] + $datos);
}

function api_error(string $mensaje, int $http = 400, array $extra = []): void
{
    api_responder(['ok' => false, 'error' => $mensaje] + $extra, $http);
}

/** Corta la petición si no llega con el método esperado. */
function api_metodo(string $esperado): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $esperado) {
        api_error('Método no permitido', 405);
    }
}

/** Cuerpo JSON de la petición, con tope de tamaño. */
function api_cuerpo(): array
{
    $crudo = file_get_contents('php://input') ?: '';
    if (strlen($crudo) > API_MAX_CUERPO) {
        api_error('El contenido es demasiado grande', 413);
    }
    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        api_error('No se pudo leer el contenido enviado');
    }
    return $datos;
}

/** Texto obligatorio/opcional, recortado y limitado. */
function api_texto(array $datos, string $campo, int $max, bool $obligatorio = true, string $porDefecto = ''): string
{
    $valor = isset($datos[$campo]) && is_scalar($datos[$campo]) ? trim((string) $datos[$campo]) : '';
    if ($valor === '') {
        if ($obligatorio) {
            api_error('Falta el campo: ' . $campo, 422);
        }
        return $porDefecto;
    }
    return mb_substr($valor, 0, $max);
}

/** Valor que debe estar dentro de una lista cerrada. */
function api_opcion(array $datos, string $campo, array $permitidos, string $porDefecto): string
{
    $valor = isset($datos[$campo]) && is_scalar($datos[$campo]) ? (string) $datos[$campo] : '';
    return in_array($valor, $permitidos, true) ? $valor : $porDefecto;
}

function api_token(): string
{
    return bin2hex(random_bytes(16));
}

/** Convierte errores y excepciones en respuestas JSON legibles. */
function api_iniciar(): void
{
    set_exception_handler(static function (Throwable $e): void {
        error_log('[Ludia API] ' . $e->getMessage());
        api_error(config('app.debug') ? $e->getMessage() : 'Ocurrió un error en el servidor', 500);
    });
}
