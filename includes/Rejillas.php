<?php
/**
 * Generación de cuadrículas para sopa de letras y crucigrama.
 *
 * Se arman en el servidor y con semilla fija: todos los participantes de una
 * misma pregunta ven exactamente el mismo tablero, y el resultado se puede
 * recalcular igual al corregir.
 */

declare(strict_types=1);

/** Deja la palabra lista para la cuadrícula: mayúsculas, sin tildes ni espacios. */
function rejilla_normalizar(string $palabra): string
{
    $palabra = mb_strtoupper(trim($palabra));
    $palabra = strtr($palabra, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
    ]);
    return preg_replace('/[^A-ZÑ]/u', '', $palabra) ?? '';
}

/** Arranca el azar de forma reproducible para una pregunta concreta. */
function rejilla_semilla(string $semilla): void
{
    mt_srand(crc32($semilla));
}

/* =====================================================================
   Sopa de letras
   ===================================================================== */

const SOPA_DIRECCIONES = [
    [0, 1],   // →
    [1, 0],   // ↓
    [1, 1],   // ↘
    [-1, 1],  // ↗
    [0, -1],  // ←
    [1, -1],  // ↙
];

/**
 * Coloca las palabras en una cuadrícula y rellena el resto.
 *
 * @return array{tamano:int, celdas:array, palabras:array, sin_lugar:array}
 *         'palabras' lleva dónde quedó cada una (fila, columna y dirección),
 *         que es lo que necesita el servidor para corregir.
 */
