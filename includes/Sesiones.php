<?php
/**
 * Sesiones en vivo: creación, códigos de acceso y participantes.
 *
 * En el plan gratis la sesión no se guarda: las filas caducan a las 24 horas y
 * se limpian solas cuando alguien crea una sesión nueva.
 */

declare(strict_types=1);

require_once __DIR__ . '/api.php';
// Planes.php se cargaba solo DENTRO de una función. Al copiar el contenido de
// un paquete se usa actividad_titulo(), así que tiene que estar disponible
// siempre: sin esto, lanzar una sesión moría con "función no definida".
require_once __DIR__ . '/Planes.php';

const SESION_HORAS_VIDA   = 24;
const SESION_MAX_ACTIVIDADES = 50;
const SESION_MAX_ITEMS       = 30;
const SESION_MAX_PARTICIPANTES = 30;
const SESION_MINUTOS_CONECTADO = 2; // sin ping en este tiempo, se considera desconectado

/** Formatos que el servidor acepta. Debe coincidir con assets/js/formatos.js. */
const SESION_TIPOS = [
    'quiz', 'verdadero_falso', 'respuesta_multiple', 'palabra_secreta',
    'completar', 'ordenar_palabras', 'sopa_letras', 'crucigrama',
    'relacionar', 'memoria', 'clasificar', 'ordenar',
    'mural', 'encuesta', 'pregunta_abierta', 'escala', 'panel',
    'ortografia', 'anagrama', 'numerica', 'linea_tiempo', 'pagina',
    'video_preguntas',
];

/**
 * Formatos que solo funcionan en modo «enviar».
 *
 * El vídeo con preguntas necesita que cada quien lo vea a su ritmo y pueda
 * volver atrás; proyectado en clase habría que sincronizar el sonido del salón
 * con treinta teléfonos, que es otra cosa y se decidió no hacerla por ahora.
 */
const SESION_SOLO_ENVIAR = ['video_preguntas'];

/**
 * Borra las sesiones caducadas.
 *
 * NO toca las marcadas con informe_guardado: esas son el histórico de una
 * cuenta de pago y tienen que sobrevivir a su propia caducidad. Sin esa
 * condición, el informe se borraría solo a las 24 h de jugarlo.
 */
function sesiones_limpiar_vencidas(): void
{
    db()->exec('DELETE FROM sesiones WHERE expira_at < NOW() AND informe_guardado = 0');
}

/** Código de 6 dígitos que no esté en uso. */
function sesion_codigo_nuevo(): string
{
    $consulta = db()->prepare('SELECT 1 FROM sesiones WHERE codigo = ?');
    for ($intento = 0; $intento < 20; $intento++) {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $consulta->execute([$codigo]);
        if (!$consulta->fetchColumn()) {
            return $codigo;
        }
    }
    throw new RuntimeException('No se pudo generar un código de acceso libre');
}

/**
 * Revisa la estructura de las actividades que manda el editor.
 * No valida el contenido pedagógico (eso ya lo hace el editor en el navegador):
 * aquí solo se controlan tipos, tamaños y cantidades.
 */
/* Vive AQUÍ y no en Juego.php a propósito: Juego.php requiere a este
   archivo, así que la dependencia al revés dejaría la función sin definir
   al cargar Sesiones.php solo —como hace api/sesion-crear.php— y lanzar
   cualquier sesión moriría con «función no definida». */
/**
 * Saca el identificador de un vídeo de YouTube de casi cualquier forma de
 * pegarlo: la URL larga, la corta, la de incrustar, el <iframe> entero de
 * «Compartir → Insertar», o el id a secas.
 *
 * Devuelve cadena vacía si no reconoce nada, y la pantalla lo dice en vez de
 * intentar cargar un vídeo que no existe.
 */
