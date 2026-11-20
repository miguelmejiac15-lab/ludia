<?php
/**
 * Juegos: escribir el contenido UNA vez y jugarlo en muchas plantillas.
 *
 * El profesor solo escribe palabras (y, en los planes de pago, sube imágenes):
 * una lista de PAREJAS («vaca → ternero») o de PREGUNTAS con opciones. Ludia
 * hace el resto. Cada plantilla es uno de los formatos de siempre (Relacionar,
 * Memoria, Quiz…), y un ADAPTADOR convierte el contenido en esa actividad.
 *
 * Dónde se guarda (ver sql/migracion-juegos.sql): una fila de `paquetes` con
 * clase = 'juego'. `juego_contenido` es lo que escribió el profesor y nunca se
 * transforma; `contenido` es la conversión a la plantilla elegida. Cambiar de
 * plantilla solo vuelve a convertir: es reversible y sin pérdidas. Y como el
 * resultado es una actividad normal, jugar en vivo, enviar, calificar en el
 * servidor e informes funcionan sin una línea nueva.
 *
 * Los adaptadores son funciones puras y deterministas: la misma semilla (el id
 * del juego) da siempre las mismas opciones y el mismo orden.
 */

declare(strict_types=1);

require_once __DIR__ . '/Paquetes.php';
require_once __DIR__ . '/Planes.php';
require_once __DIR__ . '/Juego.php';

/** Lo que el profesor puede escribir. */
const JUEGO_CONTENIDOS = ['parejas', 'preguntas'];

/**
 * Las plantillas. La clave ES el formato de Ludia en que se convierte, así que
 * el plan que la incluye sale de `formatos.plan_minimo` como todo lo demás.
 * `nativo` es el contenido que se le propone escribir a quien empieza por ella.
 */
const JUEGO_PLANTILLAS = [
    'relacionar'      => ['nombre' => 'Relacionar',        'icono' => '🔗', 'nativo' => 'parejas',   'tiempo' => 60,  'descripcion' => 'Une cada palabra con su pareja.'],
    'memoria'         => ['nombre' => 'Memoria',           'icono' => '🃏', 'nativo' => 'parejas',   'tiempo' => 90,  'descripcion' => 'Voltea dos tarjetas y encuentra las parejas.'],
    'avion'           => ['nombre' => 'Vuela a la respuesta', 'icono' => '✈️', 'nativo' => 'preguntas', 'tiempo' => 30, 'descripcion' => 'Mueve el avión y atraviesa la nube con la respuesta correcta.'],
    'globos'          => ['nombre' => 'Globos',            'icono' => '🎈', 'nativo' => 'preguntas', 'tiempo' => 25,  'descripcion' => 'Revienta el globo con la respuesta antes de que se escape.'],
    'atrapa'          => ['nombre' => 'Atrapa la correcta', 'icono' => '🐹', 'nativo' => 'preguntas', 'tiempo' => 25,  'descripcion' => 'Toca al animalito que asoma con la respuesta correcta.'],
    'laberinto'       => ['nombre' => 'Laberinto',         'icono' => '🌀', 'nativo' => 'preguntas', 'tiempo' => 45,  'descripcion' => 'Llega a la puerta correcta escapando de las abejas.'],
    'ruleta'          => ['nombre' => 'Ruleta',            'icono' => '🎡', 'nativo' => 'parejas',   'tiempo' => 30,  'descripcion' => 'Gírala en el proyector: sale una palabra o pregunta al azar.'],
    'cajas'           => ['nombre' => 'Cajas sorpresa',    'icono' => '🎁', 'nativo' => 'parejas',   'tiempo' => 30,  'descripcion' => 'Abre una caja numerada y descubre lo que esconde.'],
    'tren'            => ['nombre' => 'Tren de respuestas', 'icono' => '🚂', 'nativo' => 'preguntas', 'tiempo' => 35,  'descripcion' => 'Suelta la caja en el vagón correcto mientras el tren avanza.'],
    'catapulta'       => ['nombre' => 'Catapulta',         'icono' => '🎯', 'nativo' => 'preguntas', 'tiempo' => 35,  'descripcion' => 'Tensa la honda y lánzate contra el cartel correcto.'],
    'salta'           => ['nombre' => 'Salta y choca', 'icono' => '🍄', 'nativo' => 'preguntas', 'tiempo' => 35, 'descripcion' => 'Corre y salta para golpear desde abajo el bloque con la respuesta correcta. ¡Cuidado con los cocos!'],
    'mina'            => ['nombre' => 'Mina de bloques', 'icono' => '⛏️', 'nativo' => 'preguntas', 'tiempo' => 50, 'descripcion' => 'Pica bloques hacia abajo hasta la gema con la respuesta correcta. Algunas rocas son duras.'],
    'obstaculos'      => ['nombre' => 'Carrera de obstáculos', 'icono' => '🧗', 'nativo' => 'preguntas', 'tiempo' => 40, 'descripcion' => 'Corre solo: cruza el arco de la respuesta correcta y salta los demás, los charcos y las vallas.'],
    'nave'            => ['nombre' => 'Nave espacial', 'icono' => '🚀', 'nativo' => 'preguntas', 'tiempo' => 35, 'descripcion' => 'Dispara al asteroide con la respuesta correcta y esquiva los meteoritos.'],
    'quiz'            => ['nombre' => 'Quiz',              'icono' => '🎯', 'nativo' => 'preguntas', 'tiempo' => 20,  'descripcion' => 'Una pregunta y varias opciones.'],
    'verdadero_falso' => ['nombre' => 'Verdadero o falso', 'icono' => '⚖️', 'nativo' => 'preguntas', 'tiempo' => 15,  'descripcion' => '¿Es cierto o no? Rápido.'],
    'palabra_secreta' => ['nombre' => 'Palabra secreta',   'icono' => '🔡', 'nativo' => 'parejas',   'tiempo' => 45,  'descripcion' => 'Adivina la palabra letra por letra con una pista.'],
    'anagrama'        => ['nombre' => 'Anagrama',          'icono' => '🔠', 'nativo' => 'parejas',   'tiempo' => 30,  'descripcion' => 'Las letras salen revueltas: arma la palabra.'],
    'sopa_letras'     => ['nombre' => 'Sopa de letras',    'icono' => '🔤', 'nativo' => 'parejas',   'tiempo' => 120, 'descripcion' => 'Encuentra las palabras escondidas.'],
    'crucigrama'      => ['nombre' => 'Crucigrama',        'icono' => '🧩', 'nativo' => 'parejas',   'tiempo' => 180, 'descripcion' => 'Resuelve las pistas en la cuadrícula.'],
];

