-- =====================================================================
-- Ludia — migración de planes (16 de septiembre de 2026)
--
-- Los planes pasan de 'individual'/'institucional' a 'estandar'/'pro'.
-- Ejecutar una sola vez sobre una base que ya exista:
--     mysql -u root ludia < sql/migracion-planes.sql
--
-- Es segura de repetir: el paso 1 amplía el ENUM sin perder datos y el
-- paso 3 solo toca filas que todavía tengan los nombres viejos.
-- =====================================================================

USE ludia;

-- 1. El ENUM acepta temporalmente los nombres viejos y los nuevos.
ALTER TABLE usuarios
  MODIFY plan ENUM('gratis','individual','institucional','estandar','pro')
  NOT NULL DEFAULT 'gratis';

-- 2. Las cuentas que ya estaban en un plan de pago se mudan al equivalente.
UPDATE usuarios SET plan = 'estandar' WHERE plan = 'individual';
UPDATE usuarios SET plan = 'pro'      WHERE plan = 'institucional';

-- 3. Se cierra el ENUM dejando solo los nombres nuevos.
ALTER TABLE usuarios
  MODIFY plan ENUM('gratis','estandar','pro')
  NOT NULL DEFAULT 'gratis';
