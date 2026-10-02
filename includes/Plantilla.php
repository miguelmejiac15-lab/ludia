<?php
/**
 * Plantilla de Excel para cargar actividades en bloque.
 *
 * Cuatro hojas de trabajo, pensadas para que quepan todos los formatos sin
 * marear a quien la llena:
 *   · Actividades → qué actividades tiene el paquete (tipo, título, tiempo)
 *   · Preguntas   → lo que se pregunta (quiz, V/F, completar, encuestas…)
 *   · Parejas     → dos columnas que se emparejan (relacionar, memoria, crucigrama, panel)
 *   · Listas      → elementos sueltos o agrupados (sopa de letras, ordenar, clasificar)
 *
 * La conversión devuelve actividades con la misma forma que usa el editor
 * (assets/js/formatos.js), más una lista de avisos en español.
 */

declare(strict_types=1);

require_once __DIR__ . '/Excel.php';

/** Nombre amable ↔ clave técnica del formato. */
const PLANTILLA_TIPOS = [
    'quiz'                => 'quiz',
    'verdadero o falso'   => 'verdadero_falso',
    'respuesta multiple'  => 'respuesta_multiple',
    'palabra secreta'     => 'palabra_secreta',
    'completar la frase'  => 'completar',
    'completar'           => 'completar',
    'ordenar palabras'    => 'ordenar_palabras',
    'sopa de letras'      => 'sopa_letras',
    'crucigrama'          => 'crucigrama',
    'relacionar'          => 'relacionar',
    'memoria'             => 'memoria',
    'clasificar'          => 'clasificar',
    'ordenar secuencia'   => 'ordenar',
    'ordenar'             => 'ordenar',
    'mural colaborativo'  => 'mural',
    'mural'               => 'mural',
    'encuesta'            => 'encuesta',
    'pregunta abierta'    => 'pregunta_abierta',
    'escala de opinion'   => 'escala',
    'escala'              => 'escala',
    'panel de repaso'     => 'panel',
    'panel'               => 'panel',
];

/** En qué hoja se llena cada formato (para las instrucciones y los avisos). */
const PLANTILLA_HOJA_DE = [
    'quiz' => 'Preguntas', 'verdadero_falso' => 'Preguntas', 'respuesta_multiple' => 'Preguntas',
    'completar' => 'Preguntas', 'palabra_secreta' => 'Preguntas', 'ordenar_palabras' => 'Preguntas',
    'encuesta' => 'Preguntas', 'pregunta_abierta' => 'Preguntas', 'escala' => 'Preguntas', 'mural' => 'Preguntas',
    'relacionar' => 'Parejas', 'memoria' => 'Parejas', 'crucigrama' => 'Parejas', 'panel' => 'Parejas',
    'sopa_letras' => 'Listas', 'ordenar' => 'Listas', 'clasificar' => 'Listas',
];

/** Texto comparable: sin mayúsculas, tildes ni espacios de más. */
function plantilla_normalizar(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
}

/* ---------- Generación del archivo ---------- */

