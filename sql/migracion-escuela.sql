-- ---------------------------------------------------------------------------
-- Licencia de institución (escuela).
--
-- La escuela NO es un cuarto plan: es una LICENCIA que reparte plan Pro entre
-- las cuentas de un colegio. Meterla en la escala de planes obligaría a decidir
-- si "escuela" vale más que "pro", pregunta que no tiene respuesta sensata: un
-- profesor de colegio y un profesor suelto con Pro pueden exactamente lo mismo.
-- Lo que cambia es QUIÉN paga y CUÁNTOS caben.
--
-- Estructura:
--   colegios           la licencia: cupo de profesores y hasta cuándo está paga
--   usuarios.colegio_id  a qué colegio pertenece una cuenta
--   usuarios.rol_colegio coordinador (gestiona) o profesor (enseña)
--   cursos             un grupo de estudiantes, de un profesor
--   curso_estudiantes  la lista del curso; NO son cuentas, son nombres
--   curso_envios       qué paquete se mandó a qué curso, y en qué sesión
--
-- Los estudiantes siguen SIN CUENTA: entran con el código de siempre. El
-- progreso se ata emparejando su nombre con la lista del curso.
--
-- Idempotente: se puede correr varias veces sin romper nada.
-- ---------------------------------------------------------------------------

-- ---------- 1. La licencia ----------

