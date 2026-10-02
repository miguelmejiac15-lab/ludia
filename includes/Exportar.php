<?php
/**
 * Exportar un paquete (plan Pro): HTML autónomo y paquete SCORM 1.2.
 *
 * Dos usos distintos:
 *  · HTML  → un solo archivo que se abre con doble clic y funciona SIN internet
 *            ni servidor. Para llevarlo en una memoria o subirlo a cualquier web.
 *  · SCORM → un ZIP con imsmanifest.xml que Moodle (o cualquier LMS) reconoce,
 *            y que le reporta la nota con la API estándar de SCORM.
 *
 * Aviso deliberado sobre las respuestas: aquí viajan al archivo, porque sin
 * servidor no hay quien corrija. Es un export para repartir contenido, no una
 * evaluación a prueba de trampas; lo que se juega en Ludia se sigue
 * calificando en el servidor (ver includes/Juego.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/Sesiones.php';

/** Formatos que el export sabe jugar por su cuenta. */
const EXPORTAR_TIPOS = [
    'quiz', 'verdadero_falso', 'respuesta_multiple', 'completar',
    'palabra_secreta', 'numerica', 'anagrama', 'ortografia',
    'relacionar', 'ordenar', 'panel', 'pagina',
];

/**
 * Deja el contenido listo para el archivo exportado: se queda con los formatos
 * que el reproductor sabe pintar y les da una forma simple y estable.
 */
function exportar_contenido(array $contenido): array
{
    $fuera = [];

    foreach ($contenido as $actividad) {
        $tipo = (string) ($actividad['tipo'] ?? '');
        if (!in_array($tipo, EXPORTAR_TIPOS, true)) {
            continue;
        }

        $items = [];
        foreach ($actividad['items'] ?? [] as $item) {
            $limpio = exportar_item($tipo, (array) $item);
            if ($limpio !== null) {
                $items[] = $limpio;
            }
        }

        if ($items) {
            $fuera[] = [
                'tipo'   => $tipo,
                'titulo' => (string) ($actividad['titulo'] ?? ''),
                'items'  => $items,
            ];
        }
    }

    return $fuera;
}

/** Un ítem con lo justo para jugarlo fuera de Ludia. */
function exportar_item(string $tipo, array $item): ?array
{
    switch ($tipo) {
        case 'quiz':
            return [
                'enunciado' => (string) ($item['enunciado'] ?? ''),
                'opciones'  => array_values($item['opciones'] ?? []),
                'correcta'  => (int) ($item['correcta'] ?? 0),
                'imagen'    => $item['imagen'] ?? null,
            ];

        case 'respuesta_multiple':
            return [
                'enunciado' => (string) ($item['enunciado'] ?? ''),
                'opciones'  => array_values($item['opciones'] ?? []),
                'correctas' => array_map('intval', $item['correctas'] ?? []),
                'imagen'    => $item['imagen'] ?? null,
            ];

        case 'verdadero_falso':
            return [
                'afirmacion' => (string) ($item['afirmacion'] ?? ''),
                'correcta'   => (bool) ($item['correcta'] ?? false),
                'imagen'     => $item['imagen'] ?? null,
            ];

        case 'completar':
            return ['texto' => (string) ($item['texto'] ?? '')];

        case 'palabra_secreta':
        case 'anagrama':
            return [
                'palabra' => (string) ($item['palabra'] ?? ''),
                'pista'   => (string) ($item['pista'] ?? ''),
            ];

        case 'numerica':
            return [
                'enunciado'  => (string) ($item['enunciado'] ?? ''),
                'respuesta'  => (string) ($item['respuesta'] ?? ''),
                'tolerancia' => (string) ($item['tolerancia'] ?? '0'),
                'unidad'     => (string) ($item['unidad'] ?? ''),
            ];

        case 'ortografia':
            return [
                'instruccion' => (string) ($item['instruccion'] ?? ''),
                'pares'       => array_values($item['pares'] ?? []),
            ];

        case 'relacionar':
            return [
                'instruccion' => (string) ($item['instruccion'] ?? ''),
                'pares'       => array_values($item['pares'] ?? []),
            ];

        case 'ordenar':
            return [
                'instruccion' => (string) ($item['instruccion'] ?? ''),
                'elementos'   => array_values(array_filter(
                    $item['elementos'] ?? [],
                    static fn ($e): bool => trim((string) $e) !== ''
                )),
            ];

        case 'panel':
            return [
                'instruccion' => (string) ($item['instruccion'] ?? ''),
                'tarjetas'    => array_values($item['tarjetas'] ?? []),
            ];

        case 'pagina':
            return [
                'titulo'   => (string) ($item['titulo'] ?? ''),
                'parrafos' => array_values($item['parrafos'] ?? []),
                'imagen'   => $item['imagen'] ?? null,
            ];

        default:
            return null;
    }
}