const JUEGO_MIN_PAREJAS   = 3;
const JUEGO_MAX_ELEMENTOS = 30;
const JUEGO_MAX_TEXTO     = 160;

/** Cuántas parejas caben en una pantalla de Relacionar o de Memoria. */
const JUEGO_POR_PANTALLA = 6;
/** Cuántas palabras caben en una sopa o en un crucigrama. */
const JUEGO_POR_REJILLA = 8;

/* ---------- Validar lo que escribe el profesor ---------- */

/** Una URL de imagen subida a Ludia, o cadena vacía. Nada de enlaces externos. */
function juego_imagen_valida($valor, bool $permitida): string
{
    if (!$permitida || !is_string($valor) || $valor === '') {
        return '';
    }

    return preg_match('~^[A-Za-z0-9_./:-]*/uploads/[A-Za-z0-9_.-]+\.(webp|jpe?g|png|gif)$~i', $valor) ? $valor : '';
}

function juego_texto($valor): string
{
    $texto = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '') : '';

    return mb_substr($texto, 0, JUEGO_MAX_TEXTO);
}

/**
 * Limpia y valida el contenido. Las filas vacías se descartan en silencio (es
 * lo normal al dejar la última sin llenar); las que están a medias son error.
 *
 * @return array{ok:bool, contenido?:array, error?:string}
 */