function juego_id_youtube(string $texto): string
{
    $t = trim($texto);
    if ($t === '') {
        return '';
    }

    if (preg_match('~src\s*=\s*["\']([^"\']+)["\']~i', $t, $m)) {
        $t = $m[1];
    }

    $patrones = [
        '~[?&]v=([A-Za-z0-9_-]{11})~',
        '~youtu\.be/([A-Za-z0-9_-]{11})~',
        '~/embed/([A-Za-z0-9_-]{11})~',
        '~/shorts/([A-Za-z0-9_-]{11})~',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $t, $m)) {
            return $m[1];
        }
    }

    return preg_match('~^[A-Za-z0-9_-]{11}$~', $t) ? $t : '';
}

function sesion_normalizar_actividades($actividades): array
{
    if (!is_array($actividades) || !$actividades) {
        api_error('La sesión necesita al menos una actividad', 422);
    }
    if (count($actividades) > SESION_MAX_ACTIVIDADES) {
        api_error('Demasiadas actividades en una sola sesión', 422);
    }

    $limpias = [];
    foreach (array_values($actividades) as $posicion => $actividad) {
        if (!is_array($actividad) || !in_array($actividad['tipo'] ?? '', SESION_TIPOS, true)) {
            api_error('Actividad ' . ($posicion + 1) . ': tipo de formato desconocido', 422);
        }
        $items = $actividad['items'] ?? null;
        if (!is_array($items) || !$items || count($items) > SESION_MAX_ITEMS) {
            api_error('Actividad ' . ($posicion + 1) . ': cantidad de preguntas fuera de rango', 422);
        }

        // Las preguntas del vídeo se ordenan por su segundo: si el autor las
        // escribió desordenadas, el reproductor iría hacia atrás a mitad de
        // partida. Se hace en la COPIA, sin tocar el paquete original.
        if ($actividad['tipo'] === 'video_preguntas') {
            usort($items, static fn (array $a, array $b): int => ((int) ($a['segundo'] ?? 0)) <=> ((int) ($b['segundo'] ?? 0)));
        }

        $limpias[] = [
            'uid'    => mb_substr((string) ($actividad['uid'] ?? ('a' . $posicion)), 0, 32),
            'tipo'   => $actividad['tipo'],
            // Se guarda el IDENTIFICADOR ya extraído, no la URL pegada. Guardar
            // la URL y recortarla dejaba «https://www.youtube.» —20 caracteres—
            // y de ahí no sale ningún vídeo: la actividad se quedaba en negro.
            // Normalizar aquí también evita volver a analizar la URL en cada
            // petición durante toda la partida.
            'video'  => $actividad['tipo'] === 'video_preguntas'
                ? juego_id_youtube((string) ($actividad['video'] ?? ''))
                : '',
            // Si viene sin título, se guarda el nombre del formato. La copia
            // de la sesión es lo que verán la proyección y el informe, así que
            // el hueco hay que taparlo AQUÍ, no en cada pantalla.
            'titulo' => mb_substr(actividad_titulo($actividad), 0, 80),
            'tiempo' => min(600, max(5, (int) ($actividad['tiempo'] ?? 20))),
            'items'  => array_values($items),
        ];
    }
    return $limpias;
}

/**
 * Corta la petición si el paquete usa formatos que el plan no incluye.
 * El navegador ya los muestra con candado, pero quien manda es el servidor.
 */
