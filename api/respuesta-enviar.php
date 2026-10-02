<?php
/**
 * POST /api/respuesta-enviar.php
 * El participante responde la pregunta que está proyectada.
 * Cuerpo: { codigo, token, actividad, item, respuesta }
 *
 * La calificación se hace aquí (nunca en el navegador) y el resultado que
 * vuelve es solo el del propio participante.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Juego.php';
require_once __DIR__ . '/../includes/Planes.php';

api_iniciar();
api_metodo('POST');

$datos  = api_cuerpo();
$sesion = sesion_requerida(api_texto($datos, 'codigo', 6));

if ($sesion['estado'] !== 'en_curso') {
    api_error('La sesión no está en curso', 409);
}

$participante = participante_por_token((int) $sesion['id'], api_texto($datos, 'token', 32));
if (!$participante) {
    api_error('No estás en esta sesión', 403);
}

$actividadIndice = (int) ($datos['actividad'] ?? -1);
$itemIndice = (int) ($datos['item'] ?? -1);
$enviado = $sesion['modo'] === 'enviar';

if ($enviado) {
    // Paquete enviado: cada quien responde por donde va.
    if ($participante['terminado']) {
        api_error('Ya terminaste este paquete', 409);
    }
    if ($actividadIndice !== (int) $participante['actividad_indice'] || $itemIndice !== (int) $participante['item_indice']) {
        api_error('Esa pregunta ya pasó', 409);
    }
} elseif ($actividadIndice !== (int) $sesion['actividad_indice'] || $itemIndice !== (int) $sesion['item_indice']) {
    // En vivo: solo vale la pregunta que el anfitrión tiene proyectada.
    api_error('Esa pregunta ya pasó', 409);
}

$contenido = json_decode((string) $sesion['contenido'], true) ?: [];
$actividad = $contenido[$actividadIndice] ?? null;
$item = $actividad['items'][$itemIndice] ?? null;
if (!$actividad || !$item) {
    api_error('No encontramos esa pregunta', 404);
}

$tipo = (string) $actividad['tipo'];
$puntua = juego_puntua($tipo);

// En los formatos con puntaje se responde una sola vez; el mural admite varios
// aportes. (Cuando no se registra el avance no hay filas que consultar, así que
// el freno de "ya respondiste" lo pone el propio avance del participante.)
$yaRespondio = db()->prepare(
    'SELECT COUNT(*) FROM respuestas WHERE participante_id = ? AND actividad_indice = ? AND item_indice = ?'
);
$yaRespondio->execute([$participante['id'], $actividadIndice, $itemIndice]);
$previas = (int) $yaRespondio->fetchColumn();

if ($previas > 0 && $tipo !== 'mural') {
    api_error('Ya respondiste esta pregunta', 409);
}
if ($tipo === 'mural' && $previas >= 10) {
    api_error('Llegaste al máximo de aportes en este mural', 409);
}

if (!array_key_exists('respuesta', $datos)) {
    api_error('Falta la respuesta', 422);
}
$respuesta = $datos['respuesta'];
if (is_string($respuesta)) {
    $respuesta = mb_substr(trim($respuesta), 0, 300);
    if ($respuesta === '') {
        api_error('Escribe algo antes de enviar', 422);
    }
}

// Tiempo disponible y rapidez, calculados contra el reloj del servidor.
$tiempo = max(1, (int) ($actividad['tiempo'] ?? 20));
$segundos = null;
$proporcionTiempo = 0.0;
$arranque = $enviado ? $participante['item_inicio_at'] : $sesion['item_inicio_at'];
if ($arranque) {
    $inicio = new DateTimeImmutable($arranque);
    $segundos = round((float) ((new DateTimeImmutable())->getTimestamp() - $inicio->getTimestamp()), 2);
    $proporcionTiempo = max(0.0, ($tiempo - $segundos) / $tiempo);
}

// La semilla es la misma con la que se dibujó la pregunta: así la sopa y el
// crucigrama se corrigen contra la cuadrícula que vio el participante.
$semilla = $sesion['codigo'] . "-$actividadIndice-$itemIndice";
$nota = juego_calificar($tipo, $item, $respuesta, $proporcionTiempo, $semilla);

/**
 * ¿Se guarda lo que responde?
 *
 * En un paquete ENVIADO, el plan gratis no registra el avance: el estudiante
 * ve su resultado al momento, pero no queda nada para el profesor. Llevar el
 * registro de lo que hace cada estudiante por su cuenta —y el reporte que sale
 * de ahí— es lo que se paga.
 *
 * En vivo sí se guarda siempre: sin eso no habría podio, que es el juego mismo.
 */
$duenoPlan = ['plan' => plan_de_usuario_id($sesion['usuario_id'] === null ? null : (int) $sesion['usuario_id'])];
$seRegistra = !$enviado || plan_permite($duenoPlan, 'informe');

if ($seRegistra) {
    db()->prepare(
        'INSERT INTO respuestas (sesion_id, participante_id, actividad_indice, item_indice, tipo, respuesta, correcta, aciertos, total, puntos, segundos)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $sesion['id'],
        $participante['id'],
        $actividadIndice,
        $itemIndice,
        $tipo,
        json_encode($respuesta, JSON_UNESCAPED_UNICODE),
        $nota['correcta'] === null ? null : (int) $nota['correcta'],
        $nota['aciertos'],
        $nota['total'],
        $nota['puntos'],
        $segundos,
    ]);

    if ($nota['puntos'] > 0) {
        db()->prepare('UPDATE participantes SET puntaje = puntaje + ? WHERE id = ?')
            ->execute([$nota['puntos'], $participante['id']]);
    }
}

$puntajeTotal = db()->prepare('SELECT puntaje FROM participantes WHERE id = ?');
$puntajeTotal->execute([$participante['id']]);

$salida = [
    'puntua'   => $puntua,
    'correcta' => $nota['correcta'],
    'aciertos' => $nota['aciertos'],
    'total'    => $nota['total'],
    'puntos'   => $nota['puntos'],
    'puntaje'  => (int) $puntajeTotal->fetchColumn(),
    'segundos' => $segundos,
    // El estudiante ve su resultado igual; lo que cambia es si queda registrado.
    'registrado' => $seRegistra,
];

// En un paquete enviado, responder avanza a la siguiente pregunta (salvo el
// mural, donde se pueden dejar varios aportes antes de seguir).
if ($enviado && $tipo !== 'mural') {
    $avance = participante_avanzar((int) $participante['id'], $contenido, $actividadIndice, $itemIndice);
    $salida['avance'] = $avance;
    if ($avance['terminado']) {
        $salida['ranking'] = juego_ranking((int) $sesion['id']);
    }
}

api_ok($salida);
