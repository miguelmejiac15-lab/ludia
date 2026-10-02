-- ---------------------------------------------------------------------------
-- El periodo de cada suscripción: mensual o anual.
--
-- Sin esta columna, suscripcion_registrar_pago() suma un mes fijo (`P1M`) a
-- CUALQUIER cobro. Con el pago anual eso significa cobrar $141.960 y dar treinta
-- días de acceso: un fallo que no lanza ningún error y que solo se descubre
-- cuando un cliente reclama.
--
-- El periodo se guarda en la SUSCRIPCIÓN, no se deduce del monto: los precios
-- de la tabla `planes` cambian, y una suscripción vieja debe seguir renovándose
-- como se contrató.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suscripciones' AND COLUMN_NAME = 'periodo');
SET @sql := IF(@hay = 0,
  'ALTER TABLE suscripciones ADD COLUMN periodo ENUM("mensual","anual") NOT NULL DEFAULT "mensual" AFTER plan',
  'SELECT "suscripciones.periodo ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Las suscripciones que ya existían se contrataron cuando solo había cobro
-- mensual, así que el valor por defecto es el correcto para ellas.

SELECT CONCAT('suscripciones: ', COUNT(*),
              ' · mensuales: ', SUM(periodo = 'mensual'),
              ' · anuales: ',   SUM(periodo = 'anual')) AS resultado
  FROM suscripciones;
