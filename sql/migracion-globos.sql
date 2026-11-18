-- ---------------------------------------------------------------------------
-- Formato «Globos» (18 de noviembre de 2026).
--
-- Juego de acción: suben globos con las respuestas y se revienta el correcto
-- antes de que se escape. Datos de quiz; se califica igual. Pensado para los
-- más pequeños: solo hay que tocar. Planes de pago, movible desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT
    'globos',
    'Globos',
    'preguntas',
    'Suben globos con las respuestas: revienta el correcto antes de que se escape.',
    'activo',
    'estandar',
    1,
    16
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'globos');
