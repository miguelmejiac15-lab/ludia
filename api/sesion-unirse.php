<?php
/**
 * POST /api/sesion-unirse.php
 * Un participante entra con el código y su personaje.
 * Cuerpo: { codigo, nombre, personaje: {criatura, emoji, color, accesorio} }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Sesiones.php';

api_iniciar();
api_metodo('POST');

$datos  = api_cuerpo();
$sesion = sesion_requerida(api_texto($datos, 'codigo', 6));

if ($sesion['estado'] === 'terminada') {
    api_error('Esta sesión ya terminó', 410);
}

$nombre = api_texto($datos, 'nombre', 24);
if (mb_strlen($nombre) < 2) {
    api_error('Tu nombre necesita al menos 2 letras', 422);
}

$personajeEntrada = is_array($datos['personaje'] ?? null) ? $datos['personaje'] : [];
$personaje = [
    'criatura'  => mb_substr(trim((string) ($personajeEntrada['criatura'] ?? 'zorro')), 0, 20),
    'emoji'     => mb_substr(trim((string) ($personajeEntrada['emoji'] ?? '🦊')), 0, 8),
    'color'     => mb_substr(trim((string) ($personajeEntrada['color'] ?? 'coral')), 0, 20),
    'accesorio' => mb_substr(trim((string) ($personajeEntrada['accesorio'] ?? 'ninguno')), 0, 20),
];

if (sesion_contar_participantes((int) $sesion['id']) >= (int) $sesion['max_participantes']) {
    api_error('La sesión ya llegó a ' . $sesion['max_participantes'] . ' participantes', 409);
}

$token = api_token();

try {
    db()->prepare('INSERT INTO participantes (sesion_id, token, nombre, personaje) VALUES (?, ?, ?, ?)')
        ->execute([$sesion['id'], $token, $nombre, json_encode($personaje, JSON_UNESCAPED_UNICODE)]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        api_error('Ya hay alguien con ese nombre en la sesión, elige otro', 409, ['campo' => 'nombre']);
    }
    throw $e;
}

$participanteId = (int) db()->lastInsertId();

/* Si la sesión se envió a un curso, se busca a quien entra en la lista del
   salón. Sin esta costura, `estudiante_id` quedaría siempre nulo y el progreso
   del curso mostraría ceros por muchos envíos que se hicieran.

   No encontrarlo NO es un error: puede ser un invitado, o alguien que escribió
   su nombre de otra forma. Juega igual; simplemente no suma a la lista. */
if (!empty($sesion['curso_id'])) {
    require_once __DIR__ . '/../includes/Cursos.php';

    $estudianteId = curso_emparejar((int) $sesion['curso_id'], $nombre);
    if ($estudianteId !== null) {
        db()->prepare('UPDATE participantes SET estudiante_id = ? WHERE id = ?')
            ->execute([$estudianteId, $participanteId]);
    }
}

// En un paquete enviado no hay sala de espera: se empieza a jugar de inmediato.
if ($sesion['modo'] === 'enviar') {
    participante_arrancar($participanteId);
}

api_ok([
    'token'  => $token,
    'sesion' => [
        'codigo' => $sesion['codigo'],
        'nombre' => $sesion['nombre'],
        'estado' => $sesion['estado'],
    ],
    'personaje' => $personaje,
    'nombre'    => $nombre,
]);
