-- ---------------------------------------------------------------------------
-- Pago mensual o anual, con descuento por pagar el año por adelantado.
--
--   Estándar  $9.900/mes  ·  $95.040/año   (20 % — equivale a $7.920/mes)
--   Pro      $16.900/mes  ·  $141.960/año  (30 % — equivale a $11.830/mes)
--
-- Se guarda SOLO el precio anual, no el porcentaje: el descuento se calcula al
-- mostrarlo (1 - anual / (mensual × 12)). Guardar las dos cosas es garantizar
-- que un día dejen de cuadrar.
--
-- `precio_anual = 0` significa "este plan no se vende por año" (es el caso del
-- plan gratis, y el valor por defecto si alguien añade un plan nuevo).
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planes' AND COLUMN_NAME = 'precio_anual');
SET @sql := IF(@hay = 0,
  'ALTER TABLE planes ADD COLUMN precio_anual INT UNSIGNED NOT NULL DEFAULT 0 AFTER precio',
  'SELECT "planes.precio_anual ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Precios nuevos. Pro sube de 14.900 a 16.900 al mes; Estándar no se toca.
UPDATE planes SET precio = 9900,  precio_anual = 95040  WHERE clave = 'estandar';
UPDATE planes SET precio = 16900, precio_anual = 141960 WHERE clave = 'pro';
UPDATE planes SET precio = 0,     precio_anual = 0      WHERE clave = 'gratis';

SELECT clave, nombre, precio AS mensual, precio_anual AS anual,
       CASE WHEN precio > 0 AND precio_anual > 0
            THEN CONCAT(ROUND((1 - precio_anual / (precio * 12)) * 100), ' %')
            ELSE '—' END AS descuento,
       CASE WHEN precio_anual > 0 THEN ROUND(precio_anual / 12) ELSE 0 END AS equivale_al_mes
  FROM planes ORDER BY orden;