/**
 * Cuántas actividades quedaron fuera del export, para avisarlo con honestidad
 * en vez de que el profesor lo descubra al abrirlo.
 */
function exportar_descartadas(array $contenido): array
{
    $fuera = [];
    foreach ($contenido as $actividad) {
        $tipo = (string) ($actividad['tipo'] ?? '');
        if ($tipo !== '' && !in_array($tipo, EXPORTAR_TIPOS, true) && !in_array($tipo, $fuera, true)) {
            $fuera[] = $tipo;
        }
    }
    return $fuera;
}

/**
 * El HTML autónomo: un solo archivo, sin dependencias externas.
 *
 * @param bool $scorm si es true, habla con el LMS por la API de SCORM 1.2.
 */
function exportar_html(string $tema, array $contenido, bool $scorm = false): string
{
    $datos = json_encode(
        ['tema' => $tema, 'actividades' => $contenido],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    $tituloSeguro = htmlspecialchars($tema, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $puenteScorm = $scorm ? exportar_js_scorm() : '// Sin LMS: el resultado se muestra en pantalla y ya.
    var SCORM = { iniciar: function () {}, guardar: function () {}, terminar: function () {} };';

    return <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$tituloSeguro}</title>
<style>
  :root {
    --coral: #FF6A4D; --violet: #5B3DF5; --violet-soft: #ECE8FF;
    --mint: #1FA98A; --mint-soft: #E3F6F1; --sun: #FFC24B;
    --bg: #FBF6EE; --surface: #fff; --border: #E7DFD3;
    --text: #2A2140; --text-soft: #6F6785;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 20px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--bg); color: var(--text); line-height: 1.5;
  }
  .caja { max-width: 640px; margin: 0 auto; }
  .cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; font-size: .85rem; }
  .marca { font-weight: 800; color: var(--violet); }
  .puntos { font-weight: 800; color: var(--mint); }
  .barra { height: 8px; background: #EDE7DC; border-radius: 99px; overflow: hidden; margin-bottom: 18px; }
  .barra span { display: block; height: 100%; background: linear-gradient(90deg, var(--mint), var(--violet)); width: 0; transition: width .3s; }
  .tarjeta { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 22px; }
  h1 { font-size: 1.3rem; margin: 0 0 4px; }
  h2 { font-size: 1.15rem; margin: 0 0 14px; }
  .paso { font-size: .7rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--violet); margin-bottom: 6px; }
  .opciones { display: grid; gap: 10px; margin: 14px 0; }
  .opcion {
    text-align: left; padding: 13px 16px; border: 2px solid var(--border); border-radius: 12px;
    background: var(--surface); font: inherit; font-weight: 600; cursor: pointer;
  }
  .opcion:hover { border-color: var(--violet); }
  .opcion.elegida { border-color: var(--violet); background: var(--violet-soft); }
  .opcion.bien { border-color: var(--mint); background: var(--mint-soft); color: var(--mint); }
  .opcion.mal { border-color: var(--coral); background: #FFEDE8; }
  .btn {
    display: inline-block; padding: 12px 22px; border: 0; border-radius: 99px;
    background: var(--coral); color: #fff; font: inherit; font-weight: 700; cursor: pointer;
  }
  .btn:disabled { opacity: .5; cursor: default; }
  .btn.ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
  .pie { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
  input[type=text] { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; font: inherit; }
  .hueco { display: inline-block; width: 110px; margin: 0 4px; padding: 2px 6px; border: 0; border-bottom: 2px solid var(--violet); background: transparent; font: inherit; }
  .par { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 10px; }
  select { padding: 10px; border: 1px solid var(--border); border-radius: 10px; font: inherit; width: 100%; }
  .lista { display: grid; gap: 8px; }
  .lista div { background: var(--bg); border: 1px solid var(--border); border-left: 3px solid var(--violet); border-radius: 8px; padding: 10px 12px; }
  .lista b { display: block; }
  .orden li { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px; margin-bottom: 6px; display: flex; justify-content: space-between; gap: 10px; }
  .aviso { text-align: center; padding: 26px 10px; }
  .aviso b { display: block; font-size: 1.5rem; margin-bottom: 6px; }
  .final { text-align: center; padding: 30px 12px; }
  .nota { font-size: 2.6rem; font-weight: 800; color: var(--mint); }
  @media (prefers-reduced-motion: reduce) { .barra span { transition: none; } }
</style>
</head>
<body>
<div class="caja">
  <div class="cab">
    <span class="marca">Ludia</span>
    <span class="puntos" id="puntos">0 / 0</span>
  </div>
  <div class="barra"><span id="progreso"></span></div>
  <div class="tarjeta" id="pantalla"></div>
</div>

<script>
(function () {
  'use strict';
  var PAQUETE = {$datos};
  {$puenteScorm}

  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function barajar(l) {
    var c = l.slice();
    for (var i = c.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = c[i]; c[i] = c[j]; c[j] = t; }
    return c;
  }
  function normalizar(t) {
    return String(t || '').trim().toLowerCase()
      .normalize('NFD').replace(/[\\u0300-\\u036f]/g, '')
      .replace(/[^a-z0-9\\s]/g, '').replace(/\\s+/g, ' ');
  }

  // Cada ítem del paquete, en una sola lista lineal.
  var pantallas = [];
  PAQUETE.actividades.forEach(function (a) {
    a.items.forEach(function (item) { pantallas.push({ tipo: a.tipo, titulo: a.titulo, item: item }); });
  });

  // Las pantallas de solo lectura no se responden: ni puntúan ni se corrigen.
  var SOLO_LEE = ['panel', 'pagina'];

  var i = 0, aciertos = 0, puntuables = 0, respondida = false;
  var elegido = null, multi = [], orto = {};
  pantallas.forEach(function (p) { if (SOLO_LEE.indexOf(p.tipo) === -1) puntuables++; });

  var pantalla = document.getElementById('pantalla');
  var elPuntos = document.getElementById('puntos');
  var elProgreso = document.getElementById('progreso');

  SCORM.iniciar();

  function marcador() {
    elPuntos.textContent = aciertos + ' / ' + puntuables;
    elProgreso.style.width = Math.round((i / pantallas.length) * 100) + '%';
  }

  var vistas = {
    pagina: function (it) {
      return (it.imagen ? '<img src="' + esc(it.imagen) + '" alt="" style="max-width:100%;border-radius:12px;margin-bottom:12px">' : '') +
        '<h2>' + esc(it.titulo) + '</h2>' +
        (it.parrafos || []).map(function (p) { return '<p>' + esc(p) + '</p>'; }).join('');
    },
    panel: function (it) {
      return '<h2>' + esc(it.instruccion) + '</h2><div class="lista">' +
        (it.tarjetas || []).map(function (t) { return '<div><b>' + esc(t.titulo) + '</b>' + esc(t.texto) + '</div>'; }).join('') + '</div>';
    },
    quiz: function (it) {
      return (it.imagen ? '<img src="' + esc(it.imagen) + '" alt="" style="max-width:100%;border-radius:12px;margin-bottom:12px">' : '') +
        '<h2>' + esc(it.enunciado) + '</h2><div class="opciones">' +
        it.opciones.map(function (o, n) { return '<button class="opcion" data-op="' + n + '">' + esc(o) + '</button>'; }).join('') + '</div>';
    },
    respuesta_multiple: function (it) {
      return '<h2>' + esc(it.enunciado) + '</h2><p style="color:var(--text-soft);font-size:.85rem">Marca todas las correctas</p>' +
        '<div class="opciones">' + it.opciones.map(function (o, n) {
          return '<button class="opcion" data-multi="' + n + '">' + esc(o) + '</button>';
        }).join('') + '</div>';
    },
    verdadero_falso: function (it) {
      return '<h2>' + esc(it.afirmacion) + '</h2><div class="opciones">' +
        '<button class="opcion" data-vf="1">Verdadero</button>' +
        '<button class="opcion" data-vf="0">Falso</button></div>';
    },
    completar: function (it) {
      var partes = it.texto.split(/\\[[^\\]]*\\]/);
      var huecos = (it.texto.match(/\\[[^\\]]*\\]/g) || []).length;
      var html = '<h2>Completa la frase</h2><p>';
      partes.forEach(function (p, n) {
        html += esc(p);
        if (n < huecos) html += '<input class="hueco" data-hueco="' + n + '">';
      });
      return html + '</p>';
    },
    palabra_secreta: function (it) {
      return '<h2>' + esc(it.pista) + '</h2><p style="color:var(--text-soft)">' + it.palabra.length + ' letras</p>' +
        '<input type="text" data-texto placeholder="Escribe la palabra">';
    },
    anagrama: function (it) {
      return '<h2>' + esc(it.pista || 'Arma la palabra') + '</h2>' +
        '<p style="font-size:1.4rem;letter-spacing:.3em;font-weight:700">' +
        esc(barajar(it.palabra.toUpperCase().split('')).join('')) + '</p>' +
        '<input type="text" data-texto placeholder="Escribe la palabra">';
    },
    numerica: function (it) {
      return '<h2>' + esc(it.enunciado) + '</h2><input type="text" data-texto placeholder="Escribe el número">' +
        (it.unidad ? '<p style="color:var(--text-soft)">' + esc(it.unidad) + '</p>' : '');
    },
    ortografia: function (it) {
      return '<h2>' + esc(it.instruccion || 'Elige la forma correcta') + '</h2>' +
        (it.pares || []).map(function (p, n) {
          var op = barajar([p.correcta, p.incorrecta]);
          return '<div class="opciones"><button class="opcion" data-orto="' + n + '" data-valor="' + esc(op[0]) + '">' + esc(op[0]) + '</button>' +
            '<button class="opcion" data-orto="' + n + '" data-valor="' + esc(op[1]) + '">' + esc(op[1]) + '</button></div>';
        }).join('');
    },
    relacionar: function (it) {
      var derechas = barajar((it.pares || []).map(function (p) { return p.derecha; }));
      // OJO: las imagenes se enlazan al servidor, asi que en un equipo SIN
      // internet no se veran. Es la misma limitacion que ya tenian las
      // imagenes de pregunta en el archivo exportado.
      return '<h2>' + esc(it.instruccion || 'Relaciona') + '</h2>' +
        (it.pares || []).map(function (p, n) {
          var izq = (p.imagen_izq
            ? '<img src="' + esc(p.imagen_izq) + '" alt="' + esc(p.izquierda) + '" style="max-height:70px;border-radius:8px;vertical-align:middle;margin-right:8px">'
            : '') + esc(p.izquierda);
          return '<div class="par"><span>' + izq + '</span><select data-par="' + n + '">' +
            '<option value="">Elige…</option>' +
            derechas.map(function (d) { return '<option>' + esc(d) + '</option>'; }).join('') + '</select></div>';
        }).join('');
    },
    ordenar: function (it) {
      var mezcla = barajar(it.elementos);
      return '<h2>' + esc(it.instruccion || 'Ordena') + '</h2><ol class="orden" id="orden">' +
        mezcla.map(function (e, n) {
          return '<li data-elem="' + esc(e) + '">' + esc(e) +
            '<span><button class="btn ghost" data-sube="' + n + '">↑</button> ' +
            '<button class="btn ghost" data-baja="' + n + '">↓</button></span></li>';
        }).join('') + '</ol>';
    }
  };

  function pintar() {
    if (i >= pantallas.length) return terminar();

    var p = pantallas[i];
    var soloLee = SOLO_LEE.indexOf(p.tipo) > -1;
    respondida = false;
    elegido = null;
    multi = [];
    orto = {};

    pantalla.innerHTML =
      '<p class="paso">' + esc(p.titulo || '') + ' · ' + (i + 1) + ' de ' + pantallas.length + '</p>' +
      (vistas[p.tipo] ? vistas[p.tipo](p.item) : '<p>Actividad no disponible</p>') +
      '<div class="pie">' +
      (soloLee ? '<button class="btn" id="seguir">Continuar →</button>'
               : '<button class="btn" id="comprobar">Comprobar</button>') +
      '</div>';

    marcador();
  }

  // UN SOLO manejador para toda la partida, puesto una vez. Antes cada pantalla
  // instalaba el suyo y no retiraba el anterior: el mismo clic se atendía dos
  // veces y el paquete se saltaba pantallas (iba 1, 3, 5…).
  pantalla.onclick = function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('button') : null;
    if (!b) return;

    // Avanzar se atiende SIEMPRE, y lo primero: el botón «Siguiente» nace justo
    // después de corregir —o sea con `respondida` ya en true— y el guardia de
    // la línea siguiente lo dejaba muerto, encerrando al estudiante.
    if (b.id === 'seguir') { i++; pintar(); return; }
    if (respondida) return;

    var p = pantallas[i];
    if (!p) return;

    if (b.hasAttribute('data-op') || b.hasAttribute('data-vf')) {
      elegido = b.hasAttribute('data-op') ? Number(b.getAttribute('data-op')) : Number(b.getAttribute('data-vf'));
      [].forEach.call(pantalla.querySelectorAll('.opcion'), function (o) { o.classList.toggle('elegida', o === b); });
    } else if (b.hasAttribute('data-multi')) {
      var n = Number(b.getAttribute('data-multi'));
      var pos = multi.indexOf(n);
      if (pos > -1) multi.splice(pos, 1); else multi.push(n);
      b.classList.toggle('elegida', pos === -1);
    } else if (b.hasAttribute('data-orto')) {
      orto[b.getAttribute('data-orto')] = b.getAttribute('data-valor');
      [].forEach.call(pantalla.querySelectorAll('[data-orto="' + b.getAttribute('data-orto') + '"]'), function (o) {
        o.classList.toggle('elegida', o === b);
      });
    } else if (b.hasAttribute('data-sube') || b.hasAttribute('data-baja')) {
      var lista = document.getElementById('orden');
      var li = b.closest('li');
      if (b.hasAttribute('data-sube') && li.previousElementSibling) lista.insertBefore(li, li.previousElementSibling);
      if (b.hasAttribute('data-baja') && li.nextElementSibling) lista.insertBefore(li.nextElementSibling, li);
    } else if (b.id === 'comprobar') {
      comprobar(p, { elegido: elegido, multi: multi, orto: orto });
    }
  };

  function comprobar(p, sel) {
    respondida = true;
    var it = p.item;
    var bien = false;

    if (p.tipo === 'quiz') {
      bien = sel.elegido === it.correcta;
      [].forEach.call(pantalla.querySelectorAll('[data-op]'), function (o) {
        var n = Number(o.getAttribute('data-op'));
        if (n === it.correcta) o.classList.add('bien');
        else if (n === sel.elegido) o.classList.add('mal');
      });
    } else if (p.tipo === 'verdadero_falso') {
      bien = (sel.elegido === 1) === !!it.correcta;
      [].forEach.call(pantalla.querySelectorAll('[data-vf]'), function (o) {
        var v = o.getAttribute('data-vf') === '1';
        if (v === !!it.correcta) o.classList.add('bien');
        else if (v === (sel.elegido === 1)) o.classList.add('mal');
      });
    } else if (p.tipo === 'respuesta_multiple') {
      var a = sel.multi.slice().sort().join(','), b2 = (it.correctas || []).slice().sort().join(',');
      bien = a === b2 && a !== '';
      [].forEach.call(pantalla.querySelectorAll('[data-multi]'), function (o) {
        var n = Number(o.getAttribute('data-multi'));
        if ((it.correctas || []).indexOf(n) > -1) o.classList.add('bien');
        else if (sel.multi.indexOf(n) > -1) o.classList.add('mal');
      });
    } else if (p.tipo === 'completar') {
      var claves = (it.texto.match(/\\[([^\\]]*)\\]/g) || []).map(function (c) { return c.slice(1, -1); });
      var todas = true;
      [].forEach.call(pantalla.querySelectorAll('[data-hueco]'), function (h, n) {
        var ok = normalizar(h.value) === normalizar(claves[n] || '');
        h.style.borderBottomColor = ok ? 'var(--mint)' : 'var(--coral)';
        if (!ok) { todas = false; h.value = claves[n] || ''; }
      });
      bien = todas;
    } else if (p.tipo === 'palabra_secreta' || p.tipo === 'anagrama') {
      var campo = pantalla.querySelector('[data-texto]');
      bien = normalizar(campo.value) === normalizar(it.palabra);
      if (!bien) campo.value = it.palabra;
      campo.style.borderColor = bien ? 'var(--mint)' : 'var(--coral)';
    } else if (p.tipo === 'numerica') {
      var c2 = pantalla.querySelector('[data-texto]');
      var dada = parseFloat(String(c2.value).replace(',', '.'));
      var esperada = parseFloat(String(it.respuesta).replace(',', '.'));
      var margen = Math.abs(parseFloat(String(it.tolerancia || 0).replace(',', '.'))) || 0;
      bien = !isNaN(dada) && Math.abs(dada - esperada) <= margen;
      if (!bien) c2.value = it.respuesta;
      c2.style.borderColor = bien ? 'var(--mint)' : 'var(--coral)';
    } else if (p.tipo === 'ortografia') {
      bien = (it.pares || []).every(function (par, n) { return normalizar(sel.orto[n]) === normalizar(par.correcta); });
      [].forEach.call(pantalla.querySelectorAll('[data-orto]'), function (o) {
        var n = o.getAttribute('data-orto');
        if (normalizar(o.getAttribute('data-valor')) === normalizar(it.pares[n].correcta)) o.classList.add('bien');
      });
    } else if (p.tipo === 'relacionar') {
      bien = (it.pares || []).every(function (par, n) {
        var s = pantalla.querySelector('[data-par="' + n + '"]');
        var ok = normalizar(s.value) === normalizar(par.derecha);
        s.style.borderColor = ok ? 'var(--mint)' : 'var(--coral)';
        if (!ok) s.value = par.derecha;
        return ok;
      });
    } else if (p.tipo === 'ordenar') {
      var actual = [].map.call(pantalla.querySelectorAll('#orden li'), function (li) { return li.getAttribute('data-elem'); });
      bien = actual.join('|') === it.elementos.join('|');
    }

    if (bien) aciertos++;
    marcador();

    pantalla.querySelector('.pie').innerHTML =
      '<span style="font-weight:700;color:' + (bien ? 'var(--mint)' : 'var(--coral)') + '">' +
      (bien ? '¡Correcto!' : 'La respuesta correcta está marcada') + '</span>' +
      '<button class="btn" id="seguir">' + (i + 1 >= pantallas.length ? 'Ver resultado' : 'Siguiente →') + '</button>';

    SCORM.guardar(Math.round((aciertos / Math.max(1, puntuables)) * 100));
  }

  function terminar() {
    var pct = Math.round((aciertos / Math.max(1, puntuables)) * 100);
    elProgreso.style.width = '100%';
    pantalla.innerHTML = '<div class="final"><p class="paso">' + esc(PAQUETE.tema) + '</p>' +
      '<p class="nota">' + pct + '%</p>' +
      '<p>' + aciertos + ' de ' + puntuables + ' correctas</p>' +
      '<div class="pie" style="justify-content:center"><button class="btn" id="otra">Intentar de nuevo</button></div></div>';
    document.getElementById('otra').onclick = function () { i = 0; aciertos = 0; pintar(); };
    SCORM.terminar(pct);
  }

  pintar();
})();
</script>
</body>
</html>
HTML;
}

