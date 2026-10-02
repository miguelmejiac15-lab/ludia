-- =====================================================================
-- Ludia — la clave foránea que faltaba en sesiones (16 de septiembre de 2026)
--
-- `sesiones.usuario_id` no tenía restricción, así que al borrar una cuenta sus
-- paquetes quedaban vivos apuntando a un id inexistente: seguían contando en
-- las cifras del panel y se mostraban como "sin cuenta".
--
-- Ejecutar:  mysql -u root ludia < sql/migracion-fk-sesiones.sql
-- Se puede repetir sin miedo: si la restricción ya existe, no hace nada.
-- =====================================================================

USE ludia;

-- 1. Fuera los paquetes cuya cuenta ya no existe (MySQL no acepta la
--    restricción mientras haya filas que la incumplan).
DELETE s FROM sesiones s
  LEFT JOIN usuarios u ON u.id = s.usuario_id
  WHERE s.usuario_id IS NOT NULL AND u.id IS NULL;

-- 2. Los paquetes se van con su dueño, igual que ya hacían participantes y
--    respuestas con su sesión.
--
--    ALTER TABLE ... ADD CONSTRAINT no admite IF NOT EXISTS, y repetirlo corta
--    con el error 1005. Se consulta primero el catálogo y solo se ejecuta si
--    hace falta, para que la migración se pueda repetir en cualquier servidor.
SET @ya_existe = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = 'ludia'
    AND TABLE_NAME = 'sesiones'
    AND CONSTRAINT_NAME = 'fk_sesiones_usuario'
);

SET @sentencia = IF(
  @ya_existe > 0,
  'SELECT "fk_sesiones_usuario ya existía: nada que hacer" AS resultado',
  'ALTER TABLE sesiones ADD CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE'
);

PREPARE aplicar FROM @sentencia;
EXECUTE aplicar;
DEALLOCATE PREPARE aplicar;
