/* =====================================================================
   Ludia — campos de edición y vista previa de cada formato.
   Devuelve HTML (strings). crear.js maneja el estado y los eventos:
     data-bind="ruta.del.item"   → campo enlazado al ítem actual (admite rutas anidadas)
     data-type="number|bool|check|toggle-index" → cómo convertir el valor
     data-action="agregar-fila|quitar-fila" + data-key="ruta" → filas dinámicas
   ===================================================================== */
window.LudiaEditor = (function () {
  'use strict';

  var F = window.LudiaFormatos;
  var LETRAS = 'ABCDEFGHIJ';

  function esc(valor) {
    return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function leer(obj, ruta) {
    return ruta.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, obj);
  }

  /* ---------- Piezas de formulario ---------- */

  function campoTexto(id, label, bind, valor, opciones) {
    opciones = opciones || {};
    var atributos = 'class="' + (opciones.textarea ? 'textarea' : 'input') + '" id="' + id + '" data-bind="' + bind + '"' +
      ' maxlength="' + (opciones.max || 200) + '" placeholder="' + esc(opciones.placeholder || '') + '"';
    var control = opciones.textarea
      ? '<textarea ' + atributos + ' rows="3">' + esc(valor) + '</textarea>'
      : '<input type="text" ' + atributos + ' value="' + esc(valor) + '">';
    return '<div class="field"><label for="' + id + '">' + label + '</label>' + control +
      (opciones.hint ? '<span class="hint">' + opciones.hint + '</span>' : '') + '</div>';
  }

  /**
   * Control de imagen de una pregunta: miniatura, subir y quitar.
   * crear.js escucha data-action="subir-imagen" / "quitar-imagen".
   */
  /** El plan lo inyecta app/crear.php; si falta, se asume que todo está permitido. */
  function planPermiteImagenes() {
    var plan = window.LudiaPlan;
    return !plan || plan.imagenes !== false;
  }

  function campoImagen(item) {
    if (!planPermiteImagenes()) {
      return '<div class="field"><span class="field-label">Imagen</span>' +
        '<div class="imagen-bloqueada"><b>🔒 Ilustrar con imágenes es de los planes de pago</b>' +
        '<span>En el plan gratis puedes usar todo el texto que quieras. ' +
        '<a href="' + (window.LudiaRutas ? window.LudiaRutas.base : '/') + '#planes">Ver los planes</a></span></div></div>';
    }

    var url = item.imagen || '';
    var vista = url
      ? '<div class="img-miniatura"><img src="' + esc(url) + '" alt="Imagen de la pregunta">' +
        '<button type="button" class="icon-btn" data-action="quitar-imagen" aria-label="Quitar imagen">✕</button></div>'
      : '';

    return '<div class="field"><span class="field-label">Imagen (opcional)</span>' + vista +
      '<label class="add-row" for="img-pregunta">' + (url ? '🖼️ Cambiar imagen' : '🖼️ Agregar imagen') + '</label>' +
      '<input id="img-pregunta" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-action="subir-imagen" hidden>' +
      '<span class="hint" id="img-estado">Se ve arriba de la pregunta. JPG, PNG, GIF o WebP.</span></div>';
  }

  function selector(id, label, bind, valor, opciones, hint) {
    var html = opciones.map(function (o) {
      return '<option value="' + esc(o.valor) + '"' + (String(o.valor) === String(valor) ? ' selected' : '') + '>' + esc(o.nombre) + '</option>';
    }).join('');
    return '<div class="field"><label for="' + id + '">' + label + '</label>' +
      '<select class="select" id="' + id + '" data-bind="' + bind + '"' + (typeof valor === 'number' ? ' data-type="number"' : '') + '>' + html + '</select>' +
      (hint ? '<span class="hint">' + esc(hint) + '</span>' : '') + '</div>';
  }

  function interruptor(label, bind, valor, ayuda) {
    return '<label class="switch"><input type="checkbox" data-bind="' + bind + '" data-type="check"' + (valor ? ' checked' : '') + '>' +
      '<span><b>' + esc(label) + '</b>' + (ayuda ? '<small>' + esc(ayuda) + '</small>' : '') + '</span></label>';
  }

  // Lista de textos simples. `ruta` admite anidación ("categorias.0.elementos").
  // modo: 'radio' (una correcta) · 'check' (varias) · 'plain' (sin correcta)
  function listaSimple(ctx, item, ruta, modo, min, max, placeholder, numerada) {
    var lista = leer(item, ruta) || [];
    var html = lista.map(function (texto, i) {
      var marca;
      if (modo === 'radio') {
        marca = '<label class="option-mark opt-' + i + '" title="Marcar como correcta">' +
          '<input type="radio" name="correcta-' + ctx.key + '" data-bind="correcta" data-type="number" value="' + i + '"' +
          (item.correcta === i ? ' checked' : '') + ' aria-label="Opción ' + LETRAS[i] + ' es la correcta">' + LETRAS[i] + '</label>';
      } else if (modo === 'check') {
        marca = '<label class="option-mark opt-' + i + '" title="Marcar como correcta">' +
          '<input type="checkbox" data-bind="correctas" data-type="toggle-index" value="' + i + '"' +
          ((item.correctas || []).indexOf(i) > -1 ? ' checked' : '') + ' aria-label="Opción ' + LETRAS[i] + ' es correcta">' + LETRAS[i] + '</label>';
      } else {
        marca = '<span class="option-mark plain">' + (numerada ? i + 1 : LETRAS[i]) + '</span>';
      }
      return '<div class="option-row">' + marca +
        '<input class="input" type="text" maxlength="120" data-bind="' + ruta + '.' + i + '" value="' + esc(texto) + '"' +
        ' placeholder="' + esc(placeholder) + ' ' + (numerada ? i + 1 : LETRAS[i]) + '" aria-label="' + esc(placeholder) + ' ' + (numerada ? i + 1 : LETRAS[i]) + '">' +
        '<button type="button" class="icon-btn" data-action="quitar-fila" data-key="' + ruta + '" data-index="' + i + '"' +
        (lista.length <= min ? ' disabled' : '') + ' aria-label="Quitar">✕</button></div>';
    }).join('');
    if (lista.length < max) {
      html += '<button type="button" class="add-row" data-action="agregar-fila" data-key="' + ruta + '">+ Agregar</button>';
    }
    return html;
  }

  // Lista de textos largos (párrafos): igual que listaSimple pero con textarea.
  function listaParrafos(item, ruta, min, max, placeholder) {
    var lista = leer(item, ruta) || [];
    var filas = lista.map(function (texto, i) {
      return '<div class="option-row parrafo"><span class="option-mark plain">' + (i + 1) + '</span>' +
        '<textarea class="textarea" rows="3" maxlength="600" data-bind="' + ruta + '.' + i + '"' +
        ' placeholder="' + esc(placeholder) + ' ' + (i + 1) + '" aria-label="' + esc(placeholder) + ' ' + (i + 1) + '">' +
        esc(texto) + '</textarea>' +
        '<button type="button" class="icon-btn" data-action="quitar-fila" data-key="' + ruta + '" data-index="' + i + '"' +
        (lista.length <= min ? ' disabled' : '') + ' aria-label="Quitar">✕</button></div>';
    }).join('');
    return filas + (lista.length < max
      ? '<button type="button" class="add-row" data-action="agregar-fila" data-key="' + ruta + '">+ Agregar párrafo</button>' : '');
  }

  // Filas de dos campos (parejas, definiciones, tarjetas…).
  /**
   * Botón de imagen para UN lado de una pareja.
   *
   * El texto del lado sigue siendo obligatorio aunque haya imagen: es lo que
   * compara el servidor al calificar y el texto alternativo para quien no pueda
   * ver la imagen. La imagen es lo que se muestra grande; el texto, su etiqueta.
   */
  function imagenDeLado(destino, url, etiqueta) {
    if (!planPermiteImagenes()) return '';

    var id = 'img-' + destino.replace(/\./g, '-');
    return '<span class="par-imagen">' +
      (url
        ? '<span class="par-miniatura"><img src="' + esc(url) + '" alt="">' +
          '<button type="button" class="icon-btn" data-action="quitar-imagen" data-destino="' + esc(destino) + '"' +
          ' aria-label="Quitar imagen de ' + esc(etiqueta) + '">✕</button></span>'
        : '') +
      '<label class="par-subir" for="' + id + '" title="' + (url ? 'Cambiar' : 'Agregar') + ' imagen">🖼️' +
      '<span class="sr-only">' + (url ? 'Cambiar' : 'Agregar') + ' imagen de ' + esc(etiqueta) + '</span></label>' +
      '<input id="' + id + '" type="file" accept="image/jpeg,image/png,image/gif,image/webp"' +
      ' data-action="subir-imagen" data-destino="' + esc(destino) + '" hidden></span>';
  }

  function filasDobles(item, ruta, campos, min, max, etiquetaBoton) {
    var lista = leer(item, ruta) || [];
    // Solo las parejas admiten imagen por lado; en listas como las tarjetas de
    // repaso el segundo campo es una explicación larga, no algo que ilustrar.
    var conImagen = !!(campos[0].imagen && campos[1].imagen);

    var filas = lista.map(function (fila, i) {
      function lado(n, largo) {
        var clave = campos[n].clave;
        var destino = ruta + '.' + i + '.' + campos[n].imagen;
        return '<span class="par-lado">' +
          '<input class="input" type="text" maxlength="' + largo + '" data-bind="' + ruta + '.' + i + '.' + clave + '" value="' + esc(fila[clave]) + '"' +
          ' placeholder="' + esc(campos[n].etiqueta) + ' ' + (i + 1) + '" aria-label="' + esc(campos[n].etiqueta) + ' ' + (i + 1) + '">' +
          (conImagen ? imagenDeLado(destino, fila[campos[n].imagen] || '', campos[n].etiqueta + ' ' + (i + 1)) : '') +
          '</span>';
      }

      return '<div class="option-row pair' + (conImagen ? ' pair-img' : '') + '">' +
        lado(0, 90) +
        '<span class="arrow" aria-hidden="true">' + (campos[0].union || '↔') + '</span>' +
        lado(1, 160) +
        '<button type="button" class="icon-btn" data-action="quitar-fila" data-key="' + ruta + '" data-index="' + i + '"' +
        (lista.length <= min ? ' disabled' : '') + ' aria-label="Quitar">✕</button></div>';
    }).join('');

    return filas + (lista.length < max
      ? '<button type="button" class="add-row" data-action="agregar-fila" data-key="' + ruta + '">+ ' + esc(etiquetaBoton) + '</button>' : '');
  }

  /* ---------- Campos por formato ---------- */

  var campos = {
    quiz: function (it, ctx) {
      return campoTexto('f-enunciado', 'Pregunta', 'enunciado', it.enunciado, { textarea: true, placeholder: 'Ej. ¿Qué país fue invadido en 1939?' }) +
        '<div class="field"><span class="field-label">Opciones · toca la letra de la correcta</span>' +
        listaSimple(ctx, it, 'opciones', 'radio', 2, 4, 'Opción') + '</div>';
    },

    verdadero_falso: function (it, ctx) {
      return campoTexto('f-afirmacion', 'Afirmación', 'afirmacion', it.afirmacion, { textarea: true, placeholder: 'Ej. La Segunda Guerra Mundial comenzó en 1939.' }) +
        '<div class="field"><span class="field-label">Respuesta correcta</span><div class="toggle-vf">' +
        '<label><input type="radio" name="vf-' + ctx.key + '" data-bind="correcta" data-type="bool" value="true"' + (it.correcta ? ' checked' : '') + '> Verdadero</label>' +
        '<label><input type="radio" name="vf-' + ctx.key + '" data-bind="correcta" data-type="bool" value="false"' + (!it.correcta ? ' checked' : '') + '> Falso</label>' +
        '</div></div>';
    },

    respuesta_multiple: function (it, ctx) {
      return campoTexto('f-enunciado', 'Pregunta', 'enunciado', it.enunciado, { textarea: true, placeholder: 'Ej. ¿Cuáles fueron países del Eje?' }) +
        '<div class="field"><span class="field-label">Opciones · marca todas las correctas</span>' +
        listaSimple(ctx, it, 'opciones', 'check', 3, 6, 'Opción') + '</div>';
    },

    palabra_secreta: function (it) {
      return campoTexto('f-palabra', 'Palabra secreta', 'palabra', it.palabra, { max: 20, placeholder: 'Ej. ARMISTICIO', hint: 'Se muestra letra por letra, como el ahorcado.' }) +
        campoTexto('f-pista', 'Pista', 'pista', it.pista, { textarea: true, placeholder: 'Ej. Acuerdo para detener los combates' });
    },

    completar: function (it) {
      return campoTexto('f-texto', 'Frase', 'texto', it.texto, {
        textarea: true, max: 300,
        placeholder: 'Ej. La Segunda Guerra Mundial comenzó en [1939] con la invasión a [Polonia].',
        hint: 'Encierra entre <code>[corchetes]</code> las palabras que el público debe escribir.'
      });
    },

    ordenar_palabras: function (it) {
      return campoTexto('f-frase', 'Frase', 'frase', it.frase, {
        textarea: true, max: 200,
        placeholder: 'Ej. El Tratado de Versalles impuso duras condiciones a Alemania',
        hint: 'Las palabras se mostrarán desordenadas para que el público reconstruya la frase.'
      });
    },

    sopa_letras: function (it, ctx) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Palabras escondidas · sin espacios, máximo 12 letras</span>' +
        listaSimple(ctx, it, 'palabras', 'plain', 3, 10, 'Palabra', true) + '</div>';
    },

    ortografia: function (it) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Forma correcta y forma incorrecta · se muestran barajadas</span>' +
        filasDobles(it, 'pares', [{ clave: 'correcta', etiqueta: 'Correcta', union: '✓' }, { clave: 'incorrecta', etiqueta: 'Incorrecta' }], 3, 10, 'Agregar pareja') + '</div>';
    },

    anagrama: function (it) {
      return campoTexto('f-palabra', 'Palabra', 'palabra', it.palabra, {
        max: 30, placeholder: 'Ej. MURCIELAGO',
        hint: 'Sus letras se mostrarán revueltas para que el público la arme.'
      }) +
        campoTexto('f-pista', 'Pista (opcional)', 'pista', it.pista, { max: 120, placeholder: 'Ej. Vuela de noche' });
    },

    video_preguntas: function (it, ctx) {
      var F = window.LudiaFormatos;
      var seg = Math.max(0, Number(it.segundo) || 0);
      return '<div class="field"><label for="f-segundo">¿En qué segundo del vídeo aparece?</label>' +
        '<input class="input" id="f-segundo" type="number" min="1" max="36000" data-bind="segundo" data-type="number" value="' + seg + '">' +
        '<span class="hint">Minuto <b>' + F.minuto(seg) + '</b>. El vídeo se reproduce hasta ahí y se detiene para preguntar. ' +
        'Las preguntas se ordenan solas por el segundo.</span></div>' +
        campoTexto('f-enunciado', 'Pregunta', 'enunciado', it.enunciado, { textarea: true, placeholder: 'Ej. ¿Qué acaba de explicar?' }) +
        '<div class="field"><span class="field-label">Opciones · toca la letra de la correcta</span>' +
        listaSimple(ctx, it, 'opciones', 'radio', 2, 4, 'Opción') + '</div>';
    },

    numerica: function (it) {
      return campoTexto('f-enunciado', 'Pregunta', 'enunciado', it.enunciado, { textarea: true, placeholder: 'Ej. ¿Cuántos litros tiene un metro cúbico?' }) +
        '<div class="row-2" style="grid-template-columns:1fr 1fr">' +
        campoTexto('f-respuesta', 'Respuesta correcta', 'respuesta', it.respuesta, { max: 20, placeholder: '1000' }) +
        campoTexto('f-tolerancia', 'Margen aceptado (±)', 'tolerancia', it.tolerancia, { max: 20, placeholder: '0' }) +
        '</div>' +
        campoTexto('f-unidad', 'Unidad (opcional)', 'unidad', it.unidad, { max: 20, placeholder: 'litros, %, km…' });
    },

    linea_tiempo: function (it) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Fecha y hecho · se ordenan solos por fecha</span>' +
        filasDobles(it, 'hechos', [{ clave: 'fecha', etiqueta: 'Fecha', union: '→' }, { clave: 'texto', etiqueta: 'Qué pasó' }], 3, 10, 'Agregar hecho') + '</div>';
    },

    crucigrama: function (it) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Palabra y su definición</span>' +
        filasDobles(it, 'entradas', [{ clave: 'palabra', etiqueta: 'Palabra', union: '→' }, { clave: 'pista', etiqueta: 'Definición' }], 3, 10, 'Agregar definición') + '</div>';
    },

    relacionar: function (it) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Parejas · la columna derecha se baraja al jugar</span>' +
        filasDobles(it, 'pares', [{ clave: 'izquierda', etiqueta: 'Elemento', imagen: 'imagen_izq' }, { clave: 'derecha', etiqueta: 'Pareja', imagen: 'imagen_der' }], 3, 8, 'Agregar pareja') + '</div>';
    },

    memoria: function (it) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Parejas de tarjetas</span>' +
        filasDobles(it, 'pares', [{ clave: 'izquierda', etiqueta: 'Tarjeta A', imagen: 'imagen_izq' }, { clave: 'derecha', etiqueta: 'Tarjeta B', imagen: 'imagen_der' }], 3, 8, 'Agregar pareja') + '</div>';
    },

    clasificar: function (it, ctx) {
      var bloques = it.categorias.map(function (cat, i) {
        return '<div class="cat-block"><div class="cat-head">' +
          '<input class="input" type="text" maxlength="60" data-bind="categorias.' + i + '.nombre" value="' + esc(cat.nombre) + '"' +
          ' placeholder="Categoría ' + (i + 1) + '" aria-label="Nombre de la categoría ' + (i + 1) + '">' +
          '<button type="button" class="icon-btn" data-action="quitar-fila" data-key="categorias" data-index="' + i + '"' +
          (it.categorias.length <= 2 ? ' disabled' : '') + ' aria-label="Quitar categoría">✕</button></div>' +
          listaSimple(ctx, it, 'categorias.' + i + '.elementos', 'plain', 2, 6, 'Elemento', true) + '</div>';
      }).join('');
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Categorías y sus elementos</span>' + bloques +
        (it.categorias.length < 4 ? '<button type="button" class="add-row" data-action="agregar-fila" data-key="categorias">+ Agregar categoría</button>' : '') + '</div>';
    },

    ordenar: function (it, ctx) {
      return campoTexto('f-instruccion', 'Instrucción', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Elementos en el orden correcto · se barajan al jugar</span>' +
        listaSimple(ctx, it, 'elementos', 'plain', 3, 8, 'Elemento', true) + '</div>';
    },

    mural: function (it, ctx) {
      var tipo = F.tipos.mural;
      var modo = tipo.modos.filter(function (m) { return m.clave === it.modo; })[0] || tipo.modos[0];
      return campoTexto('f-consigna', 'Consigna del mural', 'consigna', it.consigna, {
        textarea: true, placeholder: 'Ej. Escribe una idea que te llevas de la sesión'
      }) +
        selector('f-modo', 'Cómo se ven los aportes', 'modo', it.modo, tipo.modos.map(function (m) {
          return { valor: m.clave, nombre: m.nombre };
        }), modo.ayuda) +
        selector('f-max', 'Largo máximo del aporte', 'maxCaracteres', it.maxCaracteres, [
          { valor: 60, nombre: '60 caracteres' }, { valor: 120, nombre: '120 caracteres' }, { valor: 240, nombre: '240 caracteres' }
        ]) +
        '<div class="field"><span class="field-label">Reglas de participación</span>' +
        interruptor('Permitir aportes anónimos', 'anonimo', it.anonimo, 'Si lo apagas, cada nota muestra el nombre del personaje.') +
        interruptor('Revisar antes de publicar', 'moderar', it.moderar, 'Los aportes esperan tu aprobación para aparecer en el mural.') + '</div>' +
        '<div class="field"><span class="field-label">Notas de ejemplo (opcional)</span>' +
        listaSimple(ctx, it, 'ejemplos', 'plain', 0, 3, 'Ejemplo', true) + '</div>';
    },

    encuesta: function (it, ctx) {
      return campoTexto('f-pregunta', 'Pregunta', 'pregunta', it.pregunta, { textarea: true, placeholder: 'Ej. ¿Qué tema quieres repasar la próxima clase?' }) +
        '<div class="field"><span class="field-label">Opciones</span>' +
        listaSimple(ctx, it, 'opciones', 'plain', 2, 6, 'Opción') + '</div>';
    },

    pregunta_abierta: function (it) {
      return campoTexto('f-pregunta', 'Pregunta', 'pregunta', it.pregunta, { textarea: true, placeholder: 'Ej. En una palabra, ¿qué te llevas de hoy?' }) +
        selector('f-max', 'Largo máximo de la respuesta', 'maxCaracteres', it.maxCaracteres, [
          { valor: 30, nombre: '30 caracteres' }, { valor: 60, nombre: '60 caracteres' }, { valor: 120, nombre: '120 caracteres' }
        ]);
    },

    escala: function (it) {
      return campoTexto('f-pregunta', 'Pregunta', 'pregunta', it.pregunta, { textarea: true, placeholder: 'Ej. ¿Qué tan claro fue el tema de hoy?' }) +
        '<div class="row-2" style="grid-template-columns:1fr 1fr">' +
        campoTexto('f-min', 'Etiqueta del 1', 'etiquetaMin', it.etiquetaMin, { max: 40 }) +
        campoTexto('f-max', 'Etiqueta del 5', 'etiquetaMax', it.etiquetaMax, { max: 40 }) + '</div>';
    },

    panel: function (it) {
      return campoTexto('f-instruccion', 'Título del panel', 'instruccion', it.instruccion, { max: 120 }) +
        '<div class="field"><span class="field-label">Tarjetas de repaso</span>' +
        filasDobles(it, 'tarjetas', [{ clave: 'titulo', etiqueta: 'Concepto', union: '→' }, { clave: 'texto', etiqueta: 'Explicación' }], 2, 8, 'Agregar tarjeta') + '</div>';
    },

    pagina: function (it) {
      return campoTexto('f-titulo', 'Título de la página', 'titulo', it.titulo, {
        max: 120, placeholder: 'Ej. ¿Qué vamos a ver hoy?',
        hint: 'Se muestra grande, como la portada de una diapositiva.'
      }) +
        '<div class="field"><span class="field-label">Texto explicativo · un bloque por párrafo</span>' +
        listaParrafos(it, 'parrafos', 1, 6, 'Párrafo') + '</div>';
    }
  };

  /* ---------- Vista previa (lo que ve el participante) ---------- */

  function textoO(valor, relleno) {
    return F.vacio(valor) ? '<span class="pv-placeholder">' + esc(relleno) + '</span>' : esc(valor);
  }

  // Baraja determinista (rota una posición) para que la vista previa no "salte" al escribir.
  function rotar(lista) { return lista.length > 1 ? lista.slice(1).concat(lista[0]) : lista.slice(); }

  var vistas = {
    quiz: function (it) {
      return '<div class="pv-question">' + textoO(it.enunciado, 'Aquí va tu pregunta…') + '</div>' +
        '<div class="pv-options grid-2">' + it.opciones.map(function (t, i) {
          return '<div class="pv-option opt-' + i + '">' + LETRAS[i] + ' · ' + textoO(t, 'Opción ' + LETRAS[i]) + '</div>';
        }).join('') + '</div>';
    },
    verdadero_falso: function (it) {
      return '<div class="pv-question">' + textoO(it.afirmacion, 'Aquí va tu afirmación…') + '</div>' +
        '<div class="pv-options grid-2"><div class="pv-option opt-2">✔ Verdadero</div><div class="pv-option opt-0">✘ Falso</div></div>';
    },
    respuesta_multiple: function (it) {
      return '<div class="pv-question">' + textoO(it.enunciado, 'Aquí va tu pregunta…') + '</div>' +
        '<span class="hint">Elige todas las correctas</span><div class="pv-options">' + it.opciones.map(function (t, i) {
          return '<div class="pv-option neutral">☐ ' + textoO(t, 'Opción ' + LETRAS[i]) + '</div>';
        }).join('') + '</div>';
    },
    palabra_secreta: function (it) {
      var palabra = (it.palabra || '').trim().toUpperCase();
      var huecos = (palabra || '?????').split('').map(function () { return '<span class="pv-hueco"></span>'; }).join('');
      return '<div class="pv-question">' + textoO(it.pista, 'Aquí va tu pista…') + '</div>' +
        '<div class="pv-word">' + huecos + '</div>' +
        '<div class="pv-chips">' + 'AEIOU RST'.split('').filter(function (c) { return c !== ' '; }).map(function (c) {
          return '<span class="pv-chip">' + c + '</span>';
        }).join('') + '<span class="pv-chip mas">…</span></div>';
    },
    completar: function (it) {
      var frase = F.vacio(it.texto)
        ? '<span class="pv-placeholder">Aquí va tu frase con espacios en blanco…</span>'
        : esc(it.texto).replace(/\[([^\]]*)\]/g, '<span class="pv-blank"></span>');
      return '<div class="pv-text">' + frase + '</div><div class="pv-input">Escribe la palabra que falta…</div>';
    },
    ordenar_palabras: function (it) {
      var palabras = F.vacio(it.frase) ? [] : rotar(it.frase.trim().split(/\s+/));
      return '<div class="pv-question">Reconstruye la frase</div>' +
        (palabras.length
          ? '<div class="pv-chips">' + palabras.map(function (p) { return '<span class="pv-chip">' + esc(p) + '</span>'; }).join('') + '</div>'
          : '<span class="pv-placeholder">Aquí verás las palabras desordenadas…</span>');
    },
    sopa_letras: function (it) {
      var palabras = it.palabras.filter(function (p) { return !F.vacio(p); });
      var letras = (palabras.join('').toUpperCase() + 'LUDIAACTIVIDADESENJUEGO').replace(/[^A-ZÑ]/g, '');
      var celdas = '';
      for (var i = 0; i < 36; i++) celdas += '<span>' + letras.charAt(i % letras.length) + '</span>';
      return '<div class="pv-question">' + textoO(it.instruccion, 'Encuentra las palabras') + '</div>' +
        '<div class="pv-letters">' + celdas + '</div>' +
        '<div class="pv-chips">' + (palabras.length
          ? palabras.map(function (p) { return '<span class="pv-chip">' + esc(p.toUpperCase()) + '</span>'; }).join('')
          : '<span class="pv-placeholder">Agrega palabras…</span>') + '</div>';
    },
    crucigrama: function (it) {
      var lista = it.entradas.map(function (e, i) {
        var casillas = '';
        var largo = (e.palabra || '').trim().length || 5;
        for (var c = 0; c < Math.min(largo, 10); c++) casillas += '<span></span>';
        return '<div class="pv-cruci"><b>' + (i + 1) + '.</b><div><div class="pv-cruci-cells">' + casillas + '</div>' +
          '<small>' + textoO(e.pista, 'Definición ' + (i + 1)) + '</small></div></div>';
      }).join('');
      return '<div class="pv-question">' + textoO(it.instruccion, 'Resuelve el crucigrama') + '</div>' + lista;
    },
    relacionar: function (it) {
      var derechas = rotar(it.pares.map(function (p) { return p.derecha; }));
      var celdas = it.pares.map(function (p, i) {
        var mini = p.imagen_izq
          ? '<img class="pv-mini" src="' + esc(p.imagen_izq) + '" alt=""> '
          : '';
        return '<div>' + mini + textoO(p.izquierda, 'Elemento ' + (i + 1)) + '</div><div class="right">' + textoO(derechas[i], 'Pareja') + '</div>';
      }).join('');
      return '<div class="pv-question">' + textoO(it.instruccion, 'Relaciona…') + '</div><div class="pv-pairs">' + celdas + '</div>';
    },
    memoria: function (it) {
      var total = it.pares.length * 2;
      var cartas = '';
      for (var i = 0; i < total; i++) cartas += '<span class="pv-card">?</span>';
      return '<div class="pv-question">' + textoO(it.instruccion, 'Encuentra las parejas') + '</div>' +
        '<div class="pv-cards">' + cartas + '</div>';
    },
    clasificar: function (it) {
      var sueltos = [];
      it.categorias.forEach(function (c) {
        (c.elementos || []).forEach(function (e) { if (!F.vacio(e)) sueltos.push(e); });
      });
      var columnas = it.categorias.map(function (c, i) {
        return '<div class="pv-col"><b>' + textoO(c.nombre, 'Categoría ' + (i + 1)) + '</b><span class="pv-drop">Suelta aquí</span></div>';
      }).join('');
      return '<div class="pv-question">' + textoO(it.instruccion, 'Clasifica…') + '</div>' +
        '<div class="pv-chips">' + (sueltos.length
          ? rotar(sueltos).map(function (e) { return '<span class="pv-chip">' + esc(e) + '</span>'; }).join('')
          : '<span class="pv-placeholder">Agrega elementos…</span>') + '</div>' +
        '<div class="pv-cols">' + columnas + '</div>';
    },
    ordenar: function (it) {
      return '<div class="pv-question">' + textoO(it.instruccion, 'Ordena…') + '</div><div class="pv-options">' +
        rotar(it.elementos).map(function (t) { return '<div class="pv-option neutral">≡&nbsp; ' + textoO(t, 'Elemento') + '</div>'; }).join('') + '</div>';
    },
    mural: function (it) {
      var notas = it.ejemplos.filter(function (e) { return !F.vacio(e); });
      if (!notas.length) notas = ['Tu aporte aparece aquí', 'Y el de tus compañeros'];
      var clase = it.modo === 'postit' ? 'pv-mural postit' : it.modo === 'flotante' ? 'pv-mural flotante' : 'pv-mural fijo';
      var cuerpo = notas.map(function (n, i) {
        return '<span class="nota nota-' + (i % 4) + '">' + esc(n) + (it.anonimo ? '' : '<small>— Zorro veloz</small>') + '</span>';
      }).join('');
      return '<div class="pv-question">' + textoO(it.consigna, 'Aquí va tu consigna…') + '</div>' +
        '<div class="' + clase + '">' + cuerpo + '</div>' +
        '<div class="pv-input">Escribe tu aporte (máx. ' + it.maxCaracteres + ')</div>' +
        (it.moderar ? '<span class="hint">Tu aporte se publica cuando el anfitrión lo aprueba</span>' : '');
    },
    ortografia: function (it) {
      return '<div class="pv-question">' + textoO(it.instruccion, 'Elige la forma correcta') + '</div>' +
        it.pares.map(function (p, i) {
          return '<div class="pv-orto">' +
            '<span class="pv-option neutral">' + textoO(p.correcta, 'Correcta ' + (i + 1)) + '</span>' +
            '<span class="pv-option neutral">' + textoO(p.incorrecta, 'Incorrecta ' + (i + 1)) + '</span></div>';
        }).join('');
    },

    anagrama: function (it) {
      var letras = (it.palabra || '').toUpperCase().replace(/\s+/g, '').split('');
      return '<div class="pv-question">' + (F.vacio(it.pista) ? 'Arma la palabra' : esc(it.pista)) + '</div>' +
        '<div class="pv-chips">' + (letras.length
          ? rotar(letras).map(function (l) { return '<span class="pv-chip">' + esc(l) + '</span>'; }).join('')
          : '<span class="pv-placeholder">Aquí verás las letras revueltas…</span>') + '</div>';
    },

    video_preguntas: function (it) {
      var F = window.LudiaFormatos;
      return '<div class="pv-video">🎬 El vídeo se detiene en <b>' + F.minuto(it.segundo) + '</b></div>' +
        '<div class="pv-question">' + textoO(it.enunciado, 'Aquí va tu pregunta…') + '</div>' +
        '<div class="pv-options">' + (it.opciones || []).map(function (o, i) {
          return '<div class="pv-option' + (i === it.correcta ? ' ok' : '') + '">' + textoO(o, 'Opción ' + (i + 1)) + '</div>';
        }).join('') + '</div>';
    },

    numerica: function (it) {
      return '<div class="pv-question">' + textoO(it.enunciado, 'Aquí va tu pregunta…') + '</div>' +
        '<div class="pv-input">Escribe el número' + (F.vacio(it.unidad) ? '' : ' (' + esc(it.unidad) + ')') + '</div>';
    },

    linea_tiempo: function (it) {
      return '<div class="pv-question">' + textoO(it.instruccion, 'Ordena por fecha') + '</div><div class="pv-options">' +
        rotar(it.hechos.map(function (h) { return h.texto; })).map(function (t) {
          return '<div class="pv-option neutral">≡&nbsp; ' + textoO(t, 'Hecho') + '</div>';
        }).join('') + '</div>';
    },

    encuesta: function (it) {
      return '<div class="pv-question">' + textoO(it.pregunta, 'Aquí va tu pregunta…') + '</div><div class="pv-options">' +
        it.opciones.map(function (t, i) { return '<div class="pv-option neutral">' + textoO(t, 'Opción ' + LETRAS[i]) + '</div>'; }).join('') + '</div>';
    },
    pregunta_abierta: function (it) {
      return '<div class="pv-question">' + textoO(it.pregunta, 'Aquí va tu pregunta…') + '</div>' +
        '<div class="pv-input">Tu respuesta (máx. ' + it.maxCaracteres + ' caracteres)</div>';
    },
    escala: function (it) {
      return '<div class="pv-question">' + textoO(it.pregunta, 'Aquí va tu pregunta…') + '</div>' +
        '<div class="pv-scale"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span></div>' +
        '<div class="pv-scale-labels"><span>' + esc(it.etiquetaMin) + '</span><span>' + esc(it.etiquetaMax) + '</span></div>';
    },
    panel: function (it) {
      return '<div class="pv-question">' + textoO(it.instruccion, 'Repaso') + '</div>' +
        '<div class="pv-panel">' + it.tarjetas.map(function (t, i) {
          return '<div><b>' + textoO(t.titulo, 'Concepto ' + (i + 1)) + '</b><small>' + textoO(t.texto, 'Explicación breve') + '</small></div>';
        }).join('') + '</div>';
    },

    pagina: function (it) {
      var parrafos = (it.parrafos || []).filter(function (p) { return !F.vacio(p); });
      return '<div class="pv-pagina-titulo">' + textoO(it.titulo, 'Título de la página…') + '</div>' +
        '<div class="pv-pagina">' + (parrafos.length
          ? parrafos.map(function (p) { return '<p>' + esc(p) + '</p>'; }).join('')
          : '<p><span class="pv-placeholder">Aquí va tu texto explicativo…</span></p>') + '</div>';
    }
  };

  function vistaPrevia(actividad, indice) {
    var tipo = F.tipos[actividad.tipo];
    var item = actividad.items[indice];
    var total = actividad.items.length;
    var imagen = item.imagen ? '<img class="pv-imagen" src="' + esc(item.imagen) + '" alt="">' : '';
    return '<div class="pv-top"><span class="pv-player"><span class="pv-avatar">🦊</span>Tu personaje</span>' +
      (tipo.lectura ? '<span class="pv-timer">⏸ Sin tiempo</span>' : '<span class="pv-timer">⏱ ' + actividad.tiempo + ' s</span>') +
      (tipo.puntua ? '<span>⭐ 0 pts</span>' : '') + '</div>' +
      '<div class="pv-progress"><span style="width:' + Math.round(((indice + 1) / total) * 100) + '%"></span></div>' +
      '<div class="pv-kind">' + tipo.icono + ' ' + (indice + 1) + ' de ' + total + ' · ' + esc(tipo.nombre) + '</div>' +
      imagen +
      vistas[actividad.tipo](item) +
      '<div class="pv-foot">' + (tipo.puntua ? 'Suma puntos por acierto y rapidez'
        : tipo.lectura ? 'No se responde · en vivo la pasas tú con «Siguiente»'
        : 'No suma puntos · va al informe final') + '</div>';
  }

  /** Formatos donde la imagen acompaña al enunciado. */
  var CON_IMAGEN = [
    'quiz', 'verdadero_falso', 'respuesta_multiple', 'palabra_secreta',
    'completar', 'relacionar', 'clasificar', 'ordenar',
    'encuesta', 'pregunta_abierta', 'escala', 'mural',
    'anagrama', 'numerica', 'linea_tiempo', 'pagina'
  ];

  function admiteImagen(tipo) { return CON_IMAGEN.indexOf(tipo) > -1; }

  return {
    esc: esc,
    admiteImagen: admiteImagen,
    campos: function (tipo, item, ctx) {
      return campos[tipo](item, ctx) + (admiteImagen(tipo) ? campoImagen(item) : '');
    },
    vistaPrevia: vistaPrevia
  };
})();