/** Puente con el LMS: API de SCORM 1.2, buscándola como manda el estándar. */
function exportar_js_scorm(): string
{
    return <<<'JS'
  // SCORM 1.2: la API la expone el LMS en la ventana padre o en la de apertura.
  var SCORM = (function () {
    var api = null;

    function buscar(ventana, saltos) {
      while (ventana && !ventana.API && ventana.parent && ventana.parent !== ventana && saltos-- > 0) {
        ventana = ventana.parent;
      }
      return ventana && ventana.API ? ventana.API : null;
    }

    try {
      api = buscar(window, 10) || (window.opener ? buscar(window.opener, 10) : null);
    } catch (e) { api = null; }

    var vivo = false;

    return {
      iniciar: function () {
        if (!api) return;
        vivo = api.LMSInitialize('') === 'true';
        if (vivo) {
          api.LMSSetValue('cmi.core.lesson_status', 'incomplete');
          api.LMSCommit('');
        }
      },
      guardar: function (pct) {
        if (!api || !vivo) return;
        api.LMSSetValue('cmi.core.score.raw', String(pct));
        api.LMSSetValue('cmi.core.score.min', '0');
        api.LMSSetValue('cmi.core.score.max', '100');
        api.LMSCommit('');
      },
      terminar: function (pct) {
        if (!api || !vivo) return;
        api.LMSSetValue('cmi.core.score.raw', String(pct));
        api.LMSSetValue('cmi.core.lesson_status', pct >= 60 ? 'passed' : 'failed');
        api.LMSCommit('');
        api.LMSFinish('');
        vivo = false;
      }
    };
  })();
JS;
}