function juego_validar(string $tipo, $datos, bool $conImagenes): array
{
    if (!in_array($tipo, JUEGO_CONTENIDOS, true)) {
        return ['ok' => false, 'error' => 'Tipo de contenido desconocido.'];
    }
    $filas = is_array($datos['items'] ?? null) ? array_values($datos['items']) : [];
    $items = [];

    if ($tipo === 'parejas') {
        foreach ($filas as $i => $fila) {
            if (!is_array($fila)) {
                continue;
            }
            // Dos columnas: en cada lado va una palabra, una imagen o las dos
            // (la palabra junto a la imagen es su nombre).
            $izq = juego_texto($fila['izquierda'] ?? '');
            $der = juego_texto($fila['derecha'] ?? '');
            $imgIzq = juego_imagen_valida($fila['imagen_izq'] ?? '', $conImagenes);
            $imgDer = juego_imagen_valida($fila['imagen_der'] ?? '', $conImagenes);
            $hayIzq = $izq !== '' || $imgIzq !== '';
            $hayDer = $der !== '' || $imgDer !== '';
            if (!$hayIzq && !$hayDer) {
                continue;
            }
            if (!$hayIzq || !$hayDer) {
                return ['ok' => false, 'error' => 'La pareja ' . ($i + 1) . ' está a medio llenar: pon una palabra o una imagen en cada lado.'];
            }
            $items[] = [
                'izquierda'  => $izq,
                'derecha'    => $der,
                'imagen_izq' => $imgIzq,
                'imagen_der' => $imgDer,
            ];
        }
        if (count($items) < JUEGO_MIN_PAREJAS) {
            return ['ok' => false, 'error' => 'Escribe al menos ' . JUEGO_MIN_PAREJAS . ' parejas.'];
        }
    } else {
        foreach ($filas as $i => $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $enunciado = juego_texto($fila['enunciado'] ?? '');
            $opciones = [];
            $correcta = (int) ($fila['correcta'] ?? 0);
            $correctaFinal = -1;
            foreach (array_values(is_array($fila['opciones'] ?? null) ? $fila['opciones'] : []) as $j => $opcion) {
                $texto = juego_texto($opcion);
                if ($texto === '') {
                    continue;
                }
                if ($j === $correcta) {
                    $correctaFinal = count($opciones);
                }
                $opciones[] = $texto;
            }
            if ($enunciado === '' && !$opciones) {
                continue;
            }
            $n = $i + 1;
            if ($enunciado === '') {
                return ['ok' => false, 'error' => "Escribe la pregunta $n."];
            }
            if (count($opciones) < 2) {
                return ['ok' => false, 'error' => "La pregunta $n necesita al menos 2 respuestas."];
            }
            if ($correctaFinal < 0) {
                return ['ok' => false, 'error' => "Marca la respuesta correcta de la pregunta $n."];
            }
            $items[] = [
                'enunciado' => $enunciado,
                'imagen'    => juego_imagen_valida($fila['imagen'] ?? '', $conImagenes),
                'opciones'  => array_slice($opciones, 0, 6),
                'correcta'  => min($correctaFinal, 5),
            ];
        }
        if (!$items) {
            return ['ok' => false, 'error' => 'Escribe al menos una pregunta.'];
        }
    }

    if (count($items) > JUEGO_MAX_ELEMENTOS) {
        return ['ok' => false, 'error' => 'Un juego admite hasta ' . JUEGO_MAX_ELEMENTOS . ' elementos.'];
    }

    return ['ok' => true, 'contenido' => ['items' => $items]];
}

/* ---------- Adaptadores ---------- */

/** Número pseudoaleatorio estable a partir de una semilla. */
function juego_azar(string $semilla, int $max): int
{
    return $max <= 0 ? 0 : (int) (crc32($semilla) % $max);
}

/** Pares (izquierda, derecha, imágenes) a partir de cualquier contenido. */
function juego_como_pares(string $tipo, array $contenido): array
{
    if ($tipo === 'parejas') {
        return $contenido['items'];
    }

    return array_map(static fn (array $p): array => [
        'izquierda'  => $p['enunciado'],
        'derecha'    => $p['opciones'][$p['correcta']] ?? '',
        'imagen_izq' => $p['imagen'] ?? '',
        'imagen_der' => '',
    ], $contenido['items']);
}

/**
 * Las palabras para los juegos de letras: la palabra y su pista.
 * En parejas la palabra es la izquierda (el concepto) y la pista la derecha
 * (su definición); en preguntas, la palabra es la respuesta correcta.
 */
function juego_como_palabras(string $tipo, array $contenido): array
{
    return array_map(static function (array $p) use ($tipo): array {
        return $tipo === 'parejas'
            ? ['palabra' => $p['izquierda'], 'pista' => $p['derecha'], 'imagen' => ($p['imagen_izq'] ?? '') ?: ($p['imagen_der'] ?? '')]
            : ['palabra' => $p['derecha'], 'pista' => $p['izquierda'], 'imagen' => $p['imagen_izq'] ?? ''];
    }, juego_como_pares($tipo, $contenido));
}

