-- ---------------------------------------------------------------------------
-- Recuperar la contraseña: testigos de un solo uso.
--
-- Tabla aparte y no columnas en `usuarios` porque un testigo vive minutos y
-- la inmensa mayoría de las cuentas no tiene ninguno: serían dos columnas
-- vacías en todas las filas, para siempre.
--
-- Idempotente: se puede correr varias veces sin romper nada.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS recuperaciones (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,

    -- Se guarda el HASH del testigo, no el testigo. Si alguien llega a leer
    -- esta tabla, no puede usar lo que ve para entrar en ninguna cuenta:
    -- es el mismo motivo por el que no se guardan las contraseñas en claro.
    testigo_hash CHAR(64) NOT NULL,

    expira_at   DATETIME NOT NULL,
    usado_at    DATETIME NULL DEFAULT NULL,
    pedido_desde VARCHAR(45) NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    UNIQUE KEY uq_testigo (testigo_hash),
    KEY idx_usuario (usuario_id),
    KEY idx_expira (expira_at),

    CONSTRAINT fk_recuperaciones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
