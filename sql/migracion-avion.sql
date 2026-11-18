-- ---------------------------------------------------------------------------
-- Formato «Vuela a la respuesta» (18 de noviembre de 2026).
--
-- El primer juego de acción: el estudiante mueve un avioncito con el dedo y
-- atraviesa la nube que tiene la respuesta correcta. Los datos son los de un
-- quiz (enunciado, opciones, correcta), así que se califica igual.
--
-- Planes de pago (Estándar y arriba), como los juegos de acción de la
-- competencia. Se puede mover de plan desde el panel sin tocar esto.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT
    'avion',
    'Vuela a la respuesta',
    'preguntas',
    'Un avioncito vuela entre nubes con las respuestas: atraviesa la correcta.',
    'activo',
    'estandar',
    1,
    15
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'avion');