/** Para que la pantalla diga cuál palabra estorba, entre comillas. */
function juego_cita(string $texto): string
{
    return '«' . mb_substr($texto, 0, 30) . '»';
}

/** Primera palabra que no cumple, o null si todas sirven. */
function juego_palabra_que_falla(array $palabras, callable $sirve): ?string
{
    foreach ($palabras as $p) {
        if (!$sirve($p['palabra'])) {
            return $p['palabra'];
        }
    }

    return null;
}

/** @return list<array> */
function juego_trozos(array $lista, int $tamano): array
{
    $trozos = array_chunk($lista, $tamano);
    // Una última pantalla con una sola pareja no es un juego: se reparte.
    $n = count($trozos);
    if ($n > 1 && count($trozos[$n - 1]) < 3) {
        $resto = array_pop($trozos);
        $trozos[$n - 2] = array_merge($trozos[$n - 2], $resto);
    }

    return $trozos;
}

/**
 * Convierte el contenido a una plantilla.
 *
 * @return array{ok:bool, items?:array, motivo?:string}
 */
function juego_adaptar(string $tipo, array $contenido, string $plantilla, string $semilla): array
{
    $pares = juego_como_pares($tipo, $contenido);
    // Una imagen sin nombre se distingue por su archivo.
    $derechas = array_values(array_unique(array_map(static fn (array $p): string =>
        $p['derecha'] !== '' ? mb_strtolower($p['derecha']) : 'img:' . ($p['imagen_der'] ?? ''), $pares)));
    // Lados que son solo imagen: los juegos de texto no los pueden mostrar.
    $sinTextoIzq = (bool) array_filter($pares, static fn (array $p): bool => $p['izquierda'] === '');
    $sinTextoDer = (bool) array_filter($pares, static fn (array $p): bool => $p['derecha'] === '');
    $pideNombreDer = 'Las respuestas de la derecha tienen imágenes sin nombre: escríbeles un nombre para jugarlo aquí.';
    $pideNombreIzq = 'La columna izquierda tiene imágenes sin nombre: escríbeles un nombre para jugarlo aquí.';

    switch ($plantilla) {
        case 'relacionar':
        case 'memoria':
            if (count($pares) < JUEGO_MIN_PAREJAS) {
                return ['ok' => false, 'motivo' => 'Necesita al menos ' . JUEGO_MIN_PAREJAS . ' parejas.'];
            }
            if (count($derechas) !== count($pares)) {
                return ['ok' => false, 'motivo' => 'Hay respuestas repetidas: cada pareja debe ser distinta.'];
            }
            $instruccion = $plantilla === 'memoria' ? 'Encuentra las parejas' : 'Relaciona cada elemento con su pareja';
            $items = array_map(static function (array $grupo) use ($instruccion, $plantilla): array {
                // En Relacionar se elige la pareja por su nombre (y así se
                // califica): una imagen sin nombre de la derecha se llama
                // «Imagen 1», «Imagen 2»… dentro de su pantalla.
                $n = 0;
                return [
                    'instruccion' => $instruccion,
                    'pares' => array_map(static function (array $p) use (&$n, $plantilla): array {
                        $derecha = $p['derecha'];
                        if ($derecha === '' && $plantilla === 'relacionar') {
                            $derecha = 'Imagen ' . (++$n);
                        }
                        return [
                            'izquierda' => $p['izquierda'], 'derecha' => $derecha,
                            'imagen_izq' => $p['imagen_izq'] ?? '', 'imagen_der' => $p['imagen_der'] ?? '',
                        ];
                    }, $grupo),
                ];
            }, juego_trozos($pares, JUEGO_POR_PANTALLA));

            return ['ok' => true, 'items' => $items];

        case 'quiz':
        case 'avion':
        case 'globos':
        case 'atrapa':
        case 'laberinto':
        case 'tren':
        case 'catapulta':
        case 'salta':
        case 'mina':
        case 'obstaculos':
        case 'nave':
            // Los juegos de acción llevan los mismos datos que un quiz: cambia
            // el juego, no la pregunta ni la forma de calificarla.
            if ($plantilla === 'laberinto' && $tipo === 'preguntas') {
                foreach ($contenido['items'] as $n => $p) {
                    if (count($p['opciones']) > 4) {
                        return ['ok' => false, 'motivo' => 'El laberinto tiene 4 puertas y la pregunta ' . ($n + 1) . ' tiene ' . count($p['opciones']) . ' respuestas.'];
                    }
                }
            }
            if ($tipo === 'preguntas') {
                return ['ok' => true, 'items' => array_map(static fn (array $p): array => [
                    'enunciado' => $p['enunciado'], 'opciones' => $p['opciones'],
                    'correcta' => $p['correcta'], 'imagen' => $p['imagen'],
                ], $contenido['items'])];
            }
            if ($sinTextoDer) {
                return ['ok' => false, 'motivo' => $pideNombreDer];
            }
            if (count($derechas) < 2) {
                return ['ok' => false, 'motivo' => 'Necesita al menos 2 respuestas distintas.'];
            }
            $items = [];
            foreach ($pares as $i => $p) {
                // Distractores: otras respuestas del mismo juego, elegidas con semilla.
                $otras = array_values(array_filter(
                    array_unique(array_column($pares, 'derecha')),
                    static fn (string $d): bool => mb_strtolower($d) !== mb_strtolower($p['derecha'])
                ));
                $otras = array_slice(juego_barajar($otras, $semilla . '-q' . $i), 0, 3);
                $opciones = juego_barajar(array_merge([$p['derecha']], $otras), $semilla . '-o' . $i);
                $items[] = [
                    'enunciado' => $p['izquierda'],
                    'opciones'  => $opciones,
                    'correcta'  => (int) array_search($p['derecha'], $opciones, true),
                    'imagen'    => $p['imagen_izq'] ?? '',
                ];
            }

            return ['ok' => true, 'items' => $items];

        case 'verdadero_falso':
            $items = [];
            if ($tipo === 'preguntas') {
                foreach ($contenido['items'] as $i => $p) {
                    $malas = array_values(array_diff_key($p['opciones'], [$p['correcta'] => true]));
                    $cierta = !$malas || juego_azar($semilla . '-v' . $i, 2) === 0;
                    $opcion = $cierta ? $p['opciones'][$p['correcta']] : $malas[juego_azar($semilla . '-m' . $i, count($malas))];
                    $items[] = ['afirmacion' => $p['enunciado'] . ' → ' . $opcion, 'correcta' => $cierta, 'imagen' => $p['imagen']];
                }

                return ['ok' => true, 'items' => $items];
            }
            if ($sinTextoDer) {
                return ['ok' => false, 'motivo' => $pideNombreDer];
            }
            if (count($derechas) < 2) {
                return ['ok' => false, 'motivo' => 'Necesita al menos 2 respuestas distintas.'];
            }
            foreach ($pares as $i => $p) {
                $otras = array_values(array_filter(array_column($pares, 'derecha'), static fn (string $d): bool => mb_strtolower($d) !== mb_strtolower($p['derecha'])));
                $cierta = juego_azar($semilla . '-v' . $i, 2) === 0;
                $derecha = $cierta ? $p['derecha'] : $otras[juego_azar($semilla . '-m' . $i, count($otras))];
                // Si la izquierda es solo imagen, la afirmación habla de ella.
                $afirmacion = $p['izquierda'] !== '' ? $p['izquierda'] . ' → ' . $derecha : 'Esto es: ' . $derecha;
                $items[] = ['afirmacion' => $afirmacion, 'correcta' => $cierta, 'imagen' => $p['imagen_izq'] ?? ''];
            }

            return ['ok' => true, 'items' => $items];

        case 'ruleta':
        case 'cajas':
            // Herramientas de la clase: sale un elemento al azar y su respuesta
            // se descubre cuando el docente quiere. No se califican.
            if (count($pares) < 2) {
                return ['ok' => false, 'motivo' => 'Necesita al menos 2 elementos.'];
            }
            if ($sinTextoIzq) {
                return ['ok' => false, 'motivo' => $pideNombreIzq];
            }
            return ['ok' => true, 'items' => [[
                'instruccion' => $plantilla === 'ruleta' ? 'Gira la ruleta' : 'Elige una caja',
                'sorteo'      => array_map(static fn (array $p): array => ['texto' => $p['izquierda'], 'respuesta' => $p['derecha'] !== '' ? $p['derecha'] : '🖼️ (imagen)'], $pares),
            ]]];

        case 'palabra_secreta':
        case 'anagrama':
            if ($tipo === 'parejas' && $sinTextoIzq) {
                return ['ok' => false, 'motivo' => 'Las palabras van a la izquierda y ahí hay imágenes sin nombre: escríbeles su palabra.'];
            }
            $palabras = juego_como_palabras($tipo, $contenido);
            $minimo = $plantilla === 'anagrama' ? 4 : 3;
            $falla = juego_palabra_que_falla($palabras, static function (string $w) use ($minimo): bool {
                $letras = preg_replace('/\s+/u', '', $w) ?? '';
                return mb_strlen($letras) >= $minimo && mb_strlen($w) <= 20 && preg_match('/^[\p{L} ]+$/u', $w) === 1;
            });
            if ($falla !== null) {
                return ['ok' => false, 'motivo' => 'Cada palabra debe tener solo letras, de ' . $minimo . ' a 20. No sirve ' . juego_cita($falla) . '.'];
            }

            return ['ok' => true, 'items' => array_map(static fn (array $p): array => [
                'palabra' => $p['palabra'], 'pista' => $p['pista'], 'imagen' => $p['imagen'],
            ], $palabras)];

        case 'sopa_letras':
        case 'crucigrama':
            if ($tipo === 'parejas' && ($sinTextoIzq || ($plantilla === 'crucigrama' && $sinTextoDer))) {
                return ['ok' => false, 'motivo' => $sinTextoIzq
                    ? 'Las palabras van a la izquierda y ahí hay imágenes sin nombre: escríbeles su palabra.'
                    : 'El crucigrama necesita pistas escritas: ponles nombre a las imágenes de la derecha.'];
            }
            $palabras = juego_como_palabras($tipo, $contenido);
            $falla = juego_palabra_que_falla($palabras, static fn (string $w): bool =>
                preg_match('/^\p{L}{2,12}$/u', $w) === 1);
            if ($falla !== null) {
                return ['ok' => false, 'motivo' => 'Necesita palabras sueltas, sin espacios y de máximo 12 letras. No sirve ' . juego_cita($falla) . '.'];
            }
            if (count($palabras) < 3) {
                return ['ok' => false, 'motivo' => 'Necesita al menos 3 palabras.'];
            }
            $items = [];
            foreach (juego_trozos($palabras, JUEGO_POR_REJILLA) as $grupo) {
                $items[] = $plantilla === 'sopa_letras'
                    ? ['instruccion' => 'Encuentra las palabras', 'palabras' => array_column($grupo, 'palabra')]
                    : ['instruccion' => 'Resuelve el crucigrama', 'entradas' => array_map(
                        static fn (array $p): array => ['palabra' => $p['palabra'], 'pista' => $p['pista']], $grupo)];
            }

            return ['ok' => true, 'items' => $items];
    }

    return ['ok' => false, 'motivo' => 'Plantilla desconocida.'];
}

