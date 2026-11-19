-- ---------------------------------------------------------------------------
-- Juegos enviados: el estudiante elige cómo jugar (18 de noviembre de 2026).
--
-- Como en la referencia (Wordwall): quien abre el enlace de un JUEGO puede
-- cambiar de plantilla. Al enviar un juego, la sesión lleva una actividad por
-- cada plantilla compatible (`eleccion_libre` = 1) y el estudiante elige cuál
-- jugar; `participantes.eligiendo` = 1 mientras está en el menú.
--
-- Los paquetes siguen igual: un recorrido fijo. En vivo, tampoco cambia nada.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'eleccion_libre');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN eleccion_libre TINYINT(1) NOT NULL DEFAULT 0 AFTER modo',
  'SELECT "sesiones.eleccion_libre ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participantes' AND COLUMN_NAME = 'eligiendo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE participantes ADD COLUMN eligiendo TINYINT(1) NOT NULL DEFAULT 0 AFTER terminado',
  'SELECT "participantes.eligiendo ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Qué respondió el estudiante en cada forma de jugar: {"3": {"i": [0, 1], "p": 900}}.
-- Va aparte de `respuestas` porque en el plan gratis lo enviado no se
-- registra (el informe es de pago), y el menú igual necesita saber por dónde
-- iba. Vive en la fila del participante y caduca con la sesión.
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participantes' AND COLUMN_NAME = 'avance_libre');
SET @sql := IF(@hay = 0,
  'ALTER TABLE participantes ADD COLUMN avance_libre TEXT NULL AFTER eligiendo',
  'SELECT "participantes.avance_libre ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
