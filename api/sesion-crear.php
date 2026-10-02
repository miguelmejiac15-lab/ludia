<?php
/**
 * RETIRADO el 17 de septiembre de 2026.
 *
 * Creaba el paquete y la sala a la vez, porque hasta entonces eran la misma
 * fila en la base. Ahora son dos cosas distintas:
 *
 *   · el contenido se guarda en  /api/paquete-guardar.php
 *   · jugarlo o enviarlo abre una sesión aparte, desde "Mis paquetes"
 *
 * Se deja este aviso en vez de borrar el archivo para que una copia antigua del
 * editor reciba una explicación, y no un 404 sin sentido.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_error(
    'Este endpoint se retiró. Ahora el contenido se guarda con paquete-guardar.php, y jugarlo o enviarlo abre una sesión aparte desde "Mis paquetes".',
    410,
    ['mis_sesiones' => base_url('app/mis-sesiones.php')]
);
