<?php
/**
 * Equipos de una partida en vivo.
 *
 * Cuando ya entraron todos, el profesor los reparte —al azar o a mano— y a
 * partir de ahí el marcador y el podio son por equipo. Cada quien sigue
 * respondiendo en su propio dispositivo: lo único que cambia es a dónde suman
 * sus puntos.
 *
 * Los equipos pertenecen a la SESIÓN: se arman para una partida y mueren con
 * ella (clave foránea en cascada). No son grupos de clase ni listas guardadas;
 * eso vendrá con la licencia Escuela, que es otra cosa.
 */

declare(strict_types=1);

require_once __DIR__ . '/Sesiones.php';

/**
 * Nombres y colores de los equipos, emparejados por posición.
 *
 * Son animales porque el resto del juego ya habla ese idioma (los avatares son
 * criaturas) y porque un nombre con cara se grita mejor en un salón que
 * "Equipo 3". El color es un nombre del tema, no un hexadecimal: la paleta se
 * cambia en el CSS sin migrar datos.
 */
const EQUIPO_NOMBRES = ['Tigres', 'Búhos', 'Zorros', 'Pandas', 'Dragones', 'Pulpos'];
const EQUIPO_COLORES = ['coral', 'violeta', 'menta', 'sol', 'cielo', 'uva'];

/** Cuántos equipos admite una partida. Menos de 2 no es jugar en equipo. */
const EQUIPOS_MINIMO = 2;
const EQUIPOS_MAXIMO = 6;

/** Los equipos de una sesión, con su gente y su puntaje. */
function equipos_de_sesion(int $sesionId): array
{
    $consulta = db()->prepare(
        'SELECT e.id, e.nombre, e.color,
                COUNT(p.id)                        AS integrantes,
                COALESCE(SUM(p.puntaje), 0)        AS puntaje
           FROM equipos e
           LEFT JOIN participantes p ON p.equipo_id = e.id AND p.estado <> "salio"
          WHERE e.sesion_id = ?
          GROUP BY e.id
          ORDER BY e.id'
    );
    $consulta->execute([$sesionId]);

    return array_map(static fn (array $f): array => [
        'id'          => (int) $f['id'],
        'nombre'      => $f['nombre'],
        'color'       => $f['color'],
        'integrantes' => (int) $f['integrantes'],
        'puntaje'     => (int) $f['puntaje'],
    ], $consulta->fetchAll());
}

/**
 * Reparte al azar a quienes están dentro, en $cuantos equipos.
 *
 * Deshace lo que hubiera antes: armar dos veces no deja equipos zombis con
 * gente repartida a medias. El reparto es por rondas (uno a cada equipo, y
 * vuelta a empezar), que es la forma más simple de que queden parejos.
 *
 * @return array{ok:bool, mensaje:string, equipos?:array}
 */
function equipos_armar_al_azar(int $sesionId, int $cuantos): array
{
    $gente = db()->prepare('SELECT id FROM participantes WHERE sesion_id = ? AND estado <> "salio" ORDER BY id');
    $gente->execute([$sesionId]);
    $ids = array_map('intval', array_column($gente->fetchAll(), 'id'));

    if (count($ids) < EQUIPOS_MINIMO) {
        return ['ok' => false, 'mensaje' => 'Hacen falta al menos ' . EQUIPOS_MINIMO . ' participantes para armar equipos.'];
    }

    // Nunca más equipos que gente: un equipo de una persona no es un equipo.
    $cuantos = max(EQUIPOS_MINIMO, min(EQUIPOS_MAXIMO, $cuantos, count($ids)));

    equipos_deshacer($sesionId);

    $equipos = [];
    for ($n = 0; $n < $cuantos; $n++) {
        $crear = db()->prepare('INSERT INTO equipos (sesion_id, nombre, color) VALUES (?, ?, ?)');
        $crear->execute([$sesionId, EQUIPO_NOMBRES[$n], EQUIPO_COLORES[$n]]);
        $equipos[] = (int) db()->lastInsertId();
    }

    shuffle($ids);
    $asignar = db()->prepare('UPDATE participantes SET equipo_id = ? WHERE id = ? AND sesion_id = ?');
    foreach ($ids as $posicion => $participanteId) {
        $asignar->execute([$equipos[$posicion % $cuantos], $participanteId, $sesionId]);
    }

    sesion_marcar_por_equipos($sesionId, true);

    return [
        'ok'      => true,
        'mensaje' => 'Equipos armados al azar: ' . $cuantos . '.',
        'equipos' => equipos_de_sesion($sesionId),
    ];
}

/**
 * Crea $cuantos equipos vacíos para repartirlos a mano.
 * Útil cuando el profesor quiere decidir quién va con quién.
 *
 * @return array{ok:bool, mensaje:string, equipos?:array}
 */
