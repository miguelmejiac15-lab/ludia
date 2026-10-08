<?php
/**
 * GET /api/sesion-estado.php?codigo=123456[&token=...]
 * Endpoint de sondeo (polling): la sala y los participantes preguntan cada
 * pocos segundos cómo va la sesión. Sin WebSockets, para que funcione igual
 * en XAMPP, en hosting compartido y en un VPS.
 *
 * Dos ritmos según el modo del paquete:
 *  - 'vivo'   → la pregunta la marca el anfitrión; es la misma para todos.
 *  - 'enviar' → cada participante va por donde va; el anfitrión ve el avance.
 *
 * Al anfitrión se le entrega la clave de respuesta (la proyecta); al
 * participante, nunca.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Juego.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Equipos.php';

api_iniciar();
api_metodo('GET');

$sesion = sesion_requerida(trim((string) ($_GET['codigo'] ?? '')));
$token  = trim((string) ($_GET['token'] ?? ''));
$esAnfitrion = sesion_es_anfitrion($sesion, $token);
$sesionId = (int) $sesion['id'];
$modo = $sesion['modo'];

// Si el token es de un participante, la consulta sirve de señal de "sigo aquí".
$participante = null;
$yo = null;
if (!$esAnfitrion && $token !== '') {
    $participante = participante_por_token($sesionId, $token);
    if ($participante) {
        participante_ping((int) $participante['id']);
        $yo = [
            'nombre'    => $participante['nombre'],
            'personaje' => json_decode((string) $participante['personaje'], true) ?: [],
            'puntaje'   => (int) $participante['puntaje'],
            'terminado' => (bool) $participante['terminado'],
            // Su equipo, para que lo vea en su propia pantalla: jugar en equipo
            // sin saber de cuál eres no tiene ninguna gracia.
            'equipo'    => equipo_de_participante((int) $participante['id']),
        ];
    }
}

$participantes = sesion_participantes($sesionId);
$contenido = json_decode((string) $sesion['contenido'], true) ?: [];

$respuesta = [
    'sesion' => [
        'codigo'            => $sesion['codigo'],
        'nombre'            => $sesion['nombre'],
        'modo'              => $modo,
        'estado'            => $sesion['estado'],
        'actividad_indice'  => (int) $sesion['actividad_indice'],
        'item_indice'       => (int) $sesion['item_indice'],
        'total_actividades' => (int) $sesion['total_actividades'],
        'total_preguntas'   => sesion_total_preguntas($contenido),
        'max_participantes' => (int) $sesion['max_participantes'],
        'por_equipos'       => (bool) $sesion['por_equipos'],
    ],
    'participantes' => $participantes,
    // Los equipos viajan siempre: la sala los pinta mientras se arman y el
    // marcador los necesita durante la partida.
    'equipos'       => equipos_de_sesion($sesionId),
    'total'         => count($participantes),
    'conectados'    => count(array_filter($participantes, static fn (array $p): bool => $p['conectado'])),
    'soy_anfitrion' => $esAnfitrion,
    'yo'            => $yo,
];

/**
 * Arma el bloque de la pregunta que toca, con su cronómetro.
 */
function bloque_actividad(array $contenido, string $codigo, int $a, int $i, ?string $inicio, bool $conClave): ?array
{
    $actividad = $contenido[$a] ?? null;
    if (!$actividad) {
        return null;
    }

    $tiempo = max(1, (int) ($actividad['tiempo'] ?? 20));
    $transcurrido = $inicio ? max(0, time() - (new DateTimeImmutable($inicio))->getTimestamp()) : 0;

    $bloque = [
        'indice'      => $a,
        'tipo'        => $actividad['tipo'],
        'titulo'      => actividad_titulo($actividad),
        'tiempo'      => $tiempo,
        'restante'    => max(0, $tiempo - $transcurrido),
        'puntua'      => juego_puntua($actividad['tipo']),
        'lectura'     => juego_es_lectura($actividad['tipo']),
        'item_indice' => $i,
        'total_items' => count($actividad['items']),
        'item'        => juego_item_publico($actividad, $i, $codigo . "-$a-$i"),
        'es_ultima'   => ($a >= count($contenido) - 1) && ($i >= count($actividad['items']) - 1),
    ];

    if ($conClave) {
        $bloque['clave'] = $actividad['items'][$i] ?? [];
    }
    return $bloque;
}