function sesion_exigir_formatos_del_plan(array $actividades, ?array $usuario): void
{
    require_once __DIR__ . '/Planes.php';

    // Cuántas actividades caben en un paquete según el plan (0 = sin tope, Pro).
    $tope = plan_max_actividades($usuario);
    if ($tope > 0 && count($actividades) > $tope) {
        api_error(
            'Tu plan ' . plan_nombre(plan_de($usuario)) . ' permite ' . $tope .
            ' actividades por paquete y este tiene ' . count($actividades) . '. Quita alguna o pásate a Pro, que no tiene tope.',
            402,
            ['tope_actividades' => $tope, 'planes' => base_url('app/suscripcion.php')]
        );
    }

    $fuera = [];
    foreach ($actividades as $actividad) {
        $tipo = (string) ($actividad['tipo'] ?? '');
        if ($tipo !== '' && !plan_permite_formato($usuario, $tipo) && !in_array($tipo, $fuera, true)) {
            $fuera[] = $tipo;
        }
    }

    if ($fuera) {
        api_error(
            'Tu plan no incluye ' . (count($fuera) === 1 ? 'este formato' : 'estos formatos') . ': ' .
            implode(', ', array_map(static fn (string $t): string => str_replace('_', ' ', $t), $fuera)) .
            '. Quítalo del paquete o pásate a un plan superior.',
            402,
            ['formatos' => $fuera, 'planes' => base_url('/') . '#planes']
        );
    }
}

/**
 * Modos de paquete:
 *  - 'vivo'   → el anfitrión proyecta y todos responden a la vez, en la misma sala.
 *  - 'enviar' → se comparte el enlace y cada quien juega a su ritmo, donde esté;
 *               nace ya en curso, sin sala de espera.
 */
const SESION_MODOS = ['vivo', 'enviar'];

function sesion_crear(
    string $nombre,
    string $audiencia,
    array $actividades,
    ?int $usuarioId = null,
    string $modo = 'vivo',
    ?int $maxParticipantes = null,
    ?int $paqueteId = null
): array {
    sesiones_limpiar_vencidas();

    $codigo = sesion_codigo_nuevo();
    $token  = api_token();
    $modo   = in_array($modo, SESION_MODOS, true) ? $modo : 'vivo';
    $estado = $modo === 'enviar' ? 'en_curso' : 'lobby';
    $cupo   = max(1, $maxParticipantes ?? SESION_MAX_PARTICIPANTES);

    db()->prepare(
        'INSERT INTO sesiones (codigo, anfitrion_token, usuario_id, paquete_id, nombre, audiencia, modo, estado, contenido, total_actividades, max_participantes, item_inicio_at, expira_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))'
    )->execute([
        $codigo,
        $token,
        $usuarioId,
        $paqueteId,
        $nombre,
        $audiencia,
        $modo,
        $estado,
        json_encode($actividades, JSON_UNESCAPED_UNICODE),
        count($actividades),
        $cupo,
        SESION_HORAS_VIDA,
    ]);

    return ['codigo' => $codigo, 'anfitrion_token' => $token, 'modo' => $modo, 'estado' => $estado];
}

/**
 * Cierra una sesión y decide qué pasa con sus datos.
 *
 * En un plan de pago la sesión se conserva como INFORME: se marca
 * informe_guardado y con eso deja de caducar, así que el profesor la encuentra
 * en "Informes" semanas después. En el plan gratis no se marca nada y la sesión
 * caduca sola a las 24 h: el podio se vio en pantalla, pero no queda histórico.
 *
 * A propósito NO se borran aquí participantes ni respuestas del plan gratis:
 * el podio final se pinta justo después de cerrar y se quedaría vacío. Que
 * desaparezcan al caducar es suficiente para "no se guarda nada".
 */
function sesion_cerrar(int $sesionId): void
{
    require_once __DIR__ . '/Planes.php';

    $consulta = db()->prepare('SELECT usuario_id FROM sesiones WHERE id = ?');
    $consulta->execute([$sesionId]);
    $duenoId = $consulta->fetchColumn();

    $dueno = ['plan' => plan_de_usuario_id(
        $duenoId === false || $duenoId === null ? null : (int) $duenoId
    )];

    $guardaInforme = plan_permite($dueno, 'informe') ? 1 : 0;

    db()->prepare(
        'UPDATE sesiones SET estado = "terminada", terminada_at = NOW(), informe_guardado = ? WHERE id = ?'
    )->execute([$guardaInforme, $sesionId]);
}

