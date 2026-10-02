/* =====================================================================
   Ludia — sala del anfitrión: acceso (código, enlace, QR), proyección de
   la pregunta en curso y resultados finales.
   Sin WebSockets: sondeo cada 3 segundos, que funciona igual en XAMPP,
   hosting compartido y VPS.
   ===================================================================== */
(function () {
  'use strict';

  var sala = document.querySelector('[data-sala]');
  if (!sala) return;

  var P = window.LudiaPersonaje;
  var CODIGO = sala.getAttribute('data-codigo');
  var MAX = Number(sala.getAttribute('data-max'));
  var MODO = sala.getAttribute('data-modo') || 'vivo';
  var INTERVALO = 3000;
  var LETRAS = 'ABCDEFGHIJ';

  var el = {
    lobby: document.getElementById('lobby'),
    qr: document.getElementById('qr'),
    enlace: document.getElementById('enlace'),
    copiar: document.getElementById('btn-copiar'),
    avisoCopia: document.getElementById('aviso-copia'),
    participantes: document.getElementById('participantes'),
    progreso: document.getElementById('progreso'),
    contador: document.getElementById('contador'),
    estado: document.getElementById('estado-sala'),
    barra: document.getElementById('bar-status'),
    comenzar: document.getElementById('btn-comenzar'),
    siguiente: document.getElementById('btn-siguiente'),
    terminar: document.getElementById('btn-terminar'),
    proyeccion: document.getElementById('proyeccion'),
    proyTitulo: document.getElementById('proy-titulo'),
    proyCrono: document.getElementById('proy-crono'),
    proyConteo: document.getElementById('proy-conteo'),
    proyCuerpo: document.getElementById('proy-cuerpo'),
    proyBarra: document.getElementById('proy-barra'),
    resultado: document.getElementById('resultado'),
    podio: document.getElementById('podio'),
    informe: document.getElementById('informe'),
    marcador: document.getElementById('marcador'),
    equiposCaja: document.getElementById('equipos-caja'),
    equiposLista: document.getElementById('equipos-lista'),
    equiposAviso: document.getElementById('equipos-aviso'),
    equiposCuantos: document.getElementById('equipos-cuantos'),
    equiposAzar: document.getElementById('btn-equipos-azar'),
    equiposMano: document.getElementById('btn-equipos-mano'),
    equiposDeshacer: document.getElementById('btn-equipos-deshacer')
  };

  // El servidor entrega el token cuando la sala es de quien la está viendo. El
  // de localStorage queda como respaldo para las salas creadas antes de eso.
  var token = sala.getAttribute('data-token') || '';
  if (!token) {
    try { token = localStorage.getItem('ludia.anfitrion.' + CODIGO) || ''; } catch (e) { token = ''; }
  }

  var urlUnirse = location.origin + location.pathname.replace(/sesion\.php$/, 'unirse.php') + '?c=' + CODIGO;
  var ultimoEstado = sala.getAttribute('data-estado');
  var preguntaActual = '';
  var finCronometro = 0;

  function esc(texto) {
    return String(texto == null ? '' : texto).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Enlace y QR ---------- */

  el.enlace.value = urlUnirse;

  if (typeof window.qrcode === 'function') {
    var qr = window.qrcode(0, 'M');
    qr.addData(urlUnirse);
    qr.make();
    el.qr.innerHTML = qr.createSvgTag({ scalable: true, margin: 1 });
  } else {
    el.qr.innerHTML = '<p class="hint">No se pudo dibujar el QR. Comparte el código o el enlace.</p>';
  }

  el.copiar.addEventListener('click', function () {
    var listo = function () {
      el.avisoCopia.hidden = false;
      setTimeout(function () { el.avisoCopia.hidden = true; }, 2500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(urlUnirse).then(listo, seleccionar);
    } else {
      seleccionar();
    }
  });

  function seleccionar() {
    el.enlace.focus();
    el.enlace.select();
  }

  /* ---------- Participantes en la sala de espera ---------- */

  function pintarParticipantes(lista) {
    if (!lista.length) {
      el.participantes.innerHTML = '<div class="participantes-vacio">' +
        '<b aria-hidden="true">👋</b><span>Nadie ha entrado todavía. Proyecta el código o el QR.</span></div>';
      return;
    }
    el.participantes.innerHTML = lista.map(function (p) {
      return '<div class="participante' + (p.conectado ? '' : ' ausente') + '">' +
        P.avatar(p.personaje, 'md') +
        '<span class="participante-nombre">' + esc(p.nombre) + '</span>' +
        (p.conectado ? '' : '<span class="participante-nota">se desconectó</span>') +
        '</div>';
    }).join('');
  }

  /* ---------- Equipos (solo en vivo, y solo antes de comenzar) ---------- */

  // El lobby se repinta cada 3 s. Sin esta firma, un <select> abierto se
  // cerraría solo en mitad del gesto de mover a alguien de equipo.
  var firmaEquipos = '';

  function opcionesEquipo(equipos, actual) {
    return '<option value="">— sin equipo —</option>' + equipos.map(function (e) {
      return '<option value="' + e.id + '"' + (e.id === actual ? ' selected' : '') + '>' + esc(e.nombre) + '</option>';
    }).join('');
  }

  function pintarEquipos(datos) {
    if (!el.equiposCaja) return;   // en un paquete enviado ni existe la caja

    var equipos = datos.equipos || [];
    var gente = datos.participantes || [];

    // Armar equipos es cosa del anfitrión, y solo en la sala de espera.
    el.equiposCaja.hidden = !token || datos.sesion.estado !== 'lobby';
    if (el.equiposCaja.hidden) return;

    var firma = JSON.stringify([
      equipos.map(function (e) { return [e.id, e.nombre, e.integrantes]; }),
      gente.map(function (p) { return [p.id, p.equipo_id]; })
    ]);
    if (firma === firmaEquipos) return;
    firmaEquipos = firma;

    el.equiposDeshacer.hidden = equipos.length === 0;

    if (!equipos.length) {
      el.equiposLista.innerHTML = '';
      el.equiposAviso.textContent = 'Reparte a tu grupo en equipos: los puntos de cada quien suman al suyo.';
      return;
    }

    var porEquipo = {};
    var sueltos = [];
    equipos.forEach(function (e) { porEquipo[e.id] = []; });
    gente.forEach(function (p) {
      if (p.equipo_id && porEquipo[p.equipo_id]) porEquipo[p.equipo_id].push(p);
      else sueltos.push(p);
    });

    function fila(p, actual) {
      return '<li><span class="equipo-quien">' + esc(p.nombre) + '</span>' +
        '<select class="select select-sm" data-mover="' + p.id + '" aria-label="Equipo de ' + esc(p.nombre) + '">' +
        opcionesEquipo(equipos, actual) + '</select></li>';
    }

    el.equiposLista.innerHTML = equipos.map(function (e) {
      var suyos = porEquipo[e.id];
      return '<div class="equipo-tarjeta eq-' + esc(e.color) + '">' +
        '<div class="equipo-cab"><b>' + esc(e.nombre) + '</b><span class="equipo-cuenta">' + suyos.length + '</span></div>' +
        (suyos.length
          ? '<ul class="equipo-gente">' + suyos.map(function (p) { return fila(p, e.id); }).join('') + '</ul>'
          : '<p class="equipo-vacio">Nadie todavía</p>') +
        '</div>';
    }).join('') + (sueltos.length
      ? '<div class="equipo-tarjeta eq-sueltos">' +
        '<div class="equipo-cab"><b>Sin equipo</b><span class="equipo-cuenta">' + sueltos.length + '</span></div>' +
        '<ul class="equipo-gente">' + sueltos.map(function (p) { return fila(p, null); }).join('') + '</ul></div>'
      : '');

    el.equiposAviso.textContent = sueltos.length
      ? sueltos.length + (sueltos.length === 1 ? ' persona sin equipo' : ' personas sin equipo') + ': repártelas antes de comenzar.'
      : 'Equipos listos. Al comenzar, el marcador y el podio serán por equipo.';
  }

  /* ---------- Proyección de la pregunta ---------- */

  // Cada vista recibe el ítem público, la clave (solo la ve el anfitrión) y los aportes recibidos.
  var vistas = {
    quiz: function (it, clave) {
      return '<p class="proy-pregunta">' + esc(it.enunciado) + '</p><div class="proy-opciones">' +
        it.opciones.map(function (t, i) {
          var buena = clave && clave.correcta === i;
          return '<div class="proy-opcion opt-' + i + (buena ? ' correcta' : '') + '"><b>' + LETRAS[i] + '</b> ' + esc(t) +
            (buena ? '<span class="marca">✓</span>' : '') + '</div>';
        }).join('') + '</div>';
    },
    respuesta_multiple: function (it, clave) {
      var correctas = (clave && clave.correctas) || [];
      return '<p class="proy-pregunta">' + esc(it.enunciado) + '</p><div class="proy-opciones">' +
        it.opciones.map(function (t, i) {
          var buena = correctas.indexOf(i) > -1;
          return '<div class="proy-opcion neutra' + (buena ? ' correcta' : '') + '"><b>' + LETRAS[i] + '</b> ' + esc(t) +
            (buena ? '<span class="marca">✓</span>' : '') + '</div>';
        }).join('') + '</div>';
    },
    verdadero_falso: function (it, clave) {
      return '<p class="proy-pregunta">' + esc(it.afirmacion) + '</p><div class="proy-opciones">' +
        '<div class="proy-opcion opt-2' + (clave && clave.correcta ? ' correcta' : '') + '">✔ Verdadero</div>' +
        '<div class="proy-opcion opt-0' + (clave && !clave.correcta ? ' correcta' : '') + '">✘ Falso</div></div>';
    },
    palabra_secreta: function (it, clave) {
      return '<p class="proy-pregunta">' + esc(it.pista) + '</p><p class="j-ayuda">' + it.letras + ' letras</p>' +
        (clave ? '<p class="proy-clave">Respuesta: ' + esc(clave.palabra) + '</p>' : '');
    },
    completar: function (it, clave) {
      return '<p class="proy-pregunta">' + esc((clave && clave.texto ? clave.texto : it.partes.join(' ____ ')).replace(/[\[\]]/g, '')) + '</p>';
    },
    ordenar_palabras: function (it, clave) {
      return '<p class="proy-pregunta">Reconstruye la frase</p><div class="j-chips">' +
        it.palabras.map(function (p) { return '<span class="j-chip">' + esc(p) + '</span>'; }).join('') + '</div>' +
        (clave ? '<p class="proy-clave">Frase: ' + esc(clave.frase) + '</p>' : '');
    },
    sopa_letras: function (it) {
      var celdas = '';
      (it.celdas || []).forEach(function (fila) {
        fila.forEach(function (letra) { celdas += '<span class="proy-letra">' + esc(letra) + '</span>'; });
      });
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p>' +
        '<div class="j-sopa-palabras">' + (it.palabras || []).map(function (p) {
          return '<span class="j-sopa-palabra">' + esc(p) + '</span>';
        }).join('') + '</div>' +
        '<div class="proy-sopa" style="grid-template-columns:repeat(' + (it.tamano || 10) + ',1fr)">' + celdas + '</div>';
    },
    crucigrama: function (it) {
      var casillas = '';
      (it.celdas || []).forEach(function (fila) {
        fila.forEach(function (celda) {
          casillas += celda
            ? '<span class="proy-casilla">' + (celda.numero ? '<span class="num">' + celda.numero + '</span>' : '') + '</span>'
            : '<span class="proy-casilla vacia"></span>';
        });
      });

      // El número va con su sentido: una horizontal y una vertical pueden
      // compartir la casilla de inicio y, por tanto, el número.
      var grupo = function (titulo, fichas, flecha) {
        return '<div class="j-cruci-grupo"><b>' + titulo + '</b>' + fichas.map(function (f) {
          return '<div class="j-pista"><span class="n">' + f.numero + flecha + '</span>' + esc(f.pista) + '</div>';
        }).join('') + '</div>';
      };

      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p>' +
        '<div class="proy-cruci" style="grid-template-columns:repeat(' + (it.columnas || 1) + ',1fr)">' + casillas + '</div>' +
        '<div class="j-cruci-pistas">' +
        grupo('Horizontales', it.horizontales || [], '→') +
        grupo('Verticales', it.verticales || [], '↓') + '</div>';
    },
    relacionar: function (it, clave) {
      var pares = (clave && clave.pares) || [];
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        (pares.length
          ? pares.map(function (p) { return '<div>' + esc(p.izquierda) + ' <b>↔</b> ' + esc(p.derecha) + '</div>'; }).join('')
          // Los lados llegan como {texto, imagen} desde que las parejas admiten
          // foto; sin esto la proyeccion imprimiria «[object Object]».
          : it.izquierda.map(function (i2) {
              var t = (i2 && typeof i2 === 'object') ? i2.texto : i2;
              return '<div>' + esc(t) + '</div>';
            }).join('')) + '</div>';
    },
    memoria: function (it) {
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p>' +
        '<p class="j-ayuda">' + (it.cartas.length / 2) + ' parejas · cada quien juega en su pantalla</p>';
    },
    clasificar: function (it, clave) {
      var categorias = (clave && clave.categorias) || [];
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        (categorias.length
          ? categorias.map(function (c) { return '<div><b>' + esc(c.nombre) + ':</b> ' + esc((c.elementos || []).join(', ')) + '</div>'; }).join('')
          : it.categorias.map(function (c) { return '<div>' + esc(c) + '</div>'; }).join('')) + '</div>';
    },
    ordenar: function (it, clave) {
      var orden = (clave && clave.elementos) || it.elementos;
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        orden.map(function (e, i) { return '<div>' + (i + 1) + '. ' + esc(e) + '</div>'; }).join('') + '</div>';
    },
    ortografia: function (it, clave) {
      var correctas = (clave && clave.pares) || [];
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        (it.parejas || []).map(function (par, i) {
          var buena = correctas[i] ? correctas[i].correcta : '';
          return '<div>' + par.opciones.map(function (texto) {
            var esBuena = buena && esc(texto) === esc(buena);
            return '<b class="' + (esBuena ? 'proy-clave' : '') + '">' + esc(texto) + (esBuena ? ' ✓' : '') + '</b>';
          }).join(' &nbsp;·&nbsp; ') + '</div>';
        }).join('') + '</div>';
    },

    anagrama: function (it, clave) {
      return '<p class="proy-pregunta">' + (it.pista ? esc(it.pista) : 'Arma la palabra') + '</p>' +
        '<div class="j-chips">' + (it.letras || []).map(function (l) {
          return '<span class="j-chip">' + esc(l) + '</span>';
        }).join('') + '</div>' +
        (clave && clave.palabra ? '<p class="proy-clave">Respuesta: ' + esc(clave.palabra) + '</p>' : '');
    },

    numerica: function (it, clave) {
      return '<p class="proy-pregunta">' + esc(it.enunciado) + '</p>' +
        (clave ? '<p class="proy-clave">Respuesta: ' + esc(clave.respuesta) +
          (clave.tolerancia ? ' (± ' + esc(clave.tolerancia) + ')' : '') +
          (it.unidad ? ' ' + esc(it.unidad) : '') + '</p>' : '');
    },

    linea_tiempo: function (it, clave) {
      var orden = (clave && clave.hechos) ? clave.hechos.slice().sort(function (a, b) {
        return String(a.fecha).localeCompare(String(b.fecha));
      }) : null;
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        (orden
          ? orden.map(function (h, i) { return '<div>' + (i + 1) + '. <b>' + esc(h.fecha) + '</b> — ' + esc(h.texto) + '</div>'; }).join('')
          : (it.hechos || []).map(function (t) { return '<div>' + esc(t) + '</div>'; }).join('')) + '</div>';
    },

    encuesta: function (it, clave, aportes) {
      var conteos = it.opciones.map(function () { return 0; });
      aportes.forEach(function (a) {
        var i = Number(a.respuesta);
        if (conteos[i] !== undefined) conteos[i]++;
      });
      var total = Math.max(1, aportes.length);
      return '<p class="proy-pregunta">' + esc(it.pregunta) + '</p><div class="proy-resultados">' +
        it.opciones.map(function (t, i) {
          return '<div class="proy-resultado"><b><span>' + esc(t) + '</span><span>' + conteos[i] + '</span></b>' +
            '<span class="barra"><span style="width:' + Math.round((conteos[i] / total) * 100) + '%"></span></span></div>';
        }).join('') + '</div>';
    },
    escala: function (it, clave, aportes) {
      var suma = 0;
      var conteos = [0, 0, 0, 0, 0];
      aportes.forEach(function (a) {
        var n = Number(a.respuesta);
        if (n >= 1 && n <= 5) { conteos[n - 1]++; suma += n; }
      });
      var promedio = aportes.length ? (suma / aportes.length).toFixed(1) : '—';
      var total = Math.max(1, aportes.length);
      return '<p class="proy-pregunta">' + esc(it.pregunta) + '</p>' +
        '<p class="proy-clave">Promedio: ' + promedio + ' de 5</p><div class="proy-resultados">' +
        conteos.map(function (n, i) {
          return '<div class="proy-resultado"><b><span>' + (i + 1) + '</span><span>' + n + '</span></b>' +
            '<span class="barra"><span style="width:' + Math.round((n / total) * 100) + '%"></span></span></div>';
        }).join('') + '</div>' +
        '<div class="j-escala-pies"><span>' + esc(it.etiquetaMin) + '</span><span>' + esc(it.etiquetaMax) + '</span></div>';
    },
    pregunta_abierta: function (it, clave, aportes) {
      return '<p class="proy-pregunta">' + esc(it.pregunta) + '</p>' +
        (aportes.length
          ? '<div class="j-chips">' + aportes.map(function (a) { return '<span class="j-chip">' + esc(a.respuesta) + '</span>'; }).join('') + '</div>'
          : '<p class="j-ayuda">Las respuestas aparecerán aquí.</p>');
    },
    mural: function (it, clave, aportes, muro) {
      var notas = muro || [];
      return '<p class="proy-pregunta">' + esc(it.consigna) + '</p>' +
        (notas.length
          ? '<div class="proy-muro ' + esc(it.modo) + '">' + notas.map(function (n, i) {
            return '<div class="nota nota-' + (i % 4) + '">' + esc(n.texto) +
              (n.nombre ? '<small>— ' + esc(n.nombre) + '</small>' : '') + '</div>';
          }).join('') + '</div>'
          : '<p class="j-ayuda">Los aportes aparecerán aquí a medida que lleguen.</p>');
    },
    panel: function (it) {
      return '<p class="proy-pregunta">' + esc(it.instruccion) + '</p><div class="proy-lista">' +
        it.tarjetas.map(function (t) { return '<div><b>' + esc(t.titulo) + '</b><br>' + esc(t.texto) + '</div>'; }).join('') + '</div>';
    },

    pagina: function (it) {
      return '<h2 class="proy-pagina-titulo">' + esc(it.titulo) + '</h2>' +
        '<div class="proy-pagina">' + (it.parrafos || []).map(function (p) {
          return '<p>' + esc(p) + '</p>';
        }).join('') + '</div>';
    }
  };

  function pintarProyeccion(datos) {
    var actividad = datos.actividad;
    if (!actividad) return;

    el.lobby.hidden = true;
    el.proyeccion.hidden = false;
    el.resultado.hidden = true;

    var clave = actividad.indice + '-' + actividad.item_indice;
    if (clave !== preguntaActual) {
      preguntaActual = clave;
      finCronometro = Date.now() + actividad.restante * 1000;
    }

    el.proyTitulo.textContent = actividad.titulo + ' · pregunta ' + (actividad.item_indice + 1) + ' de ' + actividad.total_items;

    var vista = vistas[actividad.item.tipo];
    var imagen = actividad.item.imagen
      ? '<img class="proy-imagen" src="' + esc(actividad.item.imagen) + '" alt="">'
      : '';
    el.proyCuerpo.innerHTML = imagen + (vista
      ? vista(actividad.item, actividad.clave, datos.aportes || [], datos.muro)
      : '<p class="proy-pregunta">Actividad no disponible</p>');

    var recibidas = datos.respuestas_recibidas || 0;
    el.proyConteo.textContent = recibidas + (recibidas === 1 ? ' respuesta' : ' respuestas');
    el.proyBarra.style.width = datos.total ? Math.round((recibidas / datos.total) * 100) + '%' : '0%';

    pintarMarcador(datos);

    el.comenzar.hidden = true;
    el.siguiente.hidden = !token;
    el.siguiente.disabled = false;
    el.siguiente.textContent = actividad.es_ultima ? 'Terminar y ver resultados' : 'Siguiente →';
    el.terminar.hidden = !token;
    actualizarCrono();
  }

  /**
   * Marcador durante la partida: quién va ganando, proyectado junto a la
   * pregunta. Es lo que hace ameno el juego en vivo — sin esto, las posiciones
   * solo aparecían al final y nadie sabía cómo iba.
   */
  function pintarMarcador(datos) {
    if (!el.marcador) return;

    var ranking = datos.ranking || [];
    var porEquipos = datos.ranking_equipos || [];
    var medallas = ['🥇', '🥈', '🥉'];

    // Si se juega por equipos, el marcador es de equipos: lo que se grita en el
    // salón es "van ganando los Tigres", no quién va primero de la lista.
    if (porEquipos.length) {
      el.marcador.hidden = false;
      el.marcador.innerHTML = '<h3 class="marcador-titulo">Van ganando</h3>' +
        '<div class="marcador-lista">' + porEquipos.map(function (e, i) {
          return '<div class="marcador-fila eq-' + esc(e.color) + (i === 0 ? ' lider' : '') + '">' +
            '<span class="marcador-pos">' + (medallas[i] || e.posicion) + '</span>' +
            '<span class="marcador-nombre">' + esc(e.nombre) +
            '<small class="marcador-sub"> · ' + e.integrantes + (e.integrantes === 1 ? ' persona' : ' personas') + '</small></span>' +
            '<span class="marcador-puntos">' + e.puntaje + '</span></div>';
        }).join('') + '</div>';
      return;
    }

    if (!ranking.length) {
      el.marcador.hidden = true;
      return;
    }
    el.marcador.hidden = false;

    el.marcador.innerHTML = '<h3 class="marcador-titulo">Van ganando</h3>' +
      '<div class="marcador-lista">' + ranking.slice(0, 5).map(function (f, i) {
        return '<div class="marcador-fila' + (i === 0 ? ' lider' : '') + '">' +
          '<span class="marcador-pos">' + (medallas[i] || f.posicion) + '</span>' +
          P.avatar(f.personaje, 'sm') +
          '<span class="marcador-nombre">' + esc(f.nombre) + '</span>' +
          '<span class="marcador-puntos">' + f.puntaje + '</span></div>';
      }).join('') + '</div>' +
      (ranking.length > 5 ? '<span class="marcador-resto">y ' + (ranking.length - 5) + ' más</span>' : '');
  }

  function actualizarCrono() {
    if (el.proyeccion.hidden) return;
    var restante = Math.max(0, Math.round((finCronometro - Date.now()) / 1000));
    el.proyCrono.textContent = restante + ' s';
  }

  setInterval(actualizarCrono, 1000);

  /* ---------- Resultados finales ---------- */

  function pintarResultado(datos) {
    el.lobby.hidden = true;
    el.proyeccion.hidden = true;
    el.resultado.hidden = false;
    el.comenzar.hidden = true;
    el.siguiente.hidden = true;
    el.terminar.hidden = true;

    // Con equipos, el podio manda: primero quién ganó como equipo y debajo, más
    // discreto, el detalle de cada quien (que sigue siendo suyo y lo quiere ver).
    var porEquipos = datos.ranking_equipos || [];
    var podioEquipos = porEquipos.length
      ? '<div class="podio-equipos">' + porEquipos.map(function (e) {
        return '<div class="podio-fila eq-' + esc(e.color) + (e.posicion === 1 ? ' top' : '') + '">' +
          '<span class="podio-pos">' + e.posicion + '</span>' +
          '<span><span class="podio-nombre">' + esc(e.nombre) + '</span>' +
          '<span class="podio-datos">' + e.integrantes + (e.integrantes === 1 ? ' persona' : ' personas') +
          ' · ' + e.aciertos + ' aciertos</span></span>' +
          '<span class="podio-puntos">' + e.puntaje + '</span></div>';
      }).join('') + '</div><h3 class="podio-subtitulo">Cada quien</h3>'
      : '';

    el.podio.innerHTML = podioEquipos + (datos.ranking || []).map(function (f) {
      return '<div class="podio-fila' + (f.posicion === 1 ? ' top' : '') + '">' +
        '<span class="podio-pos">' + f.posicion + '</span>' +
        P.avatar(f.personaje, 'sm') +
        '<span><span class="podio-nombre">' + esc(f.nombre) + '</span>' +
        '<span class="podio-datos">' + f.aciertos + ' aciertos · ' + f.promedio + ' s de promedio</span></span>' +
        '<span class="podio-puntos">' + f.puntaje + '</span></div>';
    }).join('') || '<p class="j-ayuda">Nadie respondió en esta sesión.</p>';

    if (datos.informe) {
      el.informe.innerHTML = '<h3>Informe de la sesión</h3>' + datos.informe.map(function (act) {
        return '<div class="informe-actividad"><b>' + esc(act.titulo) + '</b>' +
          '<span class="informe-tipo">' + esc(act.tipo.replace(/_/g, ' ')) + '</span>' +
          act.preguntas.map(function (p) {
            var dato = act.puntuable
              ? p.correctas + ' de ' + p.respuestas + ' correctas'
              : p.respuestas + (p.respuestas === 1 ? ' aporte' : ' aportes');
            return '<div class="informe-pregunta"><span>' + p.numero + '. ' + esc(p.resumen) + '</span>' +
              '<span class="informe-dato">' + dato + '</span></div>' +
              (p.aportes && p.aportes.length
                ? '<ul class="informe-aportes">' + p.aportes.map(function (a) {
                  return '<li>' + esc(a.texto) + ' — ' + esc(a.nombre) + '</li>';
                }).join('') + '</ul>'
                : '');
          }).join('') + '</div>';
      }).join('');
      el.barra.textContent = 'La sesión terminó · guarda o imprime esta página: no se conserva en el servidor';
    } else if (datos.informe_bloqueado) {
      el.informe.innerHTML = '<div class="informe-bloqueado"><b>🔒 Informe de la sesión</b>' +
        '<span>' + esc(datos.informe_bloqueado.motivo) + '</span>' +
        '<a class="btn btn-primary btn-sm" href="' + esc(datos.informe_bloqueado.planes) + '">Ver los planes</a></div>';
      el.barra.textContent = 'La sesión terminó · las posiciones son tuyas; el informe detallado está en los planes de pago';
    }
  }

  /* ---------- Estado ---------- */

  /* ---------- Paquete enviado: avance de cada quien ---------- */

  function pintarEnviado(datos) {
    el.lobby.hidden = false;
    el.proyeccion.hidden = true;
    el.resultado.hidden = true;
    el.comenzar.hidden = true;
    el.siguiente.hidden = true;
    el.terminar.hidden = !token;
    el.terminar.textContent = 'Cerrar paquete';

    // Aquí no hay sala de espera: lo que importa es cuánto lleva cada quien.
    el.participantes.hidden = true;
    el.progreso.hidden = false;
    el.contador.textContent = datos.total + ' de ' + MAX;

    var lista = datos.progreso || [];
    if (!lista.length) {
      el.progreso.innerHTML = '<div class="participantes-vacio">' +
        '<b aria-hidden="true">📨</b><span>Nadie ha abierto el paquete todavía. Comparte el enlace o el QR.</span></div>';
      el.estado.textContent = 'El paquete está disponible: cada quien lo juega cuando pueda.';
      return;
    }

    el.progreso.innerHTML = lista.map(function (p) {
      return '<div class="progreso-fila' + (p.terminado ? ' listo' : '') + (p.conectado ? '' : ' ausente') + '">' +
        P.avatar(p.personaje, 'sm') +
        '<span><span class="progreso-nombre">' + esc(p.nombre) + '</span>' +
        '<span class="progreso-barra" aria-hidden="true"><span style="width:' + p.porcentaje + '%"></span></span></span>' +
        '<span class="progreso-dato">' + (p.terminado ? '✓ ' + p.puntaje + ' pts' : p.posicion + '/' + p.total) + '</span></div>';
    }).join('');

    var terminados = datos.terminados || 0;
    el.estado.textContent = terminados + ' de ' + lista.length +
      (terminados === 1 ? ' terminó' : ' terminaron') + ' · puedes cerrar el paquete cuando quieras.';
  }

  function aplicarEstado(datos) {
    var estado = datos.sesion.estado;
    sala.setAttribute('data-estado', estado);

    if (MODO === 'enviar' && estado !== 'terminada') {
      pintarEnviado(datos);
      if (!token) {
        el.barra.textContent = 'Estás viendo este paquete desde otro navegador: solo quien lo creó puede cerrarlo.';
      }
      return;
    }

    if (estado === 'lobby') {
      el.contador.textContent = datos.total + ' de ' + MAX;
      pintarParticipantes(datos.participantes);
      pintarEquipos(datos);
      el.estado.textContent = datos.total
        ? (datos.conectados + ' conectado' + (datos.conectados === 1 ? '' : 's') + ' · puedes comenzar cuando quieras')
        : 'Esperando a que entre tu público…';
      el.comenzar.disabled = !token || datos.total === 0;
      el.comenzar.hidden = false;
      el.siguiente.hidden = true;
      el.terminar.hidden = !token;
    } else if (estado === 'en_curso') {
      pintarProyeccion(datos);
    } else {
      pintarResultado(datos);
      detener();
    }

    if (!token) {
      el.barra.textContent = 'Estás viendo esta sala desde otro navegador: solo quien la creó puede controlarla.';
    }
    ultimoEstado = estado;
  }

  /* ---------- Sondeo ---------- */

  var temporizador = null;

  function consultar() {
    var url = window.LudiaRutas.api('sesion-estado.php') + '?codigo=' + CODIGO + (token ? '&token=' + encodeURIComponent(token) : '');
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (!datos.ok) {
          el.estado.textContent = datos.error || 'No pudimos consultar la sesión.';
          return;
        }
        aplicarEstado(datos);
      })
      .catch(function () {
        el.estado.textContent = 'Sin conexión con el servidor. Reintentando…';
      });
  }

  function arrancar() {
    consultar();
    if (!temporizador) temporizador = setInterval(consultar, INTERVALO);
  }

  function detener() {
    if (temporizador) clearInterval(temporizador);
    temporizador = null;
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) detener();
    else if (!temporizador && sala.getAttribute('data-estado') !== 'terminada') arrancar();
  });

  /* ---------- Controles del anfitrión ---------- */

  function controlar(accion, extra) {
    var cuerpo = Object.assign({ codigo: CODIGO, anfitrion_token: token, accion: accion }, extra || {});
    return fetch(window.LudiaRutas.api('sesion-control.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cuerpo)
    }).then(function (r) { return r.json(); });
  }

  /* ---------- Armar equipos ---------- */

  function trasArmar(datos) {
    if (!datos.ok) {
      el.equiposAviso.textContent = datos.error || 'No se pudieron armar los equipos.';
      return;
    }
    firmaEquipos = '';   // fuerza el repintado con lo que acaba de llegar
    consultar();
  }

  if (el.equiposAzar) {
    el.equiposAzar.addEventListener('click', function () {
      controlar('equipos_azar', { cuantos: Number(el.equiposCuantos.value) }).then(trasArmar);
    });

    el.equiposMano.addEventListener('click', function () {
      controlar('equipos_vacios', { cuantos: Number(el.equiposCuantos.value) }).then(trasArmar);
    });

    el.equiposDeshacer.addEventListener('click', function () {
      controlar('equipos_deshacer').then(trasArmar);
    });

    // Mover a alguien de equipo: delegado, porque la lista se repinta sola.
    el.equiposLista.addEventListener('change', function (evento) {
      var selector = evento.target.closest('[data-mover]');
      if (!selector) return;
      controlar('equipo_mover', {
        participante_id: Number(selector.getAttribute('data-mover')),
        equipo_id: selector.value === '' ? null : Number(selector.value)
      }).then(trasArmar);
    });
  }

  el.comenzar.addEventListener('click', function () {
    el.comenzar.disabled = true;
    controlar('comenzar').then(function (datos) {
      if (!datos.ok) {
        el.estado.textContent = datos.error;
        el.comenzar.disabled = false;
        return;
      }
      consultar();
    });
  });

  el.siguiente.addEventListener('click', function () {
    el.siguiente.disabled = true;
    controlar('siguiente').then(function () { consultar(); });
  });

  el.terminar.addEventListener('click', function () {
    var aviso = MODO === 'enviar'
      ? '¿Cerrar el paquete? Quien no haya terminado ya no podrá seguir.'
      : '¿Terminar la sesión para todos? No se guarda nada.';
    if (!window.confirm(aviso)) return;
    controlar('terminar').then(consultar);
  });

  arrancar();
})();
