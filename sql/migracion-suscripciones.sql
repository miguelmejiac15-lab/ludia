-- =====================================================================
-- Ludia — suscripciones con Mercado Pago (16 de septiembre de 2026)
--
-- Decisiones que refleja este esquema:
--  · Cobro RECURRENTE: Mercado Pago cobra cada mes y avisa por webhook.
--  · Al vencer NO se borra nada: la cuenta cae a gratis y sus paquetes quedan
--    guardados pero bloqueados. Si vuelve a pagar, lo recupera todo.
--
-- Ejecutar:  mysql -u root ludia < sql/migracion-suscripciones.sql
-- Se puede repetir sin miedo.
-- =====================================================================

USE ludia;

-- ---------------------------------------------------------------------
-- suscripciones — una fila por intento de suscripción de una cuenta.
--
-- `pagado_hasta` es la fuente de verdad del acceso: mientras esté en el futuro
-- el usuario disfruta de su plan, sin importar lo que diga usuarios.plan. Así
-- una cancelación no exige tocar nada más: la fecha simplemente deja de
-- renovarse y el plan efectivo vuelve a gratis solo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suscripciones (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id        INT UNSIGNED NOT NULL,
  plan              ENUM('estandar','pro') NOT NULL,
  estado            ENUM('pendiente','activa','pausada','cancelada','vencida') NOT NULL DEFAULT 'pendiente',
  -- Identificadores que devuelve Mercado Pago (preapproval = suscripción).
  preapproval_id    VARCHAR(80)  NULL,
  payer_email       VARCHAR(190) NULL,
  moneda            CHAR(3)      NOT NULL DEFAULT 'COP',
  monto             INT UNSIGNED NOT NULL DEFAULT 0,
  pagado_hasta      DATETIME     NULL,
  ultimo_pago_at    DATETIME     NULL,
  cancelada_at      DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suscripciones_preapproval (preapproval_id),
  KEY idx_suscripciones_usuario (usuario_id, estado),
  CONSTRAINT fk_suscripciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- pagos — historial de cobros confirmados. Sirve de comprobante y evita
-- procesar dos veces el mismo aviso de Mercado Pago (de ahí la clave única).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  suscripcion_id  INT UNSIGNED NULL,
  usuario_id      INT UNSIGNED NULL,
  pago_id         VARCHAR(80)  NOT NULL,          -- id del pago en Mercado Pago
  estado          VARCHAR(40)  NOT NULL,          -- approved, rejected, refunded...
  monto           INT UNSIGNED NOT NULL DEFAULT 0,
  moneda          CHAR(3)      NOT NULL DEFAULT 'COP',
  crudo           JSON         NULL,              -- lo que respondió Mercado Pago
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pagos_pago (pago_id),
  KEY idx_pagos_usuario (usuario_id, created_at),
  CONSTRAINT fk_pagos_suscripcion FOREIGN KEY (suscripcion_id) REFERENCES suscripciones (id) ON DELETE SET NULL,
  CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
