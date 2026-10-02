-- ---------------------------------------------------------------------------
-- Separar el PAQUETE (el contenido, tuyo y permanente) de la SESIÓN (cada vez
-- que se juega o se envía, con sus participantes y su informe).
--
-- Hasta ahora la tabla `sesiones` era las dos cosas a la vez, y de ahí salían
-- tres problemas: el contenido caducaba a las 24 h, no se podía editar en
-- cuanto alguien respondía, y no había dónde guardar el histórico.
--
-- Regla que lo sostiene todo: la sesión CONSERVA SU PROPIA COPIA del contenido
-- (`sesiones.contenido`) tal como se jugó. Por eso el paquete puede editarse
-- para siempre sin estropear los informes ya emitidos.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

/* ---------- 1. El paquete: contenido permanente de una cuenta ---------- */

CREATE TABLE IF NOT EXISTS paquetes (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id         INT UNSIGNED NOT NULL,
  nombre             VARCHAR(120) NOT NULL,
  audiencia          ENUM('estudiantes','auditorio','capacitacion') NOT NULL DEFAULT 'estudiantes',
  contenido          LONGTEXT NOT NULL,
  total_actividades  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- Rastro de la migración: de qué sesión salió este paquete. Se conserva para
  -- poder auditar la conversión; en los paquetes nuevos va a NULL.
  migrado_de_sesion_id INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_paquetes_migrado (migrado_de_sesion_id),
  KEY idx_paquetes_usuario (usuario_id),
  CONSTRAINT fk_paquetes_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- 2. La sesión apunta a su paquete ---------- */

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'paquete_id');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN paquete_id INT UNSIGNED NULL AFTER usuario_id',
  'SELECT "sesiones.paquete_id ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- ON DELETE SET NULL, no CASCADE: si el profesor borra el paquete, sus informes
-- siguen existiendo. El contenido jugado está copiado dentro de la sesión.
SET @hay := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones'
               AND CONSTRAINT_NAME = 'fk_sesiones_paquete');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD CONSTRAINT fk_sesiones_paquete FOREIGN KEY (paquete_id) REFERENCES paquetes (id) ON DELETE SET NULL',
  'SELECT "fk_sesiones_paquete ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

/* ---------- 3. Qué sesiones se conservan como informe ---------- */
-- IMPORTANTE: sesiones_limpiar_vencidas() borra por `expira_at`. Sin esta marca,
-- el histórico de un plan de pago desaparecería a las 24 h. Las sesiones con
-- informe_guardado = 1 quedan fuera de esa limpieza.

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'informe_guardado');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN informe_guardado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado',
  'SELECT "sesiones.informe_guardado ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND COLUMN_NAME = 'terminada_at');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD COLUMN terminada_at DATETIME NULL AFTER item_inicio_at',
  'SELECT "sesiones.terminada_at ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Índice para listar los informes de un paquete por fecha.
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones' AND INDEX_NAME = 'idx_sesiones_informe');
SET @sql := IF(@hay = 0,
  'ALTER TABLE sesiones ADD KEY idx_sesiones_informe (paquete_id, informe_guardado, terminada_at)',
  'SELECT "idx_sesiones_informe ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

/* ---------- 4. Convertir lo que ya existe ---------- */
-- Cada sesión con dueño se convierte en un paquete. El UNIQUE sobre
-- migrado_de_sesion_id impide duplicar si esto se ejecuta dos veces.

INSERT IGNORE INTO paquetes (usuario_id, nombre, audiencia, contenido, total_actividades, migrado_de_sesion_id, created_at, updated_at)
SELECT s.usuario_id, s.nombre, s.audiencia, s.contenido, s.total_actividades, s.id, s.created_at, s.updated_at
  FROM sesiones s
 WHERE s.usuario_id IS NOT NULL
   AND s.paquete_id IS NULL;

UPDATE sesiones s
  JOIN paquetes p ON p.migrado_de_sesion_id = s.id
   SET s.paquete_id = p.id
 WHERE s.paquete_id IS NULL;

-- Las sesiones ya terminadas de cuentas de pago se conservan como informe; el
-- resto sigue caducando como hasta ahora.
UPDATE sesiones s
  JOIN usuarios u ON u.id = s.usuario_id
   SET s.informe_guardado = 1,
       s.terminada_at = COALESCE(s.terminada_at, s.updated_at)
 WHERE s.estado = 'terminada'
   AND u.plan IN ('estandar', 'pro');

SELECT CONCAT('paquetes: ', (SELECT COUNT(*) FROM paquetes),
              ' · sesiones enlazadas: ', (SELECT COUNT(*) FROM sesiones WHERE paquete_id IS NOT NULL),
              ' · informes conservados: ', (SELECT COUNT(*) FROM sesiones WHERE informe_guardado = 1)) AS resultado;