function plantilla_hojas(): array
{
    return [
        'Instrucciones' => [
            'anchos' => [95],
            'filas'  => [
                ['Cómo llenar esta plantilla'],
                [''],
                ['1. En la hoja ACTIVIDADES escribe una fila por actividad: el tipo, el título y los segundos por pregunta.'],
                ['2. En las otras hojas, la columna "Actividad" debe repetir EXACTAMENTE el título que pusiste en ACTIVIDADES.'],
                ['3. Guarda el archivo y súbelo en Ludia con el botón "Cargar desde Excel".'],
                [''],
                ['Dónde va cada tipo:'],
                ['   Hoja PREGUNTAS → Quiz, Verdadero o falso, Respuesta múltiple, Completar la frase, Palabra secreta,'],
                ['                    Ordenar palabras, Encuesta, Pregunta abierta, Escala de opinión y Mural.'],
                ['   Hoja PAREJAS   → Relacionar, Memoria, Crucigrama y Panel de repaso.'],
                ['   Hoja LISTAS    → Sopa de letras, Ordenar secuencia y Clasificar.'],
                [''],
                ['Detalles útiles:'],
                ['   · Quiz: escribe las opciones y pon en "Correcta" la letra (A, B, C o D).'],
                ['   · Respuesta múltiple: varias letras separadas por coma, por ejemplo A,C'],
                ['   · Verdadero o falso: en "Correcta" escribe V o F.'],
                ['   · Completar la frase: encierra entre [corchetes] lo que el público debe escribir.'],
                ['   · Palabra secreta: la pista va en "Pregunta" y la palabra en "Correcta".'],
                ['   · Ordenar palabras: escribe la frase completa en "Pregunta".'],
                ['   · Escala de opinión: "Opción A" es la etiqueta del 1 y "Opción B" la del 5.'],
                ['   · Mural y Pregunta abierta: solo necesitan la consigna en "Pregunta".'],
                ['   · Clasificar: en la hoja LISTAS, "Grupo" es la categoría de cada elemento.'],
                ['   · Ordenar secuencia: en LISTAS, escribe los elementos en el orden correcto.'],
                [''],
                ['Las filas de ejemplo que trae la plantilla se pueden borrar o reemplazar.'],
            ],
        ],

        'Actividades' => [
            'anchos' => [26, 42, 14],
            'filas'  => [
                ['Tipo', 'Título', 'Tiempo (segundos)'],
                ['Quiz', 'Sumas de dos cifras', 20],
                ['Verdadero o falso', 'Repaso rápido', 20],
                ['Relacionar', 'Une cada número', 30],
                ['Sopa de letras', 'Palabras del tema', 60],
                ['Mural', 'Tu idea de hoy', 120],
            ],
        ],

        'Preguntas' => [
            'anchos' => [28, 52, 20, 20, 20, 20, 14],
            'filas'  => [
                ['Actividad', 'Pregunta', 'Opción A', 'Opción B', 'Opción C', 'Opción D', 'Correcta'],
                ['Sumas de dos cifras', '¿Cuánto es 24 + 18?', '42', '32', '46', '38', 'A'],
                ['Sumas de dos cifras', '¿Cuánto es 30 + 25?', '55', '45', '65', '50', 'A'],
                ['Repaso rápido', 'Sumar 10 + 10 da 20.', '', '', '', '', 'V'],
                ['Tu idea de hoy', 'Escribe algo que aprendiste hoy', '', '', '', '', ''],
            ],
        ],

        'Parejas' => [
            'anchos' => [28, 38, 46],
            'filas'  => [
                ['Actividad', 'Izquierda (o palabra)', 'Derecha (o pista)'],
                ['Une cada número', '10 + 5', '15'],
                ['Une cada número', '20 + 20', '40'],
                ['Une cada número', '7 + 3', '10'],
            ],
        ],

        'Listas' => [
            'anchos' => [28, 28, 38],
            'filas'  => [
                ['Actividad', 'Grupo (solo para Clasificar)', 'Elemento'],
                ['Palabras del tema', '', 'suma'],
                ['Palabras del tema', '', 'total'],
                ['Palabras del tema', '', 'cifra'],
            ],
        ],
    ];
}

function plantilla_generar(string $ruta): void
{
    excel_escribir($ruta, plantilla_hojas());
}

/* ---------- Conversión a actividades ---------- */

function plantilla_valor(array $fila, int $columna): string
{
    return trim((string) ($fila[$columna] ?? ''));
}

/** Busca una hoja sin importar mayúsculas ni tildes. */
function plantilla_hoja(array $hojas, string $buscada): array
{
    foreach ($hojas as $nombre => $filas) {
        if (plantilla_normalizar($nombre) === plantilla_normalizar($buscada)) {
            return $filas;
        }
    }
    return [];
}

