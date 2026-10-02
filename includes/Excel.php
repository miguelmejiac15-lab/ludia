<?php
/**
 * Lectura y escritura de archivos .xlsx sin librerías externas.
 *
 * Un .xlsx es un ZIP con XML dentro, así que basta ZipArchive (ya viene con
 * PHP) y un poco de XML. Se escribe con textos "en línea" (inlineStr) para no
 * tener que mantener la tabla de cadenas compartidas; al leer sí se entiende
 * esa tabla, porque Excel la usa cuando el usuario guarda el archivo.
 */

declare(strict_types=1);

const EXCEL_MAX_FILAS    = 2000;
const EXCEL_MAX_COLUMNAS = 40;

/* ---------- Escritura ---------- */

function excel_xml(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/** Número de columna (0) a letra de Excel (A, B… AA). */
function excel_columna(int $indice): string
{
    $letra = '';
    $indice++;
    while ($indice > 0) {
        $resto = ($indice - 1) % 26;
        $letra = chr(65 + $resto) . $letra;
        $indice = intdiv($indice - 1 - $resto, 26);
    }
    return $letra;
}

function excel_celda($valor, string $ref, bool $encabezado): string
{
    $estilo = $encabezado ? ' s="1"' : '';

    if (is_int($valor) || is_float($valor)) {
        return '<c r="' . $ref . '"' . $estilo . '><v>' . $valor . '</v></c>';
    }
    $texto = trim((string) $valor);
    if ($texto === '') {
        return '<c r="' . $ref . '"' . $estilo . '/>';
    }
    return '<c r="' . $ref . '"' . $estilo . ' t="inlineStr"><is><t xml:space="preserve">' . excel_xml($texto) . '</t></is></c>';
}

function excel_hoja_xml(array $filas, array $anchos): string
{
    $cols = '';
    foreach ($anchos as $i => $ancho) {
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $ancho . '" customWidth="1"/>';
    }

    $cuerpo = '';
    foreach (array_values($filas) as $numero => $fila) {
        $celdas = '';
        foreach (array_values($fila) as $columna => $valor) {
            $celdas .= excel_celda($valor, excel_columna($columna) . ($numero + 1), $numero === 0);
        }
        $cuerpo .= '<row r="' . ($numero + 1) . '">' . $celdas . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . ($cols ? '<cols>' . $cols . '</cols>' : '')
        . '<sheetData>' . $cuerpo . '</sheetData></worksheet>';
}

/**
 * Crea un .xlsx.
 *
 * @param string $ruta  dónde guardarlo
 * @param array  $hojas ['Nombre de hoja' => ['filas' => [[...], [...]], 'anchos' => [40, 18]]]
 *                      La primera fila de cada hoja se escribe en negrita.
 */
function excel_escribir(string $ruta, array $hojas): void
{
    $zip = new ZipArchive();
    if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el archivo de Excel');
    }

    $nombres = array_keys($hojas);
    $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

    $hojasXml = '';
    $relaciones = '';
    foreach ($nombres as $i => $nombre) {
        $n = $i + 1;
        $tipos .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $hojasXml .= '<sheet name="' . excel_xml(mb_substr($nombre, 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $relaciones .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
    }
    $tipos .= '</Types>';

    $idEstilos = count($nombres) + 1;
    $relaciones .= '<Relationship Id="rId' . $idEstilos . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml', $tipos);
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $hojasXml . '</sheets></workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $relaciones . '</Relationships>');

    // Dos fuentes: normal y negrita (la negrita es el estilo 1, para encabezados).
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs></styleSheet>');

    foreach (array_values($hojas) as $i => $hoja) {
        $zip->addFromString(
            'xl/worksheets/sheet' . ($i + 1) . '.xml',
            excel_hoja_xml($hoja['filas'] ?? [], $hoja['anchos'] ?? [])
        );
    }

    $zip->close();
}

/* ---------- Lectura ---------- */

/** Letra de columna de Excel a índice (A → 0). */
function excel_indice(string $referencia): int
{
    preg_match('/^([A-Z]+)/', strtoupper($referencia), $m);
    $letras = $m[1] ?? 'A';
    $indice = 0;
    for ($i = 0, $n = strlen($letras); $i < $n; $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - 64);
    }
    return $indice - 1;
}

/**
 * Lee un .xlsx y devuelve ['Nombre de hoja' => [[celda, celda], ...]].
 * Entiende tanto los archivos que genera Ludia como los que guarda Excel.
 */
function excel_leer(string $ruta): array
{
    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        throw new RuntimeException('El archivo no se pudo abrir: ¿seguro que es un Excel (.xlsx)?');
    }

    $cargar = static function (ZipArchive $zip, string $nombre): ?SimpleXMLElement {
        $crudo = $zip->getFromName($nombre);
        if ($crudo === false) {
            return null;
        }
        $previo = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($crudo);
        libxml_use_internal_errors($previo);
        return $xml ?: null;
    };

    $libro = $cargar($zip, 'xl/workbook.xml');
    if (!$libro) {
        $zip->close();
        throw new RuntimeException('El archivo no parece un Excel válido');
    }

    // Cadenas compartidas (las usa Excel al guardar).
    $compartidas = [];
    $cadenas = $cargar($zip, 'xl/sharedStrings.xml');
    if ($cadenas) {
        foreach ($cadenas->si as $si) {
            $texto = '';
            if (isset($si->t)) {
                $texto = (string) $si->t;
            } else {
                foreach ($si->r as $trozo) {   // texto con formato mezclado
                    $texto .= (string) $trozo->t;
                }
            }
            $compartidas[] = $texto;
        }
    }

    // Relación rId → archivo de hoja.
    $destinos = [];
    $rels = $cargar($zip, 'xl/_rels/workbook.xml.rels');
    if ($rels) {
        foreach ($rels->Relationship as $rel) {
            $destinos[(string) $rel['Id']] = ltrim((string) $rel['Target'], '/');
        }
    }

    $hojas = [];
    $numero = 0;
    foreach ($libro->sheets->sheet as $hoja) {
        $numero++;
        $nombre = (string) $hoja['name'];
        $rid = (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $archivo = $destinos[$rid] ?? ('worksheets/sheet' . $numero . '.xml');
        $rutaHoja = str_starts_with($archivo, 'xl/') ? $archivo : 'xl/' . $archivo;

        $datos = $cargar($zip, $rutaHoja);
        if (!$datos) {
            $hojas[$nombre] = [];
            continue;
        }

        $filas = [];
        $cuenta = 0;
        foreach ($datos->sheetData->row as $fila) {
            if (++$cuenta > EXCEL_MAX_FILAS) {
                break;
            }
            $valores = [];
            foreach ($fila->c as $celda) {
                $columna = excel_indice((string) $celda['r']);
                if ($columna >= EXCEL_MAX_COLUMNAS) {
                    continue;
                }
                $tipo = (string) $celda['t'];

                if ($tipo === 's') {
                    $valor = $compartidas[(int) $celda->v] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = isset($celda->is->t) ? (string) $celda->is->t : '';
                } else {
                    $valor = isset($celda->v) ? (string) $celda->v : '';
                }

                $valores[$columna] = trim($valor);
            }

            if ($valores) {
                $ancho = max(array_keys($valores)) + 1;
                $completa = [];
                for ($i = 0; $i < $ancho; $i++) {
                    $completa[] = $valores[$i] ?? '';
                }
                $filas[] = $completa;
            } else {
                $filas[] = [];
            }
        }

        $hojas[$nombre] = $filas;
    }

    $zip->close();
    return $hojas;
}