/**
 * Abre una sesión NUEVA a partir de un paquete: código nuevo, participantes
 * nuevos y una COPIA del contenido tal como está en este momento.
 *
 * Esa copia es la pieza que sostiene todo el modelo: el profesor puede seguir
 * editando el paquete después, y esta sesión —y su informe— seguirán mostrando
 * exactamente lo que los estudiantes respondieron.
 *
 * Cada vez que se lanza sale un código distinto, para que un enlace viejo no
 * meta a nadie en la ronda nueva.
 *
 * @return array{ok:bool, mensaje:string, codigo?:string, modo?:string}
 */
function sesion_lanzar(array $paquete, int $usuarioId, string $modo = 'vivo', ?int $maxParticipantes = null): array
{
    $actividades = json_decode((string) ($paquete['contenido'] ?? ''), true);
    if (!is_array($actividades) || $actividades === []) {
        return ['ok' => false, 'mensaje' => 'Ese paquete todavía no tiene actividades.'];
    }

    // Quien manda es el servidor: que el botón no aparezca es solo cosmética.
    if ($modo === 'vivo') {
        $soloEnviar = [];
        foreach ($actividades as $a) {
            if (in_array($a['tipo'] ?? '', SESION_SOLO_ENVIAR, true)) {
                $soloEnviar[] = formato_nombre((string) $a['tipo']);
            }
        }
        if ($soloEnviar) {
            return [
                'ok' => false,
                'mensaje' => 'Este paquete tiene ' . implode(' y ', array_unique($soloEnviar)) .
                    ', que solo funciona enviándolo: cada estudiante lo ve a su ritmo. Usa «Enviar».',
            ];
        }
    }

    $sesion = sesion_crear(
        (string) $paquete['nombre'],
        (string) $paquete['audiencia'],
        $actividades,
        $usuarioId,
        $modo,
        $maxParticipantes,
        (int) $paquete['id']
    );

    return [
        'ok'      => true,
        'mensaje' => $sesion['modo'] === 'enviar'
            ? 'Listo: comparte el enlace con tus estudiantes.'
            : 'Sala abierta: proyecta y comparte el código.',
        'codigo'  => $sesion['codigo'],
        'modo'    => $sesion['modo'],
        // Quien lanza la sesión es su anfitrión: el token se le devuelve para
        // que pueda controlarla aunque cambie de dispositivo o de pestaña.
        'anfitrion_token' => $sesion['anfitrion_token'],
    ];
}

/* Aquí vivían sesion_enviar() y sesion_volver_a_vivo(), que CAMBIABAN EL MODO
   de un paquete ya creado y solo lo permitían mientras nadie hubiera entrado.
   Se retiraron el 17/09/2026: un paquete ya no tiene modo ni estado. Jugarlo o
   enviarlo abre una sesión nueva con sesion_lanzar(), así que no hay nada que
   cambiar a mitad ni a nadie a quien dejar a medias. */

function sesion_por_codigo(string $codigo): ?array
{
    $consulta = db()->prepare('SELECT * FROM sesiones WHERE codigo = ? AND expira_at > NOW()');
    $consulta->execute([$codigo]);
    $sesion = $consulta->fetch();
    return $sesion ?: null;
}

/** Busca la sesión o corta la petición con un error claro. */
function sesion_requerida(string $codigo): array
{
    if (!preg_match('/^\d{6}$/', $codigo)) {
        api_error('El código debe tener 6 dígitos', 422);
    }
    $sesion = sesion_por_codigo($codigo);
    if (!$sesion) {
        api_error('No encontramos una sesión con ese código', 404);
    }
    return $sesion;
}

/**
 * El estado de la sesión AHORA, recién leído de la base.
 *
 * Los endpoints cargan la sesión al empezar y luego deciden con esa foto. Para
 * casi todo vale, pero no para las reglas del tipo "esto solo antes de
 * comenzar": entre dos peticiones seguidas —un doble clic, dos pestañas— la
 * partida pudo arrancar, y la foto vieja deja pasar lo que debía rechazar.
 */
