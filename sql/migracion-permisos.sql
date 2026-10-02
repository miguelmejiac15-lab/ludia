-- ---------------------------------------------------------------------------
-- Permisos personalizados por cuenta, y «exportar» como capacidad del plan.
--
-- Dos cosas que hasta ahora estaban mezcladas o clavadas:
--
-- 1. EXPORTAR A SCORM/HTML estaba escrito en el código como `plan === 'pro'`.
--    Eso dejaba fuera a las cuentas de Escuela, que deben poder todo lo que
--    puede Pro, y obligaba a tocar código para cambiar de idea. Pasa a ser una
--    columna de `planes`, como `imagenes` o `informe`: editable desde el panel.
--
-- 2. NO HABÍA FORMA DE DARLE ALGO A UNA CUENTA CONCRETA. Si un profesor
--    necesitaba exportar, había que subirlo de plan entero. Ahora
--    `usuarios.permisos` guarda SOLO las excepciones: lo que no esté ahí se
--    resuelve por su plan, como siempre.
--
-- Idempotente: se puede correr varias veces sin romper nada.
-- ---------------------------------------------------------------------------

-- ---------- 1. Exportar, como capacidad del plan ----------

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'planes' AND column_name = 'exporta');
SET @sql := IF(@hay = 0,
    'ALTER TABLE planes ADD COLUMN exporta TINYINT(1) NOT NULL DEFAULT 0 AFTER guarda',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Quien exportaba hasta hoy: Pro. Y Escuela, que da lo mismo que Pro y estaba
-- quedándose fuera por el `=== 'pro'` escrito a mano.
UPDATE planes SET exporta = 1 WHERE clave IN ('pro', 'escuela') AND exporta = 0;

-- ---------- 2. Permisos por cuenta ----------

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'permisos');
SET @sql := IF(@hay = 0,
    -- JSON con SOLO las excepciones, p. ej. {"exporta":1,"formatos":"todos"}.
    -- NULL o {} = esta cuenta se comporta exactamente como su plan, que es lo
    -- normal y debe seguir siéndolo: los permisos sueltos son la excepción.
    "ALTER TABLE usuarios ADD COLUMN permisos TEXT NULL DEFAULT NULL AFTER plan",
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
