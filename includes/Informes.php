<?php
/**
 * Informes: el resultado de cada sesión jugada o enviada.
 *
 * Un informe es una SESIÓN cerrada que se conservó (informe_guardado = 1). Solo
 * los planes de pago las conservan; en el plan gratis la sesión caduca y no
 * queda histórico.
 *
 * El informe se lee del contenido copiado DENTRO de la sesión, no del paquete:
 * así sigue cuadrando con lo que respondieron los estudiantes aunque el
 * profesor haya editado el paquete cien veces después.
 */

declare(strict_types=1);

require_once __DIR__ . '/Sesiones.php';

/**
 * Los informes de una cuenta, del más reciente al más antiguo.
 * Con $paqueteId se filtran los de un solo paquete.
 */
function informes_del_usuario(int $usuarioId, ?int $paqueteId = null): array
{
    $sql =
        'SELECT s.id, s.codigo, s.nombre, s.modo, s.total_actividades, s.terminada_at, s.paquete_id,
                p.nombre AS paquete_nombre,
                (SELECT COUNT(*) FROM participantes t WHERE t.sesion_id = s.id AND t.estado <> "salio") AS participantes,
                (SELECT COUNT(*) FROM respuestas r WHERE r.sesion_id = s.id) AS respuestas
           FROM sesiones s
           LEFT JOIN paquetes p ON p.id = s.paquete_id
          WHERE s.usuario_id = ? AND s.informe_guardado = 1';

    $parametros = [$usuarioId];

    if ($paqueteId !== null) {
        $sql .= ' AND s.paquete_id = ?';
        $parametros[] = $paqueteId;
    }

    $sql .= ' ORDER BY s.terminada_at DESC, s.id DESC';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/** Un informe propio por el código de su sesión, o null. */
function informe_sesion(string $codigo, int $usuarioId): ?array
{
    $consulta = db()->prepare(
        'SELECT * FROM sesiones WHERE codigo = ? AND usuario_id = ? AND informe_guardado = 1'
    );
    $consulta->execute([$codigo, $usuarioId]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/**
 * Borra un informe propio (la sesión con sus participantes y respuestas).
 * El paquete no se toca: son cosas distintas desde que se separaron.
 */
function informe_eliminar(string $codigo, int $usuarioId): bool
{
    $borrar = db()->prepare(
        'DELETE FROM sesiones WHERE codigo = ? AND usuario_id = ? AND informe_guardado = 1'
    );
    $borrar->execute([$codigo, $usuarioId]);

    return $borrar->rowCount() > 0;
}

/** Cuántos informes tiene guardados la cuenta. */
function informes_contar(int $usuarioId): int
{
    $consulta = db()->prepare(
        'SELECT COUNT(*) FROM sesiones WHERE usuario_id = ? AND informe_guardado = 1'
    );
    $consulta->execute([$usuarioId]);

    return (int) $consulta->fetchColumn();
}