/** La actividad de Ludia que resulta de jugar el contenido en una plantilla. */
function juego_actividad(string $titulo, string $plantilla, array $items): array
{
    return [
        'uid'    => 'j' . substr(md5($plantilla . $titulo), 0, 10),
        'tipo'   => $plantilla,
        'titulo' => mb_substr($titulo, 0, 80),
        'tiempo' => JUEGO_PLANTILLAS[$plantilla]['tiempo'] ?? 30,
        'items'  => array_slice($items, 0, SESION_MAX_ITEMS),
    ];
}

/**
 * Todas las plantillas con su estado para este contenido y esta cuenta:
 * 'ok', 'plan' (la incluye un plan superior) o 'no' (no se puede, con motivo).
 */
function juego_plantillas_para(?string $tipo, ?array $contenido, ?array $usuario, string $semilla = 's'): array
{
    $lista = [];
    foreach (JUEGO_PLANTILLAS as $clave => $p) {
        $estado = 'ok';
        $motivo = '';
        if (!plan_permite_formato($usuario, $clave)) {
            $estado = 'plan';
            $motivo = 'Incluida en los planes de pago.';
        } elseif ($tipo !== null && $contenido !== null) {
            $prueba = juego_adaptar($tipo, $contenido, $clave, $semilla);
            if (!$prueba['ok']) {
                $estado = 'no';
                $motivo = $prueba['motivo'];
            }
        }
        $lista[$clave] = $p + ['clave' => $clave, 'estado' => $estado, 'motivo' => $motivo];
    }

    return $lista;
}

