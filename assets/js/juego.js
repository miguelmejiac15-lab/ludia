/* =====================================================================
   Ludia — editor de JUEGOS (app/juego.php, paso 2).
   El profesor solo escribe palabras y, en los planes de pago, sube imágenes.
   El servidor valida y convierte (includes/Juegos.php): aquí no se decide
   nada que importe, solo se ayuda a escribir rápido.
   ===================================================================== */
(function () {
  'use strict';

  var J = window.LudiaJuego;
  var raiz = document.getElementById('juego-editor');
  if (!J || !raiz) return;

  var MAX_OPCIONES = 6;

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function parejaVacia() { return { izquierda: '', derecha: '', imagen_izq: '', imagen_der: '' }; }
  function preguntaVacia() { return { enunciado: '', imagen: '', opciones: ['', '', '', ''], correcta: 0 }; }
  function nuevo() { return J.tipo === 'parejas' ? parejaVacia() : preguntaVacia(); }

  var estado = {
    titulo: J.titulo || '',
    items: (J.contenido && J.contenido.items && J.contenido.items.length)
      ? JSON.parse(JSON.stringify(J.contenido.items))
      : (J.tipo === 'parejas' ? [nuevo(), nuevo(), nuevo(), nuevo()] : [nuevo()])
  };
  if (J.tipo === 'preguntas') {
    // Al editar, se deja hueco para una respuesta más, como en el primer borrador.
    estado.items.forEach(function (p) { if (p.opciones.length < 4) p.opciones.push(''); });
  }

  /* Qué significa cada columna según la plantilla con la que se empezó. */
  var DE_LETRAS = ['palabra_secreta', 'anagrama', 'sopa_letras', 'crucigrama'];
  var etiquetas = DE_LETRAS.indexOf(J.plantilla) > -1
    ? { izq: 'Palabra', der: 'Pista o definición', ayuda: 'La palabra que hay que encontrar va a la izquierda; su pista, a la derecha.' }
    : { izq: 'Palabra o pregunta', der: 'Su pareja o respuesta', ayuda: 'Escribe cada palabra con su pareja. Pulsa Enter para pasar a la siguiente fila.' };

  /* ---------- Imágenes ---------- */

  function botonImagen(ruta, url) {
    if (url) {
      return '<span class="img-mini"><img src="' + esc(url) + '" alt=""><button type="button" class="img-quitar" data-quitar-img="' + ruta + '" aria-label="Quitar imagen">✕</button></span>';
    }
    return J.imagenes
      ? '<button type="button" class="btn-icono" data-subir-img="' + ruta + '" aria-label="Añadir imagen" title="Añadir imagen">🖼</button>'
      : '<button type="button" class="btn-icono bloqueado" data-img-plan aria-label="Imágenes: planes de pago" title="Imágenes en los planes de pago">🖼🔒</button>';
  }

  var entradaArchivo = document.createElement('input');
  entradaArchivo.type = 'file';
  entradaArchivo.accept = 'image/*';
  entradaArchivo.hidden = true;
  document.body.appendChild(entradaArchivo);
  var rutaPendiente = null;

  function escribir(ruta, valor) {
    var partes = ruta.split('.');
    var obj = estado.items[+partes[0]];
    obj[partes[1]] = valor;
  }

  entradaArchivo.addEventListener('change', function () {
    var archivo = entradaArchivo.files && entradaArchivo.files[0];
    if (!archivo || !rutaPendiente) return;
    var ruta = rutaPendiente;
    aviso('Subiendo «' + archivo.name + '»…', '');
    var cuerpo = new FormData();
    cuerpo.append('imagen', archivo);
    fetch(window.LudiaRutas.api('imagen-subir.php'), { method: 'POST', body: cuerpo })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        entradaArchivo.value = '';
        if (!d.ok) { aviso(d.error || 'No se pudo subir la imagen.', 'error'); return; }
        escribir(ruta, d.imagen.url);
        aviso('', '');
        pintar();
      })
      .catch(function () { aviso('Sin conexión: no se pudo subir la imagen.', 'error'); });
  });

  /* ---------- Pintar ---------- */

  function filaPareja(p, i) {
    return '<li class="fila-pareja" data-i="' + i + '">' +
      '<span class="fila-num">' + (i + 1) + '.</span>' +
      '<span class="campo-con-img"><input class="input" data-campo="' + i + '.izquierda" value="' + esc(p.izquierda) + '" maxlength="160" aria-label="' + etiquetas.izq + ' ' + (i + 1) + '">' + botonImagen(i + '.imagen_izq', p.imagen_izq) + '</span>' +
      '<span class="campo-con-img"><input class="input" data-campo="' + i + '.derecha" value="' + esc(p.derecha) + '" maxlength="160" aria-label="' + etiquetas.der + ' ' + (i + 1) + '">' + botonImagen(i + '.imagen_der', p.imagen_der) + '</span>' +
      acciones(i) + '</li>';
  }

  function tarjetaPregunta(p, i) {
    var opciones = p.opciones.map(function (o, j) {
      return '<li class="opcion">' +
        '<label class="opcion-correcta" title="Marcar como correcta"><input type="radio" name="correcta-' + i + '" data-correcta="' + i + '.' + j + '"' + (p.correcta === j ? ' checked' : '') + '><span>' + (p.correcta === j ? '✓' : String.fromCharCode(65 + j)) + '</span></label>' +
        '<input class="input" data-opcion="' + i + '.' + j + '" value="' + esc(o) + '" maxlength="160" placeholder="Respuesta ' + String.fromCharCode(65 + j) + '" aria-label="Respuesta ' + String.fromCharCode(65 + j) + ' de la pregunta ' + (i + 1) + '">' +
        (p.opciones.length > 2 ? '<button type="button" class="btn-icono" data-quitar-opcion="' + i + '.' + j + '" aria-label="Quitar respuesta">✕</button>' : '') +
        '</li>';
    }).join('');
    return '<li class="tarjeta-pregunta" data-i="' + i + '">' +
      '<div class="tarjeta-cabeza"><span class="fila-num">' + (i + 1) + '.</span>' +
      '<span class="campo-con-img"><input class="input" data-campo="' + i + '.enunciado" value="' + esc(p.enunciado) + '" maxlength="160" placeholder="Escribe la pregunta" aria-label="Pregunta ' + (i + 1) + '">' + botonImagen(i + '.imagen', p.imagen) + '</span>' +
      acciones(i) + '</div>' +
      '<ol class="opciones" role="list">' + opciones + '</ol>' +
      (p.opciones.length < MAX_OPCIONES ? '<button type="button" class="link-btn" data-mas-opcion="' + i + '">+ Añadir respuesta</button>' : '') +
      '<p class="hint">Toca la letra para marcar la respuesta correcta.</p>' +
      '</li>';
  }

  function acciones(i) {
    var n = estado.items.length;
    return '<span class="fila-acciones">' +
      '<button type="button" class="btn-icono" data-mover="' + i + '.-1" aria-label="Subir"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
      '<button type="button" class="btn-icono" data-mover="' + i + '.1" aria-label="Bajar"' + (i === n - 1 ? ' disabled' : '') + '>↓</button>' +
      '<button type="button" class="btn-icono" data-duplicar="' + i + '" aria-label="Duplicar"' + (n >= J.max ? ' disabled' : '') + '>⧉</button>' +
      '<button type="button" class="btn-icono" data-borrar="' + i + '" aria-label="Eliminar"' + (n <= 1 ? ' disabled' : '') + '>🗑</button>' +
      '</span>';
  }

  function pintar(enfocar) {
    var esParejas = J.tipo === 'parejas';
    var n = estado.items.length;
    raiz.innerHTML =
      '<div class="field"><label for="juego-titulo">Título del juego</label>' +
      '<input class="input" id="juego-titulo" maxlength="120" value="' + esc(estado.titulo) + '" placeholder="Ej. Animales y sus crías"></div>' +
      (esParejas
        ? '<div class="columnas-cabeza"><span></span><b>' + etiquetas.izq + '</b><b>' + etiquetas.der + '</b>' +
          '<button type="button" class="link-btn" data-intercambiar title="Pasa lo de la izquierda a la derecha y al revés">⇄ Intercambiar columnas</button></div>' +
          '<p class="hint">' + etiquetas.ayuda + '</p>' +
          '<ol class="lista-parejas" role="list">' + estado.items.map(filaPareja).join('') + '</ol>'
        : '<ol class="lista-preguntas" role="list">' + estado.items.map(tarjetaPregunta).join('') + '</ol>') +
      '<div class="editor-pie">' +
      '<button type="button" class="btn btn-ghost" data-anadir' + (n >= J.max ? ' disabled' : '') + '>+ Añadir ' + (esParejas ? 'pareja' : 'pregunta') + '</button>' +
      '<span class="hint">' + (esParejas ? 'mín. ' + J.min + ' · ' : '') + 'máx. ' + J.max + '</span>' +
      '</div>' +
      '<p class="mensaje" id="juego-aviso" hidden></p>' +
      '<div class="editor-final">' +
      '<a class="btn btn-ghost" href="' + esc(J.volver) + '">← ' + (J.id ? 'Cancelar' : 'Otra plantilla') + '</a>' +
      '<button type="button" class="btn btn-primary" data-guardar>Listo →</button></div>';

    if (enfocar) {
      var el = raiz.querySelector('[data-campo="' + enfocar + '"]');
      if (el) el.focus();
    }
  }

  function aviso(texto, tipo) {
    var el = document.getElementById('juego-aviso');
    if (!el) return;
    el.hidden = !texto;
    el.textContent = texto;
    el.className = 'mensaje' + (tipo ? ' ' + tipo : '');
  }

  /* ---------- Eventos ---------- */

  raiz.addEventListener('input', function (ev) {
    var t = ev.target;
    if (t.id === 'juego-titulo') { estado.titulo = t.value; return; }
    var campo = t.getAttribute('data-campo');
    if (campo) { escribir(campo, t.value); return; }
    var op = t.getAttribute('data-opcion');
    if (op) { var p = op.split('.'); estado.items[+p[0]].opciones[+p[1]] = t.value; }
  });

  raiz.addEventListener('change', function (ev) {
    var c = ev.target.getAttribute('data-correcta');
    if (c) { var p = c.split('.'); estado.items[+p[0]].correcta = +p[1]; pintar(); }
  });

  // Enter en la última columna de una pareja salta a la siguiente fila (y la
  // crea si hace falta): así se escribe una lista entera sin tocar el ratón.
  raiz.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    var campo = ev.target.getAttribute('data-campo');
    if (!campo) return;
    ev.preventDefault();
    var p = campo.split('.'), i = +p[0];
    if (J.tipo === 'parejas' && p[1] === 'izquierda') {
      raiz.querySelector('[data-campo="' + i + '.derecha"]').focus();
      return;
    }
    if (J.tipo === 'parejas') {
      if (i === estado.items.length - 1) {
        if (estado.items.length >= J.max) return;
        estado.items.push(nuevo());
        pintar((i + 1) + '.izquierda');
      } else {
        raiz.querySelector('[data-campo="' + (i + 1) + '.izquierda"]').focus();
      }
    }
  });

  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b) return;
    var v;

    if (b.hasAttribute('data-anadir')) {
      estado.items.push(nuevo());
      pintar(J.tipo === 'parejas' ? (estado.items.length - 1) + '.izquierda' : (estado.items.length - 1) + '.enunciado');
    } else if ((v = b.getAttribute('data-borrar')) !== null) {
      estado.items.splice(+v, 1); pintar();
    } else if ((v = b.getAttribute('data-duplicar')) !== null) {
      estado.items.splice(+v + 1, 0, JSON.parse(JSON.stringify(estado.items[+v]))); pintar();
    } else if ((v = b.getAttribute('data-mover')) !== null) {
      var m = v.split('.'), i = +m[0], j = i + (+m[1]);
      var tmp = estado.items[i]; estado.items[i] = estado.items[j]; estado.items[j] = tmp; pintar();
    } else if (b.hasAttribute('data-intercambiar')) {
      estado.items.forEach(function (p) {
        var t = p.izquierda; p.izquierda = p.derecha; p.derecha = t;
        t = p.imagen_izq; p.imagen_izq = p.imagen_der; p.imagen_der = t;
      });
      pintar();
    } else if ((v = b.getAttribute('data-mas-opcion')) !== null) {
      estado.items[+v].opciones.push(''); pintar();
      var nueva = raiz.querySelector('[data-opcion="' + v + '.' + (estado.items[+v].opciones.length - 1) + '"]');
      if (nueva) nueva.focus();
    } else if ((v = b.getAttribute('data-quitar-opcion')) !== null) {
      var q = v.split('.'), preg = estado.items[+q[0]], k = +q[1];
      preg.opciones.splice(k, 1);
      if (preg.correcta === k) preg.correcta = 0; else if (preg.correcta > k) preg.correcta--;
      pintar();
    } else if ((v = b.getAttribute('data-subir-img')) !== null) {
      rutaPendiente = v; entradaArchivo.click();
    } else if ((v = b.getAttribute('data-quitar-img')) !== null) {
      escribir(v, ''); pintar();
    } else if (b.hasAttribute('data-img-plan')) {
      aviso('Las imágenes son de los planes Estándar y Pro. En el plan gratis puedes usar texto.', 'error');
    } else if (b.hasAttribute('data-guardar')) {
      guardar(b);
    }
  });

  function guardar(boton) {
    boton.disabled = true;
    aviso('Guardando…', '');
    fetch(window.LudiaRutas.api('juego-guardar.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': J.csrf },
      body: JSON.stringify({ id: J.id, titulo: estado.titulo, tipo: J.tipo, plantilla: J.plantilla, contenido: { items: estado.items } })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ingresar) { location.href = d.ingresar; return; }
        if (!d.ok) { boton.disabled = false; aviso(d.error, 'error'); return; }
        location.href = d.url;
      })
      .catch(function () { boton.disabled = false; aviso('Sin conexión: no se pudo guardar. Inténtalo otra vez.', 'error'); });
  }

  pintar(J.tipo === 'parejas' ? (J.id ? null : '0.izquierda') : (J.id ? null : '0.enunciado'));
})();
