-- ---------------------------------------------------------------------------
-- Entrar con Google.
--
-- Dos columnas y ninguna tabla nueva: el usuario de Google es un usuario normal
-- de Ludia, solo que se identifica de otra forma. Nada del juego, los paquetes
-- ni los planes se entera.
--
--   google_id      el `sub` que devuelve Google. Es estable y único por cuenta;
--                  el correo puede cambiar, el sub no, así que la identidad se
--                  ata a él y no al correo.
--   password_hash  pasa a admitir NULL: quien entra solo con Google NO TIENE
--                  contraseña, y guardar un hash inventado sería fingir que sí.
--
-- ⚠ ESTA MIGRACIÓN SOLA NO BASTA. Con `password_hash` nulo, cualquier
--   `password_verify($clave, $usuario['password_hash'])` recibe null y falla en
--   PHP 8. El código de ingreso tiene que comprobarlo ANTES y decir "esta cuenta
--   entra con Google", que además es lo útil para quien lo intente.
--
-- Enlace de cuentas: si el correo ya existe con contraseña, se enlazan (se
-- rellena google_id). Google verifica el correo, así que no hay suplantación;
-- exigir una cuenta separada solo obligaría a la gente a tener dos.
--
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ---------------------------------------------------------------------------

SET @hay := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'google_id');
SET @sql := IF(@hay = 0,
  'ALTER TABLE usuarios ADD COLUMN google_id VARCHAR(40) NULL AFTER email',
  'SELECT "usuarios.google_id ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- Único, pero admitiendo muchos NULL (las cuentas que entran con contraseña).
SET @hay := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'uq_usuarios_google');
SET @sql := IF(@hay = 0,
  'ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_google (google_id)',
  'SELECT "uq_usuarios_google ya existe" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- password_hash nulable. Las filas existentes conservan su hash intacto.
SET @nulable := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'password_hash');
SET @sql := IF(@nulable = 'NO',
  'ALTER TABLE usuarios MODIFY COLUMN password_hash VARCHAR(255) NULL',
  'SELECT "usuarios.password_hash ya admite NULL" AS nota');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SELECT CONCAT('cuentas: ', COUNT(*),
              ' · con contraseña: ', SUM(password_hash IS NOT NULL),
              ' · con Google: ', SUM(google_id IS NOT NULL)) AS resultado
  FROM usuarios;
