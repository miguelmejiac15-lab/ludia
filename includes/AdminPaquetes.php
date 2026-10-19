<?php
/**
 * Panel → «Paquetes de usuarios» (19/10/2026).
 *
 * Para soporte: cuando un docente dice «hay algo raro en mi actividad», el
 * administrador busca su paquete, lo ve entero —preguntas, opciones y cuál es
 * la correcta— y, si hace falta probarlo, lo copia a su propia cuenta para
 * jugarlo sin tocar el original.
 *
 * Es de solo lectura sobre el paquete del usuario: aquí no se edita nada suyo.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Planes.php';
require_once __DIR__ . '/Paquetes.php';

const ADMIN_PAQUETES_POR_PAGINA = 40;

/**
 * Lista filtrada y paginada.
 *
 * @param array{q?:string, usuario?:int, formato?:string, audiencia?:string, orden?:string} $f
 * @return array{filas:list<array>, total:int, paginas:int}
 */
function admin_paquetes(array $f, int $pagina = 1): array
{
    $donde = [];
    $datos = [];

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $donde[] = '(p.nombre LIKE ? OR u.email LIKE ? OR u.nombre LIKE ?)';
        $like = '%' . $q . '%';
        array_push($datos, $like, $like, $like);
    }
    if (!empty($f['usuario'])) {
        $donde[] = 'p.usuario_id = ?';
        $datos[] = (int) $f['usuario'];
    }
    // El tipo va escrito tal cual dentro del JSON del contenido.
    $formato = preg_replace('/[^a-z_]/', '', (string) ($f['formato'] ?? ''));
    if ($formato !== '') {
        $donde[] = 'p.contenido LIKE ?';
        $datos[] = '%"tipo":"' . $formato . '"%';
    }
    if (in_array($f['audiencia'] ?? '', ['estudiantes', 'auditorio', 'capacitacion'], true)) {
        $donde[] = 'p.audiencia = ?';
        $datos[] = $f['audiencia'];
    }

    $orden = match ($f['orden'] ?? '') {
        'creados'     => 'p.created_at DESC',
        'actividades' => 'p.total_actividades DESC, p.updated_at DESC',
        'jugados'     => 'jugadas DESC, p.updated_at DESC',
        'nombre'      => 'p.nombre ASC',
        default       => 'p.updated_at DESC',
    };

    $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

    $cuenta = db()->prepare("SELECT COUNT(*) FROM paquetes p JOIN usuarios u ON u.id = p.usuario_id $where");
    $cuenta->execute($datos);
    $total = (int) $cuenta->fetchColumn();

    $paginas = max(1, (int) ceil($total / ADMIN_PAQUETES_POR_PAGINA));
    $pagina = max(1, min($pagina, $paginas));
    $desde = ($pagina - 1) * ADMIN_PAQUETES_POR_PAGINA;

    $consulta = db()->prepare(
        "SELECT p.id, p.nombre, p.audiencia, p.total_actividades, p.contenido, p.created_at, p.updated_at,
                u.id AS usuario_id, u.nombre AS usuario_nombre, u.email AS usuario_email, u.plan AS usuario_plan,
                (SELECT COUNT(*) FROM sesiones s WHERE s.paquete_id = p.id) AS jugadas
           FROM paquetes p
           JOIN usuarios u ON u.id = p.usuario_id
           $where
          ORDER BY $orden
          LIMIT " . ADMIN_PAQUETES_POR_PAGINA . " OFFSET $desde"
    );
    $consulta->execute($datos);

    $filas = [];
    foreach ($consulta->fetchAll() as $fila) {
        // Para la tabla basta con qué formatos usa; el contenido entero es pesado.
        $actividades = json_decode((string) $fila['contenido'], true) ?: [];
        $fila['formatos'] = array_values(array_unique(array_map(static fn ($a) => (string) ($a['tipo'] ?? ''), $actividades)));
        unset($fila['contenido']);
        $filas[] = $fila;
    }

    return ['filas' => $filas, 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
}

/** Las actividades del catálogo, para el filtro. @return list<array{clave:string, nombre:string}> */
function admin_formatos_lista(): array
{
    return db()->query('SELECT clave, nombre FROM formatos ORDER BY nombre')->fetchAll();
}

