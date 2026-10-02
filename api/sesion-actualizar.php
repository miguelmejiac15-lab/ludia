<?php
/**
 * RETIRADO el 17 de septiembre de 2026.
 *
 * Guardaba los cambios de una sesión ya creada, y solo mientras nadie hubiera
 * respondido: reordenar el contenido habría dejado las respuestas apuntando a
 * otra pregunta.
 *
 * Esa restricción desapareció al separar el contenido de los resultados. El
 * paquete se edita SIEMPRE en /api/paquete-guardar.php, porque cada sesión
 * jugada conserva su propia copia de lo que se jugó.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_error(
    'Este endpoint se retiró. Los paquetes se editan siempre con paquete-guardar.php: cada sesión guarda su propia copia, así que los informes antiguos no se ven afectados.',
    410,
    ['mis_sesiones' => base_url('app/mis-sesiones.php')]
);