function sesion_estado_actual(int $sesionId): string
{
    $consulta = db()->prepare('SELECT estado FROM sesiones WHERE id = ?');
    $consulta->execute([$sesionId]);

    return (string) ($consulta->fetchColumn() ?: '');
}

function sesion_es_anfitrion(array $sesion, string $token): bool
{
    return $token !== '' && hash_equals($sesion['anfitrion_token'], $token);
}

function sesion_participantes(int $sesionId): array
{
    $consulta = db()->prepare(
        'SELECT id, equipo_id, nombre, personaje, estado, puntaje,
                (ultimo_ping >= DATE_SUB(NOW(), INTERVAL ? MINUTE)) AS conectado
         FROM participantes
         WHERE sesion_id = ? AND estado <> "salio"
         ORDER BY created_at'
    );
    $consulta->execute([SESION_MINUTOS_CONECTADO, $sesionId]);

    return array_map(static function (array $fila): array {
        return [
            // El id viaja para que el anfitrión pueda mover a alguien de equipo.
            // No es un secreto: solo sirve dentro de esta sesión y toda acción
            // se vuelve a comprobar en el servidor contra el anfitrion_token.
            'id'        => (int) $fila['id'],
            'equipo_id' => $fila['equipo_id'] === null ? null : (int) $fila['equipo_id'],
            'nombre'    => $fila['nombre'],
            'personaje' => json_decode((string) $fila['personaje'], true) ?: [],
            'estado'    => $fila['estado'],
            'puntaje'   => (int) $fila['puntaje'],
            'conectado' => (bool) $fila['conectado'],
        ];
    }, $consulta->fetchAll());
}

function sesion_contar_participantes(int $sesionId): int
{
    $consulta = db()->prepare('SELECT COUNT(*) FROM participantes WHERE sesion_id = ? AND estado <> "salio"');
    $consulta->execute([$sesionId]);
    return (int) $consulta->fetchColumn();
}

