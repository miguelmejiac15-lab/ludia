-- ---------------------------------------------------------------------------
-- «Escuela» pasa a ser un plan visible, no solo una licencia por dentro.
--
-- Decidido por el usuario el 18/09/2026: debe aparecer junto a Gratis, Estándar
-- y Pro en la portada, en «Mi plan» y en el panel de precios — pero SIN cobro
-- automático. Seis millones al año no se cobran con tarjeta: se factura, y el
-- administrador activa la licencia a mano.
--
-- Lo que este archivo NO toca, a propósito:
--
--   · `suscripciones.plan` sigue siendo enum('estandar','pro'). Es la
--     salvaguarda de que «escuela» no pueda colarse en un cobro con tarjeta
--     ni forzando la petición a mano. Si algún día se cobra por pasarela,
--     esa decisión se toma aparte y con los ojos abiertos.
--
--   · `formatos.plan_minimo` sigue con tres valores. `plan_formatos()`
--     resuelve por NIVEL, y «escuela» comparte nivel con Pro, así que hereda
--     el catálogo completo sin necesidad de una cuarta columna de asignación.
--
-- Idempotente: se puede correr varias veces sin romper nada.
-- ---------------------------------------------------------------------------

-- ---------- 1. Los enums que sí deben admitirlo ----------

SET @tipo := (SELECT column_type FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'planes' AND column_name = 'clave');
SET @sql := IF(LOCATE('escuela', @tipo) = 0,
    "ALTER TABLE planes MODIFY clave ENUM('gratis','estandar','pro','escuela') NOT NULL",
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @tipo := (SELECT column_type FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'plan');
SET @sql := IF(LOCATE('escuela', @tipo) = 0,
    "ALTER TABLE usuarios MODIFY plan ENUM('gratis','estandar','pro','escuela') NOT NULL DEFAULT 'gratis'",
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- ---------- 2. La fila del plan ----------
-- `precio` a 0 porque NO se vende por mes: el precio vive en `precio_anual`.
-- Los topes son los de Pro, que es lo que la licencia reparte; el cupo de
-- profesores lo decide cada licencia en la tabla `colegios`, no aquí.

INSERT INTO planes
    (clave, nombre, resumen, precio, precio_anual, moneda,
     max_paquetes, max_participantes, max_actividades,
     imagenes, informe, guarda, destacado, orden)
SELECT
    'escuela',
    'Escuela',
    'Toda la institución: un coordinador, sus profesores y sus cursos con el progreso de cada estudiante.',
    0,
    6000000,
    'COP',
    50,     -- los mismos paquetes guardados que Pro
    200,    -- más participantes: un colegio proyecta a salones enteros
    0,      -- actividades por paquete sin tope, como Pro
    1, 1, 1,
    0,      -- no se destaca: el destacado es Pro, que es el que se compra solo
    40
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE clave = 'escuela');