/** Un paquete completo con los datos de su dueño, o null. */
function admin_paquete_ver(int $id): ?array
{
    $consulta = db()->prepare(
        'SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email, u.plan AS usuario_plan,
                (SELECT COUNT(*) FROM sesiones s WHERE s.paquete_id = p.id) AS jugadas
           FROM paquetes p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?'
    );
    $consulta->execute([$id]);
    $fila = $consulta->fetch();
    if (!$fila) {
        return null;
    }
    $fila['actividades'] = json_decode((string) $fila['contenido'], true) ?: [];
    return $fila;
}

/**
 * Copia el paquete de un usuario a la cuenta del administrador, para probarlo
 * tal cual sin tocar el original. No mira el plan: es una herramienta de soporte.
 */
function admin_paquete_copiar(int $id, int $adminId): array
{
    $p = admin_paquete_ver($id);
    if (!$p) {
        return ['ok' => false, 'mensaje' => 'Ese paquete ya no existe.'];
    }
    $nombre = mb_substr('Copia de ' . $p['usuario_email'] . ' · ' . $p['nombre'], 0, 120);
    paquete_crear($adminId, $nombre, (string) $p['audiencia'], $p['actividades']);
    return ['ok' => true, 'mensaje' => 'Copiado a tus paquetes como «' . $nombre . '». Ábrelo desde «Mis paquetes» para jugarlo o revisarlo en el editor.'];
}

/* ---------- Mostrar un ítem de forma legible ---------- */

/** Texto de un lado de una pareja: puede venir como texto o como {texto, imagen}. */
function admin_texto_lado($lado): string
{
    if (is_array($lado)) {
        return trim((string) ($lado['texto'] ?? '')) ?: (empty($lado['imagen']) ? '' : '[imagen]');
    }
    return (string) $lado;
}

/**
 * Un ítem como HTML de solo lectura. Es genérico a propósito: recorre los
 * campos conocidos en vez de tener una vista por formato, así un formato nuevo
 * se ve aquí sin tocar este archivo (en el peor caso, con su JSON al final).
 */
