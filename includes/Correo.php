<?php
/**
 * Correo saliente por SMTP (06/11/2026).
 *
 * mail() de PHP no sirve en el servidor: no hay un servidor de correo local y
 * falla en silencio. Aquí se habla SMTP directamente con un proveedor —Gmail,
 * con una «contraseña de aplicación» de ludia.click@gmail.com— sin librerías.
 *
 * Sin la contraseña configurada (SMTP_CLAVE), correo_enviar() devuelve false y
 * no hace nada: quien lo use debe tener un plan B (guardar en la base).
 *
 * La contraseña nunca se escribe en el registro de errores.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function correo_configurado(): bool
{
    return trim((string) config('smtp.usuario', '')) !== '' && trim((string) config('smtp.clave', '')) !== '';
}

/** Quita saltos de línea: impide colar cabeceras extra en un campo. */
function correo_linea(string $texto): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $texto) ?? '');
}

/**
 * Envía un correo de texto plano. Devuelve true si el servidor SMTP lo aceptó.
 */
function correo_enviar(string $para, string $asunto, string $texto, ?string $responderA = null): bool
{
    if (!correo_configurado() || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $host      = (string) config('smtp.host', 'smtp.gmail.com');
    $puerto    = (int) config('smtp.puerto', 465);
    $seguridad = (string) config('smtp.seguridad', 'ssl');   // ssl (465) | tls (587) | ninguna (solo pruebas)
    $usuario   = trim((string) config('smtp.usuario'));
    $clave     = (string) config('smtp.clave');
    $nombre    = correo_linea((string) config('smtp.nombre', 'Ludia'));

    $destino = ($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
    $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client($destino, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $contexto);
    if (!$s) {
        error_log('[Ludia correo] no se pudo conectar a ' . $host . ':' . $puerto . ' — ' . $errstr);
        return false;
    }
    stream_set_timeout($s, 15);

    $leer = static function () use ($s): string {
        $respuesta = '';
        while (($linea = fgets($s, 515)) !== false) {
            $respuesta .= $linea;
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                break;   // última línea de una respuesta de varias
            }
        }
        return $respuesta;
    };
    // $etiqueta es lo que se anota si falla: nunca el comando en sí, que en
    // AUTH lleva el usuario y la contraseña.
    $paso = static function (?string $comando, array $codigos, string $etiqueta) use ($s, $leer): bool {
        if ($comando !== null) {
            fwrite($s, $comando . "\r\n");
        }
        $respuesta = $leer();
        if (!in_array((int) substr($respuesta, 0, 3), $codigos, true)) {
            error_log('[Ludia correo] falló ' . $etiqueta . ': ' . correo_linea($respuesta));
            return false;
        }
        return true;
    };

    $ok = $paso(null, [220], 'saludo') && $paso('EHLO ludia.click', [250], 'EHLO');
    if ($ok && $seguridad === 'tls') {
        $ok = $paso('STARTTLS', [220], 'STARTTLS')
            && stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && $paso('EHLO ludia.click', [250], 'EHLO tras TLS');
    }
    $ok = $ok
        && $paso('AUTH LOGIN', [334], 'AUTH')
        && $paso(base64_encode($usuario), [334], 'usuario')
        && $paso(base64_encode($clave), [235], 'contraseña (¿es una contraseña de aplicación?)')
        && $paso('MAIL FROM:<' . $usuario . '>', [250], 'MAIL FROM')
        && $paso('RCPT TO:<' . $para . '>', [250, 251], 'RCPT TO')
        && $paso('DATA', [354], 'DATA');

    if ($ok) {
        $cabeceras = [
            'From: =?UTF-8?B?' . base64_encode($nombre) . '?= <' . $usuario . '>',
            'To: <' . $para . '>',
            'Subject: =?UTF-8?B?' . base64_encode(correo_linea($asunto)) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@ludia.click>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($responderA !== null && filter_var($responderA, FILTER_VALIDATE_EMAIL)) {
            $cabeceras[] = 'Reply-To: <' . correo_linea($responderA) . '>';
        }
        // Cuerpo en base64: así ninguna línea empieza por «.» y no hay que
        // escapar nada.
        $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($texto), 76, "\r\n")) . "\r\n.";
        $ok = $paso($mensaje, [250], 'envío del mensaje');
    }

    @fwrite($s, "QUIT\r\n");
    fclose($s);
    return $ok;
}
