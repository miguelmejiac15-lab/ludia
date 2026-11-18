-- ---------------------------------------------------------------------------
-- Formato «Atrapa la correcta» (18 de noviembre de 2026).
--
-- Juego de acción: en un jardín se asoman animalitos con un cartel y hay que
-- tocar al que lleva la respuesta correcta antes de que se esconda. Datos de
-- quiz; se califica igual. Planes de pago, movible desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT
    'atrapa',
    'Atrapa la correcta',
    'preguntas',
    'Se asoman animalitos con carteles: toca el que lleva la respuesta correcta.',
    'activo',
    'estandar',
    1,
    17
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'atrapa');
