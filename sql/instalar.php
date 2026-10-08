<?php

declare(strict_types=1);

/**
 * Prepara la base de datos: esquema y migraciones, en orden.
 *
 * Se usa al montar Ludia en un servidor nuevo y también para poner al día una
 * instalación que se quedó atrás. Todas las migraciones son idempotentes, así
 * que correr esto dos veces no rompe nada.
 *
 *     php sql/instalar.php
 *
 * SOLO por línea de comandos. Un instalador accesible desde el navegador es una
 * puerta abierta a que cualquiera reinicie la base, así que se corta de entrada.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este instalador solo se ejecuta por línea de comandos.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

/**
 * El orden importa y NO es el alfabético ni el de fechas.
 *
 * El esquema va primero, y varias migraciones dependen de otras: la escuela
 * necesita `paquetes`, el plan escuela necesita `planes`, el periodo necesita
 * `suscripciones`. Esta lista es el orden en que se aplicaron de verdad.
 *
 * Al añadir una migración nueva, va AL FINAL.
 */
const ORDEN = [
    'schema.sql',
    'migracion-planes.sql',
    'migracion-fk-sesiones.sql',
    'migracion-suscripciones.sql',
    'migracion-planes-editables.sql',
    'migracion-paquetes.sql',
    'migracion-equipos.sql',
    'migracion-precios-anuales.sql',
    'migracion-periodo-suscripcion.sql',
    'migracion-google.sql',
    'migracion-recuperar-clave.sql',
    'migracion-escuela.sql',
    'migracion-plan-escuela.sql',
    'migracion-permisos.sql',
    'migracion-video-preguntas.sql',
    'migracion-wompi.sql',
];

$base = __DIR__;
$fallos = 0;

echo "Preparando la base «" . config('db.name', 'ludia') . "»\n\n";

// Aviso antes de empezar: un archivo que falte deja la base a medias, y es
// mejor saberlo ahora que a mitad del proceso.
$faltan = array_values(array_filter(ORDEN, static fn (string $a): bool => !is_file($base . '/' . $a)));
if ($faltan) {
    echo "No encuentro estos archivos:\n  " . implode("\n  ", $faltan) . "\n";
    exit(1);
}

// Y al revés: un .sql que existe pero nadie corre pasaría desapercibido.
$sueltos = array_values(array_diff(
    array_map('basename', glob($base . '/*.sql') ?: []),
    ORDEN
));
if ($sueltos) {
    echo "AVISO · hay archivos .sql que este instalador NO corre:\n  "
        . implode("\n  ", $sueltos) . "\n"
        . "  Si son migraciones de verdad, añádelas a ORDEN en este archivo.\n\n";
}

foreach (ORDEN as $archivo) {
    $sql = (string) file_get_contents($base . '/' . $archivo);

    // `schema.sql` trae `CREATE DATABASE ludia` y `USE ludia;` para poder
    // importarlo a mano desde phpMyAdmin. Aquí estorban: ya estamos conectados
    // a la base que dice la configuración, y en un hosting compartido esa base
    // casi nunca se llama «ludia» —suele ser algo como «c12345_ludia»—. Sin
    // quitarlos, el instalador crearía una base paralela y la aplicación
    // arrancaría vacía sin que nadie entendiera por qué.
    $sql = preg_replace('~^\s*(CREATE\s+DATABASE|USE)\s[^;]*;~im', '', $sql) ?? $sql;

    try {
        db()->exec($sql);
        printf("  ok    %s\n", $archivo);
    } catch (PDOException $e) {
        $fallos++;
        printf("  FALLA %s\n        %s\n", $archivo, $e->getMessage());
    }
}

echo "\n";

if ($fallos > 0) {
    echo $fallos . " archivo(s) fallaron. La base puede haber quedado a medias: revisa antes de seguir.\n";
    exit(1);
}

// Comprobación de que quedó utilizable, no solo de que no hubo errores.
$planes   = (int) db()->query('SELECT COUNT(clave) FROM planes')->fetchColumn();
$formatos = (int) db()->query("SELECT COUNT(clave) FROM formatos WHERE estado = 'activo'")->fetchColumn();

echo "Listo: " . $planes . " planes y " . $formatos . " actividades activas.\n";

if ($planes < 4 || $formatos < 20) {
    echo "AVISO: son menos de los esperados (4 planes, 23 actividades). Revísalo.\n";
    exit(1);
}
