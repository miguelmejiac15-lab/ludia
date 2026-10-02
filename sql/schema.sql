-- =====================================================================
-- Ludia — esquema de base de datos (Tarea 1 del masterplan, sección 13)
-- Compatible con MariaDB 10.4+ (XAMPP) y MySQL 5.7+/8 (producción).
-- Re-ejecutable: usa IF NOT EXISTS y ON DUPLICATE KEY UPDATE.
--
-- Importar:  mysql -u root < sql/schema.sql   (o desde phpMyAdmin)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ludia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ludia;

-- ---------------------------------------------------------------------
-- usuarios — se usará a partir de la Tarea 6 (auth con $_SESSION).
-- En el sprint 1 las actividades se crean sin usuario (usuario_id NULL).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(120)     NOT NULL,
  email           VARCHAR(190)     NOT NULL,
  password_hash   VARCHAR(255)     NOT NULL,           -- password_hash() / password_verify()
  rol             ENUM('creador','admin')                NOT NULL DEFAULT 'creador',
  plan            ENUM('gratis','estandar','pro') NOT NULL DEFAULT 'gratis',
  perfil          ENUM('docente','capacitador','creador_contenido','comunidad','otro') NULL,
  pais            CHAR(2)          NULL,               -- ISO 3166-1 alfa-2 (CO, MX, ...)
  ultimo_acceso   DATETIME         NULL,
  created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- imagenes — archivos que sube el creador para ilustrar sus preguntas.
