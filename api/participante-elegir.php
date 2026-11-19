<?php
/**
 * POST /api/participante-elegir.php
 * Cuerpo: { codigo, token, actividad }   actividad = índice, -1 (menú) o "terminar"
 *
 * Solo en un JUEGO enviado (sesiones.eleccion_libre): el estudiante elige con
 * qué plantilla jugar, vuelve al menú para cambiar o termina cuando quiere.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Juego.php';

api_iniciar();
api_metodo('POST');

$datos  = api_cuerpo();
$sesion = sesion_requerida(api_texto($datos, 'codigo', 6));

if ($sesion['modo'] !== 'enviar' || empty($sesion['eleccion_libre'])) {
    api_error('Aquí no se puede cambiar de juego', 409);
}
if ($sesion['estado'] === 'terminada') {
    api_error('Este juego ya se cerró', 410);
}
$participante = participante_por_token((int) $sesion['id'], api_texto($datos, 'token', 32));
if (!$participante) {
    api_error('No estás en este juego', 403);
}
if ($participante['terminado']) {
    api_error('Ya terminaste', 409);
}

if (($datos['actividad'] ?? null) === 'terminar') {
    participante_terminar((int) $participante['id']);
    api_ok(['terminado' => true, 'ranking' => juego_ranking((int) $sesion['id'])]);
}

$contenido = json_decode((string) $sesion['contenido'], true) ?: [];
$resultado = participante_elegir((int) $participante['id'], $contenido, (int) ($datos['actividad'] ?? -1));
if (!$resultado['ok']) {
    api_error($resultado['mensaje'], 409);
}
api_ok();