/** Letras de "Correcta" (A, B…) a índices de opción. */
function plantilla_letras_a_indices(string $texto): array
{
    $indices = [];
    foreach (preg_split('/[,\s;]+/', mb_strtoupper(trim($texto)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $letra) {
        if (preg_match('/^[A-D]$/', $letra)) {
            $indices[] = ord($letra) - 65;
        }
    }
    return array_values(array_unique($indices));
}

/**
 * Convierte las hojas leídas en actividades del editor.
 *
 * @return array{actividades: array, avisos: array}
 */
function plantilla_a_actividades(array $hojas): array
{
    $avisos = [];
    $definiciones = plantilla_hoja($hojas, 'Actividades');

    if (count($definiciones) < 2) {
        return ['actividades' => [], 'avisos' => ['La hoja «Actividades» está vacía: ahí va una fila por actividad.']];
    }

    // 1. Actividades declaradas
    $actividades = [];
    $porTitulo = [];
    foreach (array_slice($definiciones, 1) as $n => $fila) {
        $numeroFila = $n + 2;
        $tipoTexto = plantilla_valor($fila, 0);
        $titulo = plantilla_valor($fila, 1);
        if ($tipoTexto === '' && $titulo === '') {
            continue;
        }

        $clave = PLANTILLA_TIPOS[plantilla_normalizar($tipoTexto)] ?? (isset(PLANTILLA_HOJA_DE[$tipoTexto]) ? $tipoTexto : null);
        if (!$clave) {
            $avisos[] = 'Actividades, fila ' . $numeroFila . ': no reconocemos el tipo «' . $tipoTexto . '».';
            continue;
        }
        if ($titulo === '') {
            $avisos[] = 'Actividades, fila ' . $numeroFila . ': falta el título.';
            continue;
        }

        $tiempo = (int) preg_replace('/\D/', '', plantilla_valor($fila, 2));
        $actividades[] = [
            'uid'    => 'x' . $n . substr(md5($titulo), 0, 6),
            'tipo'   => $clave,
            'titulo' => mb_substr($titulo, 0, 80),
            'tiempo' => $tiempo >= 5 ? min(600, $tiempo) : 20,
            'items'  => [],
        ];
        $porTitulo[plantilla_normalizar($titulo)] = count($actividades) - 1;
    }

    // 2. Filas de contenido, repartidas por hoja
    $reparto = [
        'Preguntas' => 'plantilla_item_pregunta',
        'Parejas'   => 'plantilla_item_pareja',
        'Listas'    => 'plantilla_item_lista',
    ];

    foreach ($reparto as $hoja => $constructor) {
        $filas = plantilla_hoja($hojas, $hoja);
        foreach (array_slice($filas, 1) as $n => $fila) {
            $numeroFila = $n + 2;
            $titulo = plantilla_valor($fila, 0);
            if ($titulo === '') {
                continue;
            }

            $indice = $porTitulo[plantilla_normalizar($titulo)] ?? null;
            if ($indice === null) {
                $avisos[] = $hoja . ', fila ' . $numeroFila . ': «' . $titulo . '» no está en la hoja Actividades.';
                continue;
            }

            $tipo = $actividades[$indice]['tipo'];
            if ((PLANTILLA_HOJA_DE[$tipo] ?? '') !== $hoja) {
                $avisos[] = $hoja . ', fila ' . $numeroFila . ': «' . $titulo . '» se llena en la hoja ' . (PLANTILLA_HOJA_DE[$tipo] ?? '—') . '.';
                continue;
            }

            $constructor($actividades[$indice], $fila, $hoja . ', fila ' . $numeroFila, $avisos);
        }
    }

    // 3. Fuera las que quedaron sin contenido.
    //    Ojo: las de una sola pantalla (relacionar, sopa de letras…) crean su
    //    ítem contenedor antes de validar cada fila, así que puede quedar vacío
    //    por dentro aunque el ítem exista.
    $listas = [];
    foreach ($actividades as $actividad) {
        $actividad['items'] = array_values(array_filter($actividad['items'], static function (array $item): bool {
            foreach (['pares', 'entradas', 'tarjetas', 'palabras', 'elementos', 'categorias'] as $lista) {
                if (array_key_exists($lista, $item)) {
                    return count($item[$lista]) > 0;
                }
            }
            return true;   // las pantallas sueltas (quiz, mural…) ya venían validadas
        }));

        if (!$actividad['items']) {
            $avisos[] = 'La actividad «' . $actividad['titulo'] . '» se quedó sin contenido: revisa la hoja ' . (PLANTILLA_HOJA_DE[$actividad['tipo']] ?? '—') . '.';
            continue;
        }
        $listas[] = $actividad;
    }

    return ['actividades' => $listas, 'avisos' => $avisos];
}

/* ---------- Constructores por hoja ---------- */

function plantilla_item_pregunta(array &$actividad, array $fila, string $donde, array &$avisos): void
{
    $texto = plantilla_valor($fila, 1);
    $opciones = array_values(array_filter([
        plantilla_valor($fila, 2), plantilla_valor($fila, 3),
        plantilla_valor($fila, 4), plantilla_valor($fila, 5),
    ], static fn (string $o): bool => $o !== ''));
    $correcta = plantilla_valor($fila, 6);

    if ($texto === '') {
        $avisos[] = $donde . ': falta la pregunta.';
        return;
    }

    switch ($actividad['tipo']) {
        case 'quiz': {
            $indices = plantilla_letras_a_indices($correcta);
            if (count($opciones) < 2) {
                $avisos[] = $donde . ': un quiz necesita al menos 2 opciones.';
                return;
            }
            if (!$indices || !isset($opciones[$indices[0]])) {
                $avisos[] = $donde . ': en «Correcta» pon la letra de la opción buena (A, B, C o D).';
                return;
            }
            $actividad['items'][] = ['enunciado' => $texto, 'opciones' => $opciones, 'correcta' => $indices[0]];
            break;
        }

        case 'respuesta_multiple': {
            $indices = array_values(array_filter(plantilla_letras_a_indices($correcta), static fn (int $i): bool => isset($opciones[$i])));
            if (count($opciones) < 3 || count($indices) < 2) {
                $avisos[] = $donde . ': necesita 3 opciones o más y al menos 2 correctas (por ejemplo A,C).';
                return;
            }
            $actividad['items'][] = ['enunciado' => $texto, 'opciones' => $opciones, 'correctas' => $indices];
            break;
        }

        case 'verdadero_falso': {
            $valor = plantilla_normalizar($correcta);
            if (!in_array($valor, ['v', 'f', 'verdadero', 'falso', 'si', 'no'], true)) {
                $avisos[] = $donde . ': en «Correcta» escribe V o F.';
                return;
            }
            $actividad['items'][] = ['afirmacion' => $texto, 'correcta' => in_array($valor, ['v', 'verdadero', 'si'], true)];
            break;
        }

        case 'completar':
            if (!preg_match('/\[[^\]]+\]/', $texto)) {
                $avisos[] = $donde . ': encierra entre [corchetes] lo que hay que completar.';
                return;
            }
            $actividad['items'][] = ['texto' => $texto];
            break;

        case 'palabra_secreta':
            if (mb_strlen($correcta) < 3) {
                $avisos[] = $donde . ': la palabra secreta va en «Correcta» y necesita 3 letras o más.';
                return;
            }
            $actividad['items'][] = ['palabra' => $correcta, 'pista' => $texto];
            break;

        case 'ordenar_palabras':
            if (count(preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: []) < 4) {
                $avisos[] = $donde . ': la frase necesita al menos 4 palabras.';
                return;
            }
            $actividad['items'][] = ['frase' => $texto];
            break;

        case 'encuesta':
            if (count($opciones) < 2) {
                $avisos[] = $donde . ': una encuesta necesita al menos 2 opciones.';
                return;
            }
            $actividad['items'][] = ['pregunta' => $texto, 'opciones' => $opciones];
            break;

        case 'pregunta_abierta':
            $actividad['items'][] = ['pregunta' => $texto, 'maxCaracteres' => 60];
            break;

        case 'escala':
            $actividad['items'][] = [
                'pregunta'    => $texto,
                'etiquetaMin' => $opciones[0] ?? 'Nada de acuerdo',
                'etiquetaMax' => $opciones[1] ?? 'Totalmente de acuerdo',
            ];
            break;

        case 'mural':
            $actividad['items'][] = [
                'consigna' => $texto, 'modo' => 'postit', 'anonimo' => true,
                'moderar' => false, 'maxCaracteres' => 120, 'ejemplos' => [],
            ];
            break;
    }
}

function plantilla_item_pareja(array &$actividad, array $fila, string $donde, array &$avisos): void
{
    $izquierda = plantilla_valor($fila, 1);
    $derecha = plantilla_valor($fila, 2);

    if ($izquierda === '' || $derecha === '') {
        $avisos[] = $donde . ': hacen falta las dos columnas.';
        return;
    }

    // Estas actividades son una sola pantalla con varias filas dentro.
    if (!$actividad['items']) {
        $actividad['items'][] = match ($actividad['tipo']) {
            'crucigrama' => ['instruccion' => 'Resuelve el crucigrama', 'entradas' => []],
            'panel'      => ['instruccion' => $actividad['titulo'], 'tarjetas' => []],
            'memoria'    => ['instruccion' => 'Encuentra las parejas', 'pares' => []],
            default      => ['instruccion' => 'Relaciona cada elemento con su pareja', 'pares' => []],
        };
    }

    switch ($actividad['tipo']) {
        case 'crucigrama':
            if (preg_match('/\s/u', $izquierda)) {
                $avisos[] = $donde . ': la palabra del crucigrama no puede llevar espacios.';
                return;
            }
            $actividad['items'][0]['entradas'][] = ['palabra' => $izquierda, 'pista' => $derecha];
            break;

        case 'panel':
            $actividad['items'][0]['tarjetas'][] = ['titulo' => $izquierda, 'texto' => $derecha];
            break;

        default:
            $actividad['items'][0]['pares'][] = ['izquierda' => $izquierda, 'derecha' => $derecha];
            break;
    }
}

function plantilla_item_lista(array &$actividad, array $fila, string $donde, array &$avisos): void
{
    $grupo = plantilla_valor($fila, 1);
    $elemento = plantilla_valor($fila, 2);

    if ($elemento === '') {
        $avisos[] = $donde . ': falta el elemento.';
        return;
    }

    if (!$actividad['items']) {
        $actividad['items'][] = match ($actividad['tipo']) {
            'sopa_letras' => ['instruccion' => 'Encuentra las palabras', 'palabras' => []],
            'clasificar'  => ['instruccion' => 'Clasifica cada elemento', 'categorias' => []],
            default       => ['instruccion' => 'Ordena de primero a último', 'elementos' => []],
        };
    }

    switch ($actividad['tipo']) {
        case 'sopa_letras':
            if (preg_match('/\s/u', $elemento) || mb_strlen($elemento) > 12) {
                $avisos[] = $donde . ': cada palabra va sin espacios y con máximo 12 letras.';
                return;
            }
            $actividad['items'][0]['palabras'][] = $elemento;
            break;

        case 'clasificar': {
            if ($grupo === '') {
                $avisos[] = $donde . ': para clasificar hay que decir a qué grupo pertenece.';
                return;
            }
            $categorias = &$actividad['items'][0]['categorias'];
            $indice = null;
            foreach ($categorias as $i => $categoria) {
                if (plantilla_normalizar($categoria['nombre']) === plantilla_normalizar($grupo)) {
                    $indice = $i;
                    break;
                }
            }
            if ($indice === null) {
                $categorias[] = ['nombre' => $grupo, 'elementos' => []];
                $indice = count($categorias) - 1;
            }
            $categorias[$indice]['elementos'][] = $elemento;
            break;
        }

        default:
            $actividad['items'][0]['elementos'][] = $elemento;
            break;
    }
}
