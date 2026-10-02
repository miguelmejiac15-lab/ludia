<?php

declare(strict_types=1);

/**
 * Cursos de un colegio y la lista de estudiantes de cada uno.
 *
 * Los estudiantes NO tienen cuenta: siguen entrando con el código de seis
 * dígitos y escribiendo su nombre, como todo el mundo en Ludia. La lista del
 * curso solo sirve para RECONOCERLOS y poder decir "Ana lleva 3 de 5 enviados"
 * en vez de tener nombres sueltos por cada sesión.
 *
 * El emparejado es lo delicado de este archivo: hay que aceptar "jose perez"
 * cuando en la lista dice "José Pérez", pero NO fundir a dos personas
 * distintas. Por eso se compara una clave normalizada y la unicidad se exige
 * por curso, no globalmente.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Colegios.php';

/* ---------- Normalizar nombres ---------- */

/**
 * Clave de comparación: minúsculas, sin tildes, sin dobles espacios.
 *
 * No se usa para MOSTRAR nada —el nombre bonito se guarda aparte—, solo para
 * decidir si dos formas de escribir son la misma persona dentro de un curso.
 */
function curso_clave_nombre(string $nombre): string
{
    $limpio = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
    $limpio = mb_strtolower($limpio, 'UTF-8');

    $tildes = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    return mb_substr(strtr($limpio, $tildes), 0, 80, 'UTF-8');
}

/* ---------- Cursos ---------- */

/** @return array{ok:bool, mensaje:string, id?:int} */
function curso_crear(int $colegioId, int $profesorId, string $nombre, array $campos = []): array
{
    $nombre = trim($nombre);
    if (mb_strlen($nombre) < 2) {
        return ['ok' => false, 'mensaje' => 'Escribe el nombre del curso.'];
    }

    db()->prepare(
        'INSERT INTO cursos (colegio_id, profesor_id, nombre, grado, jornada) VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $colegioId,
        $profesorId,
        $nombre,
        trim((string) ($campos['grado'] ?? '')) ?: null,
        trim((string) ($campos['jornada'] ?? '')) ?: null,
    ]);

    return ['ok' => true, 'mensaje' => 'Curso «' . $nombre . '» creado.', 'id' => (int) db()->lastInsertId()];
}

