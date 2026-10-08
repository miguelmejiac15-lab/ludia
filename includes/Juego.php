<?php
/**
 * Motor de la sesión en vivo.
 *
 * Dos responsabilidades, ambas en el servidor a propósito:
 *  1. juego_item_publico(): entrega la pregunta SIN las respuestas correctas.
 *  2. juego_calificar(): decide el acierto y los puntos.
 * Así el navegador del participante nunca conoce la respuesta antes de tiempo.
 */

declare(strict_types=1);

require_once __DIR__ . '/Sesiones.php';
require_once __DIR__ . '/Rejillas.php';

const JUEGO_PUNTOS_BASE   = 500; // por acertar
const JUEGO_PUNTOS_RAPIDEZ = 500; // extra según lo que quede de tiempo

/** Formatos que suman puntos. El resto son aportes (mural, encuesta…). */
const JUEGO_TIPOS_PUNTUABLES = [
    'quiz', 'verdadero_falso', 'respuesta_multiple', 'palabra_secreta',
    'completar', 'ordenar_palabras', 'sopa_letras', 'crucigrama',
    'relacionar', 'memoria', 'clasificar', 'ordenar',
    'ortografia', 'anagrama', 'numerica', 'linea_tiempo',
    'video_preguntas',
];

function juego_puntua(string $tipo): bool
{
    return in_array($tipo, JUEGO_TIPOS_PUNTUABLES, true);
}

/**
 * Pantallas que se leen y no se responden: diapositivas para explicar.
 * No tienen tiempo: en vivo las pasa el docente cuando termina de explicar,
 * así que un cronómetro solo metería prisa (decisión del 2026-10-08).
 * La misma lista vive en jugar.js (SOLO_LECTURA) y en formatos.js (`lectura`).
 */
const JUEGO_TIPOS_LECTURA = ['panel', 'pagina'];

function juego_es_lectura(string $tipo): bool
{
    return in_array($tipo, JUEGO_TIPOS_LECTURA, true);
}

/** Texto comparable: sin mayúsculas, tildes, ni signos. */
function juego_normalizar(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', '', $texto) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
}

/** Baraja igual para todos: misma sesión y pregunta, mismo orden. */
function juego_barajar(array $lista, string $semilla): array
{
    mt_srand(crc32($semilla));
    $claves = array_keys($lista);
    for ($i = count($claves) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$claves[$i], $claves[$j]] = [$claves[$j], $claves[$i]];
    }
    mt_srand();
    return array_map(static fn ($k) => $lista[$k], $claves);
}

