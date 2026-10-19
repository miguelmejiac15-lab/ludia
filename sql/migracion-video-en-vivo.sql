-- ---------------------------------------------------------------------------
-- Vídeo con preguntas EN VIVO (19 de octubre de 2026).
--
-- En vivo el vídeo se reproduce en el proyector y los celulares esperan. La
-- pregunta se ABRE cuando el vídeo llega a su minuto: el proyector lo avisa y
-- en ese momento arranca el cronómetro, para que los puntos por rapidez no
-- cuenten el tiempo que se pasó mirando el vídeo.
--
-- `pregunta_abierta` vale 1 para todo lo demás (las preguntas de siempre se
-- abren en cuanto aparecen) y 0 mientras corre el tramo de un vídeo.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'pregunta_abierta');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN pregunta_abierta TINYINT(1) NOT NULL DEFAULT 1 AFTER item_inicio_at',
  'SELECT "sesiones.pregunta_abierta ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