/* ---------- Guardar ---------- */

function juego_es(array $paquete): bool
{
    return ($paquete['clase'] ?? 'paquete') === 'juego';
}

/** Lo que escribió el profesor, ya decodificado. */
function juego_contenido(array $paquete): array
{
    $datos = json_decode((string) ($paquete['juego_contenido'] ?? ''), true);

    return is_array($datos) && is_array($datos['items'] ?? null) ? $datos : ['items' => []];
}

function juegos_contar(int $usuarioId): int
{
    $consulta = db()->prepare("SELECT COUNT(*) FROM paquetes WHERE usuario_id = ? AND clase = 'juego'");
    $consulta->execute([$usuarioId]);

    return (int) $consulta->fetchColumn();
}

/**
 * Crea o actualiza un juego. Valida, convierte a la plantilla y guarda.
 *
 * @return array{ok:bool, mensaje?:string, id?:int, http?:int}
 */
function juego_guardar(array $usuario, int $id, string $titulo, string $tipo, $datos, string $plantilla): array
{
    $usuarioId = (int) $usuario['id'];
    $titulo = trim($titulo) !== '' ? mb_substr(trim($titulo), 0, 120) : 'Juego sin título';

    if (!isset(JUEGO_PLANTILLAS[$plantilla])) {
        return ['ok' => false, 'mensaje' => 'Elige una plantilla.', 'http' => 422];
    }
    if (!plan_permite_formato($usuario, $plantilla)) {
        return ['ok' => false, 'mensaje' => 'La plantilla ' . JUEGO_PLANTILLAS[$plantilla]['nombre'] . ' es de los planes de pago.', 'http' => 402];
    }

    $validado = juego_validar($tipo, $datos, plan_permite($usuario, 'imagenes'));
    if (!$validado['ok']) {
        return ['ok' => false, 'mensaje' => $validado['error'], 'http' => 422];
    }
    $contenido = $validado['contenido'];

    if ($id > 0) {
        $actual = paquete_de($id, $usuarioId);
        if (!$actual || !juego_es($actual)) {
            return ['ok' => false, 'mensaje' => 'Ese juego no es tuyo.', 'http' => 403];
        }
    } else {
        $tope = plan_max_paquetes($usuario);
        if ($tope > 0 && juegos_contar($usuarioId) >= $tope) {
            return ['ok' => false, 'mensaje' => 'Tu plan ' . plan_nombre(plan_de($usuario)) . ' guarda ' . $tope .
                ' juegos. Borra uno en «Mis paquetes» o pásate a un plan superior.', 'http' => 409];
        }
    }

    // La semilla debe existir antes de convertir: para un juego nuevo se
    // reserva la fila primero y se convierte con su id.
    if ($id === 0) {
        db()->prepare(
            "INSERT INTO paquetes (usuario_id, nombre, audiencia, clase, juego_tipo, juego_contenido, plantilla, contenido, total_actividades)
             VALUES (?, ?, 'estudiantes', 'juego', ?, ?, ?, '[]', 1)"
        )->execute([$usuarioId, $titulo, $tipo, json_encode($contenido, JSON_UNESCAPED_UNICODE), $plantilla]);
        $id = (int) db()->lastInsertId();
    }

    $adaptado = juego_adaptar($tipo, $contenido, $plantilla, 'juego-' . $id);
    if (!$adaptado['ok']) {
        // La plantilla elegida no sirve con este contenido: se busca la
        // primera que sí, para no dejar al profesor con un juego roto.
        foreach (juego_plantillas_para($tipo, $contenido, $usuario, 'juego-' . $id) as $clave => $p) {
            if ($p['estado'] === 'ok') {
                $plantilla = $clave;
                $adaptado = juego_adaptar($tipo, $contenido, $plantilla, 'juego-' . $id);
                break;
            }
        }
    }
    if (!$adaptado['ok']) {
        if (!isset($actual)) {
            db()->prepare('DELETE FROM paquetes WHERE id = ? AND usuario_id = ?')->execute([$id, $usuarioId]);
        }
        return ['ok' => false, 'mensaje' => $adaptado['motivo'], 'http' => 422];
    }

    db()->prepare(
        "UPDATE paquetes
            SET nombre = ?, juego_tipo = ?, juego_contenido = ?, plantilla = ?, contenido = ?, total_actividades = 1
          WHERE id = ? AND usuario_id = ? AND clase = 'juego'"
    )->execute([
        $titulo, $tipo, json_encode($contenido, JSON_UNESCAPED_UNICODE), $plantilla,
        json_encode([juego_actividad($titulo, $plantilla, $adaptado['items'])], JSON_UNESCAPED_UNICODE),
        $id, $usuarioId,
    ]);

    return ['ok' => true, 'id' => $id, 'plantilla' => $plantilla];
}

