-- ---------------------------------------------------------------------------
-- Cuatro juegos de acción más (19 de noviembre de 2026), con la línea visual
-- del laberinto y datos de quiz (calificación en el servidor):
--   · salta: plataformas; golpear desde abajo el bloque correcto.
--   · mina: mundo de bloques; picar hasta la gema correcta.
--   · obstaculos: carrera; cruzar el arco correcto y saltar los demás.
--   · nave: naves; disparar al asteroide correcto.
-- Se inspiran en GÉNEROS de videojuegos, nunca en personajes ni en el aspecto
-- de un juego concreto. Planes de pago, movibles desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'salta', 'Salta y choca', 'preguntas', 'Corre y salta para golpear desde abajo el bloque con la respuesta correcta. ¡Cuidado con los cocos!', 'activo', 'estandar', 1, 21
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'salta');

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'mina', 'Mina de bloques', 'preguntas', 'Pica bloques hacia abajo hasta la gema con la respuesta correcta. Algunas rocas son duras.', 'activo', 'estandar', 1, 22
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'mina');

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'obstaculos', 'Carrera de obstáculos', 'preguntas', 'Corre solo: cruza el arco de la respuesta correcta y salta los demás, los charcos y las vallas.', 'activo', 'estandar', 1, 23
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'obstaculos');

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'nave', 'Nave espacial', 'preguntas', 'Dispara al asteroide con la respuesta correcta y esquiva los meteoritos.', 'activo', 'estandar', 1, 24
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'nave');