function equipos_crear_vacios(int $sesionId, int $cuantos): array
{
    $cuantos = max(EQUIPOS_MINIMO, min(EQUIPOS_MAXIMO, $cuantos));

    equipos_deshacer($sesionId);

    for ($n = 0; $n < $cuantos; $n++) {
        $crear = db()->prepare('INSERT INTO equipos (sesion_id, nombre, color) VALUES (?, ?, ?)');
        $crear->execute([$sesionId, EQUIPO_NOMBRES[$n], EQUIPO_COLORES[$n]]);
    }

    sesion_marcar_por_equipos($sesionId, true);

    return [
        'ok'      => true,
        'mensaje' => 'Equipos listos: arrastra a cada quien al suyo.',
        'equipos' => equipos_de_sesion($sesionId),
    ];
}

/**
 * Mueve a una persona de equipo (o la deja sin equipo con $equipoId = null).
 * Comprueba que participante y equipo sean de ESTA sesión: el id viaja por la
 * red y no se confía en él.
 */
function equipo_mover(int $sesionId, int $participanteId, ?int $equipoId): array
{
    $suyo = db()->prepare('SELECT id FROM participantes WHERE id = ? AND sesion_id = ?');
    $suyo->execute([$participanteId, $sesionId]);
    if (!$suyo->fetchColumn()) {
        return ['ok' => false, 'mensaje' => 'Esa persona no está en esta sesión.'];
    }

    if ($equipoId !== null) {
        $existe = db()->prepare('SELECT id FROM equipos WHERE id = ? AND sesion_id = ?');
        $existe->execute([$equipoId, $sesionId]);
        if (!$existe->fetchColumn()) {
            return ['ok' => false, 'mensaje' => 'Ese equipo no es de esta sesión.'];
        }
    }

    db()->prepare('UPDATE participantes SET equipo_id = ? WHERE id = ?')
        ->execute([$equipoId, $participanteId]);

    return ['ok' => true, 'mensaje' => 'Listo.', 'equipos' => equipos_de_sesion($sesionId)];
}

/**
 * Deshace los equipos. La gente NO se toca: la clave foránea es ON DELETE SET
 * NULL, así que quedan sin equipo pero con sus respuestas y su puntaje.
 */
function equipos_deshacer(int $sesionId): void
{
    db()->prepare('DELETE FROM equipos WHERE sesion_id = ?')->execute([$sesionId]);
    sesion_marcar_por_equipos($sesionId, false);
}

/** Enciende o apaga el modo por equipos de una sesión. */
function sesion_marcar_por_equipos(int $sesionId, bool $activo): void
{
    db()->prepare('UPDATE sesiones SET por_equipos = ? WHERE id = ?')
        ->execute([$activo ? 1 : 0, $sesionId]);
}

/**
 * Posiciones por equipo: suma de puntos y de aciertos de sus integrantes.
 *
 * Se ordena por puntos y, en empate, por aciertos: dos equipos pueden sumar lo
 * mismo habiendo acertado distinto, porque el puntaje premia la rapidez.
 */
function equipos_ranking(int $sesionId): array
{
    $consulta = db()->prepare(
        'SELECT e.id, e.nombre, e.color,
                COUNT(DISTINCT p.id)                      AS integrantes,
                COALESCE(SUM(p.puntaje), 0)               AS puntaje,
                COALESCE(SUM(r.correcta = 1), 0)          AS aciertos
           FROM equipos e
           LEFT JOIN participantes p ON p.equipo_id = e.id AND p.estado <> "salio"
           LEFT JOIN respuestas r    ON r.participante_id = p.id
          WHERE e.sesion_id = ?
          GROUP BY e.id
          ORDER BY puntaje DESC, aciertos DESC, e.nombre ASC'
    );
    $consulta->execute([$sesionId]);

    $posicion = 0;
    return array_map(static function (array $f) use (&$posicion): array {
        $posicion++;
        return [
            'posicion'    => $posicion,
            'id'          => (int) $f['id'],
            'nombre'      => $f['nombre'],
            'color'       => $f['color'],
            'integrantes' => (int) $f['integrantes'],
            'puntaje'     => (int) $f['puntaje'],
            'aciertos'    => (int) $f['aciertos'],
        ];
    }, $consulta->fetchAll());
}

/** El equipo de un participante, para que lo vea en su pantalla. */
function equipo_de_participante(int $participanteId): ?array
{
    $consulta = db()->prepare(
        'SELECT e.id, e.nombre, e.color
           FROM equipos e JOIN participantes p ON p.equipo_id = e.id
          WHERE p.id = ?'
    );
    $consulta->execute([$participanteId]);
    $fila = $consulta->fetch();

    return $fila ? ['id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'color' => $fila['color']] : null;
}
