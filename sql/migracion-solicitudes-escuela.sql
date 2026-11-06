-- ---------------------------------------------------------------------------
-- Solicitudes de la licencia Escuela (6 de noviembre de 2026).
--
-- Lo que deja un colegio en el formulario de escuela.php. Se guarda SIEMPRE,
-- aunque el correo falle: el servidor no tenía correo saliente y una solicitud
-- perdida es un colegio perdido. Se ve en el panel → «Solicitudes de colegios».
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS solicitudes_escuela (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contacto        VARCHAR(120) NOT NULL,
  cargo           VARCHAR(60)  NOT NULL DEFAULT '',
  institucion     VARCHAR(160) NOT NULL,
  ciudad          VARCHAR(100) NOT NULL DEFAULT '',
  correo          VARCHAR(190) NOT NULL,
  telefono        VARCHAR(40)  NOT NULL DEFAULT '',
  profesores      SMALLINT UNSIGNED NULL,
  mensaje         TEXT NULL,
  correo_enviado  TINYINT(1) NOT NULL DEFAULT 0,
  atendida        TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_solicitudes_atendida (atendida, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
