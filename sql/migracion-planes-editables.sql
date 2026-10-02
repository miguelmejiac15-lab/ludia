-- =====================================================================
-- Ludia — los planes pasan a la base de datos (16 de septiembre de 2026)
--
-- Hasta ahora los planes y sus precios vivían escritos a fuego en
-- includes/Planes.php. Para poder cambiarlos desde el panel de administración
-- tienen que ser datos, no código.
--
-- Reglas de los planes (decididas por el usuario):
--   · Gratis   → 3 paquetes, medio catálogo, sin imágenes.
--   · Estándar → muchos más paquetes pero CON TOPE, casi todas las actividades.
--   · Pro      → paquetes y actividades ILIMITADOS.
--   · Los dos de pago usan imágenes y reciben las actividades nuevas.
--   · No hay plan institucional: se vende por usuario.
--
-- Ejecutar:  mysql -u root ludia < sql/migracion-planes-editables.sql
-- Se puede repetir sin miedo.
-- =====================================================================

USE ludia;

CREATE TABLE IF NOT EXISTS planes (
  clave          ENUM('gratis','estandar','pro') NOT NULL,
  nombre         VARCHAR(40)       NOT NULL,
  resumen        VARCHAR(160)      NOT NULL DEFAULT '',
  precio         INT UNSIGNED      NOT NULL DEFAULT 0,      -- en pesos, por mes
  moneda         CHAR(3)           NOT NULL DEFAULT 'COP',
  -- 0 = sin tope. Es la diferencia central entre Estándar y Pro.
  max_paquetes   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_participantes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  max_actividades SMALLINT UNSIGNED NOT NULL DEFAULT 0,     -- por paquete; 0 = sin tope
  imagenes       TINYINT(1)        NOT NULL DEFAULT 0,
  informe        TINYINT(1)        NOT NULL DEFAULT 0,
  guarda         TINYINT(1)        NOT NULL DEFAULT 0,
  destacado      TINYINT(1)        NOT NULL DEFAULT 0,      -- el "recomendado" de la portada
  orden          TINYINT UNSIGNED  NOT NULL DEFAULT 10,
  updated_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores de partida. Al repetir la migración NO se pisan los precios que el
-- administrador haya cambiado desde el panel: solo se rellena lo que falte.
INSERT INTO planes (clave, nombre, resumen, precio, max_paquetes, max_participantes, max_actividades, imagenes, informe, guarda, destacado, orden) VALUES
  ('gratis',   'Gratis',   'Para probar Ludia con tu grupo',        0,  3,  30, 10, 0, 0, 0, 0, 10),
  ('estandar', 'Estándar', 'Para el uso regular en tus clases',   9900, 30,  60, 30, 1, 1, 1, 0, 20),
  ('pro',      'Pro',      'Sin límites: paquetes y actividades', 14900,  0, 200,  0, 1, 1, 1, 1, 30)
ON DUPLICATE KEY UPDATE clave = clave;

-- ---------------------------------------------------------------------
-- Qué actividad entra en qué plan.
--
-- Estándar se queda con "casi todas": las que faltan son las cuatro más
-- vistosas, reservadas a Pro. Así el salto a Pro tiene algo visible además de
-- quitar los topes. El administrador puede cambiarlo desde el panel.
-- ---------------------------------------------------------------------
UPDATE formatos SET plan_minimo = 'estandar'
 WHERE plan_minimo <> 'gratis';

UPDATE formatos SET plan_minimo = 'pro'
 WHERE clave IN ('mural', 'crucigrama', 'memoria', 'linea_tiempo');
