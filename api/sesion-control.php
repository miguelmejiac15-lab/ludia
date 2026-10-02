<?php
/**
 * POST /api/sesion-control.php
 * Acciones del anfitrión sobre su sesión.
 * Cuerpo: { codigo, anfitrion_token, accion: "comenzar" | "siguiente" | "terminar" | "sacar", nombre? }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Juego.php';
require_once __DIR__ . '/../includes/Equipos.php';

api_iniciar();
api_metodo('POST');

$datos  = api_cuerpo();
$sesion = sesion_requerida(api_texto($datos, 'codigo', 6));

if (!sesion_es_anfitrion($sesion, api_texto($datos, 'anfitrion_token', 32, false))) {
    api_error('Solo quien creó la sesión puede controlarla', 403);
}

$accion = api_opcion($datos, 'accion', [
    'comenzar', 'siguiente', 'terminar', 'saltar', 'sacar',
    // Equipos: se arman en la sala, antes de empezar.
    'equipos_azar', 'equipos_vacios', 'equipo_mover', 'equipos_deshacer',
], '');
$id = (int) $sesion['id'];

// En un paquete enviado no hay proyección: el ritmo lo lleva cada participante.
if ($sesion['modo'] === 'enviar' && ($accion === 'comenzar' || $accion === 'siguiente')) {
    api_error('Este paquete se juega a ritmo de cada participante: no hay que proyectarlo', 409);
}

// Los equipos son de las partidas proyectadas: en un paquete enviado cada quien
// juega solo y cuando puede, así que no hay con quién formar equipo.
if ($sesion['modo'] === 'enviar' && str_starts_with($accion, 'equipo')) {
    api_error('Los equipos son para jugar en vivo, no para un paquete enviado', 409);
}

switch ($accion) {
    case 'comenzar':
        if ($sesion['estado'] !== 'lobby') {
            api_error('La sesión ya había comenzado', 409);
        }
        db()->prepare('UPDATE sesiones SET estado = "en_curso", actividad_indice = 0, item_indice = 0, item_inicio_at = NOW() WHERE id = ?')->execute([$id]);
        db()->prepare('UPDATE participantes SET estado = "jugando" WHERE sesion_id = ? AND estado = "esperando"')->execute([$id]);
        break;

    // Pasa a la siguiente pregunta; si era la última de la actividad, a la siguiente
    // actividad; y si ya no queda nada, la sesión termina.
    case 'siguiente': {
        if ($sesion['estado'] !== 'en_curso') {
            api_error('La sesión no está en curso', 409);
        }
        $contenido = json_decode((string) $sesion['contenido'], true) ?: [];
        $a = (int) $sesion['actividad_indice'];
        $i = (int) $sesion['item_indice'];

        if ($i + 1 < count($contenido[$a]['items'] ?? [])) {
            $i++;
        } elseif ($a + 1 < count($contenido)) {
            $a++;
            $i = 0;
        } else {
            // Se acabó el recorrido. Cerrar decide si la sesión queda como
            // informe (plan de pago) o si simplemente caducará (gratis).
            sesion_cerrar($id);
            break;
        }

        db()->prepare('UPDATE sesiones SET actividad_indice = ?, item_indice = ?, item_inicio_at = NOW() WHERE id = ?')
            ->execute([$a, $i, $id]);
        break;
    }

    case 'terminar':
        sesion_cerrar($id);
        break;

    case 'sacar':
        $nombre = api_texto($datos, 'nombre', 40);
        db()->prepare('UPDATE participantes SET estado = "salio" WHERE sesion_id = ? AND nombre = ?')->execute([$id, $nombre]);
        break;

    /* ---------- Equipos ----------
       Se arman con la gente ya dentro, así que solo tienen sentido en la sala
       de espera o al empezar; rearmarlos a mitad de partida movería puntos de
       un equipo a otro y el marcador dejaría de cuadrar. */
    case 'equipos_azar':
    case 'equipos_vacios': {
        // El estado se RELEE aquí, no se usa el `$sesion` de arriba. Esa foto
        // se tomó al entrar en el script, y entre una petición y otra la sesión
        // pudo arrancar: basta un doble clic en "Comenzar", o el profesor con
        // dos pestañas abiertas. Con la foto vieja el rearmado se colaba a
        // mitad de partida y aparecía un tercer equipo.
        if (sesion_estado_actual($id) !== 'lobby') {
            api_error('Los equipos se arman antes de comenzar: a mitad de partida los puntos ya están repartidos', 409);
        }
        $cuantos = (int) ($datos['cuantos'] ?? 2);
        $resultado = $accion === 'equipos_azar'
            ? equipos_armar_al_azar($id, $cuantos)
            : equipos_crear_vacios($id, $cuantos);

        if (!$resultado['ok']) {
            api_error($resultado['mensaje'], 409);
        }
        break;
    }

    case 'equipo_mover': {
        // Mismo motivo que arriba: se relee, no se confía en la foto inicial.
        if (sesion_estado_actual($id) !== 'lobby') {
            api_error('Ya empezó la partida: cambiar a alguien de equipo movería puntos ya ganados', 409);
        }
        $participanteId = (int) ($datos['participante_id'] ?? 0);
        $equipoId = isset($datos['equipo_id']) && $datos['equipo_id'] !== null && $datos['equipo_id'] !== ''
            ? (int) $datos['equipo_id']
            : null;

        $resultado = equipo_mover($id, $participanteId, $equipoId);
        if (!$resultado['ok']) {
            api_error($resultado['mensaje'], 422);
        }
        break;
    }

    case 'equipos_deshacer':
        equipos_deshacer($id);
        break;

    default:
        api_error('Acción desconocida', 422);
}

$sesion = sesion_requerida($sesion['codigo']);

api_ok([
    'estado'        => $sesion['estado'],
    'participantes' => sesion_participantes($id),
    'equipos'       => equipos_de_sesion($id),
    'por_equipos'   => (bool) $sesion['por_equipos'],
]);