/** Palabras de una frase, sin signos. */
function juego_palabras(string $frase): array
{
    $limpia = preg_replace('/[^\p{L}\p{N}\s]/u', '', trim($frase)) ?? '';
    return preg_split('/\s+/u', $limpia, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

/** Valores entre [corchetes] de una frase de "completar". */
function juego_huecos(string $texto): array
{
    preg_match_all('/\[([^\]]+)\]/u', $texto, $coincidencias);
    return $coincidencias[1] ?? [];
}

/**
 * La pregunta tal como la verá el participante: sin claves de respuesta.
 */
/**
 * Un lado de una pareja, tal como lo recibe el navegador.
 *
 * La imagen es opcional y NUNCA sustituye al texto: el texto es lo que compara
 * la calificación y lo que oye quien usa lector de pantalla. Si un día se
 * permitiera imagen sin texto, la comparación daría todo por bueno.
 */
function juego_lado_par(array $par, string $claveTexto, string $claveImagen): array
{
    return [
        'texto'  => (string) ($par[$claveTexto] ?? ''),
        'imagen' => (string) ($par[$claveImagen] ?? ''),
    ];
}

function juego_item_publico(array $actividad, int $itemIndice, string $semilla): array
{
    $tipo = $actividad['tipo'];
    $item = $actividad['items'][$itemIndice] ?? [];
    $publico = ['tipo' => $tipo];

    // La imagen acompaña al enunciado: viaja siempre, no revela la respuesta.
    if (!empty($item['imagen'])) {
        $publico['imagen'] = (string) $item['imagen'];
    }

    switch ($tipo) {
        case 'quiz':
        case 'respuesta_multiple':
            $publico['enunciado'] = $item['enunciado'] ?? '';
            $publico['opciones']  = array_values($item['opciones'] ?? []);
            $publico['multiple']  = $tipo === 'respuesta_multiple';
            break;

        case 'video_preguntas': {
            // La respuesta correcta NO viaja, igual que en el quiz: solo el
            // enunciado y las opciones.
            $publico['enunciado'] = $item['enunciado'] ?? '';
            $publico['opciones']  = array_values($item['opciones'] ?? []);
            // El identificador se extrae AQUÍ, no en el navegador: la pantalla
            // del estudiante no carga formatos.js, y además así hay una sola
            // fuente de verdad para leer la URL.
            $publico['video'] = juego_id_youtube((string) ($actividad['video'] ?? ''));

            // El tramo que toca ver antes de esta pregunta: desde donde se
            // quedó la anterior hasta el segundo de esta. Así el vídeo avanza
            // sin repetir y sin saltarse nada.
            $todas = array_values($actividad['items'] ?? []);
            $publico['hasta'] = max(0, (int) ($item['segundo'] ?? 0));
            $publico['desde'] = $itemIndice > 0
                ? max(0, (int) ($todas[$itemIndice - 1]['segundo'] ?? 0))
                : 0;

            // Todas las marcas, para dibujar la línea de tiempo con los puntos
            // donde hay pregunta. Son segundos, no respuestas: no revelan nada.
            $publico['marcas'] = array_map(
                static fn (array $i): int => max(0, (int) ($i['segundo'] ?? 0)),
                $todas
            );
            break;
        }

        case 'verdadero_falso':
            $publico['afirmacion'] = $item['afirmacion'] ?? '';
            break;

        case 'palabra_secreta':
            $publico['pista']   = $item['pista'] ?? '';
            $publico['letras']  = mb_strlen(trim((string) ($item['palabra'] ?? '')));
            break;

        case 'completar':
            $texto = (string) ($item['texto'] ?? '');
            $publico['partes'] = preg_split('/\[[^\]]*\]/u', $texto) ?: [];
            $publico['huecos'] = count(juego_huecos($texto));
            break;

        case 'ordenar_palabras':
            $publico['palabras'] = juego_barajar(juego_palabras((string) ($item['frase'] ?? '')), $semilla);
            break;

        case 'sopa_letras': {
            // La cuadrícula se arma aquí: el participante ve las letras, no dónde
            // está cada palabra.
            $sopa = sopa_generar($item['palabras'] ?? [], $semilla);
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['tamano']      = $sopa['tamano'];
            $publico['celdas']      = $sopa['celdas'];
            $publico['palabras']    = array_map(
                static fn (array $p): string => $p['palabra'],
                $sopa['palabras']
            );
            break;
        }

        case 'crucigrama': {
            $cruci = crucigrama_generar($item['entradas'] ?? [], $semilla);
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['filas']       = $cruci['filas'];
            $publico['columnas']    = $cruci['columnas'];
            $publico['celdas']      = $cruci['celdas'];   // null = casilla negra
            // Las pistas viajan sin su palabra: solo número, texto y largo.
            $sinRespuesta = static fn (array $f): array => [
                'numero'  => $f['numero'],
                'pista'   => $f['pista'],
                'largo'   => $f['largo'],
                'fila'    => $f['fila'],
                'columna' => $f['columna'],
            ];
            $publico['horizontales'] = array_map($sinRespuesta, $cruci['horizontales']);
            $publico['verticales']   = array_map($sinRespuesta, $cruci['verticales']);
            break;
        }

        case 'relacionar':
            // Cada lado viaja como {texto, imagen}: el texto sigue siendo lo que
            // se compara al calificar y el alternativo de la imagen, así que es
            // obligatorio aunque haya foto. Contenido viejo sin imagen sale con
            // la clave vacía y se pinta igual que siempre.
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['izquierda']   = array_map(
                static fn (array $p): array => juego_lado_par($p, 'izquierda', 'imagen_izq'),
                array_values($item['pares'] ?? [])
            );
            $publico['derecha'] = juego_barajar(array_map(
                static fn (array $p): array => juego_lado_par($p, 'derecha', 'imagen_der'),
                array_values($item['pares'] ?? [])
            ), $semilla);
            break;

        case 'memoria':
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $cartas = [];
            foreach (array_values($item['pares'] ?? []) as $i => $par) {
                $cartas[] = ['pareja' => $i] + juego_lado_par($par, 'izquierda', 'imagen_izq');
                $cartas[] = ['pareja' => $i] + juego_lado_par($par, 'derecha', 'imagen_der');
            }
            $publico['cartas'] = juego_barajar($cartas, $semilla);
            break;

        case 'clasificar':
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['categorias']  = array_map(static fn (array $c): string => (string) ($c['nombre'] ?? ''), array_values($item['categorias'] ?? []));
            $elementos = [];
            foreach (array_values($item['categorias'] ?? []) as $cat) {
                foreach ($cat['elementos'] ?? [] as $elemento) {
                    if (trim((string) $elemento) !== '') {
                        $elementos[] = (string) $elemento;
                    }
                }
            }
            $publico['elementos'] = juego_barajar($elementos, $semilla);
            break;

        case 'ordenar':
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['elementos']   = juego_barajar(array_values(array_filter($item['elementos'] ?? [], static fn ($e) => trim((string) $e) !== '')), $semilla);
            break;

        case 'ortografia': {
            // Cada pareja se muestra barajada: no se sabe cuál es la correcta.
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['parejas'] = [];
            foreach (array_values($item['pares'] ?? []) as $i => $par) {
                $opciones = [(string) ($par['correcta'] ?? ''), (string) ($par['incorrecta'] ?? '')];
                if (crc32($semilla . '-orto-' . $i) % 2 === 1) {
                    $opciones = array_reverse($opciones);
                }
                $publico['parejas'][] = ['opciones' => $opciones];
            }
            break;
        }

        case 'anagrama': {
            $letras = preg_split('//u', mb_strtoupper(str_replace(' ', '', (string) ($item['palabra'] ?? ''))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $publico['letras'] = juego_barajar($letras, $semilla);
            $publico['pista']  = $item['pista'] ?? '';
            $publico['largo']  = count($letras);
            break;
        }

        case 'numerica':
            $publico['enunciado'] = $item['enunciado'] ?? '';
            $publico['unidad']    = $item['unidad'] ?? '';
            break;

        case 'linea_tiempo': {
            // Los hechos se barajan y las fechas no se muestran todavía.
            $textos = array_map(
                static fn (array $h): string => (string) ($h['texto'] ?? ''),
                array_values($item['hechos'] ?? [])
            );
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['hechos']      = juego_barajar($textos, $semilla);
            break;
        }

        case 'encuesta':
            $publico['pregunta'] = $item['pregunta'] ?? '';
            $publico['opciones'] = array_values($item['opciones'] ?? []);
            break;

        case 'pregunta_abierta':
            $publico['pregunta']      = $item['pregunta'] ?? '';
            $publico['maxCaracteres'] = (int) ($item['maxCaracteres'] ?? 60);
            break;

        case 'escala':
            $publico['pregunta']    = $item['pregunta'] ?? '';
            $publico['etiquetaMin'] = $item['etiquetaMin'] ?? '';
            $publico['etiquetaMax'] = $item['etiquetaMax'] ?? '';
            break;

        case 'mural':
            $publico['consigna']      = $item['consigna'] ?? '';
            $publico['modo']          = $item['modo'] ?? 'postit';
            $publico['anonimo']       = (bool) ($item['anonimo'] ?? true);
            $publico['moderar']       = (bool) ($item['moderar'] ?? false);
            $publico['maxCaracteres'] = (int) ($item['maxCaracteres'] ?? 120);
            break;

        case 'panel':
            $publico['instruccion'] = $item['instruccion'] ?? '';
            $publico['tarjetas']    = array_values($item['tarjetas'] ?? []);
            break;

        case 'pagina':
            $publico['titulo']   = $item['titulo'] ?? '';
            $publico['parrafos'] = array_values(array_filter(
                $item['parrafos'] ?? [],
                static fn ($p): bool => trim((string) $p) !== ''
            ));
            break;
    }

    return $publico;
}

/**
 * Compara la respuesta con la clave y reparte puntos.
 * Devuelve aciertos parciales: responder bien 3 de 4 parejas suma 3/4.
 */
function juego_calificar(string $tipo, array $item, $respuesta, float $proporcionTiempo, string $semilla = ''): array
{
    $aciertos = 0;
    $total = 1;

    switch ($tipo) {
        case 'quiz':
        case 'video_preguntas':
            $aciertos = ((int) $respuesta === (int) ($item['correcta'] ?? -1)) ? 1 : 0;
            break;

        case 'verdadero_falso':
            $aciertos = ((bool) $respuesta === (bool) ($item['correcta'] ?? false)) ? 1 : 0;
            break;

        case 'respuesta_multiple':
            $marcadas = array_map('intval', is_array($respuesta) ? $respuesta : []);
            $claves = array_map('intval', $item['correctas'] ?? []);
            sort($marcadas);
            sort($claves);
            $aciertos = ($marcadas === $claves) ? 1 : 0;
            break;

        case 'palabra_secreta':
            $aciertos = juego_normalizar((string) $respuesta) === juego_normalizar((string) ($item['palabra'] ?? '')) ? 1 : 0;
            break;

        case 'completar': {
            $claves = juego_huecos((string) ($item['texto'] ?? ''));
            $total = max(1, count($claves));
            foreach ($claves as $i => $clave) {
                $dada = is_array($respuesta) ? ($respuesta[$i] ?? '') : '';
                if (juego_normalizar((string) $dada) === juego_normalizar($clave)) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'ordenar_palabras': {
            $esperada = juego_palabras((string) ($item['frase'] ?? ''));
            $dada = is_array($respuesta) ? array_map('strval', $respuesta) : juego_palabras((string) $respuesta);
            $total = max(1, count($esperada));
            foreach ($esperada as $i => $palabra) {
                if (juego_normalizar($dada[$i] ?? '') === juego_normalizar($palabra)) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'sopa_letras': {
            // Se corrige contra la misma cuadrícula que vio el participante: vale
            // la palabra solo si de verdad estaba colocada ahí.
            $sopa = sopa_generar($item['palabras'] ?? [], $semilla);
            $colocadas = array_map(static fn (array $p): string => $p['palabra'], $sopa['palabras']);
            $total = max(1, count($colocadas));

            $dadas = [];
            foreach (is_array($respuesta) ? $respuesta : [] as $hallada) {
                $dadas[] = rejilla_normalizar((string) $hallada);
            }
            $aciertos = count(array_intersect($colocadas, array_unique($dadas)));
            break;
        }

        case 'crucigrama': {
            // La respuesta llega por pista, con la misma clave que usa la pantalla:
            // "3h" = horizontal número 3, "1v" = vertical número 1.
            $cruci = crucigrama_generar($item['entradas'] ?? [], $semilla);
            $fichas = [];
            foreach ($cruci['horizontales'] as $ficha) {
                $fichas[$ficha['numero'] . 'h'] = $ficha['palabra'];
            }
            foreach ($cruci['verticales'] as $ficha) {
                $fichas[$ficha['numero'] . 'v'] = $ficha['palabra'];
            }

            $total = max(1, count($fichas));
            foreach ($fichas as $clave => $palabra) {
                $dada = is_array($respuesta) ? ($respuesta[$clave] ?? '') : '';
                if (rejilla_normalizar((string) $dada) === $palabra) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'relacionar': {
            $pares = array_values($item['pares'] ?? []);
            $total = max(1, count($pares));
            foreach ($pares as $i => $par) {
                $dada = is_array($respuesta) ? ($respuesta[$i] ?? '') : '';
                if (juego_normalizar((string) $dada) === juego_normalizar((string) ($par['derecha'] ?? ''))) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'memoria': {
            $total = max(1, count($item['pares'] ?? []));
            $aciertos = min($total, max(0, (int) (is_array($respuesta) ? ($respuesta['encontradas'] ?? 0) : $respuesta)));
            break;
        }

        case 'clasificar': {
            $categorias = array_values($item['categorias'] ?? []);
            $esperado = [];
            foreach ($categorias as $indice => $categoria) {
                foreach ($categoria['elementos'] ?? [] as $elemento) {
                    if (trim((string) $elemento) !== '') {
                        $esperado[juego_normalizar((string) $elemento)] = $indice;
                    }
                }
            }
            $total = max(1, count($esperado));
            if (is_array($respuesta)) {
                foreach ($respuesta as $elemento => $categoria) {
                    $clave = juego_normalizar((string) $elemento);
                    if (isset($esperado[$clave]) && $esperado[$clave] === (int) $categoria) {
                        $aciertos++;
                    }
                }
            }
            break;
        }

        case 'ortografia': {
            $pares = array_values($item['pares'] ?? []);
            $total = max(1, count($pares));
            foreach ($pares as $i => $par) {
                $elegida = is_array($respuesta) ? ($respuesta[$i] ?? '') : '';
                if (juego_normalizar((string) $elegida) === juego_normalizar((string) ($par['correcta'] ?? ''))) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'anagrama':
            $aciertos = juego_normalizar(str_replace(' ', '', (string) $respuesta))
                === juego_normalizar(str_replace(' ', '', (string) ($item['palabra'] ?? ''))) ? 1 : 0;
            break;

        case 'numerica': {
            $esperada = (float) str_replace(',', '.', (string) ($item['respuesta'] ?? '0'));
            $margen = abs((float) str_replace(',', '.', (string) ($item['tolerancia'] ?? 0)));
            $dada = str_replace(',', '.', trim((string) $respuesta));
            $aciertos = (is_numeric($dada) && abs((float) $dada - $esperada) <= $margen) ? 1 : 0;
            break;
        }

        case 'linea_tiempo': {
            // Se compara contra el orden por fecha, no contra el orden de captura.
            $hechos = array_values(array_filter(
                $item['hechos'] ?? [],
                static fn (array $h): bool => trim((string) ($h['texto'] ?? '')) !== ''
            ));
            usort($hechos, static fn (array $a, array $b): int => strcmp((string) $a['fecha'], (string) $b['fecha']));

            $total = max(1, count($hechos));
            $dada = is_array($respuesta) ? array_map('strval', $respuesta) : [];
            foreach ($hechos as $i => $hecho) {
                if (juego_normalizar($dada[$i] ?? '') === juego_normalizar((string) $hecho['texto'])) {
                    $aciertos++;
                }
            }
            break;
        }

        case 'ordenar': {
            $esperado = array_values(array_filter($item['elementos'] ?? [], static fn ($e) => trim((string) $e) !== ''));
            $total = max(1, count($esperado));
            $dada = is_array($respuesta) ? array_map('strval', $respuesta) : [];
            foreach ($esperado as $i => $elemento) {
                if (juego_normalizar($dada[$i] ?? '') === juego_normalizar((string) $elemento)) {
                    $aciertos++;
                }
            }
            break;
        }

        default:
            // Formatos de participación: se registran, no se califican.
            return ['correcta' => null, 'aciertos' => 0, 'total' => 0, 'puntos' => 0];
    }

    $proporcion = $total > 0 ? $aciertos / $total : 0.0;
    $rapidez = max(0.0, min(1.0, $proporcionTiempo));
    $puntos = (int) round($proporcion * (JUEGO_PUNTOS_BASE + JUEGO_PUNTOS_RAPIDEZ * $rapidez));

    return [
        'correcta' => $proporcion >= 1.0,
        'aciertos' => $aciertos,
        'total'    => $total,
        'puntos'   => $puntos,
    ];
}

/** Tabla de posiciones: puntos primero, y a igualdad quien respondió más rápido. */
function juego_ranking(int $sesionId): array
{
    $consulta = db()->prepare(
        'SELECT p.nombre, p.personaje, p.puntaje,
                COALESCE(SUM(r.correcta = 1), 0) AS aciertos,
                COALESCE(AVG(r.segundos), 0)     AS promedio
         FROM participantes p
         LEFT JOIN respuestas r ON r.participante_id = p.id
         WHERE p.sesion_id = ? AND p.estado <> "salio"
         GROUP BY p.id
         ORDER BY p.puntaje DESC, promedio ASC, p.created_at ASC'
    );
    $consulta->execute([$sesionId]);

    $posicion = 0;
    return array_map(static function (array $fila) use (&$posicion): array {
        $posicion++;
        return [
            'posicion'  => $posicion,
            'nombre'    => $fila['nombre'],
            'personaje' => json_decode((string) $fila['personaje'], true) ?: [],
            'puntaje'   => (int) $fila['puntaje'],
            'aciertos'  => (int) $fila['aciertos'],
            'promedio'  => round((float) $fila['promedio'], 1),
        ];
    }, $consulta->fetchAll());
}

/** Respuestas de una pregunta concreta (para proyectar resultados y para el informe). */
function juego_respuestas_item(int $sesionId, int $actividad, int $item): array
{
    $consulta = db()->prepare(
        'SELECT r.respuesta, r.correcta, r.puntos, r.segundos, p.nombre, p.personaje
         FROM respuestas r JOIN participantes p ON p.id = r.participante_id
         WHERE r.sesion_id = ? AND r.actividad_indice = ? AND r.item_indice = ?
         ORDER BY r.created_at'
    );
    $consulta->execute([$sesionId, $actividad, $item]);

    return array_map(static fn (array $f): array => [
        'nombre'    => $f['nombre'],
        'personaje' => json_decode((string) $f['personaje'], true) ?: [],
        'respuesta' => json_decode((string) $f['respuesta'], true),
        'correcta'  => $f['correcta'] === null ? null : (bool) $f['correcta'],
        'puntos'    => (int) $f['puntos'],
        'segundos'  => $f['segundos'] === null ? null : (float) $f['segundos'],
    ], $consulta->fetchAll());
}

/** Informe final: una fila por pregunta con aciertos y aportes. */
function juego_informe(array $sesion): array
{
    $contenido = json_decode((string) $sesion['contenido'], true) ?: [];
    $sesionId = (int) $sesion['id'];
    $informe = [];

    foreach ($contenido as $a => $actividad) {
        $preguntas = [];
        foreach ($actividad['items'] as $i => $item) {
            $respuestas = juego_respuestas_item($sesionId, $a, $i);
            $puntuable = juego_puntua($actividad['tipo']);
            $correctas = count(array_filter($respuestas, static fn (array $r): bool => $r['correcta'] === true));

            $preguntas[] = [
                'numero'     => $i + 1,
                'resumen'    => juego_resumen_item($actividad['tipo'], $item),
                'respuestas' => count($respuestas),
                'correctas'  => $puntuable ? $correctas : null,
                'aportes'    => $puntuable ? [] : array_map(static fn (array $r) => [
                    'nombre' => $r['nombre'],
                    'texto'  => juego_aporte_legible($actividad['tipo'], $item, $r['respuesta']),
                ], $respuestas),
            ];
        }

        $informe[] = [
            'titulo'    => actividad_titulo($actividad),
            'tipo'      => $actividad['tipo'],
            'puntuable' => juego_puntua($actividad['tipo']),
            'preguntas' => $preguntas,
        ];
    }

    return $informe;
}

/**
 * Convierte la respuesta guardada en algo legible en el informe.
 * En una encuesta se guarda el índice de la opción; aquí se devuelve su texto.
 */
function juego_aporte_legible(string $tipo, array $item, $respuesta): string
{
    switch ($tipo) {
        case 'encuesta':
            $opciones = array_values($item['opciones'] ?? []);
            return (string) ($opciones[(int) $respuesta] ?? 'Sin respuesta');
        case 'escala':
            return ((int) $respuesta) . ' de 5';
        case 'mural':
        case 'pregunta_abierta':
            return is_string($respuesta) ? $respuesta : '';
        default:
            return is_string($respuesta) ? $respuesta : json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }
}

/** Texto corto que identifica la pregunta en el informe. */
function juego_resumen_item(string $tipo, array $item): string
{
    foreach (['enunciado', 'afirmacion', 'pregunta', 'consigna', 'instruccion', 'texto', 'frase', 'pista'] as $campo) {
        if (!empty($item[$campo])) {
            return mb_substr((string) $item[$campo], 0, 120);
        }
    }
    return ucfirst(str_replace('_', ' ', $tipo));
}
