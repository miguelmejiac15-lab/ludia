<?php
/**
 * POST /api/participante-avanzar.php
 * Cuerpo: { codigo, token }
 *
 * Solo para paquetes enviados (cada quien a su ritmo). Sirve para las
 * pantallas donde responder no avanza solo: el mural (varios aportes) y el
 * panel de repaso (solo lectura). También deja seguir si ya se respondió.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Juego.php';

api_iniciar();
api_metodo('POST');

$datos  = api_cuerpo();
$sesion = sesion_requerida(api_texto($datos, 'codigo', 6));

if ($sesion['modo'] !== 'enviar') {
    api_error('En un paquete en vivo el ritmo lo marca el anfitrión', 409);
}
if ($sesion['estado'] === 'terminada') {
    api_error('Este paquete ya se cerró', 410);
}

$participante = participante_por_token((int) $sesion['id'], api_texto($datos, 'token', 32));
if (!$participante) {
    api_error('No estás en este paquete', 403);
}
if ($participante['terminado']) {
    api_error('Ya terminaste este paquete', 409);
}

$contenido = json_decode((string) $sesion['contenido'], true) ?: [];
$a = (int) $participante['actividad_indice'];
$i = (int) $participante['item_indice'];
$tipo = (string) ($contenido[$a]['tipo'] ?? '');

// Se puede saltar solo si la pantalla no exige respuesta, o si ya respondió.
$yaRespondio = db()->prepare(
    'SELECT COUNT(*) FROM respuestas WHERE participante_id = ? AND actividad_indice = ? AND item_indice = ?'
);
$yaRespondio->execute([$participante['id'], $a, $i]);

if (juego_puntua($tipo) && (int) $yaRespondio->fetchColumn() === 0) {
    api_error('Responde antes de continuar', 409);
}

$avance = participante_avanzar((int) $participante['id'], $contenido, $a, $i);

$salida = ['avance' => $avance];
if ($avance['terminado']) {
    $salida['ranking'] = juego_ranking((int) $sesion['id']);
}

api_ok($salida);