--
-- El archivo vive en /uploads; aquí se guarda de quién es y cuánto pesa, para
-- poder mostrar su galería, controlar cuota por plan y limpiar lo que sobre.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS imagenes (
  id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED      NULL,              -- NULL si la cuenta se borra
  archivo     VARCHAR(120)      NOT NULL,          -- nombre dentro de /uploads
  nombre_original VARCHAR(160)  NULL,
  mime        VARCHAR(40)       NOT NULL,
  ancho       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  alto        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  bytes       INT UNSIGNED      NOT NULL DEFAULT 0,
  created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_imagenes_archivo (archivo),
  KEY idx_imagenes_usuario (usuario_id, created_at),
  CONSTRAINT fk_imagenes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- intentos_ingreso — freno a la fuerza bruta en el formulario de ingreso.
-- Una fila por correo; se borra al ingresar bien.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS intentos_ingreso (
  email           VARCHAR(190) NOT NULL,
  intentos        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bloqueado_hasta DATETIME     NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- formatos — catálogo de tipos de actividad.
-- Agrupado por función pedagógica (ver masterplan, sección 6).
-- estado 'activo' = construido y disponible; 'backlog' = priorizado a futuro.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS formatos (
  id                 SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave              VARCHAR(40)       NOT NULL,        -- identificador estable usado en el código y en el editor
  nombre             VARCHAR(80)       NOT NULL,
  descripcion        VARCHAR(255)      NOT NULL,
  grupo              ENUM('preguntas','texto_lengua','clasificar_relacionar','ordenar_construir','numericas','expositivas') NOT NULL,
  evaluable          TINYINT(1)        NOT NULL DEFAULT 1,  -- 0 = material de apoyo (esquema, galería...)
  ia_generable       TINYINT(1)        NOT NULL DEFAULT 1,  -- sin uso hoy: se reserva para la generación automática de los planes de pago
  estado             ENUM('activo','backlog') NOT NULL DEFAULT 'backlog',
  plan_minimo        ENUM('gratis','estandar','pro') NOT NULL DEFAULT 'estandar', -- plan mínimo que lo incluye
  referencia         VARCHAR(80)       NULL,        -- nota interna de origen del formato
  orden              SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  PRIMARY KEY (id),
  UNIQUE KEY uq_formatos_clave (clave),
  KEY idx_formatos_estado (estado, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- actividades — el contrato entre el editor y cada formato de juego.
--
-- `preguntas` (JSON) es SIEMPRE un arreglo de ítems; la clave correcta de
-- cada ítem va dentro del propio ítem. Forma de cada ítem por formato
-- (version_esquema = 1):
--
--   quiz:
--     { "id": "p1", "enunciado": "¿Qué país fue invadido en 1939?",
--       "opciones": [ {"id":"a","texto":"Polonia"}, {"id":"b","texto":"Francia"}, ... ],  -- 3 a 4 opciones
--       "correcta": "a",
--       "explicacion": "Texto breve opcional" }
--
--   verdadero_falso:
--     { "id": "p1", "afirmacion": "La guerra comenzó en 1939.",
--       "correcta": true,
--       "explicacion": "Texto breve opcional" }
--
--   relacionar:   (cada ítem es un par; el juego baraja la columna derecha)
--     { "id": "p1", "izquierda": "1939", "derecha": "Invasión de Polonia" }
--
-- `metadatos` (JSON) guarda datos de la generación y de presentación, ej.:
--     { "instruccion": "Relaciona cada fecha con su evento",
--       "idioma": "es", "tokens_entrada": 812, "tokens_salida": 640,
--       "formatos_sugeridos": ["quiz","relacionar","completar"] }
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS actividades (
  id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  codigo            CHAR(8)           NOT NULL,           -- código público para enlaces (/app/actividad.php?c=XXXXXXXX)
  usuario_id        INT UNSIGNED      NULL,               -- NULL en el sprint 1 (sin login)
  formato_id        SMALLINT UNSIGNED NOT NULL,
  titulo            VARCHAR(160)      NOT NULL,
  tema              VARCHAR(160)      NULL,
  nivel_publico     VARCHAR(80)       NULL,               -- "9° grado", "Inducción corporativa", ...
  fuente_tipo       ENUM('texto','tema','enlace','documento') NOT NULL DEFAULT 'texto',
  fuente_contenido  MEDIUMTEXT        NULL,               -- texto pegado / tema / URL original
  preguntas         JSON              NOT NULL,
  metadatos         JSON              NULL,
  version_esquema   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  total_preguntas   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  modo_generacion   ENUM('manual','ia_parcial','ia_completa') NOT NULL DEFAULT 'ia_completa', -- espectro de automatización (sección 5)
  modelo_ia         VARCHAR(60)       NULL,
  estado            ENUM('borrador','publicada','archivada') NOT NULL DEFAULT 'borrador',
  created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_actividades_codigo (codigo),
  KEY idx_actividades_usuario (usuario_id, created_at),
  KEY idx_actividades_formato (formato_id),
  CONSTRAINT fk_actividades_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_actividades_formato FOREIGN KEY (formato_id) REFERENCES formatos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- resultados — un registro por participante que juega una actividad.
--
-- `respuestas` (JSON) es un arreglo con una entrada por ítem:
--     { "pregunta_id": "p1", "respuesta": "a", "correcta": true, "ms": 5400 }
-- (en relacionar, "respuesta" es la derecha elegida para ese par)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resultados (
  id                   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  actividad_id         INT UNSIGNED      NOT NULL,
  participante_nombre  VARCHAR(80)       NULL,
  participante_token   CHAR(32)          NULL,           -- id anónimo del navegador, evita duplicados sin login
  modo                 ENUM('autonomo','en_vivo') NOT NULL DEFAULT 'autonomo',
  aciertos             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_preguntas      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  puntaje              DECIMAL(5,2)      NOT NULL DEFAULT 0.00, -- 0 a 100
  duracion_segundos    INT UNSIGNED      NULL,
  respuestas           JSON              NOT NULL,
  iniciado_at          DATETIME          NULL,
  completado_at        DATETIME          NULL,
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_resultados_actividad (actividad_id, created_at),
  KEY idx_resultados_token (participante_token),
  CONSTRAINT fk_resultados_actividad FOREIGN KEY (actividad_id) REFERENCES actividades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- sesiones — una sesión en vivo: código de acceso, contenido y estado.
--
-- OJO: en el plan gratis la sesión NO se guarda. Esta tabla es almacenamiento
-- temporal para que el servidor sepa quién entró mientras la sesión ocurre:
-- cada fila caduca (expira_at) y se borra sola en la siguiente creación de
-- sesión. `contenido` guarda las actividades tal como salen del editor.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones (
  id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  codigo            CHAR(6)           NOT NULL,           -- 6 dígitos, se muestra como "482 913"
  anfitrion_token   CHAR(32)          NOT NULL,           -- autoriza controlar la sesión
  usuario_id        INT UNSIGNED      NULL,               -- NULL mientras no exista el ingreso
  nombre            VARCHAR(120)      NOT NULL,
  audiencia         ENUM('estudiantes','auditorio','capacitacion') NOT NULL DEFAULT 'estudiantes',
  modo              ENUM('vivo','enviar') NOT NULL DEFAULT 'vivo', -- vivo = proyectada; enviar = cada quien a su ritmo
  contenido         JSON              NOT NULL,           -- [{uid, tipo, titulo, tiempo, items:[...]}]
  total_actividades SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado            ENUM('lobby','en_curso','terminada') NOT NULL DEFAULT 'lobby',
  actividad_indice  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  item_indice       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_participantes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  item_inicio_at    DATETIME          NULL,               -- cuándo se proyectó la pregunta actual (para el cronómetro)
  created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expira_at         DATETIME          NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sesiones_codigo (codigo),
  KEY idx_sesiones_expira (expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- participantes — quién entró a una sesión en vivo y con qué personaje.
--
-- `personaje` (JSON) es lo que arma el participante al entrar:
--   { "criatura": "zorro", "emoji": "🦊", "color": "coral", "accesorio": "gafas" }
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS participantes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesion_id    INT UNSIGNED NOT NULL,
  token        CHAR(32)     NOT NULL,                     -- identifica al participante sin login
  nombre       VARCHAR(40)  NOT NULL,
  personaje    JSON         NOT NULL,
  estado       ENUM('esperando','jugando','salio') NOT NULL DEFAULT 'esperando',
  puntaje      INT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_ping  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participantes_token (token),
  UNIQUE KEY uq_participantes_nombre (sesion_id, nombre),
  KEY idx_participantes_sesion (sesion_id, created_at),
  CONSTRAINT fk_participantes_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para bases creadas antes de que existiera el cronómetro del ítem.
ALTER TABLE sesiones ADD COLUMN IF NOT EXISTS item_inicio_at DATETIME NULL AFTER max_participantes;

-- Los paquetes de una cuenta se van con ella. Faltaba esta clave foránea, así
-- que al borrar un usuario sus sesiones quedaban vivas apuntando a un id que ya
-- no existía: seguían contando en el panel y aparecían como "sin cuenta".
-- Se limpia primero lo huérfano porque MySQL no acepta la restricción si hay
-- filas que la incumplen.
DELETE s FROM sesiones s
  LEFT JOIN usuarios u ON u.id = s.usuario_id
  WHERE s.usuario_id IS NOT NULL AND u.id IS NULL;

-- Modo del paquete: proyectado en vivo o enviado para jugar a su ritmo.
ALTER TABLE sesiones ADD COLUMN IF NOT EXISTS modo ENUM('vivo','enviar') NOT NULL DEFAULT 'vivo' AFTER audiencia;

-- Progreso propio de cada participante (lo usa el modo "para enviar").
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS actividad_indice SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER personaje;
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS item_indice SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER actividad_indice;
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS item_inicio_at DATETIME NULL AFTER item_indice;
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS terminado TINYINT(1) NOT NULL DEFAULT 0 AFTER item_inicio_at;

-- ---------------------------------------------------------------------
-- respuestas — lo que responde cada participante en cada pregunta.
--
-- `correcta` es NULL en los formatos que no puntúan (mural, encuesta, escala,
-- pregunta abierta, panel): ahí la respuesta es un aporte, no un acierto.
-- El mural admite varios aportes por persona, así que no hay clave única.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS respuestas (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesion_id        INT UNSIGNED NOT NULL,
  participante_id  INT UNSIGNED NOT NULL,
  actividad_indice SMALLINT UNSIGNED NOT NULL,
  item_indice      SMALLINT UNSIGNED NOT NULL,
  tipo             VARCHAR(40)  NOT NULL,
  respuesta        JSON         NOT NULL,
  correcta         TINYINT(1)   NULL,
  aciertos         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  puntos           INT UNSIGNED NOT NULL DEFAULT 0,
  segundos         DECIMAL(6,2) NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_respuestas_item (sesion_id, actividad_indice, item_indice),
  KEY idx_respuestas_participante (participante_id, actividad_indice, item_indice),
  CONSTRAINT fk_respuestas_sesion FOREIGN KEY (sesion_id) REFERENCES sesiones (id) ON DELETE CASCADE,
  CONSTRAINT fk_respuestas_participante FOREIGN KEY (participante_id) REFERENCES participantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo de formatos.
-- 'activo' = ya se puede crear en el editor (app/crear.php); son 17 y todos
-- están disponibles sin límite en esta etapa. El plan gratis se limita por
-- cantidad de SESIONES (3 por cuenta), no por formatos.
-- 'backlog' = pendientes, la mayoría porque necesitan audio, imágenes o un
-- generador numérico.
--
-- Los grupos siguen el mismo orden del editor. Se ajusta el ENUM para bases
-- creadas con la versión anterior del esquema.
-- ---------------------------------------------------------------------
ALTER TABLE formatos
  MODIFY COLUMN grupo ENUM('preguntas','texto','relacionar','aportes','apoyo','numericas') NOT NULL;

-- Los planes cambiaron de nombre (ver sql/migracion-planes.sql).
ALTER TABLE formatos
  MODIFY COLUMN plan_minimo ENUM('gratis','individual','institucional','estandar','pro') NOT NULL DEFAULT 'estandar';
UPDATE formatos SET plan_minimo = 'estandar' WHERE plan_minimo = 'individual';
UPDATE formatos SET plan_minimo = 'pro'      WHERE plan_minimo = 'institucional';
ALTER TABLE formatos
  MODIFY COLUMN plan_minimo ENUM('gratis','estandar','pro') NOT NULL DEFAULT 'estandar';

-- Claves que cambiaron de nombre al construir el editor.
DELETE FROM formatos WHERE clave IN ('ordenar_secuencia', 'parejas');

INSERT INTO formatos (clave, nombre, descripcion, grupo, evaluable, ia_generable, estado, plan_minimo, referencia, orden) VALUES
  -- Preguntas y evaluación
  ('quiz',               'Quiz',                    'Una pregunta, varias opciones y una sola correcta.',                   'preguntas',  1, 1, 'activo',  'gratis',   'Preguntas',             10),
  ('verdadero_falso',    'Verdadero o falso',       'Una afirmación que el público marca como cierta o falsa.',             'preguntas',  1, 1, 'activo',  'gratis',   'Escoger',               20),
  ('respuesta_multiple', 'Respuesta múltiple',      'Una pregunta con más de una respuesta correcta.',                      'preguntas',  1, 1, 'activo',  'estandar', 'Respuesta múltiple',    30),
  ('palabra_secreta',    'Palabra secreta',         'Adivinar la palabra letra por letra a partir de una pista.',           'preguntas',  1, 1, 'activo',  'gratis',   'Palabra secreta',       40),
  -- Texto y lengua
  ('completar',          'Completar la frase',      'Frases con huecos marcados entre corchetes.',                          'texto',      1, 1, 'activo',  'gratis',   'Completar',             50),
  ('ordenar_palabras',   'Ordenar palabras',        'Una frase que se desordena para reconstruirla.',                       'texto',      1, 1, 'activo',  'gratis',   'Frases',                60),
  ('sopa_letras',        'Sopa de letras',          'Palabras clave escondidas en una cuadrícula.',                         'texto',      1, 1, 'activo',  'gratis',   'Sopa de letras',        70),
  ('crucigrama',         'Crucigrama',              'Definiciones que se resuelven en una cuadrícula cruzada.',             'texto',      1, 1, 'activo',  'estandar', NULL,                    80),
  ('ortografia',         'Ortografía',              'Elegir cómo se escribe bien entre dos formas parecidas.',               'texto',      1, 1, 'activo',  'estandar', 'Ortografía',            82),
  ('anagrama',           'Anagrama',                'Las letras salen revueltas y hay que armar la palabra.',               'texto',      1, 1, 'activo',  'estandar', NULL,                    84),
  ('numerica',           'Respuesta numérica',      'La respuesta es un número, con margen de error si se necesita.',       'preguntas',  1, 1, 'activo',  'estandar', NULL,                    45),
  -- Relacionar, clasificar y ordenar
  ('relacionar',         'Relacionar',              'Unir cada elemento con su pareja.',                                    'relacionar', 1, 1, 'activo',  'gratis',   'Relacionar',            90),
  ('memoria',            'Memoria',                 'Voltear tarjetas para encontrar las parejas.',                         'relacionar', 1, 1, 'activo',  'estandar', 'Memoria',              100),
  ('clasificar',         'Clasificar',              'Arrastrar cada elemento a su categoría.',                              'relacionar', 1, 1, 'activo',  'estandar', 'Clasificar textos',    110),
  ('ordenar',            'Ordenar secuencia',       'Poner pasos, eventos o elementos en el orden correcto.',               'relacionar', 1, 1, 'activo',  'gratis',   'Ordenar',              120),
  -- Aportes y participación (no puntúan)
  ('mural',              'Mural colaborativo',      'Tablero de aportes: notas adhesivas, fijas o flotantes.',              'aportes',    0, 1, 'activo',  'estandar', NULL,                   130),
  ('encuesta',           'Encuesta',                'Pregunta de opinión con resultados en vivo.',                          'aportes',    0, 1, 'activo',  'gratis',   NULL,                   140),
  ('pregunta_abierta',   'Pregunta abierta',        'Respuestas cortas agrupadas en nube de palabras.',                     'aportes',    0, 1, 'activo',  'estandar', 'Pregunta abierta',     150),
  ('escala',             'Escala de opinión',       'Del 1 al 5: satisfacción, acuerdo o confianza.',                       'aportes',    0, 1, 'activo',  'estandar', NULL,                   160),
  -- Apoyo y repaso
  ('panel',              'Panel de repaso',         'Tarjetas con conceptos clave para mostrar antes de jugar.',            'apoyo',      0, 1, 'activo',  'gratis',   'Panel',                170),
  ('pagina',             'Página informativa',      'Portada o diapositiva con título, imagen y texto explicativo.',        'apoyo',      0, 1, 'activo',  'gratis',   NULL,                   175),
  -- Backlog: necesitan audio, imágenes o generador numérico
  ('dictado',            'Dictado',                 'Escribir lo que se escucha en un audio.',                              'texto',      1, 0, 'backlog', 'estandar', 'Dictado',              210),
  ('linea_tiempo',       'Línea de tiempo',         'Ordenar hechos por fecha: primero el más antiguo.',                    'relacionar', 1, 1, 'activo',  'estandar', 'Series',               125),
  ('operaciones',        'Operaciones',             'Ejercicios de cálculo generados por nivel.',                           'numericas',  1, 1, 'backlog', 'estandar', 'Operaciones',          240),
  ('fracciones',         'Fracciones',              'Representar, comparar y operar fracciones.',                           'numericas',  1, 1, 'backlog', 'estandar', 'Fracciones',           250),
  ('reloj',              'Reloj',                   'Leer y marcar horas en un reloj analógico.',                           'numericas',  1, 1, 'backlog', 'estandar', 'Reloj',                260),
  ('esquema',            'Esquema del tema',        'Mapa jerárquico navegable con las ideas principales.',                 'apoyo',      0, 1, 'backlog', 'estandar', 'Esquema',              270),
  ('galeria',            'Galería con textos',      'Imágenes acompañadas de descripciones breves.',                        'apoyo',      0, 0, 'backlog', 'estandar', 'Galería de imágenes',  280)
ON DUPLICATE KEY UPDATE
  nombre            = VALUES(nombre),
  descripcion       = VALUES(descripcion),
  grupo             = VALUES(grupo),
  evaluable         = VALUES(evaluable),
  ia_generable      = VALUES(ia_generable),
  estado            = VALUES(estado),
  plan_minimo       = VALUES(plan_minimo),
  referencia        = VALUES(referencia),
  orden             = VALUES(orden);