/* ---------- Modo "para enviar": cada quien a su ritmo ---------- */
if ($modo === 'enviar' && $sesion['estado'] !== 'terminada') {
    if ($participante && !$participante['terminado']) {
        $a = (int) $participante['actividad_indice'];
        $i = (int) $participante['item_indice'];
        $bloque = bloque_actividad($contenido, $sesion['codigo'], $a, $i, $participante['item_inicio_at'], false);

        if ($bloque) {
            $respuesta['actividad'] = $bloque;
            // El avance se mide por POSICIÓN en el recorrido, no por respuestas
            // enviadas: las pantallas de lectura no generan ninguna y el plan
            // gratis ni siquiera las guarda, así que contarlas dejaba al
            // estudiante viendo "1 de 23" todo el rato aunque fuera avanzando.
            $posicion = 0;
            foreach ($contenido as $indiceActividad => $act) {
                foreach (array_keys($act['items'] ?? []) as $indiceItem) {
                    if ($indiceActividad === $a && $indiceItem === $i) {
                        break 2;
                    }
                    $posicion++;
                }
            }

            $respuesta['mi_avance'] = [
                'posicion'    => $posicion + 1,   // 1-based, para mostrarlo
                'respondidas' => (int) db()->query('SELECT COUNT(*) FROM respuestas WHERE participante_id = ' . (int) $participante['id'])->fetchColumn(),
                'total'       => $respuesta['sesion']['total_preguntas'],
            ];

            // El mural es común: se ve lo que aportaron los demás.
            if ($bloque['tipo'] === 'mural') {
                $respuesta['muro'] = muro_de($sesionId, $contenido[$a]['items'][$i] ?? [], $a, $i);
            }
        }
    }

    if ($esAnfitrion) {
        $respuesta['progreso'] = sesion_progreso($sesionId, $contenido);
        $respuesta['terminados'] = count(array_filter($respuesta['progreso'], static fn (array $p): bool => $p['terminado']));
    }

    if ($participante && $participante['terminado']) {
        $respuesta['ranking'] = juego_ranking($sesionId);
    }
}

/* ---------- Modo "en vivo": la pregunta la marca el anfitrión ---------- */
if ($modo === 'vivo' && $sesion['estado'] === 'en_curso') {
    $a = (int) $sesion['actividad_indice'];
    $i = (int) $sesion['item_indice'];
    $bloque = bloque_actividad($contenido, $sesion['codigo'], $a, $i, $sesion['item_inicio_at'], $esAnfitrion);

    if ($bloque) {
        $respuesta['actividad'] = $bloque;

        $recibidas = juego_respuestas_item($sesionId, $a, $i);
        $respuesta['respuestas_recibidas'] = count($recibidas);

        if ($esAnfitrion) {
            $respuesta['aportes'] = array_map(static fn (array $r): array => [
                'nombre'    => $r['nombre'],
                'personaje' => $r['personaje'],
                'respuesta' => $r['respuesta'],
                'correcta'  => $r['correcta'],
            ], $recibidas);
        }

        if ($participante) {
            $respuesta['mi_respuesta'] = mi_respuesta_de((int) $participante['id'], $a, $i);
        }

        if ($bloque['tipo'] === 'mural') {
            $respuesta['muro'] = muro_de($sesionId, $contenido[$a]['items'][$i] ?? [], $a, $i);
        }

        // Marcador en vivo: las posiciones se ven DURANTE la partida, no solo
        // al final. Es lo que hace ameno el juego proyectado.
        $respuesta['ranking'] = juego_ranking($sesionId);

        // Si se juega por equipos va TAMBIÉN el ranking agrupado. Se mandan los
        // dos a propósito: el marcador muestra los equipos, pero el informe y
        // el detalle individual siguen existiendo.
        if ($sesion['por_equipos']) {
            $respuesta['ranking_equipos'] = equipos_ranking($sesionId);
        }
    }
}

/* ---------- Sesión terminada: posiciones e informe ---------- */
if ($sesion['estado'] === 'terminada') {
    $respuesta['ranking'] = juego_ranking($sesionId);

    if ($sesion['por_equipos']) {
        $respuesta['ranking_equipos'] = equipos_ranking($sesionId);
    }

    // Las posiciones las ve todo el mundo; el informe con el detalle es de los
    // planes de pago. Se mira el plan del DUEÑO: aquí no hay cuenta en sesión,
    // el anfitrión se identifica con su token.
    if ($esAnfitrion) {
        $duenoPlan = ['plan' => plan_de_usuario_id($sesion['usuario_id'] === null ? null : (int) $sesion['usuario_id'])];
        if (plan_permite($duenoPlan, 'informe')) {
            $respuesta['informe'] = juego_informe($sesion);
        } else {
            $respuesta['informe_bloqueado'] = [
                'motivo' => 'El informe con aciertos, aportes y gráficos es parte de los planes Estándar y Pro.',
                'planes' => base_url('/') . '#planes',
            ];
        }
    }
}

api_responder(['ok' => true] + $respuesta);

/* ---------- Auxiliares ---------- */

function mi_respuesta_de(int $participanteId, int $a, int $i): ?array
{
    $mias = db()->prepare(
        'SELECT respuesta, correcta, aciertos, total, puntos FROM respuestas
         WHERE participante_id = ? AND actividad_indice = ? AND item_indice = ?
         ORDER BY created_at'
    );
    $mias->execute([$participanteId, $a, $i]);
    $filas = $mias->fetchAll();

    return $filas ? [
        'enviadas' => count($filas),
        'correcta' => $filas[0]['correcta'] === null ? null : (bool) $filas[0]['correcta'],
        'aciertos' => (int) $filas[0]['aciertos'],
        'total'    => (int) $filas[0]['total'],
        'puntos'   => (int) $filas[0]['puntos'],
        'valores'  => array_map(static fn (array $f) => json_decode((string) $f['respuesta'], true), $filas),
    ] : null;
}

function muro_de(int $sesionId, array $item, int $a, int $i): array
{
    return array_map(static fn (array $r): array => [
        'nombre'    => empty($item['anonimo']) ? $r['nombre'] : null,
        'personaje' => empty($item['anonimo']) ? $r['personaje'] : null,
        'texto'     => is_string($r['respuesta']) ? $r['respuesta'] : '',
    ], juego_respuestas_item($sesionId, $a, $i));
}
