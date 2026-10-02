<?php
/**
 * Paquetes: el contenido que arma el profesor.
 *
 * Un paquete es PERMANENTE y se puede editar siempre. Cada vez que se juega o
 * se envía nace una SESIÓN aparte (ver Sesiones.php), que se lleva una copia
 * del contenido tal como estaba en ese momento. Esa copia es lo que permite
 * seguir editando el paquete sin estropear los informes ya emitidos.
 *
 * Antes las dos cosas vivían en la misma fila y por eso el contenido caducaba a
 * las 24 h y se bloqueaba en cuanto alguien respondía.
 */

declare(strict_types=1);

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/Sesiones.php';

function paquete_crear(int $usuarioId, string $nombre, string $audiencia, array $actividades): int
{
    db()->prepare(
        'INSERT INTO paquetes (usuario_id, nombre, audiencia, contenido, total_actividades)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $usuarioId,
        $nombre,
        $audiencia,
        json_encode($actividades, JSON_UNESCAPED_UNICODE),
        count($actividades),
    ]);

    return (int) db()->lastInsertId();
}

/**
 * Guarda los cambios del editor. No toca las sesiones ya jugadas: cada una
 * conserva su propia copia, así que los informes antiguos siguen cuadrando.
 */
function paquete_actualizar(int $id, int $usuarioId, string $nombre, string $audiencia, array $actividades): bool
{
    $guardar = db()->prepare(
        'UPDATE paquetes
            SET nombre = ?, audiencia = ?, contenido = ?, total_actividades = ?
          WHERE id = ? AND usuario_id = ?'
    );
    $guardar->execute([
        $nombre,
        $audiencia,
        json_encode($actividades, JSON_UNESCAPED_UNICODE),
        count($actividades),
        $id,
        $usuarioId,
    ]);

    return $guardar->rowCount() > 0;
}

/** Un paquete propio, o null si no existe o es de otra cuenta. */
function paquete_de(int $id, int $usuarioId): ?array
{
    $consulta = db()->prepare('SELECT * FROM paquetes WHERE id = ? AND usuario_id = ?');
    $consulta->execute([$id, $usuarioId]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/** Las actividades de un paquete, ya decodificadas. */
function paquete_actividades(array $paquete): array
{
    $contenido = json_decode((string) ($paquete['contenido'] ?? ''), true);

    return is_array($contenido) ? $contenido : [];
}

/**
 * Los paquetes de una cuenta, con lo que hace falta para pintar la lista: si
 * hay una sesión abierta ahora mismo y cuántas veces se ha jugado.
 */
function paquetes_del_usuario(int $usuarioId): array
{
    $consulta = db()->prepare(
        'SELECT p.*,
                (SELECT COUNT(*) FROM sesiones s
                  WHERE s.paquete_id = p.id AND s.informe_guardado = 1) AS informes,
                (SELECT COUNT(*) FROM sesiones s
                  WHERE s.paquete_id = p.id AND s.estado <> "terminada" AND s.expira_at > NOW()) AS abiertas,
                (SELECT s.codigo FROM sesiones s
                  WHERE s.paquete_id = p.id AND s.estado <> "terminada" AND s.expira_at > NOW()
                  ORDER BY s.created_at DESC LIMIT 1) AS codigo_abierto,
                (SELECT s.modo FROM sesiones s
                  WHERE s.paquete_id = p.id AND s.estado <> "terminada" AND s.expira_at > NOW()
                  ORDER BY s.created_at DESC LIMIT 1) AS modo_abierto
           FROM paquetes p
          WHERE p.usuario_id = ?
          ORDER BY p.updated_at DESC'
    );
    $consulta->execute([$usuarioId]);

    return $consulta->fetchAll();
}

/** Cuántos paquetes guarda la cuenta (el tope del plan se mide aquí). */
function paquetes_contar(int $usuarioId): int
{
    $consulta = db()->prepare('SELECT COUNT(*) FROM paquetes WHERE usuario_id = ?');
    $consulta->execute([$usuarioId]);

    return (int) $consulta->fetchColumn();
}

/**
 * Copia un paquete en otro nuevo.
 *
 * Con el contenido separado de los resultados, editar ya no se bloquea nunca,
 * así que duplicar dejó de ser una salida de emergencia: sirve para hacer
 * variantes del mismo material (otro curso, otra dificultad) sin rehacerlo.
 *
 * @return array{ok:bool, mensaje:string, id?:int}
 */
function paquete_duplicar(int $id, int $usuarioId, ?array $usuario = null): array
{
    require_once __DIR__ . '/Planes.php';

    $paquete = paquete_de($id, $usuarioId);
    if (!$paquete) {
        return ['ok' => false, 'mensaje' => 'Ese paquete no es tuyo.'];
    }

    $tope = plan_max_paquetes($usuario);
    if ($tope > 0 && paquetes_contar($usuarioId) >= $tope) {
        return [
            'ok' => false,
            'mensaje' => 'Tu plan ' . plan_nombre(plan_de($usuario)) . ' guarda ' . $tope .
                ' paquetes. Borra uno y vuelve a duplicar.',
        ];
    }

    $actividades = paquete_actividades($paquete);
    if (!$actividades) {
        return ['ok' => false, 'mensaje' => 'Ese paquete no tiene contenido que copiar.'];
    }

    $nombre = mb_substr((string) $paquete['nombre'] . ' (copia)', 0, 120);
    $nuevo  = paquete_crear($usuarioId, $nombre, (string) $paquete['audiencia'], $actividades);

    return [
        'ok'      => true,
        'mensaje' => 'Copia creada con ' . count($actividades) . ' actividades.',
        'id'      => $nuevo,
    ];
}

/**
 * Borra un paquete propio.
 *
 * Los informes NO se van con él: la clave foránea es ON DELETE SET NULL y cada
 * sesión guarda su copia del contenido, así que el histórico sigue en pie
 * aunque el profesor limpie su lista.
 */
function paquete_eliminar(int $id, int $usuarioId): bool
{
    $borrar = db()->prepare('DELETE FROM paquetes WHERE id = ? AND usuario_id = ?');
    $borrar->execute([$id, $usuarioId]);

    return $borrar->rowCount() > 0;
}
