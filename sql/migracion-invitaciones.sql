-- ---------------------------------------------------------------------------
-- Cortesías y descuentos (15 de octubre de 2026).
--
-- Para las reuniones con docentes y las redes:
--  · CORTESÍA: Pro gratis por N meses (3 por defecto). La da el administrador
--    a mano, o llega con un enlace. Es una fila de `suscripciones` con
--    origen = 'cortesia' y monto 0, así que vence SOLA como cualquier plan
--    pagado: `pagado_hasta` sigue siendo la única fuente de verdad del acceso.
--  · DESCUENTO: un porcentaje (30 % por defecto) en UN SOLO pago, mensual o
--    anual. Llega con un enlace y se gasta cuando Wompi aprueba ese pago.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

-- Enlaces que reparte el administrador. El código va en la URL.
CREATE TABLE IF NOT EXISTS invitaciones (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo      VARCHAR(24)  NOT NULL,
  tipo        ENUM('cortesia','descuento') NOT NULL,
  meses       TINYINT UNSIGNED NOT NULL DEFAULT 3,     -- solo cortesía
  porcentaje  TINYINT UNSIGNED NOT NULL DEFAULT 30,    -- solo descuento
  nota        VARCHAR(120) NOT NULL DEFAULT '',        -- «Reunión colegio X», «Instagram»
  usos_max    INT UNSIGNED NULL,                       -- NULL = sin límite
  usos        INT UNSIGNED NOT NULL DEFAULT 0,
  activa      TINYINT(1)   NOT NULL DEFAULT 1,
  expira_at   DATETIME     NULL,
  creado_por  INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitaciones_codigo (codigo),
  CONSTRAINT fk_invitaciones_creador FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo que recibió cada cuenta, venga de un enlace o del panel (invitacion_id NULL).
-- Un descuento está disponible mientras `usado_at` sea NULL.
CREATE TABLE IF NOT EXISTS beneficios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED NOT NULL,
  invitacion_id   INT UNSIGNED NULL,
  tipo            ENUM('cortesia','descuento') NOT NULL,
  meses           TINYINT UNSIGNED NULL,
  porcentaje      TINYINT UNSIGNED NULL,
  suscripcion_id  INT UNSIGNED NULL,     -- la cortesía creada, o el pago que gastó el descuento
  usado_at        DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_beneficios_usuario (usuario_id, tipo),
  CONSTRAINT fk_beneficios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_beneficios_invitacion FOREIGN KEY (invitacion_id) REFERENCES invitaciones (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De dónde salió cada periodo: un pago o una cortesía.
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suscripciones' AND COLUMN_NAME = 'origen');
SET @sql := IF(@hay = 0,
  'ALTER TABLE suscripciones ADD COLUMN origen ENUM("pago","cortesia") NOT NULL DEFAULT "pago" AFTER periodo',
  'SELECT "suscripciones.origen ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- El descuento con el que se abrió el pago (NULL = precio completo).
SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suscripciones' AND COLUMN_NAME = 'descuento_pct');
SET @sql := IF(@hay = 0,
  'ALTER TABLE suscripciones ADD COLUMN descuento_pct TINYINT UNSIGNED NULL AFTER monto',
  'SELECT "suscripciones.descuento_pct ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
