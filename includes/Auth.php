<?php
/**
 * Cuentas y acceso: registro, ingreso y sesión de PHP.
 *
 * Sin dependencias externas: $_SESSION nativa + password_hash()/password_verify(),
 * como dice el masterplan (sección 13).
 *
 * El público que entra a jugar NO necesita cuenta: esto es solo para quien crea
 * sesiones (app/crear.php, app/sesion.php, app/mis-sesiones.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const AUTH_MIN_PASSWORD    = 8;
const AUTH_MAX_INTENTOS    = 5;   // intentos fallidos seguidos por correo
const AUTH_BLOQUEO_MINUTOS = 10;

/** Arranca la sesión de PHP con cookie endurecida. Idempotente. */
function auth_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Detrás de un proxy que termina el TLS —Traefik, en el servidor— la
    // petición llega al contenedor como HTTP plano y `$_SERVER['HTTPS']` NO
    // viene. Mirando solo esa variable, la cookie de sesión se emitiría SIN la
    // marca `secure` en el único sitio donde de verdad importa: producción.
    //
    // Se consulta también `X-Forwarded-Proto`, que es lo que ya hace
    // `url_absoluta()` en bootstrap.php. En local no viene ninguna de las dos,
    // así que nada cambia al desarrollar.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url('/'),
        'secure'   => $https,
        'httponly' => true,     // el JavaScript no puede leer la cookie
        'samesite' => 'Lax',    // no viaja desde otros sitios
    ]);
    session_name('ludia_sesion');
    session_start();
}

/* ---------- Protección de formularios (CSRF) ---------- */

function auth_csrf(): string
{
    auth_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function auth_csrf_valido(?string $token): bool
{
    auth_iniciar();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------- Usuario en sesión ---------- */

function auth_usuario(): ?array
{
    static $usuario = null;
    static $consultado = false;

    if ($consultado) {
        return $usuario;
    }
    $consultado = true;

    auth_iniciar();
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    // Esta consulta es la ÚNICA puerta por la que los datos de la cuenta llegan
    // a las páginas. Una columna que falte aquí no da error: simplemente no
    // existe para toda la aplicación. Así se cayó la escuela entera el 18/09 —
    // a un coordinador legítimo se le decía "tu cuenta no pertenece a ningún
    // colegio" porque `colegio_id` no viajaba, aunque en la base estuviera.
    // Al añadir una columna a `usuarios`, hay que añadirla también aquí.
    $consulta = db()->prepare(
        'SELECT id, nombre, email, rol, plan, permisos, perfil, colegio_id, rol_colegio
           FROM usuarios WHERE id = ?'
    );
    $consulta->execute([(int) $_SESSION['usuario_id']]);
    $fila = $consulta->fetch();

    if (!$fila) {          // la cuenta ya no existe: se limpia la sesión
        auth_salir();
        return null;
    }

    return $usuario = $fila;
}

function auth_hay_sesion(): bool
{
    return auth_usuario() !== null;
}

/** Para páginas: manda a ingresar y recuerda a dónde volver. */
function auth_requerir(): array
{
    $usuario = auth_usuario();
    if ($usuario) {
        return $usuario;
    }

    $volver = $_SERVER['REQUEST_URI'] ?? base_url('app/crear.php');
    header('Location: ' . base_url('app/ingresar.php') . '?volver=' . urlencode($volver));
    exit;
}

/**
 * Acepta solo rutas internas para "volver a": evita que un enlace manipulado
 * mande al usuario a otro sitio después de ingresar.
 */
function auth_ruta_interna(?string $ruta, string $porDefecto): string
{
    $ruta = trim((string) $ruta);
    $base = base_url('/');

    if ($ruta === '' || str_starts_with($ruta, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $ruta)) {
        return $porDefecto;
    }
    return str_starts_with($ruta, $base) ? $ruta : $porDefecto;
}

function auth_salir(): void
{
    auth_iniciar();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- Validaciones ---------- */

function auth_validar_registro(string $nombre, string $email, string $password): ?string
{
    if (mb_strlen(trim($nombre)) < 2) {
        return 'Escribe tu nombre.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Ese correo no parece válido.';
    }
    if (mb_strlen($password) < AUTH_MIN_PASSWORD) {
        return 'La contraseña necesita al menos ' . AUTH_MIN_PASSWORD . ' caracteres.';
    }
    return null;
}

/* ---------- Freno a la fuerza bruta ---------- */

function auth_bloqueado(string $email): int
{
    $consulta = db()->prepare(
        'SELECT TIMESTAMPDIFF(MINUTE, NOW(), bloqueado_hasta) FROM intentos_ingreso
         WHERE email = ? AND bloqueado_hasta > NOW()'
    );
    $consulta->execute([$email]);
    $minutos = $consulta->fetchColumn();
    return $minutos === false ? 0 : max(1, (int) $minutos);
}

function auth_registrar_fallo(string $email): void
{
    db()->prepare(
        'INSERT INTO intentos_ingreso (email, intentos, ultimo_at, bloqueado_hasta)
         VALUES (?, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
           intentos = IF(ultimo_at < DATE_SUB(NOW(), INTERVAL ? MINUTE), 1, intentos + 1),
           ultimo_at = NOW(),
           bloqueado_hasta = IF(intentos + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL)'
    )->execute([$email, AUTH_BLOQUEO_MINUTOS, AUTH_MAX_INTENTOS, AUTH_BLOQUEO_MINUTOS]);
}

function auth_limpiar_intentos(string $email): void
{
    db()->prepare('DELETE FROM intentos_ingreso WHERE email = ?')->execute([$email]);
}

/* ---------- Registro e ingreso ---------- */

/** @return array{ok:bool, error?:string} */
function auth_crear_cuenta(string $nombre, string $email, string $password, string $perfil = 'otro'): array
{
    $email = mb_strtolower(trim($email));
    $error = auth_validar_registro($nombre, $email, $password);
    if ($error) {
        return ['ok' => false, 'error' => $error];
    }

    $perfiles = ['docente', 'capacitador', 'creador_contenido', 'comunidad', 'otro'];
    if (!in_array($perfil, $perfiles, true)) {
        $perfil = 'otro';
    }

    try {
        db()->prepare('INSERT INTO usuarios (nombre, email, password_hash, perfil) VALUES (?, ?, ?, ?)')
            ->execute([trim($nombre), $email, password_hash($password, PASSWORD_DEFAULT), $perfil]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'error' => 'Ya hay una cuenta con ese correo. ¿Quieres ingresar?'];
        }
        throw $e;
    }

    auth_abrir_sesion((int) db()->lastInsertId());
    return ['ok' => true];
}

/** @return array{ok:bool, error?:string} */
function auth_ingresar(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));

    $minutos = auth_bloqueado($email);
    if ($minutos > 0) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Espera ' . $minutos . ' minuto' . ($minutos === 1 ? '' : 's') . ' e inténtalo de nuevo.'];
    }

    $consulta = db()->prepare('SELECT id, password_hash FROM usuarios WHERE email = ?');
    $consulta->execute([$email]);
    $usuario = $consulta->fetch();

    // Una cuenta creada con Google NO tiene contraseña (`password_hash` a
    // NULL). Sin este corte, password_verify() recibiría null y PHP 8 lanzaría
    // un TypeError: la pantalla se rompería en vez de explicar qué hacer.
    if ($usuario && $usuario['password_hash'] === null) {
        return ['ok' => false, 'error' => 'Esa cuenta entra con Google. Usa el botón «Entrar con Google».'];
    }

    // Mismo mensaje en ambos casos: no revelamos si el correo existe.
    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        auth_registrar_fallo($email);
        return ['ok' => false, 'error' => 'Correo o contraseña incorrectos.'];
    }

    // Si el algoritmo por defecto cambió, se rehace el hash al vuelo.
    if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $usuario['id']]);
    }

    auth_limpiar_intentos($email);
    auth_abrir_sesion((int) $usuario['id']);
    return ['ok' => true];
}

