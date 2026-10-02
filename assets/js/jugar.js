/* =====================================================================
   Ludia — pantalla de respuesta del participante.

   Dibuja la pregunta que llega del servidor (ya sin la clave) y arma la
   respuesta en el formato que espera api/respuesta-enviar.php.
   No decide aciertos: eso lo hace el servidor.

   Al pulsar "Enviar" lanza el evento 'ludia:responder' con la respuesta.
   ===================================================================== */
window.LudiaJugar = (function () {
  'use strict';

  var LETRAS = 'ABCDEFGHIJ';

  /** Segundos a «m:ss». Pequeña y local: esta pantalla no carga formatos.js. */
  function minuto(segundos) {
    var s = Math.max(0, Math.floor(Number(segundos) || 0));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  }
  var contenedor = null;
  var actual = null;      // actividad + item que llegó del servidor
  var seleccion = null;   // estado de la respuesta en curso
  var memoria = null;     // estado propio del juego de memoria

  function esc(valor) {
    return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function clave() { return actual ? actual.indice + '-' + actual.item_indice : ''; }

  /* ---------- Dibujo por tipo ---------- */

  var vistas = {
    quiz: function (it) {
      return '<p class="j-pregunta">' + esc(it.enunciado) + '</p><div class="j-opciones">' +
        it.opciones.map(function (t, i) {
          return '<button type="button" class="j-opcion opt-' + i + '" data-elegir="' + i + '">' +
            '<b>' + LETRAS[i] + '</b> ' + esc(t) + '</button>';
        }).join('') + '</div>';
    },

    video_preguntas: function (it) {
      // `it.video` llega ya como identificador: lo extrae el servidor, porque
      // esta pantalla no carga formatos.js.
      var id = String(it.video || '');
      if (!id) {
        return '<p class="j-pregunta">Este vídeo no está disponible. Avisa a quien te envió la actividad.</p>';
      }

      // La línea de tiempo se escala con la última pregunta más un margen: así
      // no hay que esperar a que YouTube diga cuánto dura el vídeo para
      // dibujarla, y las marcas quedan donde tienen que quedar.
      var marcas = (it.marcas || []).map(Number).filter(function (m) { return m > 0; });
      var fin = Math.max.apply(null, marcas.concat([Number(it.hasta) || 1])) * 1.08;
      var puntos = marcas.map(function (m) {
        var esta = Math.abs(m - Number(it.hasta)) < 0.5;
        return '<span class="j-video-marca' + (esta ? ' actual' : '') + '" style="left:' +
          (m / fin * 100).toFixed(2) + '%" title="Pregunta en ' + minuto(m) + '"></span>';
      }).join('');

      return '<div class="j-video">' +
        '<div class="j-video-marco"><div data-video-hueco></div></div>' +
        '<div class="j-video-linea" aria-hidden="true">' +
          '<span class="j-video-avance" data-video-avance style="width:' + (Number(it.desde) / fin * 100).toFixed(2) + '%"></span>' +
          puntos +
        '</div>' +
        '<p class="j-ayuda" data-video-ayuda>El vídeo se detiene solo en la pregunta ' +
          '(minuto <b>' + minuto(it.hasta) + '</b>).</p>' +
        '</div>' +
        '<div class="j-video-pregunta" data-video-pregunta hidden>' +
          '<p class="j-pregunta">' + esc(it.enunciado) + '</p><div class="j-opciones">' +
          it.opciones.map(function (t, i) {
            return '<button type="button" class="j-opcion opt-' + i + '" data-elegir="' + i + '">' +
              '<b>' + LETRAS[i] + '</b> ' + esc(t) + '</button>';
          }).join('') + '</div></div>';
    },

    respuesta_multiple: function (it) {
      return '<p class="j-pregunta">' + esc(it.enunciado) + '</p>' +
        '<p class="j-ayuda">Marca todas las correctas</p><div class="j-opciones">' +
        it.opciones.map(function (t, i) {
          return '<button type="button" class="j-opcion neutra" data-marcar="' + i + '" aria-pressed="false">' +
            '<b>' + LETRAS[i] + '</b> ' + esc(t) + '</button>';
        }).join('') + '</div>';
    },

    verdadero_falso: function (it) {
      return '<p class="j-pregunta">' + esc(it.afirmacion) + '</p><div class="j-opciones dos">' +
        '<button type="button" class="j-opcion opt-2" data-elegir="true">✔ Verdadero</button>' +
        '<button type="button" class="j-opcion opt-0" data-elegir="false">✘ Falso</button></div>';
    },

    palabra_secreta: function (it) {
      return '<p class="j-ayuda">Pista</p><p class="j-pregunta">' + esc(it.pista) + '</p>' +
        '<p class="j-ayuda">' + it.letras + ' letras</p>' +
        '<input class="input j-input" type="text" data-texto maxlength="40" placeholder="Escribe la palabra" autocomplete="off">';
    },

    completar: function (it) {
      var html = '<p class="j-pregunta j-frase">';
      it.partes.forEach(function (parte, i) {
        html += esc(parte);
        if (i < it.huecos) {
          html += '<input class="j-hueco" type="text" data-hueco="' + i + '" maxlength="40" aria-label="Hueco ' + (i + 1) + '" autocomplete="off">';
        }
      });
      return html + '</p>';
    },

    ordenar_palabras: function (it) {
      return '<p class="j-ayuda">Toca las palabras en el orden correcto</p>' +
        '<div class="j-armada" data-armada aria-live="polite"></div>' +
        '<div class="j-chips">' + it.palabras.map(function (p, i) {
          return '<button type="button" class="j-chip" data-palabra="' + i + '">' + esc(p) + '</button>';
        }).join('') + '</div>';
    },

    sopa_letras: function (it) {
      var celdas = '';
      (it.celdas || []).forEach(function (fila, f) {
        fila.forEach(function (letra, c) {
          celdas += '<button type="button" class="j-letra" data-letra="' + f + '-' + c + '">' + esc(letra) + '</button>';
        });
      });

      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">Toca la primera y la última letra de cada palabra</p>' +
        '<div class="j-sopa-palabras" data-sopa-palabras>' + (it.palabras || []).map(function (p) {
          return '<span class="j-sopa-palabra" data-palabra="' + esc(p) + '">' + esc(p) + '</span>';
        }).join('') + '</div>' +
        '<div class="j-sopa" data-sopa style="grid-template-columns:repeat(' + (it.tamano || 10) + ',1fr)">' + celdas + '</div>';
    },

    crucigrama: function (it) {
      // Cada celda va envuelta: así el número se apoya encima de la casilla y
      // no ocupa un lugar propio en la cuadrícula (eso corría todo de sitio).
      var casillas = '';
      (it.celdas || []).forEach(function (fila, f) {
        fila.forEach(function (celda, c) {
          if (!celda) {
            casillas += '<span class="j-celda vacia"></span>';
            return;
          }
          casillas += '<span class="j-celda">' +
            (celda.numero ? '<span class="j-num">' + celda.numero + '</span>' : '') +
            '<input class="j-casilla" data-casilla="' + f + '-' + c + '" maxlength="1" autocomplete="off"' +
            ' aria-label="Fila ' + (f + 1) + ', columna ' + (c + 1) + '"></span>';
        });
      });

      // Dos pistas pueden compartir número (cuando una horizontal y una vertical
      // arrancan en la misma casilla), así que el número lleva su sentido.
      var grupo = function (titulo, fichas, sentido) {
        var flecha = sentido === 'h' ? '→' : '↓';
        return '<div class="j-cruci-grupo"><b>' + titulo + '</b>' + fichas.map(function (f) {
          return '<button type="button" class="j-pista" data-pista="' + f.numero + sentido + '"' +
            ' data-fila="' + f.fila + '" data-col="' + f.columna + '" data-largo="' + f.largo + '" data-sentido="' + sentido + '">' +
            '<span class="n">' + f.numero + flecha + '</span>' + esc(f.pista) + '</button>';
        }).join('') + '</div>';
      };

      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">Toca una pista y escribe la palabra</p>' +
        '<div class="j-cruci" data-cruci style="grid-template-columns:repeat(' + (it.columnas || 1) + ',1fr)">' + casillas + '</div>' +
        '<div class="j-cruci-pistas">' +
        grupo('Horizontales', it.horizontales || [], 'h') +
        grupo('Verticales', it.verticales || [], 'v') + '</div>';
    },

    relacionar: function (it) {
      // Cada lado puede venir como texto suelto (contenido de antes) o como
      // {texto, imagen}. Se normaliza aquí para no repetir la comprobación.
      function lado(x) {
        return (x && typeof x === 'object') ? x : { texto: String(x == null ? '' : x), imagen: '' };
      }

      var izquierda = it.izquierda.map(lado);
      var derecha = it.derecha.map(lado);
      var hayImagenDerecha = derecha.some(function (d) { return d.imagen; });

      // Un <select> NO puede mostrar imágenes. Cuando el lado que se elige las
      // tiene, se pintan arriba en una galería con su etiqueta: así se ven, y
      // elegir sigue siendo por texto, que funciona con teclado y con lector
      // de pantalla.
      var galeria = hayImagenDerecha
        ? '<div class="j-galeria">' + derecha.map(function (d) {
            return '<figure class="j-galeria-item">' +
              (d.imagen ? '<img src="' + esc(d.imagen) + '" alt="' + esc(d.texto) + '">' : '') +
              '<figcaption>' + esc(d.texto) + '</figcaption></figure>';
          }).join('') + '</div>'
        : '';

      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' + galeria +
        izquierda.map(function (izq, i) {
          var muestra = izq.imagen
            ? '<span class="j-par-img"><img src="' + esc(izq.imagen) + '" alt="' + esc(izq.texto) + '">' +
              '<span>' + esc(izq.texto) + '</span></span>'
            : '<span>' + esc(izq.texto) + '</span>';

          return '<label class="j-par' + (izq.imagen ? ' j-par-con-img' : '') + '">' + muestra +
            '<select class="select" data-par="' + i + '"><option value="">Elige…</option>' +
            derecha.map(function (d) {
              return '<option value="' + esc(d.texto) + '">' + esc(d.texto) + '</option>';
            }).join('') +
            '</select></label>';
        }).join('');
    },

    clasificar: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        it.elementos.map(function (elemento, i) {
          return '<label class="j-par"><span>' + esc(elemento) + '</span>' +
            '<select class="select" data-clasificar="' + esc(elemento) + '" data-indice="' + i + '"><option value="">Elige…</option>' +
            it.categorias.map(function (c, ci) { return '<option value="' + ci + '">' + esc(c) + '</option>'; }).join('') +
            '</select></label>';
        }).join('');
    },

    ordenar: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">Usa las flechas para ordenar</p><ol class="j-orden" data-orden></ol>';
    },

    memoria: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda"><span data-memoria-conteo>0</span> de ' + (it.cartas.length / 2) + ' parejas</p>' +
        '<div class="j-cartas" data-cartas>' + it.cartas.map(function (carta, i) {
          return '<button type="button" class="j-carta" data-carta="' + i + '" data-pareja="' + carta.pareja + '">' +
            '<span class="j-carta-reverso">?</span><span class="j-carta-frente">' +
            (carta.imagen
              ? '<img src="' + esc(carta.imagen) + '" alt="' + esc(carta.texto) + '">'
              : esc(carta.texto)) + '</span></button>';
        }).join('') + '</div>';
    },

    ortografia: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">Toca la palabra bien escrita de cada pareja</p>' +
        (it.parejas || []).map(function (par, i) {
          return '<div class="j-orto" data-orto="' + i + '">' + par.opciones.map(function (texto) {
            return '<button type="button" class="j-orto-opcion" data-orto-fila="' + i + '" data-orto-valor="' + esc(texto) + '">' +
              esc(texto) + '</button>';
          }).join('') + '</div>';
        }).join('');
    },

    anagrama: function (it) {
      return (it.pista ? '<p class="j-pregunta">' + esc(it.pista) + '</p>' : '<p class="j-pregunta">Arma la palabra</p>') +
        '<p class="j-ayuda">Toca las letras en orden · ' + it.largo + ' letras</p>' +
        '<div class="j-armada" data-armada aria-live="polite"></div>' +
        '<div class="j-chips">' + (it.letras || []).map(function (l, i) {
          return '<button type="button" class="j-chip" data-palabra="' + i + '">' + esc(l) + '</button>';
        }).join('') + '</div>';
    },

    numerica: function (it) {
      return '<p class="j-pregunta">' + esc(it.enunciado) + '</p>' +
        '<div class="j-numerica">' +
        '<input class="input j-numero" type="text" inputmode="decimal" data-texto autocomplete="off" placeholder="0">' +
        (it.unidad ? '<span class="j-unidad">' + esc(it.unidad) + '</span>' : '') + '</div>';
    },

    linea_tiempo: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">Del más antiguo al más reciente</p>' +
        '<ol class="j-linea" data-orden></ol>';
    },

    encuesta: function (it) {
      return '<p class="j-pregunta">' + esc(it.pregunta) + '</p><div class="j-opciones">' +
        it.opciones.map(function (t, i) {
          return '<button type="button" class="j-opcion neutra" data-elegir="' + i + '">' + esc(t) + '</button>';
        }).join('') + '</div>';
    },

    escala: function (it) {
      return '<p class="j-pregunta">' + esc(it.pregunta) + '</p>' +
        '<div class="j-escala">' + [1, 2, 3, 4, 5].map(function (n) {
          return '<button type="button" class="j-nivel" data-elegir="' + n + '">' + n + '</button>';
        }).join('') + '</div>' +
        '<div class="j-escala-pies"><span>' + esc(it.etiquetaMin) + '</span><span>' + esc(it.etiquetaMax) + '</span></div>';
    },

    pregunta_abierta: function (it) {
      return '<p class="j-pregunta">' + esc(it.pregunta) + '</p>' +
        '<textarea class="textarea" data-texto maxlength="' + it.maxCaracteres + '" rows="3" placeholder="Tu respuesta"></textarea>' +
        '<p class="j-ayuda">Máximo ' + it.maxCaracteres + ' caracteres</p>';
    },

    mural: function (it) {
      return '<p class="j-pregunta">' + esc(it.consigna) + '</p>' +
        '<textarea class="textarea" data-texto maxlength="' + it.maxCaracteres + '" rows="3" placeholder="Escribe tu aporte"></textarea>' +
        '<p class="j-ayuda">' + (it.anonimo ? 'Tu aporte es anónimo' : 'Tu aporte se muestra con tu nombre') +
        (it.moderar ? ' · lo revisa el anfitrión antes de publicarse' : '') + ' · puedes enviar varios</p>';
    },

    panel: function (it) {
      return '<p class="j-pregunta">' + esc(it.instruccion) + '</p><div class="j-panel">' +
        it.tarjetas.map(function (t) {
          return '<div><b>' + esc(t.titulo) + '</b><span>' + esc(t.texto) + '</span></div>';
        }).join('') + '</div><p class="j-ayuda">Solo para leer: no hay que responder.</p>';
    },

    pagina: function (it) {
      return '<h2 class="j-pagina-titulo">' + esc(it.titulo) + '</h2>' +
        '<div class="j-pagina">' + (it.parrafos || []).map(function (p) {
          return '<p>' + esc(p) + '</p>';
        }).join('') + '</div>' +
        '<p class="j-ayuda">Solo para leer: no hay que responder.</p>';
    }
  };

  /** Pantallas que se leen y no se responden. */
  var SOLO_LECTURA = ['panel', 'pagina'];

  /* ---------- Estado inicial de la respuesta ---------- */

  function estadoInicial(tipo, item) {
    switch (tipo) {
      case 'respuesta_multiple': return [];
      case 'ordenar_palabras': return [];
      case 'clasificar': return {};
      case 'ordenar': return item.elementos.slice();
      case 'memoria': return { encontradas: 0 };
      case 'sopa_letras': return { halladas: [], desde: null };
      case 'crucigrama': return { pista: null };
      case 'ortografia': return {};
      case 'anagrama': return [];
      case 'linea_tiempo': return item.hechos.slice();
      default: return null;
    }
  }

  /* ---------- Sopa de letras: se marca de una letra a otra ---------- */

  /** Letras que hay entre dos casillas, si están alineadas. */
  function caminoSopa(desde, hasta, celdas) {
    var df = Math.sign(hasta.f - desde.f);
    var dc = Math.sign(hasta.c - desde.c);
    var pasosF = Math.abs(hasta.f - desde.f);
    var pasosC = Math.abs(hasta.c - desde.c);

    // Vale en línea recta: horizontal, vertical o diagonal exacta.
    if (pasosF !== 0 && pasosC !== 0 && pasosF !== pasosC) return null;

    var pasos = Math.max(pasosF, pasosC);
    var camino = [];
    for (var i = 0; i <= pasos; i++) {
      var f = desde.f + df * i;
      var c = desde.c + dc * i;
      if (!celdas[f] || celdas[f][c] === undefined) return null;
      camino.push({ f: f, c: c, letra: celdas[f][c] });
    }
    return camino;
  }

  function marcarSopa(boton) {
    var partes = boton.getAttribute('data-letra').split('-');
    var punto = { f: Number(partes[0]), c: Number(partes[1]) };

    if (!seleccion.desde) {
      seleccion.desde = punto;
      boton.classList.add('marcada');
      return;
    }

    var camino = caminoSopa(seleccion.desde, punto, actual.item.celdas);
    contenedor.querySelectorAll('.j-letra.marcada').forEach(function (b) { b.classList.remove('marcada'); });
    seleccion.desde = null;

    if (!camino) return;

    var palabra = camino.map(function (p) { return p.letra; }).join('');
    var alReves = palabra.split('').reverse().join('');
    var buscada = null;

    contenedor.querySelectorAll('[data-palabra]').forEach(function (chip) {
      var valor = chip.getAttribute('data-palabra');
      if (valor === palabra || valor === alReves) buscada = { chip: chip, valor: valor };
    });

    if (!buscada || seleccion.halladas.indexOf(buscada.valor) > -1) return;

    seleccion.halladas.push(buscada.valor);
    buscada.chip.classList.add('hallada');
    camino.forEach(function (p) {
      var celda = contenedor.querySelector('[data-letra="' + p.f + '-' + p.c + '"]');
      if (celda) celda.classList.add('hallada');
    });
  }

  /* ---------- Crucigrama: se elige pista y se escribe ---------- */

  function casillasDePista(boton) {
    var fila = Number(boton.getAttribute('data-fila'));
    var col = Number(boton.getAttribute('data-col'));
    var largo = Number(boton.getAttribute('data-largo'));
    var horizontal = boton.getAttribute('data-sentido') === 'h';

    var lista = [];
    for (var i = 0; i < largo; i++) {
      var f = horizontal ? fila : fila + i;
      var c = horizontal ? col + i : col;
      var casilla = contenedor.querySelector('[data-casilla="' + f + '-' + c + '"]');
      if (casilla) lista.push(casilla);
    }
    return lista;
  }

  function elegirPista(boton) {
    seleccion.pista = boton.getAttribute('data-pista');

    contenedor.querySelectorAll('.j-pista').forEach(function (p) { p.classList.toggle('activa', p === boton); });
    contenedor.querySelectorAll('.j-casilla').forEach(function (c) { c.classList.remove('activa'); });

    var casillas = casillasDePista(boton);
    casillas.forEach(function (c) { c.classList.add('activa'); });
    if (casillas[0]) casillas[0].focus();
  }

  /** Lo escrito en cada pista, con la clave que espera el servidor. */
  function leerCrucigrama() {
    var respuestas = {};
    contenedor.querySelectorAll('.j-pista').forEach(function (boton) {
      var texto = casillasDePista(boton).map(function (c) { return c.value || ' '; }).join('').trim();
      if (texto) respuestas[boton.getAttribute('data-pista')] = texto;
    });
    return respuestas;
  }

  /* ---------- Lectura de la respuesta ---------- */

  function leer() {
    var tipo = actual.item.tipo;
    var campoTexto = contenedor.querySelector('[data-texto]');

    switch (tipo) {
      case 'quiz':
      case 'video_preguntas':
      case 'encuesta':
        return seleccion;
      case 'escala':
        return seleccion;
      case 'verdadero_falso':
        return seleccion === 'true';
      case 'respuesta_multiple':
        return seleccion.slice().sort(function (a, b) { return a - b; });
      case 'palabra_secreta':
      case 'pregunta_abierta':
      case 'mural':
      case 'numerica':
        return campoTexto ? campoTexto.value.trim() : '';
      case 'completar':
        return [].map.call(contenedor.querySelectorAll('[data-hueco]'), function (i) { return i.value.trim(); });
      case 'sopa_letras':
        return seleccion.halladas.slice();
      case 'crucigrama':
        return leerCrucigrama();
      case 'ordenar_palabras':
        return seleccion.map(function (i) { return actual.item.palabras[i]; });
      case 'ortografia':
        return Object.keys(seleccion).sort(function (a, b) { return a - b; }).map(function (k) { return seleccion[k]; });
      case 'anagrama':
        return seleccion.map(function (i) { return actual.item.letras[i]; }).join('');
      case 'linea_tiempo':
        return seleccion.slice();
      case 'relacionar':
        return [].map.call(contenedor.querySelectorAll('[data-par]'), function (s) { return s.value; });
      case 'clasificar':
        return seleccion;
      case 'ordenar':
        return seleccion.slice();
      case 'memoria':
        return { encontradas: seleccion.encontradas };
      default:
        return null;
    }
  }

  /** ¿Se puede enviar ya? */
  function completa() {
    var tipo = actual.item.tipo;
    var valor = leer();

    switch (tipo) {
      case 'quiz':
      case 'video_preguntas':
      case 'encuesta':
      case 'escala':
        return seleccion !== null;
      case 'verdadero_falso':
        return seleccion !== null;
      case 'respuesta_multiple':
        return valor.length > 0;
      case 'palabra_secreta':
      case 'pregunta_abierta':
      case 'mural':
        return String(valor).length > 0;
      case 'completar':
        return valor.some(function (t) { return t.length > 0; });
      case 'sopa_letras':
        return valor.length > 0;
      case 'crucigrama':
        return Object.keys(valor).length > 0;
      case 'ordenar_palabras':
        return valor.length === actual.item.palabras.length;
      case 'ortografia':
        return valor.length === (actual.item.parejas || []).length;
      case 'anagrama':
        return valor.length === actual.item.largo;
      case 'linea_tiempo':
        return true;
      case 'numerica':
        return String(valor).trim().length > 0;
      case 'relacionar':
        return valor.every(function (v) { return v !== ''; });
      case 'clasificar':
        return Object.keys(valor).length === actual.item.elementos.length;
      case 'ordenar':
      case 'memoria':
        return true;
      default:
        return false;
    }
  }

  /* ---------- Pintado de partes dinámicas ---------- */

  function refrescarOrden() {
    var lista = contenedor.querySelector('[data-orden]');
    if (!lista) return;
    lista.innerHTML = seleccion.map(function (texto, i) {
      return '<li><span>' + esc(texto) + '</span><span class="j-flechas">' +
        '<button type="button" class="icon-btn" data-subir="' + i + '"' + (i === 0 ? ' disabled' : '') + ' aria-label="Subir">↑</button>' +
        '<button type="button" class="icon-btn" data-bajar="' + i + '"' + (i === seleccion.length - 1 ? ' disabled' : '') + ' aria-label="Bajar">↓</button>' +
        '</span></li>';
    }).join('');
  }

  function refrescarArmada() {
    var caja = contenedor.querySelector('[data-armada]');
    if (!caja) return;
    var piezas = actual.item.tipo === 'anagrama' ? actual.item.letras : actual.item.palabras;
    caja.innerHTML = seleccion.length
      ? seleccion.map(function (i) { return '<button type="button" class="j-chip puesto" data-quitar="' + i + '">' + esc(piezas[i]) + '</button>'; }).join('')
      : '<span class="j-ayuda">' + (actual.item.tipo === 'anagrama' ? 'Tu palabra aparecerá aquí' : 'Tu frase aparecerá aquí') + '</span>';
    [].forEach.call(contenedor.querySelectorAll('[data-palabra]'), function (chip) {
      chip.disabled = seleccion.indexOf(Number(chip.getAttribute('data-palabra'))) > -1;
    });
  }

  function refrescarEnviar() {
    var boton = contenedor.querySelector('[data-enviar]');
    if (boton) boton.disabled = !completa();
  }

  /* ---------- Eventos ---------- */

  function alPulsar(evento) {
    var boton = evento.target.closest('button');
    if (!boton || !contenedor.contains(boton)) return;

    if (boton.hasAttribute('data-elegir')) {
      seleccion = boton.getAttribute('data-elegir');
      if (actual.item.tipo !== 'verdadero_falso') seleccion = Number(seleccion);
      [].forEach.call(contenedor.querySelectorAll('[data-elegir]'), function (b) {
        b.classList.toggle('elegida', b === boton);
      });
    } else if (boton.hasAttribute('data-marcar')) {
      var indice = Number(boton.getAttribute('data-marcar'));
      var posicion = seleccion.indexOf(indice);
      if (posicion > -1) seleccion.splice(posicion, 1); else seleccion.push(indice);
      boton.classList.toggle('elegida', posicion === -1);
      boton.setAttribute('aria-pressed', String(posicion === -1));
    } else if (boton.hasAttribute('data-palabra')) {
      seleccion.push(Number(boton.getAttribute('data-palabra')));
      refrescarArmada();
    } else if (boton.hasAttribute('data-quitar')) {
      seleccion.splice(seleccion.indexOf(Number(boton.getAttribute('data-quitar'))), 1);
      refrescarArmada();
    } else if (boton.hasAttribute('data-subir') || boton.hasAttribute('data-bajar')) {
      var desde = Number(boton.getAttribute('data-subir') || boton.getAttribute('data-bajar'));
      var hasta = boton.hasAttribute('data-subir') ? desde - 1 : desde + 1;
      var movido = seleccion.splice(desde, 1)[0];
      seleccion.splice(hasta, 0, movido);
      refrescarOrden();
    } else if (boton.hasAttribute('data-carta')) {
      voltear(boton);
    } else if (boton.hasAttribute('data-orto-fila')) {
      var filaOrto = boton.getAttribute('data-orto-fila');
      seleccion[filaOrto] = boton.getAttribute('data-orto-valor');
      contenedor.querySelectorAll('[data-orto-fila="' + filaOrto + '"]').forEach(function (b) {
        b.classList.toggle('elegida', b === boton);
      });
    } else if (boton.hasAttribute('data-letra')) {
      marcarSopa(boton);
    } else if (boton.hasAttribute('data-pista')) {
      elegirPista(boton);
    } else if (boton.hasAttribute('data-enviar')) {
      enviar();
      return;
    } else {
      return;
    }

    refrescarEnviar();
  }

  // Memoria: dos cartas volteadas; si coinciden se quedan, si no se giran de vuelta.
  function voltear(carta) {
    if (carta.classList.contains('vista') || memoria.bloqueado || memoria.abiertas.length === 2) return;
    carta.classList.add('vista');
    memoria.abiertas.push(carta);
    if (memoria.abiertas.length < 2) return;

    var a = memoria.abiertas[0];
    var b = memoria.abiertas[1];
    if (a.getAttribute('data-pareja') === b.getAttribute('data-pareja') && a !== b) {
      a.classList.add('lograda');
      b.classList.add('lograda');
      memoria.abiertas = [];
      seleccion.encontradas++;
      var conteo = contenedor.querySelector('[data-memoria-conteo]');
      if (conteo) conteo.textContent = String(seleccion.encontradas);
      refrescarEnviar();
    } else {
      memoria.bloqueado = true;
      setTimeout(function () {
        a.classList.remove('vista');
        b.classList.remove('vista');
        memoria.abiertas = [];
        memoria.bloqueado = false;
      }, 900);
    }
  }

  function alEscribir(evento) {
    // En el crucigrama, al escribir una letra se salta a la casilla siguiente.
    var casilla = evento && evento.target && evento.target.classList.contains('j-casilla') ? evento.target : null;
    if (casilla && casilla.value) {
      casilla.value = casilla.value.toUpperCase().slice(-1);
      var activas = [].slice.call(contenedor.querySelectorAll('.j-casilla.activa'));
      var siguiente = activas[activas.indexOf(casilla) + 1];
      if (siguiente) siguiente.focus();
    }
    refrescarEnviar();
  }

  function enviar() {
    var boton = contenedor.querySelector('[data-enviar]');
    if (boton) boton.disabled = true;
    contenedor.dispatchEvent(new CustomEvent('ludia:responder', {
      bubbles: true,
      detail: { actividad: actual.indice, item: actual.item_indice, respuesta: leer(), tipo: actual.item.tipo }
    }));
  }

  /* ---------- Vídeo con preguntas ---------- */

  /**
   * Carga la API de YouTube una sola vez para toda la página.
   *
   * YouTube llama a `onYouTubeIframeAPIReady` cuando está lista, y es una sola
   * función global: se encadena la anterior por si algo más la usaba.
   */
  function cargarApiYouTube(alEstar) {
    if (window.YT && window.YT.Player) { alEstar(); return; }

    var previo = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
      if (typeof previo === 'function') previo();
      alEstar();
    };

    if (!document.querySelector('script[data-youtube-api]')) {
      var s = document.createElement('script');
      s.src = 'https://www.youtube.com/iframe_api';
      s.setAttribute('data-youtube-api', '1');
      document.head.appendChild(s);
    }
  }

  /**
   * Reproduce el tramo que toca y destapa la pregunta al llegar.
   *
   * La red de seguridad importa: si YouTube no carga, el vídeo está bloqueado
   * en la red del colegio o el reproductor falla, la pregunta aparece igual
   * pasado el tiempo del tramo. Un estudiante atascado sin poder continuar es
   * peor que uno que se salte un trozo.
   */
  function montarVideo(item) {
    var hueco = contenedor.querySelector('[data-video-hueco]');
    if (!hueco) return;

    var desde = Math.max(0, Number(item.desde) || 0);
    var hasta = Math.max(desde + 1, Number(item.hasta) || 0);
    var marcas = (item.marcas || []).map(Number).filter(function (m) { return m > 0; });
    var fin = Math.max.apply(null, marcas.concat([hasta])) * 1.08;

    var avance = contenedor.querySelector('[data-video-avance]');
    var ayuda = contenedor.querySelector('[data-video-ayuda]');
    var reloj = null;
    var destapado = false;

    function destapar() {
      if (destapado) return;
      destapado = true;
      if (reloj) clearInterval(reloj);

      var pregunta = contenedor.querySelector('[data-video-pregunta]');
      if (pregunta) pregunta.hidden = false;
      if (ayuda) ayuda.textContent = 'Responde para continuar.';
      if (avance) avance.style.width = (hasta / fin * 100).toFixed(2) + '%';
      refrescarEnviar();
    }

    cargarApiYouTube(function () {
      // El contenedor pudo cambiar mientras cargaba la API (otra pregunta).
      if (!contenedor.contains(hueco)) return;

      var reproductor;
      try {
        reproductor = new window.YT.Player(hueco, {
          videoId: String(item.video || ''),
          playerVars: {
            start: desde, end: hasta, autoplay: 1, rel: 0,
            modestbranding: 1, playsinline: 1
          },
          events: {
            onStateChange: function (e) {
              if (e.data === window.YT.PlayerState.ENDED) destapar();
            },
            onError: destapar
          }
        });
      } catch (e) {
        destapar();
        return;
      }

      var limite = Date.now() + (hasta - desde + 8) * 1000;
      reloj = setInterval(function () {
        if (!contenedor.contains(hueco)) { clearInterval(reloj); return; }

        var t = desde;
        try { t = reproductor.getCurrentTime ? reproductor.getCurrentTime() : desde; } catch (e) { /* aún no */ }

        if (avance) avance.style.width = Math.min(100, t / fin * 100).toFixed(2) + '%';
        // Medio segundo de margen: `end` de YouTube no es exacto al fotograma.
        if (t >= hasta - 0.5 || Date.now() > limite) destapar();
      }, 500);
    });
  }

  /* ---------- API del módulo ---------- */

  /**
   * Dibuja la pregunta actual.
   * @param {HTMLElement} caja  contenedor donde se dibuja
   * @param {object} actividad  lo que devuelve api/sesion-estado.php
   * @param {object|null} mia   respuesta ya enviada por este participante
   */
  function pintar(caja, actividad, mia) {
    var mismaPregunta = actual && clave() === actividad.indice + '-' + actividad.item_indice;
    contenedor = caja;

    // Mientras sea la misma pregunta y no se haya respondido, no se redibuja:
    // así no se borra lo que el participante está escribiendo.
    if (mismaPregunta && !mia && caja.querySelector('[data-enviar]')) return;

    actual = actividad;
    var item = actividad.item;
    var tipo = item.tipo;
    var soloLectura = SOLO_LECTURA.indexOf(tipo) > -1;

    if (mia && tipo !== 'mural') {
      caja.innerHTML = vistas[tipo] ? '<div class="j-enviada">' + resumenEnviado(mia) + '</div>' : '';
      return;
    }

    seleccion = estadoInicial(tipo, item);
    memoria = { abiertas: [], bloqueado: false };

    var imagen = item.imagen
      ? '<img class="j-imagen" src="' + esc(item.imagen) + '" alt="Imagen de la pregunta">'
      : '';

    caja.innerHTML = imagen + (vistas[tipo] ? vistas[tipo](item) : '<p class="j-pregunta">Actividad no disponible</p>') +
      (soloLectura ? '' : '<button class="btn btn-primary btn-block" type="button" data-enviar disabled>' +
        (tipo === 'mural' ? 'Enviar aporte' : 'Enviar respuesta') + '</button>');

    if (tipo === 'video_preguntas') montarVideo(item);
    if (tipo === 'ordenar' || tipo === 'linea_tiempo') refrescarOrden();
    if (tipo === 'ordenar_palabras' || tipo === 'anagrama') refrescarArmada();
    if (tipo === 'clasificar') {
      caja.addEventListener('change', function (e) {
        var select = e.target.closest('[data-clasificar]');
        if (!select) return;
        if (select.value === '') delete seleccion[select.getAttribute('data-clasificar')];
        else seleccion[select.getAttribute('data-clasificar')] = Number(select.value);
        refrescarEnviar();
      });
    }

    caja.addEventListener('click', alPulsar);
    caja.addEventListener('input', alEscribir);
    caja.addEventListener('change', alEscribir);
    refrescarEnviar();
  }

  function resumenEnviado(mia) {
    if (mia.correcta === null) {
      return '<b>✓ Aporte enviado</b><span>Gracias, ya quedó registrado.</span>';
    }
    if (mia.correcta) {
      return '<b class="bien">🎉 ¡Correcto!</b><span>+' + mia.puntos + ' puntos</span>';
    }
    if (mia.aciertos > 0) {
      return '<b class="parcial">Casi</b><span>' + mia.aciertos + ' de ' + mia.total + ' · +' + mia.puntos + ' puntos</span>';
    }
    return '<b class="mal">Respuesta enviada</b><span>Esta vez no acertaste. Sigue en la próxima.</span>';
  }

  /** Vuelve a permitir un nuevo aporte (mural). */
  function reiniciar() { actual = null; }

  /** ¿Esta pantalla se lee y no se responde? La usa unirse.js para decidir si
   *  hace falta un botón propio de "Continuar" en los paquetes enviados. */
  function esSoloLectura(tipo) { return SOLO_LECTURA.indexOf(tipo) > -1; }

  return {
    pintar: pintar,
    reiniciar: reiniciar,
    resumenEnviado: resumenEnviado,
    esSoloLectura: esSoloLectura,
    esc: esc
  };
})();
