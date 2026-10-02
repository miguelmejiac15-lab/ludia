-- ---------------------------------------------------------------------------
-- Equipos para las partidas en vivo.
--
-- Cuando ya entraron todos, el profesor puede repartirlos en equipos —al azar o
-- a mano— y a partir de ahí el marcador y el podio son por equipo. Cada quien
-- sigue respondiendo en su propio dispositivo: lo que cambia es a dónde suman
-- sus puntos.
--
-- El equipo pertenece a la SESIÓN, no al paquete ni a la cuenta: se arma para
-- una partida concreta y muere con ella. Por eso la clave foránea va en
-- cascada: al borrar la sesión (o al caducar) no queda ningún equipo huérfano.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

/* ---------- 1. Los equipos de una partida ---------- */

CREATE TABLE IF NOT EXISTS equipos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesion_id  INT UNSIGNED NOT NULL,
  nombre     VARCHAR(40) NOT NULL,
  -- Nombre de color del tema (coral, violeta, menta, sol…), no un hexadecimal:
  -- así la paleta se cambia en el CSS sin migrar datos.
  color      VARCHAR(16) NOT NULL DEFAULT 'violeta',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_equipos_sesion (sesion_id),
  CONSTRAINT fk_equipos_sesion FOREIGN KEY (sesion_id)
    REFERENCES sesiones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- 2. A qué equipo pertenece cada participante ---------- */
-- ON DELETE SET NULL, no CASCADE: deshacer los equipos no puede llevarse por
-- delante a la gente que ya está jugando ni sus respuestas.

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participantes' AND COLUMN_NAME = 'equipo_id');
SET @sql := IF(@hay = 0,
  'ALTER TABLE participantes ADD COLUMN equipo_id INT UNSIGNED NULL AFTER sesion_id',
  'SELECT "participantes.equipo_id ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participantes'
               AND CONSTRAINT_NAME = 'fk_participantes_equipo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE participantes ADD CONSTRAINT fk_participantes_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id) ON DELETE SET NULL',
  'SELECT "fk_participantes_equipo ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

/* ---------- 3. La sesión sabe si se juega por equipos ---------- */
-- Podría deducirse contando equipos, pero entonces "tengo equipos armados" y
-- "estoy jugando por equipos" serían lo mismo, y el profesor no podría
-- prepararlos antes de decidir. Con la marca, armar y activar son dos pasos.

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'por_equipos');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN por_equipos TINYINT(1) NOT NULL DEFAULT 0 AFTER modo',
  'SELECT "sesiones.por_equipos ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SELECT CONCAT('equipos: ', (SELECT COUNT(*) FROM equipos),
              ' · participantes con equipo: ', (SELECT COUNT(*) FROM participantes WHERE equipo_id IS NOT NULL),
              ' · sesiones por equipos: ', (SELECT COUNT(*) FROM sesiones WHERE por_equipos = 1)) AS resultado;