function sopa_generar(array $palabras, string $semilla): array
{
    $limpias = [];
    foreach ($palabras as $palabra) {
        $limpia = rejilla_normalizar((string) $palabra);
        if ($limpia !== '' && mb_strlen($limpia) >= 2) {
            $limpias[] = $limpia;
        }
    }

    // Las largas primero: es más fácil acomodar las cortas después.
    usort($limpias, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

    $masLarga = $limpias ? mb_strlen($limpias[0]) : 8;
    $letras = array_sum(array_map('mb_strlen', $limpias));
    $tamano = max(10, $masLarga + 2, (int) ceil(sqrt($letras * 2.2)));
    $tamano = min(14, $tamano);

    $celdas = array_fill(0, $tamano, array_fill(0, $tamano, ''));
    $colocadas = [];
    $sinLugar = [];

    rejilla_semilla($semilla . '-sopa');

    foreach ($limpias as $palabra) {
        $largo = mb_strlen($palabra);
        $puesta = false;

        // Se prueban posiciones y direcciones al azar hasta que encaje.
        for ($intento = 0; $intento < 250 && !$puesta; $intento++) {
            $direccion = SOPA_DIRECCIONES[mt_rand(0, count(SOPA_DIRECCIONES) - 1)];
            $fila = mt_rand(0, $tamano - 1);
            $columna = mt_rand(0, $tamano - 1);

            $finFila = $fila + $direccion[0] * ($largo - 1);
            $finColumna = $columna + $direccion[1] * ($largo - 1);
            if ($finFila < 0 || $finFila >= $tamano || $finColumna < 0 || $finColumna >= $tamano) {
                continue;
            }

            // Solo se puede pisar una celda si ya tiene la misma letra.
            $cabe = true;
            for ($i = 0; $i < $largo; $i++) {
                $f = $fila + $direccion[0] * $i;
                $c = $columna + $direccion[1] * $i;
                $actual = $celdas[$f][$c];
                if ($actual !== '' && $actual !== mb_substr($palabra, $i, 1)) {
                    $cabe = false;
                    break;
                }
            }
            if (!$cabe) {
                continue;
            }

            for ($i = 0; $i < $largo; $i++) {
                $celdas[$fila + $direccion[0] * $i][$columna + $direccion[1] * $i] = mb_substr($palabra, $i, 1);
            }

            $colocadas[] = [
                'palabra'  => $palabra,
                'fila'     => $fila,
                'columna'  => $columna,
                'df'       => $direccion[0],
                'dc'       => $direccion[1],
                'largo'    => $largo,
                'fila_fin' => $finFila,
                'col_fin'  => $finColumna,
            ];
            $puesta = true;
        }

        if (!$puesta) {
            $sinLugar[] = $palabra;
        }
    }

    // Relleno: se usan las letras de las propias palabras para que no canten.
    $bolsa = str_split(preg_replace('/[^A-ZÑ]/u', '', implode('', $limpias)) ?: 'AEIOURSTLNMPC');
    for ($f = 0; $f < $tamano; $f++) {
        for ($c = 0; $c < $tamano; $c++) {
            if ($celdas[$f][$c] === '') {
                $celdas[$f][$c] = $bolsa[mt_rand(0, count($bolsa) - 1)];
            }
        }
    }

    mt_srand();

    return [
        'tamano'    => $tamano,
        'celdas'    => $celdas,
        'palabras'  => $colocadas,
        'sin_lugar' => $sinLugar,
    ];
}

/* =====================================================================
   Crucigrama
   ===================================================================== */

/** ¿Cabe la palabra en esa posición sin pegarse a otras? */
function crucigrama_cabe(array $celdas, string $palabra, int $fila, int $columna, bool $horizontal, int $lado): bool
{
    $largo = mb_strlen($palabra);
    $finFila = $horizontal ? $fila : $fila + $largo - 1;
    $finColumna = $horizontal ? $columna + $largo - 1 : $columna;

    if ($fila < 0 || $columna < 0 || $finFila >= $lado || $finColumna >= $lado) {
        return false;
    }

    // Las casillas de antes y después deben estar libres: si no, se pegaría a otra palabra.
    $antesFila = $horizontal ? $fila : $fila - 1;
    $antesColumna = $horizontal ? $columna - 1 : $columna;
    $despuesFila = $horizontal ? $fila : $finFila + 1;
    $despuesColumna = $horizontal ? $finColumna + 1 : $columna;

    foreach ([[$antesFila, $antesColumna], [$despuesFila, $despuesColumna]] as [$f, $c]) {
        if ($f >= 0 && $c >= 0 && $f < $lado && $c < $lado && ($celdas[$f][$c] ?? '') !== '') {
            return false;
        }
    }

    for ($i = 0; $i < $largo; $i++) {
        $f = $horizontal ? $fila : $fila + $i;
        $c = $horizontal ? $columna + $i : $columna;
        $letra = mb_substr($palabra, $i, 1);
        $actual = $celdas[$f][$c] ?? '';

        if ($actual !== '') {
            if ($actual !== $letra) {
                return false;   // choca con otra letra
            }
            continue;           // es un cruce válido
        }

        // En casilla nueva, los lados deben estar libres (si no, quedarían dos
        // palabras pegadas en paralelo).
        $lados = $horizontal ? [[$f - 1, $c], [$f + 1, $c]] : [[$f, $c - 1], [$f, $c + 1]];
        foreach ($lados as [$lf, $lc]) {
            if ($lf >= 0 && $lc >= 0 && $lf < $lado && $lc < $lado && ($celdas[$lf][$lc] ?? '') !== '') {
                return false;
            }
        }
    }

    return true;
}

/**
 * Arma el crucigrama cruzando las palabras entre sí.
 *
 * @param array $entradas [['palabra' => 'OZONO', 'pista' => 'Capa que…'], …]
 * @return array{filas:int, columnas:int, celdas:array, horizontales:array, verticales:array, sin_lugar:array}
 */
function crucigrama_generar(array $entradas, string $semilla): array
{
    $limpias = [];
    foreach ($entradas as $entrada) {
        $palabra = rejilla_normalizar((string) ($entrada['palabra'] ?? ''));
        if (mb_strlen($palabra) >= 2) {
            $limpias[] = ['palabra' => $palabra, 'pista' => trim((string) ($entrada['pista'] ?? ''))];
        }
    }

    usort($limpias, static fn (array $a, array $b): int => mb_strlen($b['palabra']) <=> mb_strlen($a['palabra']));

    if (!$limpias) {
        return ['filas' => 0, 'columnas' => 0, 'celdas' => [], 'horizontales' => [], 'verticales' => [], 'sin_lugar' => []];
    }

    rejilla_semilla($semilla . '-cruci');

    $lado = 0;
    foreach ($limpias as $entrada) {
        $lado += mb_strlen($entrada['palabra']) + 1;
    }
    $lado = max($lado, 12);

    $celdas = array_fill(0, $lado, array_fill(0, $lado, ''));
    $colocadas = [];
    $sinLugar = [];

    // La primera, horizontal y en el centro.
    $primera = array_shift($limpias);
    $filaInicio = intdiv($lado, 2);
    $columnaInicio = max(0, intdiv($lado - mb_strlen($primera['palabra']), 2));
    for ($i = 0; $i < mb_strlen($primera['palabra']); $i++) {
        $celdas[$filaInicio][$columnaInicio + $i] = mb_substr($primera['palabra'], $i, 1);
    }
    $colocadas[] = $primera + ['fila' => $filaInicio, 'columna' => $columnaInicio, 'horizontal' => true];

    // Las demás, buscando cruces con lo ya puesto.
    foreach ($limpias as $entrada) {
        $palabra = $entrada['palabra'];
        $opciones = [];

        for ($i = 0; $i < mb_strlen($palabra); $i++) {
            $letra = mb_substr($palabra, $i, 1);

            foreach ($colocadas as $puesta) {
                $largoPuesta = mb_strlen($puesta['palabra']);
                for ($j = 0; $j < $largoPuesta; $j++) {
                    if (mb_substr($puesta['palabra'], $j, 1) !== $letra) {
                        continue;
                    }

                    // Se cruza en perpendicular a la palabra ya colocada.
                    $horizontal = !$puesta['horizontal'];
                    $fila = $puesta['horizontal'] ? $puesta['fila'] - $i : $puesta['fila'] + $j;
                    $columna = $puesta['horizontal'] ? $puesta['columna'] + $j : $puesta['columna'] - $i;

                    if (crucigrama_cabe($celdas, $palabra, $fila, $columna, $horizontal, $lado)) {
                        $opciones[] = ['fila' => $fila, 'columna' => $columna, 'horizontal' => $horizontal];
                    }
                }
            }
        }

        if (!$opciones) {
            $sinLugar[] = $palabra;
            continue;
        }

        $elegida = $opciones[mt_rand(0, count($opciones) - 1)];
        for ($i = 0; $i < mb_strlen($palabra); $i++) {
            $f = $elegida['horizontal'] ? $elegida['fila'] : $elegida['fila'] + $i;
            $c = $elegida['horizontal'] ? $elegida['columna'] + $i : $elegida['columna'];
            $celdas[$f][$c] = mb_substr($palabra, $i, 1);
        }
        $colocadas[] = $entrada + $elegida;
    }

    mt_srand();

    // Se recorta la cuadrícula a lo que de verdad se usó.
    $minFila = $lado;
    $maxFila = -1;
    $minColumna = $lado;
    $maxColumna = -1;
    for ($f = 0; $f < $lado; $f++) {
        for ($c = 0; $c < $lado; $c++) {
            if ($celdas[$f][$c] !== '') {
                $minFila = min($minFila, $f);
                $maxFila = max($maxFila, $f);
                $minColumna = min($minColumna, $c);
                $maxColumna = max($maxColumna, $c);
            }
        }
    }

    $filas = $maxFila - $minFila + 1;
    $columnas = $maxColumna - $minColumna + 1;

    $recorte = [];
    for ($f = 0; $f < $filas; $f++) {
        for ($c = 0; $c < $columnas; $c++) {
            $recorte[$f][$c] = $celdas[$minFila + $f][$minColumna + $c];
        }
    }

    foreach ($colocadas as &$puesta) {
        $puesta['fila'] -= $minFila;
        $puesta['columna'] -= $minColumna;
    }
    unset($puesta);

    // Numeración en orden de lectura, como en cualquier crucigrama.
    $numeros = [];
    $siguiente = 1;
    for ($f = 0; $f < $filas; $f++) {
        for ($c = 0; $c < $columnas; $c++) {
            if ($recorte[$f][$c] === '') {
                continue;
            }
            $empiezaH = ($c === 0 || $recorte[$f][$c - 1] === '') && ($c + 1 < $columnas && $recorte[$f][$c + 1] !== '');
            $empiezaV = ($f === 0 || $recorte[$f - 1][$c] === '') && ($f + 1 < $filas && $recorte[$f + 1][$c] !== '');
            if ($empiezaH || $empiezaV) {
                $numeros[$f . '-' . $c] = $siguiente++;
            }
        }
    }

    $horizontales = [];
    $verticales = [];
    foreach ($colocadas as $puesta) {
        $clave = $puesta['fila'] . '-' . $puesta['columna'];
        $ficha = [
            'numero'  => $numeros[$clave] ?? 0,
            'pista'   => $puesta['pista'],
            'palabra' => $puesta['palabra'],
            'fila'    => $puesta['fila'],
            'columna' => $puesta['columna'],
            'largo'   => mb_strlen($puesta['palabra']),
        ];
        if ($puesta['horizontal']) {
            $horizontales[] = $ficha;
        } else {
            $verticales[] = $ficha;
        }
    }

    $ordenar = static fn (array $a, array $b): int => $a['numero'] <=> $b['numero'];
    usort($horizontales, $ordenar);
    usort($verticales, $ordenar);

    // Celdas para dibujar: letra oculta, número visible si empieza palabra.
    $mapa = [];
    for ($f = 0; $f < $filas; $f++) {
        for ($c = 0; $c < $columnas; $c++) {
            $mapa[$f][$c] = $recorte[$f][$c] === ''
                ? null
                : ['numero' => $numeros[$f . '-' . $c] ?? null];
        }
    }

    return [
        'filas'        => $filas,
        'columnas'     => $columnas,
        'celdas'       => $mapa,
        'horizontales' => $horizontales,
        'verticales'   => $verticales,
        'sin_lugar'    => $sinLugar,
    ];
}