/**
 * Entrar con Google: busca la cuenta, la enlaza o la crea, y abre sesión.
 *
 * Tres casos, en este orden:
 *  1. Ya entró antes con Google → se reconoce por `google_id`.
 *  2. Existe con contraseña y el MISMO correo → se enlazan. Google verifica el
 *     correo (se comprueba antes de llegar aquí), así que no hay suplantación;
 *     obligar a tener dos cuentas solo sería molesto.
 *  3. No existe → se crea sin contraseña (`password_hash` a NULL). Guardar un
 *     hash inventado sería fingir que tiene una.
 *
 * @param array{sub:string, email:string, nombre:string} $perfil
 * @return array{ok:bool, error?:string, id?:int}
 */
function auth_entrar_con_google(array $perfil): array
{
    $sub    = trim((string) ($perfil['sub'] ?? ''));
    $email  = mb_strtolower(trim((string) ($perfil['email'] ?? '')));
    $nombre = trim((string) ($perfil['nombre'] ?? '')) ?: 'Docente';

    if ($sub === '' || $email === '') {
        return ['ok' => false, 'error' => 'Google no devolvió los datos de la cuenta.'];
    }

    // 1. ¿Ya entró antes con Google?
    $consulta = db()->prepare('SELECT id FROM usuarios WHERE google_id = ?');
    $consulta->execute([$sub]);
    $id = (int) ($consulta->fetchColumn() ?: 0);

    if ($id === 0) {
        // 2. ¿Existe con ese correo? Se enlaza.
        $consulta = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
        $consulta->execute([$email]);
        $id = (int) ($consulta->fetchColumn() ?: 0);

        if ($id > 0) {
            db()->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')->execute([$sub, $id]);
        }
    }

    // 3. Cuenta nueva, sin contraseña.
    if ($id === 0) {
        try {
            db()->prepare('INSERT INTO usuarios (nombre, email, google_id, password_hash) VALUES (?, ?, ?, NULL)')
                ->execute([mb_substr($nombre, 0, 120), $email, $sub]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'Ya hay una cuenta con ese correo.'];
            }
            throw $e;
        }
        $id = (int) db()->lastInsertId();
    }

    auth_abrir_sesion($id);
    return ['ok' => true, 'id' => $id];
}

function auth_abrir_sesion(int $usuarioId): void
{
    auth_iniciar();
    session_regenerate_id(true);   // evita fijación de sesión
    $_SESSION['usuario_id'] = $usuarioId;
    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$usuarioId]);
}
