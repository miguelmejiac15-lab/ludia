-- ---------------------------------------------------------------------------
-- «Ruleta» y «Cajas sorpresa» (18 de noviembre de 2026).
--
-- Herramientas de la clase que no se califican: sale una palabra, pregunta o
-- nombre al azar y la respuesta (opcional) se descubre cuando el docente
-- quiere. En vivo se manejan en el proyector; enviadas, en cada celular.
--
-- La ruleta entra en el plan gratis (es la puerta de entrada a los juegos);
-- las cajas, en los de pago. Ambas se pueden mover desde el panel.
--
-- Es idempotente.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'ruleta', 'Ruleta', 'apoyo',
       'Gira y sale una palabra, una pregunta o un nombre al azar.',
       'activo', 'gratis', 0, 90
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'ruleta');

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable, orden)
SELECT 'cajas', 'Cajas sorpresa', 'apoyo',
       'Cajas numeradas: abre una y descubre lo que esconde.',
       'activo', 'estandar', 0, 91
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'cajas');