function curso_de(int $id): ?array
{
    $consulta = db()->prepare(
        'SELECT c.*, u.nombre AS profesor_nombre, u.email AS profesor_email
           FROM cursos c
           LEFT JOIN usuarios u ON u.id = c.profesor_id
          WHERE c.id = ?'
    );
    $consulta->execute([$id]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/**
 * ¿Puede esta cuenta ver o tocar este curso?
 *
 * El profesor manda en los suyos; el coordinador ve todos los de SU colegio.
 * Se comprueba el colegio además del dueño: sin eso, un coordinador podría
 * asomarse a los cursos de otra institución cambiando un número en la URL.
 */
function curso_puede(?array $usuario, ?array $curso): bool
{
    if (!$curso || !$usuario) {
        return false;
    }
    if ((int) ($usuario['colegio_id'] ?? 0) !== (int) $curso['colegio_id']) {
        return false;
    }

    return es_coordinador($usuario) || (int) $curso['profesor_id'] === (int) $usuario['id'];
}

/** Los cursos que ve esta cuenta: los suyos, o todos si coordina. */
function cursos_visibles(array $usuario): array
{
    $colegioId = (int) ($usuario['colegio_id'] ?? 0);
    if ($colegioId <= 0) {
        return [];
    }

    $sql =
        'SELECT c.*, u.nombre AS profesor_nombre,
                (SELECT COUNT(*) FROM curso_estudiantes e WHERE e.curso_id = c.id AND e.activo = 1) AS estudiantes,
                (SELECT COUNT(*) FROM curso_envios v WHERE v.curso_id = c.id) AS envios
           FROM cursos c
           LEFT JOIN usuarios u ON u.id = c.profesor_id
          WHERE c.colegio_id = ? AND c.archivado = 0';

    $parametros = [$colegioId];

    if (!es_coordinador($usuario)) {
        $sql .= ' AND c.profesor_id = ?';
        $parametros[] = (int) $usuario['id'];
    }

    $consulta = db()->prepare($sql . ' ORDER BY c.nombre');
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/**
 * Pasa un curso a otro profesor del mismo colegio.
 *
 * Hace falta de verdad: un profesor se va, entra otro, o el coordinador
 * reparte los cursos al empezar el año. Sin esto el curso quedaba atado para
 * siempre a quien lo creó, y la única salida era archivarlo y rehacer la lista
 * del salón a mano.
 *
 * Lo que NO se toca: la lista de estudiantes, los envíos ya hechos y el
 * progreso. El curso es del colegio; el profesor es quien lo lleva ahora.
 *
 * @return array{ok:bool, mensaje:string}
 */
function curso_reasignar(int $cursoId, int $nuevoProfesorId, int $colegioId): array
{
    $curso = curso_de($cursoId);
    if (!$curso || (int) $curso['colegio_id'] !== $colegioId) {
        return ['ok' => false, 'mensaje' => 'Ese curso no es de tu colegio.'];
    }

    // El destino tiene que estar EN ESTE colegio: sin esta comprobación se
    // podría regalar un curso a una cuenta de otra institución mandando un id
    // cualquiera en el formulario.
    $consulta = db()->prepare('SELECT nombre FROM usuarios WHERE id = ? AND colegio_id = ?');
    $consulta->execute([$nuevoProfesorId, $colegioId]);
    $nombre = $consulta->fetchColumn();

    if ($nombre === false) {
        return ['ok' => false, 'mensaje' => 'Esa persona no está en el colegio.'];
    }

    if ((int) $curso['profesor_id'] === $nuevoProfesorId) {
        return ['ok' => false, 'mensaje' => 'Ese curso ya es de ' . $nombre . '.'];
    }

    db()->prepare('UPDATE cursos SET profesor_id = ? WHERE id = ?')->execute([$nuevoProfesorId, $cursoId]);

    return [
        'ok' => true,
        'mensaje' => 'El curso «' . $curso['nombre'] . '» pasó a ' . $nombre .
            '. Su lista de estudiantes y su historial se conservan.',
    ];
}

function curso_archivar(int $id): array
{
    db()->prepare('UPDATE cursos SET archivado = 1 WHERE id = ?')->execute([$id]);

    return ['ok' => true, 'mensaje' => 'Curso archivado. Sus envíos y su lista siguen guardados.'];
}

/* ---------- La lista de estudiantes ---------- */

function curso_estudiantes(int $cursoId, bool $soloActivos = true): array
{
    $consulta = db()->prepare(
        'SELECT * FROM curso_estudiantes WHERE curso_id = ?' . ($soloActivos ? ' AND activo = 1' : '') .
        ' ORDER BY nombre'
    );
    $consulta->execute([$cursoId]);

    return $consulta->fetchAll();
}

/**
 * Añade estudiantes a la lista, uno por línea.
 *
 * Se pega la lista del salón tal cual, que es como la tiene el profesor. Los
 * repetidos NO son un error que aborte el alta: se cuentan y se informan, para
 * que pegar dos veces la misma lista no deje el trabajo a medias.
 *
 * @return array{ok:bool, mensaje:string, nuevos:int, repetidos:int}
 */
function curso_agregar_estudiantes(int $cursoId, string $pegado): array
{
    $lineas = preg_split('/\r\n|\r|\n/', $pegado) ?: [];
    $nuevos = 0;
    $repetidos = 0;
    $invalidos = 0;

    $insertar = db()->prepare(
        'INSERT IGNORE INTO curso_estudiantes (curso_id, nombre, nombre_clave) VALUES (?, ?, ?)'
    );

    foreach ($lineas as $linea) {
        $nombre = trim(preg_replace('/\s+/u', ' ', $linea) ?? '');
        if ($nombre === '') {
            continue;
        }
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            $invalidos++;
            continue;
        }

        $insertar->execute([$cursoId, $nombre, curso_clave_nombre($nombre)]);
        if ($insertar->rowCount() > 0) {
            $nuevos++;
        } else {
            $repetidos++;
        }
    }

    if ($nuevos === 0 && $repetidos === 0) {
        return ['ok' => false, 'mensaje' => 'No había ningún nombre en la lista.', 'nuevos' => 0, 'repetidos' => 0];
    }

    $partes = [];
    $partes[] = $nuevos === 1 ? 'Se añadió 1 estudiante' : 'Se añadieron ' . $nuevos . ' estudiantes';
    if ($repetidos > 0) {
        $partes[] = $repetidos === 1 ? '1 ya estaba en la lista' : $repetidos . ' ya estaban en la lista';
    }
    if ($invalidos > 0) {
        $partes[] = $invalidos . ' se descartaron por el nombre';
    }

    return [
        'ok' => true,
        'mensaje' => implode(' · ', $partes) . '.',
        'nuevos' => $nuevos,
        'repetidos' => $repetidos,
    ];
}

function curso_quitar_estudiante(int $cursoId, int $estudianteId): array
{
    // Se desactiva, no se borra: sus respuestas de sesiones pasadas apuntan a
    // esta fila, y borrarla dejaría los informes del curso cojos.
    $quitar = db()->prepare('UPDATE curso_estudiantes SET activo = 0 WHERE id = ? AND curso_id = ?');
    $quitar->execute([$estudianteId, $cursoId]);

    return $quitar->rowCount() > 0
        ? ['ok' => true, 'mensaje' => 'Estudiante quitado de la lista. Su historial se conserva.']
        : ['ok' => false, 'mensaje' => 'Ese estudiante no está en el curso.'];
}

/**
 * Busca en la lista del curso a quien acaba de escribir su nombre.
 * Devuelve el id del estudiante, o null si no está en la lista (invitado).
 */
function curso_emparejar(int $cursoId, string $nombreEscrito): ?int
{
    $clave = curso_clave_nombre($nombreEscrito);
    if ($clave === '') {
        return null;
    }

    $consulta = db()->prepare(
        'SELECT id FROM curso_estudiantes WHERE curso_id = ? AND nombre_clave = ? AND activo = 1'
    );
    $consulta->execute([$cursoId, $clave]);
    $id = $consulta->fetchColumn();

    return $id === false ? null : (int) $id;
}

/* ---------- Envíos y progreso ---------- */

/** Deja constancia de que un paquete se mandó a un curso. */
function curso_registrar_envio(int $cursoId, array $sesion, int $enviadoPor): void
{
    db()->prepare(
        'INSERT INTO curso_envios (curso_id, paquete_id, sesion_id, titulo, codigo, enviado_por)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $cursoId,
        !empty($sesion['paquete_id']) ? (int) $sesion['paquete_id'] : null,
        (int) $sesion['id'],
        (string) $sesion['nombre'],
        (string) $sesion['codigo'],
        $enviadoPor,
    ]);
}

function curso_envios(int $cursoId, int $limite = 30): array
{
    $consulta = db()->prepare(
        'SELECT v.*,
                s.estado, s.modo, s.expira_at,
                (SELECT COUNT(*) FROM participantes p WHERE p.sesion_id = v.sesion_id) AS participantes,
                (SELECT COUNT(*) FROM participantes p WHERE p.sesion_id = v.sesion_id AND p.terminado = 1) AS terminaron
           FROM curso_envios v
           LEFT JOIN sesiones s ON s.id = v.sesion_id
          WHERE v.curso_id = ?
       ORDER BY v.created_at DESC
          LIMIT ' . max(1, min(100, $limite))
    );
    $consulta->execute([$cursoId]);

    return $consulta->fetchAll();
}

/**
 * El progreso de cada estudiante del curso.
 *
 * Una fila por estudiante de la lista, con cuántos envíos tocó, cuántos
 * terminó y su promedio de aciertos. Quien no haya entrado nunca aparece
 * igualmente, en cero: ese es justo el dato que el profesor necesita ver.
 */
function curso_progreso(int $cursoId): array
{
    $consulta = db()->prepare(
        'SELECT e.id, e.nombre,
                COUNT(DISTINCT p.sesion_id) AS envios_tocados,
                SUM(CASE WHEN p.terminado = 1 THEN 1 ELSE 0 END) AS terminados,
                COALESCE(SUM(r.aciertos), 0) AS aciertos,
                COALESCE(SUM(r.total), 0) AS preguntas,
                MAX(p.created_at) AS ultima_vez
           FROM curso_estudiantes e
           LEFT JOIN participantes p ON p.estudiante_id = e.id
           LEFT JOIN respuestas r ON r.participante_id = p.id
          WHERE e.curso_id = ? AND e.activo = 1
       GROUP BY e.id, e.nombre
       ORDER BY e.nombre'
    );
    $consulta->execute([$cursoId]);
    $filas = $consulta->fetchAll();

    foreach ($filas as &$fila) {
        $preguntas = (int) $fila['preguntas'];
        // Sin preguntas contestadas no hay porcentaje que calcular: null, no 0.
        // Un cero diría "lo hizo todo mal", que es muy distinto de "no entró".
        $fila['porcentaje'] = $preguntas > 0
            ? (int) round(((int) $fila['aciertos'] / $preguntas) * 100)
            : null;
    }
    unset($fila);

    return $filas;
}

/** Cuántos envíos ha recibido el curso: el denominador del progreso. */
function curso_total_envios(int $cursoId): int
{
    $consulta = db()->prepare('SELECT COUNT(*) FROM curso_envios WHERE curso_id = ?');
    $consulta->execute([$cursoId]);

    return (int) $consulta->fetchColumn();
}