/** Cambia la plantilla de un juego guardado: solo vuelve a convertir. */
function juego_cambiar_plantilla(array $usuario, array $juego, string $plantilla): array
{
    if (!isset(JUEGO_PLANTILLAS[$plantilla])) {
        return ['ok' => false, 'mensaje' => 'Esa plantilla no existe.'];
    }
    if (!plan_permite_formato($usuario, $plantilla)) {
        return ['ok' => false, 'mensaje' => JUEGO_PLANTILLAS[$plantilla]['nombre'] . ' es de los planes de pago.'];
    }
    $tipo = (string) $juego['juego_tipo'];
    $adaptado = juego_adaptar($tipo, juego_contenido($juego), $plantilla, 'juego-' . $juego['id']);
    if (!$adaptado['ok']) {
        return ['ok' => false, 'mensaje' => $adaptado['motivo']];
    }

    db()->prepare("UPDATE paquetes SET plantilla = ?, contenido = ? WHERE id = ? AND usuario_id = ? AND clase = 'juego'")
        ->execute([
            $plantilla,
            json_encode([juego_actividad((string) $juego['nombre'], $plantilla, $adaptado['items'])], JSON_UNESCAPED_UNICODE),
            (int) $juego['id'],
            (int) $usuario['id'],
        ]);

    return ['ok' => true, 'mensaje' => 'Ahora se juega como ' . JUEGO_PLANTILLAS[$plantilla]['nombre'] . '.'];
}

