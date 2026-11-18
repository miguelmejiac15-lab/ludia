-- ---------------------------------------------------------------------------
-- Juegos (18 de noviembre de 2026).
--
-- Un JUEGO es contenido escrito una sola vez (una lista de parejas o de
-- preguntas) que se juega en muchas plantillas: Relacionar, Memoria, Quiz…
-- y se cambia de una a otra con un clic, sin reescribir nada.
--
-- Se guarda como una fila de `paquetes` con clase = 'juego' a propósito:
--   · `juego_contenido` es el contenido canónico, lo que escribe el profesor;
--   · `plantilla` es la forma en que se juega ahora;
--   · `contenido` es esa forma YA CONVERTIDA a una actividad normal de Ludia.
-- Así un juego hereda sin código nuevo todo lo que ya funciona con paquetes:
-- jugar en vivo, enviar por enlace, calificación en el servidor, informes,
-- cursos y exportar.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paquetes' AND COLUMN_NAME = 'clase');
SET @sql := IF(@hay = 0,
  'ALTER TABLE paquetes ADD COLUMN clase ENUM(''paquete'',''juego'') NOT NULL DEFAULT ''paquete'' AFTER audiencia',
  'SELECT "paquetes.clase ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paquetes' AND COLUMN_NAME = 'juego_tipo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE paquetes ADD COLUMN juego_tipo VARCHAR(20) NULL AFTER clase',
  'SELECT "paquetes.juego_tipo ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paquetes' AND COLUMN_NAME = 'juego_contenido');
SET @sql := IF(@hay = 0,
  'ALTER TABLE paquetes ADD COLUMN juego_contenido LONGTEXT NULL AFTER juego_tipo',
  'SELECT "paquetes.juego_contenido ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paquetes' AND COLUMN_NAME = 'plantilla');
SET @sql := IF(@hay = 0,
  'ALTER TABLE paquetes ADD COLUMN plantilla VARCHAR(40) NULL AFTER juego_contenido',
  'SELECT "paquetes.plantilla ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paquetes' AND INDEX_NAME = 'idx_paquetes_clase');
SET @sql := IF(@hay = 0,
  'ALTER TABLE paquetes ADD KEY idx_paquetes_clase (usuario_id, clase)',
  'SELECT "idx_paquetes_clase ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
