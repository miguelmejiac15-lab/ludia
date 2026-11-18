-- ---------------------------------------------------------------------------
-- Formato «Laberinto» (18 de noviembre de 2026).
--
-- Juego de acción: el personaje del estudiante sale del centro de un
-- laberinto y elige llegando a una de las puertas de las esquinas (hasta 4
-- respuestas), mientras lo persiguen dos abejas. Datos de quiz; se califica
-- igual. Planes de pago, movible desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT
    'laberinto',
    'Laberinto',
    'preguntas',
    'Llega a la puerta con la respuesta correcta escapando de las abejas.',
    'activo',
    'estandar',
    1,
    18
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'laberinto');