/** El manifiesto que Moodle lee para reconocer el paquete. */
function exportar_manifiesto(string $tema, string $codigo): string
{
    $t = htmlspecialchars($tema, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $id = 'LUDIA-' . $codigo;

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="{$id}" version="1.2"
  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
                      http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
                      http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="ORG-{$codigo}">
    <organization identifier="ORG-{$codigo}">
      <title>{$t}</title>
      <item identifier="ITEM-{$codigo}" identifierref="RES-{$codigo}" isvisible="true">
        <title>{$t}</title>
        <adlcp:masteryscore>60</adlcp:masteryscore>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES-{$codigo}" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;
}

/**
 * Arma el ZIP SCORM en la ruta indicada.
 * @return array{ok:bool, error:string}
 */
function exportar_scorm_zip(string $rutaZip, string $tema, string $codigo, array $contenido): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'El servidor no tiene la extensión zip de PHP.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'No se pudo crear el archivo.'];
    }

    $zip->addFromString('index.html', exportar_html($tema, $contenido, true));
    $zip->addFromString('imsmanifest.xml', exportar_manifiesto($tema, $codigo));
    $zip->close();

    return ['ok' => true, 'error' => ''];
}

/**
 * Nombre de archivo seguro a partir del tema.
 *
 * Se quitan tildes y eñes a propósito: estos archivos acaban descargados en
 * Windows o subidos a un Moodle, y un nombre con acentos llega corrupto según
 * cómo trate cada sistema la codificación.
 */
function exportar_nombre(string $tema, string $extension): string
{
    $sinTildes = strtr(mb_strtolower($tema), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);

    // Ya sin acentos, solo se conserva el ASCII básico.
    $limpio = preg_replace('/[^a-z0-9]+/', '-', $sinTildes) ?? '';
    $limpio = trim($limpio, '-');
    $limpio = $limpio === '' ? 'paquete' : substr($limpio, 0, 40);

    return 'ludia-' . trim($limpio, '-') . '.' . $extension;
}