function participante_por_token(int $sesionId, string $token): ?array
{
    $consulta = db()->prepare('SELECT * FROM participantes WHERE sesion_id = ? AND token = ?');
    $consulta->execute([$sesionId, $token]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

function participante_ping(int $participanteId): void
{
    db()->prepare('UPDATE participantes SET ultimo_ping = NOW() WHERE id = ?')->execute([$participanteId]);
}

/* ---------- Progreso propio (modo "para enviar") ----------
   En vivo manda el anfitrión: la pregunta proyectada es la misma para todos.
   Para enviar, cada participante lleva su propio punto del recorrido. */

function participante_arrancar(int $participanteId): void
{
    db()->prepare(
        'UPDATE participantes SET estado = "jugando", actividad_indice = 0, item_indice = 0, item_inicio_at = NOW() WHERE id = ?'
    )->execute([$participanteId]);
}

/**
 * Calcula a qué pregunta pasa el participante y lo guarda.
 * Si ya no queda nada, lo marca como terminado.
 *
 * @return array{terminado:bool, actividad:int, item:int}
 */
function participante_avanzar(int $participanteId, array $contenido, int $actividad, int $item): array
{
    $itemsActuales = count($contenido[$actividad]['items'] ?? []);

    if ($item + 1 < $itemsActuales) {
        $item++;
    } elseif ($actividad + 1 < count($contenido)) {
        $actividad++;
        $item = 0;
    } else {
        db()->prepare('UPDATE participantes SET terminado = 1, item_inicio_at = NULL WHERE id = ?')->execute([$participanteId]);
        return ['terminado' => true, 'actividad' => $actividad, 'item' => $item];
    }

    db()->prepare('UPDATE participantes SET actividad_indice = ?, item_indice = ?, item_inicio_at = NOW() WHERE id = ?')
        ->execute([$actividad, $item, $participanteId]);

    return ['terminado' => false, 'actividad' => $actividad, 'item' => $item];
}

/**
 * Avance del grupo, para que el anfitrión vea cómo va un paquete enviado.
 *
 * Se mide por posición en el recorrido, no por respuestas enviadas: hay
 * pantallas que no se responden (el panel de repaso) y así nadie llegaría
 * nunca al 100 %.
 */
function sesion_progreso(int $sesionId, array $contenido): array
{
    $posiciones = [];
    $pantallas = 0;
    foreach ($contenido as $a => $actividad) {
        foreach (array_keys($actividad['items'] ?? []) as $i) {
            $posiciones[$a . '-' . $i] = $pantallas++;
        }
    }
    $total = max(1, $pantallas);

    $consulta = db()->prepare(
        'SELECT p.nombre, p.personaje, p.puntaje, p.terminado, p.actividad_indice, p.item_indice,
                (SELECT COUNT(*) FROM respuestas r WHERE r.participante_id = p.id) AS respondidas,
                (p.ultimo_ping >= DATE_SUB(NOW(), INTERVAL ? MINUTE)) AS conectado
         FROM participantes p
         WHERE p.sesion_id = ? AND p.estado <> "salio"
         ORDER BY p.terminado DESC, p.puntaje DESC, p.created_at'
    );
    $consulta->execute([SESION_MINUTOS_CONECTADO, $sesionId]);

    return array_map(static function (array $f) use ($posiciones, $total): array {
        $terminado = (bool) $f['terminado'];
        $clave = $f['actividad_indice'] . '-' . $f['item_indice'];
        $posicion = $terminado ? $total : ($posiciones[$clave] ?? 0);

        return [
            'nombre'      => $f['nombre'],
            'personaje'   => json_decode((string) $f['personaje'], true) ?: [],
            'puntaje'     => (int) $f['puntaje'],
            'posicion'    => $posicion,
            'respondidas' => (int) $f['respondidas'],
            'total'       => $total,
            'porcentaje'  => (int) round(($posicion / $total) * 100),
            'terminado'   => $terminado,
            'conectado'   => (bool) $f['conectado'],
        ];
    }, $consulta->fetchAll());
}

/** Cuántas preguntas tiene el paquete completo. */
function sesion_total_preguntas(array $contenido): int
{
    $total = 0;
    foreach ($contenido as $actividad) {
        $total += count($actividad['items'] ?? []);
    }
    return $total;
}

/* ---------- Sesiones de una cuenta (límite del plan gratis) ---------- */

/**
 * Cuántos PAQUETES guarda el plan gratis. Es solo el valor por defecto y lo que
 * se anuncia en la página de registro: el tope real vive en la tabla `planes`
 * y se lee con plan_max_paquetes(), editable desde el panel.
 *
 * Ojo con el nombre: se llama SESION_… por herencia de cuando sesiones y
 * paquetes eran la misma tabla. Ya no cuenta sesiones ni caduca nada: los
 * paquetes son permanentes y el profesor los borra a mano.
 */
const SESION_MAX_PLAN_GRATIS = 3;

/* Aquí vivían sesiones_del_usuario() y sesiones_activas_usuario(), que listaban
   y contaban sesiones como si fueran el contenido del profesor. Se retiraron el
   17/09/2026 al separar ambas cosas: la lista de contenido es
   paquetes_del_usuario() y el cupo del plan se mide con paquetes_contar(). */

/** Borra una sesión propia (con sus participantes y respuestas, por las claves foráneas). */
function sesion_eliminar(string $codigo, int $usuarioId): bool
{
    $borrar = db()->prepare('DELETE FROM sesiones WHERE codigo = ? AND usuario_id = ?');
    $borrar->execute([$codigo, $usuarioId]);
    return $borrar->rowCount() > 0;
}
