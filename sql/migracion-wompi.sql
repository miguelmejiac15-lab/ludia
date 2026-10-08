-- ---------------------------------------------------------------------------
-- Cobros con Wompi (8 de octubre de 2026).
--
-- Wompi no tiene suscripciones automáticas como Mercado Pago: cada mes o cada
-- año es un pago suelto por adelantado. Cada pago se reconoce por su
-- `referencia`, que Ludia inventa al abrir el checkout y Wompi devuelve en el
-- aviso. Es única porque Wompi la exige única y porque dos suscripciones con la
-- misma referencia harían que un solo pago activara las dos.
--
-- `preapproval_id` (de Mercado Pago) se queda: no estorba y borrar columnas en
-- una migración que corre en cada arranque es buscarse un susto.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suscripciones' AND COLUMN_NAME = 'referencia');
SET @sql := IF(@hay = 0,
  'ALTER TABLE suscripciones ADD COLUMN referencia VARCHAR(80) NULL AFTER estado, ADD UNIQUE KEY uq_suscripciones_referencia (referencia)',
  'SELECT "suscripciones.referencia ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
