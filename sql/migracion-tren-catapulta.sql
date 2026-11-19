-- ---------------------------------------------------------------------------
-- «Tren de respuestas» y «Catapulta» (19 de noviembre de 2026).
--
-- Dos juegos de acción más, con datos de quiz y calificación en el servidor:
--   · tren: un tren con un vagón por respuesta pasa bajo una grúa; se suelta
--     la caja para que caiga en el vagón correcto mientras avanza.
--   · catapulta: se tensa la honda y el personaje sale volando contra el
--     cartel de la respuesta correcta.
-- Planes de pago, como los demás juegos de acción. Movibles desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'tren', 'Tren de respuestas', 'preguntas',
       'Suelta la caja en el vagón de la respuesta correcta mientras el tren avanza.',
       'activo', 'estandar', 1, 19
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'tren');

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'catapulta', 'Catapulta', 'preguntas',
       'Tensa la honda y lánzate contra el cartel de la respuesta correcta.',
       'activo', 'estandar', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'catapulta');
