<?php

declare(strict_types=1);

/**
 * Recuperar la contraseña: testigos de un solo uso.
 *
 * Hoy NO hay correo saliente configurado (php.ini apunta a un SMTP que no
 * existe), así que el enlace se entrega por el panel de administración. La
 * parte delicada —generar, guardar y validar el testigo— está construida como
 * debe estar; el día que haya SMTP solo cambia QUIÉN entrega el enlace, y
 * `recuperar_pedir()` ya devuelve la URL lista para enviarla.
 *
 * Tres reglas que no se aflojan:
 *   1. En la tabla se guarda el HASH del testigo, nunca el testigo.
 *   2. La respuesta a "olvidé mi contraseña" es SIEMPRE la misma, exista o no
 *      el correo: lo contrario regala la lista de cuentas a cualquiera.
 *   3. Una cuenta de Google no puede fijarse contraseña por aquí.
 */

require_once __DIR__ . '/Auth.php';

/** Cuánto vive un enlace. Una hora: suficiente para leer el correo, poco para
 *  que quede olvidado y activo en una bandeja de entrada ajena. */
const RECUPERAR_MINUTOS = 60;

/** Cuántas peticiones seguidas se admiten por cuenta antes de ignorarlas. */
const RECUPERAR_MAX_POR_HORA = 5;

/**
 * Pide un restablecimiento.
 *
 * Devuelve SIEMPRE ok:true. El campo `enlace` solo viene cuando de verdad se
 * generó algo, y quien llama no debe mostrarlo al visitante: es para el
 * administrador (o, en el futuro, para el correo).
 *
 * @return array{ok:bool, enlace?:string, usuario?:string, motivo?:string}
 */
function recuperar_pedir(string $email): array
{
    $email = mb_strtolower(trim($email));

    $consulta = db()->prepare('SELECT id, nombre, password_hash, google_id FROM usuarios WHERE email = ?');
    $consulta->execute([$email]);
    $usuario = $consulta->fetch();

    if (!$usuario) {
        return ['ok' => true, 'motivo' => 'no existe esa cuenta'];
    }

    // Cuenta de Google sin contraseña: dejarle fijar una por aquí convertiría
    // su cuenta en uno de correo y clave sin que se entere, y quien controle
    // su bandeja de entrada entraría aunque Google tenga verificación en dos
    // pasos. Se le recuerda por dónde entra.
    if ($usuario['password_hash'] === null && $usuario['google_id'] !== null) {
        return ['ok' => true, 'motivo' => 'esa cuenta entra con Google'];
    }

    // Freno: cinco peticiones por hora y cuenta. Sin esto, alguien puede
    // llenar la bandeja de entrada de un usuario pidiendo enlaces sin parar.
    $recientes = db()->prepare(
        'SELECT COUNT(*) FROM recuperaciones
          WHERE usuario_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $recientes->execute([$usuario['id']]);
    if ((int) $recientes->fetchColumn() >= RECUPERAR_MAX_POR_HORA) {
        return ['ok' => true, 'motivo' => 'demasiadas peticiones seguidas'];
    }

    // Los testigos anteriores de esa cuenta dejan de valer: pedir uno nuevo
    // debe invalidar el viejo, o quedarían varias puertas abiertas a la vez.
    db()->prepare('UPDATE recuperaciones SET usado_at = NOW() WHERE usuario_id = ? AND usado_at IS NULL')
        ->execute([$usuario['id']]);

    $testigo = bin2hex(random_bytes(32));

    db()->prepare(
        'INSERT INTO recuperaciones (usuario_id, testigo_hash, expira_at, pedido_desde)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)'
    )->execute([
        $usuario['id'],
        hash('sha256', $testigo),
        RECUPERAR_MINUTOS,
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    return [
        'ok'      => true,
        // Absoluta y no relativa: este enlace SALE de la aplicación —hoy lo
        // copia un administrador, mañana irá en un correo— y una ruta suelta
        // como «/app/clave-nueva.php?t=…» no se puede pegar en un navegador.
        'enlace'  => url_absoluta('app/clave-nueva.php') . '?t=' . $testigo,
        'usuario' => (string) $usuario['nombre'],
    ];
}

/**
 * ¿Sirve este testigo? Devuelve el id del usuario, o null.
 *
 * Se comprueba la caducidad EN LA CONSULTA (`expira_at > NOW()`), con la hora
 * del servidor de base de datos: comparar fechas en PHP abre la puerta a que
 * un desajuste de reloj alargue la validez sin que nadie lo note.
 */
function recuperar_usuario_de(string $testigo): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $testigo)) {
        return null;
    }

    $consulta = db()->prepare(
        'SELECT usuario_id FROM recuperaciones
          WHERE testigo_hash = ? AND usado_at IS NULL AND expira_at > NOW()'
    );
    $consulta->execute([hash('sha256', $testigo)]);
    $id = $consulta->fetchColumn();

    return $id === false ? null : (int) $id;
}

/**
 * Cambia la contraseña y quema el testigo.
 *
 * @return array{ok:bool, error?:string}
 */
function recuperar_aplicar(string $testigo, string $password, string $repetida): array
{
    if (mb_strlen($password) < AUTH_MIN_PASSWORD) {
        return ['ok' => false, 'error' => 'La contraseña necesita al menos ' . AUTH_MIN_PASSWORD . ' caracteres.'];
    }
    if ($password !== $repetida) {
        return ['ok' => false, 'error' => 'Las dos contraseñas no coinciden.'];
    }

    $usuarioId = recuperar_usuario_de($testigo);
    if ($usuarioId === null) {
        return ['ok' => false, 'error' => 'Ese enlace ya se usó o caducó. Pide uno nuevo.'];
    }

    $bd = db();
    $bd->beginTransaction();

    try {
        // Quemar el testigo COMPROBANDO que seguía sin usar: si dos peticiones
        // llegan a la vez con el mismo enlace, solo una encuentra la fila y la
        // otra se queda sin cambiar nada. Sin esta condición, ambas pasarían.
        $quemar = $bd->prepare('UPDATE recuperaciones SET usado_at = NOW() WHERE testigo_hash = ? AND usado_at IS NULL');
        $quemar->execute([hash('sha256', $testigo)]);

        if ($quemar->rowCount() === 0) {
            $bd->rollBack();
            return ['ok' => false, 'error' => 'Ese enlace ya se usó. Pide uno nuevo.'];
        }

        $bd->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT), $usuarioId]);

        // Cualquier otro enlace pendiente de esa cuenta deja de valer.
        $bd->prepare('UPDATE recuperaciones SET usado_at = NOW() WHERE usuario_id = ? AND usado_at IS NULL')
           ->execute([$usuarioId]);

        $bd->commit();
    } catch (PDOException $e) {
        $bd->rollBack();
        throw $e;
    }

    // Quien recupera su contraseña no puede quedar bloqueado por los intentos
    // fallidos que le hicieron pedirla.
    $correo = db()->prepare('SELECT email FROM usuarios WHERE id = ?');
    $correo->execute([$usuarioId]);
    $email = (string) $correo->fetchColumn();
    if ($email !== '') {
        auth_limpiar_intentos($email);
    }

    return ['ok' => true];
}

/** Borra los testigos vencidos o gastados. No es urgente, pero la tabla no
 *  tiene por qué crecer para siempre. */
function recuperar_limpiar(): int
{
    $borrar = db()->prepare(
        'DELETE FROM recuperaciones
          WHERE expira_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR usado_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
    );
    $borrar->execute();

    return $borrar->rowCount();
}