function admin_item_html(string $tipo, array $it): string
{
    $h = [];
    $fila = static function (string $etiqueta, string $valor) use (&$h): void {
        if (trim($valor) !== '') {
            $h[] = '<div class="item-fila"><span>' . e($etiqueta) . '</span><div>' . $valor . '</div></div>';
        }
    };

    $textos = [
        'titulo' => 'Título', 'enunciado' => 'Pregunta', 'pregunta' => 'Pregunta', 'afirmacion' => 'Afirmación',
        'consigna' => 'Consigna', 'instruccion' => 'Instrucción', 'pista' => 'Pista', 'palabra' => 'Palabra',
        'frase' => 'Frase', 'texto' => 'Texto',
    ];
    foreach ($textos as $campo => $etiqueta) {
        if (isset($it[$campo]) && is_scalar($it[$campo])) {
            $fila($etiqueta, e((string) $it[$campo]));
        }
    }

    if (isset($it['segundo'])) {
        $s = (int) $it['segundo'];
        $fila('Minuto del vídeo', e(intdiv($s, 60) . ':' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT)));
    }
    if (isset($it['respuesta']) && is_scalar($it['respuesta'])) {
        $fila('Respuesta', '<b class="ok">' . e((string) $it['respuesta']) . '</b>' .
            (!empty($it['tolerancia']) ? ' (± ' . e((string) $it['tolerancia']) . ')' : '') .
            (!empty($it['unidad']) ? ' ' . e((string) $it['unidad']) : ''));
    }

    // Opciones, marcando la(s) correcta(s)
    if (!empty($it['opciones']) && is_array($it['opciones'])) {
        $correctas = isset($it['correctas']) && is_array($it['correctas'])
            ? array_map('intval', $it['correctas'])
            : (isset($it['correcta']) && is_int($it['correcta']) ? [$it['correcta']] : []);
        $li = '';
        foreach ($it['opciones'] as $i => $op) {
            if (trim((string) $op) === '') {
                continue;
            }
            $buena = in_array($i, $correctas, true);
            $li .= '<li' . ($buena ? ' class="ok"' : '') . '>' . chr(65 + $i) . '. ' . e((string) $op) . ($buena ? ' ✓' : '') . '</li>';
        }
        $fila('Opciones', '<ul class="item-lista">' . $li . '</ul>');
    } elseif ($tipo === 'verdadero_falso' && isset($it['correcta'])) {
        $fila('Correcta', '<b class="ok">' . ($it['correcta'] ? 'Verdadero' : 'Falso') . '</b>');
    }

    $lista = static function (array $valores): string {
        return '<ul class="item-lista">' . implode('', array_map(static fn ($v) => '<li>' . e((string) $v) . '</li>',
            array_filter($valores, static fn ($v) => is_scalar($v) && trim((string) $v) !== ''))) . '</ul>';
    };

    foreach (['elementos' => 'Elementos (en orden)', 'palabras' => 'Palabras', 'parrafos' => 'Párrafos', 'ejemplos' => 'Ejemplos'] as $campo => $etiqueta) {
        if (!empty($it[$campo]) && is_array($it[$campo])) {
            $fila($etiqueta, $lista($it[$campo]));
        }
    }

    if (!empty($it['pares']) && is_array($it['pares'])) {
        $li = '';
        foreach ($it['pares'] as $par) {
            if (!is_array($par)) {
                continue;
            }
            if (array_key_exists('correcta', $par)) {
                $li .= '<li><b class="ok">' . e((string) $par['correcta']) . '</b> · <s>' . e((string) ($par['incorrecta'] ?? '')) . '</s></li>';
            } else {
                $li .= '<li>' . e(admin_texto_lado($par['izquierda'] ?? '')) . ' ↔ ' . e(admin_texto_lado($par['derecha'] ?? '')) . '</li>';
            }
        }
        $fila('Parejas', '<ul class="item-lista">' . $li . '</ul>');
    }
    if (!empty($it['categorias']) && is_array($it['categorias'])) {
        $li = '';
        foreach ($it['categorias'] as $c) {
            if (is_array($c)) {
                $li .= '<li><b>' . e((string) ($c['nombre'] ?? '')) . ':</b> ' . e(implode(', ', array_filter((array) ($c['elementos'] ?? []), 'is_scalar'))) . '</li>';
            }
        }
        $fila('Categorías', '<ul class="item-lista">' . $li . '</ul>');
    }
    if (!empty($it['hechos']) && is_array($it['hechos'])) {
        $li = '';
        foreach ($it['hechos'] as $hh) {
            if (is_array($hh)) {
                $li .= '<li><b>' . e((string) ($hh['fecha'] ?? '')) . '</b> — ' . e((string) ($hh['texto'] ?? '')) . '</li>';
            }
        }
        $fila('Hechos', '<ul class="item-lista">' . $li . '</ul>');
    }
    if (!empty($it['entradas']) && is_array($it['entradas'])) {
        $li = '';
        foreach ($it['entradas'] as $en) {
            if (is_array($en)) {
                $li .= '<li><b>' . e((string) ($en['palabra'] ?? '')) . '</b> — ' . e((string) ($en['pista'] ?? '')) . '</li>';
            }
        }
        $fila('Palabras y pistas', '<ul class="item-lista">' . $li . '</ul>');
    }
    if (!empty($it['tarjetas']) && is_array($it['tarjetas'])) {
        $li = '';
        foreach ($it['tarjetas'] as $t) {
            if (is_array($t)) {
                $li .= '<li><b>' . e((string) ($t['titulo'] ?? '')) . ':</b> ' . e((string) ($t['texto'] ?? '')) . '</li>';
            }
        }
        $fila('Tarjetas', '<ul class="item-lista">' . $li . '</ul>');
    }
    if (isset($it['etiquetaMin']) || isset($it['etiquetaMax'])) {
        $fila('Escala', e((string) ($it['etiquetaMin'] ?? '')) . ' → ' . e((string) ($it['etiquetaMax'] ?? '')));
    }

    $imagen = !empty($it['imagen']) && is_string($it['imagen'])
        ? '<img class="item-imagen" src="' . e($it['imagen']) . '" alt="" loading="lazy">'
        : '';

    if (!$h) {
        // Formato desconocido o vacío: se muestra crudo antes que esconderlo.
        $h[] = '<pre class="item-crudo">' . e((string) json_encode($it, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
    }

    return $imagen . implode('', $h);
}