CREATE TABLE IF NOT EXISTS colegios (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(140) NOT NULL,
    nit             VARCHAR(40) NULL DEFAULT NULL,
    ciudad          VARCHAR(80) NULL DEFAULT NULL,
    contacto_email  VARCHAR(190) NULL DEFAULT NULL,

    -- Cuántas cuentas de profesor caben. 0 = sin tope (para acuerdos a medida).
    cupo_profesores SMALLINT UNSIGNED NOT NULL DEFAULT 30,

    -- La licencia se vende por año y se activa a mano desde el panel, como el
    -- resto de planes mientras no haya pasarela. Vencida, los profesores
    -- vuelven a gratis y NO se borra nada: sus paquetes quedan guardados.
    pagado_hasta    DATE NULL DEFAULT NULL,
    precio_anual    INT UNSIGNED NOT NULL DEFAULT 6000000,
    activo          TINYINT(1) NOT NULL DEFAULT 1,

    created_at      DATETIME NOT NULL DEFAULT current_timestamp(),
    updated_at      DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

    PRIMARY KEY (id),
    KEY idx_activo (activo, pagado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 2. La cuenta pertenece a un colegio ----------
-- Idempotente a mano: MariaDB 10.4 no admite ADD COLUMN IF NOT EXISTS en todas
-- las variantes, así que se consulta information_schema y se prepara la orden.

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'colegio_id');
SET @sql := IF(@hay = 0,
    'ALTER TABLE usuarios ADD COLUMN colegio_id INT UNSIGNED NULL DEFAULT NULL AFTER plan',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'rol_colegio');
SET @sql := IF(@hay = 0,
    "ALTER TABLE usuarios ADD COLUMN rol_colegio ENUM('coordinador','profesor') NULL DEFAULT NULL AFTER colegio_id",
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND index_name = 'idx_colegio');
SET @sql := IF(@hay = 0,
    'ALTER TABLE usuarios ADD KEY idx_colegio (colegio_id, rol_colegio)',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND constraint_name = 'fk_usuarios_colegio');
SET @sql := IF(@hay = 0,
    -- ON DELETE SET NULL: borrar un colegio NO puede llevarse por delante las
    -- cuentas de sus profesores ni el trabajo que hay dentro. Vuelven a ser
    -- cuentas sueltas.
    'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_colegio FOREIGN KEY (colegio_id) REFERENCES colegios (id) ON DELETE SET NULL',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- ---------- 3. Los cursos ----------

CREATE TABLE IF NOT EXISTS cursos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    colegio_id  INT UNSIGNED NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
    nombre      VARCHAR(80) NOT NULL,
    grado       VARCHAR(30) NULL DEFAULT NULL,
    jornada     VARCHAR(30) NULL DEFAULT NULL,
    archivado   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT current_timestamp(),
    updated_at  DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

    PRIMARY KEY (id),
    KEY idx_colegio (colegio_id, archivado),
    KEY idx_profesor (profesor_id),

    CONSTRAINT fk_cursos_colegio FOREIGN KEY (colegio_id) REFERENCES colegios (id) ON DELETE CASCADE,
    -- El curso NO cae con el profesor: si una cuenta se borra, el coordinador
    -- debe poder reasignar el curso a otro. Se comprueba en el código.
    CONSTRAINT fk_cursos_profesor FOREIGN KEY (profesor_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 4. La lista del curso ----------
-- NO son cuentas: son nombres. El estudiante entra con el código de siempre y
-- se empareja con su nombre de esta lista.

CREATE TABLE IF NOT EXISTS curso_estudiantes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_id   INT UNSIGNED NOT NULL,
    nombre     VARCHAR(80) NOT NULL,

    -- Para emparejar sin que estorben tildes ni mayúsculas: "josé pérez" y
    -- "Jose Perez" son la misma persona en la lista de un salón.
    nombre_clave VARCHAR(80) NOT NULL,

    documento  VARCHAR(30) NULL DEFAULT NULL,
    activo     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    UNIQUE KEY uq_curso_nombre (curso_id, nombre_clave),
    KEY idx_curso (curso_id, activo),

    CONSTRAINT fk_estudiantes_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 5. Qué se mandó a cada curso ----------

CREATE TABLE IF NOT EXISTS curso_envios (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_id   INT UNSIGNED NOT NULL,
    paquete_id INT UNSIGNED NULL DEFAULT NULL,
    sesion_id  INT UNSIGNED NULL DEFAULT NULL,

    -- Copia del nombre: la sesión caduca y el paquete puede renombrarse, pero
    -- el historial del curso tiene que seguir diciendo QUÉ se mandó.
    titulo     VARCHAR(120) NOT NULL,
    codigo     CHAR(6) NULL DEFAULT NULL,
    enviado_por INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (id),
    KEY idx_curso (curso_id, created_at),
    KEY idx_sesion (sesion_id),

    CONSTRAINT fk_envios_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE,
    -- Si la sesión caduca o el paquete se borra, el ENVÍO permanece: por eso
    -- SET NULL y por eso se copia el título.
    CONSTRAINT fk_envios_paquete FOREIGN KEY (paquete_id) REFERENCES paquetes (id) ON DELETE SET NULL,
    CONSTRAINT fk_envios_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones (id) ON DELETE SET NULL,
    CONSTRAINT fk_envios_autor FOREIGN KEY (enviado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- 6. La sesión sabe a qué curso va ----------

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'sesiones' AND column_name = 'curso_id');
SET @sql := IF(@hay = 0,
    'ALTER TABLE sesiones ADD COLUMN curso_id INT UNSIGNED NULL DEFAULT NULL AFTER paquete_id',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'sesiones' AND constraint_name = 'fk_sesiones_curso');
SET @sql := IF(@hay = 0,
    'ALTER TABLE sesiones ADD CONSTRAINT fk_sesiones_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE SET NULL',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

-- ---------- 7. El participante puede ser alguien de la lista ----------

SET @hay := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'participantes' AND column_name = 'estudiante_id');
SET @sql := IF(@hay = 0,
    'ALTER TABLE participantes ADD COLUMN estudiante_id INT UNSIGNED NULL DEFAULT NULL AFTER equipo_id',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;

SET @hay := (SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'participantes' AND constraint_name = 'fk_participantes_estudiante');
SET @sql := IF(@hay = 0,
    'ALTER TABLE participantes ADD CONSTRAINT fk_participantes_estudiante FOREIGN KEY (estudiante_id) REFERENCES curso_estudiantes (id) ON DELETE SET NULL',
    'DO 0');
PREPARE q FROM @sql; EXECUTE q; DEALLOCATE PREPARE q;
