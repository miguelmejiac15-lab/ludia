<?php
/**
 * Conexión PDO a MySQL/MariaDB. Se abre una sola vez por petición y solo cuando se usa.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        config('db.host', '127.0.0.1'),
        (int) config('db.port', 3306),
        config('db.name', 'ludia'),
        config('db.charset', 'utf8mb4')
    );

    $pdo = new PDO($dsn, config('db.user', 'root'), config('db.pass', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