/**
 * Para ENVIAR un juego: una actividad por cada plantilla que el estudiante
 * puede elegir, con la del docente primero. Entran las que el plan del dueño
 * incluye y que sirven para este contenido. La ruleta y las cajas, que son
 * herramientas del docente, solo entran si son la forma que él eligió.
 *
 * @return list<array>
 */
function juego_actividades_libres(array $juego, ?array $dueno, bool $vivo = false): array
{
    $tipo = (string) $juego['juego_tipo'];
    $contenido = juego_contenido($juego);
    $actual = (string) $juego['plantilla'];
    $orden = array_keys(JUEGO_PLANTILLAS);
    usort($orden, static fn (string $a, string $b): int => ($b === $actual) <=> ($a === $actual));

    $actividades = [];
    foreach ($orden as $clave) {
        // La ruleta y las cajas solo entran si el docente las eligió a propósito
        // (enviado no tienen sentido); en vivo son herramientas del proyector.
        if ((!$vivo && $clave !== $actual && juego_es_lectura($clave)) || !plan_permite_formato($dueno, $clave)) {
            continue;
        }
        $adaptado = juego_adaptar($tipo, $contenido, $clave, 'juego-' . $juego['id']);
        if ($adaptado['ok']) {
            // Con el nombre de la forma en el título, el informe distingue
            // lo que se jugó como Memoria de lo que se jugó como Quiz.
            $actividades[] = juego_actividad((string) $juego['nombre'] . ' · ' . JUEGO_PLANTILLAS[$clave]['nombre'], $clave, $adaptado['items']);
        }
    }

    return $actividades;
}

/** Copia un juego, con su contenido original y su plantilla. */
function juego_duplicar(array $usuario, array $juego): array
{
    $tope = plan_max_paquetes($usuario);
    if ($tope > 0 && juegos_contar((int) $usuario['id']) >= $tope) {
        return ['ok' => false, 'mensaje' => 'Tu plan guarda ' . $tope . ' juegos. Borra uno y vuelve a duplicar.'];
    }

    return juego_guardar(
        $usuario,
        0,
        mb_substr((string) $juego['nombre'] . ' (copia)', 0, 120),
        (string) $juego['juego_tipo'],
        juego_contenido($juego),
        (string) $juego['plantilla']
    ) + ['mensaje' => 'Copia creada.'];
}
