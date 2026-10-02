-- ---------------------------------------------------------------------------
-- Formato «Vídeo con preguntas».
--
-- Un vídeo de YouTube que se detiene en los minutos marcados para preguntar.
-- Solo funciona en modo «enviar»: cada estudiante lo ve a su ritmo. Esa regla
-- la aplica el servidor (SESION_SOLO_ENVIAR en Sesiones.php), no esta tabla.
--
-- Va en su propia migración y no solo en la base local porque, si no, una
-- instalación nueva —la de producción, sin ir más lejos— no tendría el formato
-- y la actividad no aparecería en el catálogo.
--
-- Idempotente: se puede correr varias veces sin romper nada.
-- ---------------------------------------------------------------------------

INSERT INTO formatos (clave, nombre, grupo, descripcion, estado, plan_minimo, evaluable)
SELECT
    'video_preguntas',
    'Vídeo con preguntas',
    'preguntas',
    'Un vídeo de YouTube que se detiene en los minutos que marques para preguntar.',
    'activo',
    -- Estándar y arriba. Se puede mover de plan desde el panel sin tocar esto.
    'estandar',
    1
WHERE NOT EXISTS (SELECT 1 FROM formatos WHERE clave = 'video_preguntas');
