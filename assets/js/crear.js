/* =====================================================================
   Ludia — armado del paquete en tres pasos:
     1 Tema · 2 Actividades · 3 Revisar y crear

   Al crear NO se decide cómo se va a usar: eso se elige después, desde
   "Mis paquetes", cuando el paquete ya está listo (jugar en vivo o enviarlo
   para que cada estudiante lo haga por su cuenta). Preguntarlo al principio
   obligaba a decidir antes de tener siquiera el contenido.

   El borrador vive en sessionStorage: nada se guarda en el servidor hasta
   que el paquete se crea.
   ===================================================================== */
(function () {
  'use strict';

  var F = window.LudiaFormatos;
  var E = window.LudiaEditor;
  var root = document.querySelector('[data-builder]');
  if (!root || !F || !E) return;

  var CLAVE_BORRADOR = 'ludia.paquete.borrador.v2';
  var LIMITE = F.limites;
  var esc = E.esc;

  /* Lo que incluye el plan y, si venimos de "Editar", el paquete a modificar.
     Los inyecta app/crear.php. Aquí solo sirven para guiar: quien autoriza de
     verdad es el servidor, que vuelve a comprobarlo todo al guardar. */
  var PLAN = window.LudiaPlan || {
    clave: 'gratis', nombre: 'Gratis', paquetes: 3, participantes: 30,
    formatos: '*', imagenes: true, informe: true, guarda: false
  };
  var EDITANDO = window.LudiaEditarPaquete || null;

  function formatoDelPlan(clave) {
    return PLAN.formatos === '*' || PLAN.formatos.indexOf(clave) > -1;
  }
  function esDePago() { return PLAN.clave === 'gratis'; }

  var PASOS = [
    { numero: 1, nombre: 'Tema' },
    { numero: 2, nombre: 'Actividades' },
    { numero: 3, nombre: 'Revisar' }
  ];
  var ULTIMO_PASO = PASOS.length;

  var el = {
    pasos: document.getElementById('pasos'),
    nombre: document.getElementById('sesion-nombre'),
    audiencia: document.getElementById('sesion-audiencia'),
    resumenTema: document.getElementById('resumen-tema'),
    planBox: document.getElementById('plan-box'),
    slots: document.getElementById('slots'),
    main: document.getElementById('main-col'),
    preview: document.getElementById('preview'),
    revision: document.getElementById('revision'),
    archivo: document.getElementById('archivo-excel'),
    excelEstado: document.getElementById('excel-estado'),
    dialogoImportar: document.getElementById('dialog-importar'),
    importarCuerpo: document.getElementById('importar-cuerpo'),
    status: document.getElementById('bar-status'),
    atras: document.getElementById('btn-atras'),
    siguiente: document.getElementById('btn-siguiente'),
    continuar: document.getElementById('btn-continuar'),
    dialogo: document.getElementById('dialog-siguiente')
  };

  var state = estadoInicial();

  /**
   * De dónde sale el paquete que se está armando:
   *  - editando: el que viene del servidor, salvo que el borrador ya sea de ese
   *    mismo paquete (así no se pierde lo que se estaba escribiendo);
   *  - creando: el borrador de esta pestaña, nunca uno que quedó de una edición
   *    (si no, "Crear paquete" guardaría encima de uno ya compartido).
   */
  /**
   * Le pone a cada actividad el nombre de su formato si le falta.
   *
   * El campo de título se retiró, pero los paquetes creados ANTES lo tienen
   * vacío. Sin esto, el juego y la proyección seguirían mostrando
   * « · pregunta 1 de 3» sin nombre en todo lo ya guardado.
   */
  function nombrarPorFormato(actividades) {
    return (actividades || []).map(function (a) {
      var tipo = F.tipos[a.tipo];
      if (tipo && F.vacio(a.titulo)) a.titulo = tipo.nombre;
      return a;
    });
  }

  function estadoInicial() {
    var guardado = cargar();

    if (EDITANDO) {
      if (guardado && guardado.paqueteId === EDITANDO.id) {
        guardado.actividades = nombrarPorFormato(guardado.actividades);
        return guardado;
      }
      return {
        paso: 2,   // entra directo a las actividades
        paqueteId: EDITANDO.id,
        modo: null,
        nombre: EDITANDO.nombre,
        audiencia: EDITANDO.audiencia,
        actividades: nombrarPorFormato((EDITANDO.actividades || []).map(function (a, i) {
          a.uid = a.uid || ('a' + i + Date.now().toString(36));
          return a;
        })),
        activa: null,
        item: 0
      };
    }

    if (guardado && !guardado.paqueteId) {
      guardado.actividades = nombrarPorFormato(guardado.actividades);
      return guardado;
    }
    // Nace sin modo: cómo se usa se decide después, desde "Mis paquetes".
    return { paso: 1, paqueteId: null, modo: null, nombre: '', audiencia: 'estudiantes', actividades: [], activa: null, item: 0 };
  }

  function editando() { return !!state.paqueteId; }

  /* ---------- Borrador temporal ---------- */

  function cargar() {
    try {
      var s = JSON.parse(sessionStorage.getItem(CLAVE_BORRADOR) || 'null');
      if (!s || !Array.isArray(s.actividades)) return null;
      s.actividades = s.actividades.filter(function (a) {
        return F.tipos[a.tipo] && Array.isArray(a.items) && a.items.length;
      });
      return s;
    } catch (e) {
      return null;
    }
  }

  var temporizador;
  function guardar() {
    clearTimeout(temporizador);
    temporizador = setTimeout(function () {
      try { sessionStorage.setItem(CLAVE_BORRADOR, JSON.stringify(state)); } catch (e) { /* sin almacenamiento */ }
    }, 250);
  }

  /* ---------- Consultas ---------- */

  function actividadActiva() {
    for (var i = 0; i < state.actividades.length; i++) {
      if (state.actividades[i].uid === state.activa) return state.actividades[i];
    }
    return null;
  }

  function erroresItem(actividad, indice) {
    return F.tipos[actividad.tipo].validar(actividad.items[indice]);
  }

  function itemsConError(actividad) {
    return actividad.items.filter(function (_, i) { return erroresItem(actividad, i).length; }).length;
  }

  function totalPreguntas() {
    return state.actividades.reduce(function (n, a) { return n + a.items.length; }, 0);
  }

  function minutosEstimados() {
    // Una diapositiva no tiene tiempo; para la estimación se cuenta un minuto
    // de explicación por cada una.
    var segundos = state.actividades.reduce(function (n, a) {
      return n + a.items.length * (F.tipos[a.tipo].lectura ? 60 : a.tiempo);
    }, 0);
    return Math.max(1, Math.round(segundos / 60));
  }

  function plural(n, singular, plural_) { return n + ' ' + (n === 1 ? singular : plural_); }


  /** Qué falta para poder salir de cada paso. */
  function faltaEnPaso(paso) {
    if (paso === 1) return F.vacio(state.nombre) ? 'escribe el tema del paquete' : null;
    if (paso === 2) {
      if (!state.actividades.length) return 'agrega al menos una actividad';
      for (var i = 0; i < state.actividades.length; i++) {
        var a = state.actividades[i];
        // El título es opcional: si se deja vacío se usa el nombre del formato.
        // Hay actividades que son solo la pregunta y sus respuestas.
        var n = itemsConError(a);
        if (n) return 'revisa ' + plural(n, 'pregunta', 'preguntas') + ' de «' + (a.titulo || F.tipos[a.tipo].nombre) + '»';
      }
    }
    return null;
  }

  function pasoCompleto(paso) { return faltaEnPaso(paso) === null; }

  /**
   * ¿Ya no caben más actividades en este paquete?
   * El tope lo pone el plan (0 = sin tope, que es lo que tiene Pro). El servidor
   * lo vuelve a comprobar al crear: esto es solo para avisar antes de escribir.
   */
  function topeActividades() {
    return PLAN.actividades > 0 ? PLAN.actividades : 0;
  }

  function lleno() {
    var tope = topeActividades();
    return tope > 0 && state.actividades.length >= tope;
  }

  /* ---------- Navegación entre pasos ---------- */

  function irA(paso) {
    // Solo se avanza si los pasos anteriores están completos.
    for (var p = 1; p < paso; p++) {
      if (!pasoCompleto(p)) { paso = p; break; }
    }
    state.paso = paso;
    if (paso === 3 && !state.actividades.length) state.activa = null;
    render();
    guardar();
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }

  el.atras.addEventListener('click', function () { irA(Math.max(1, state.paso - 1)); });
  el.siguiente.addEventListener('click', function () {
    var falta = faltaEnPaso(state.paso);
    if (falta) { el.status.textContent = '⚠️ Para seguir: ' + falta; return; }
    irA(Math.min(ULTIMO_PASO, state.paso + 1));
  });

  /* ---------- Render: barra de pasos ---------- */

  function renderPasos() {
    el.pasos.innerHTML = PASOS.map(function (p) {
      var estado = p.numero === state.paso ? ' actual' : (p.numero < state.paso && pasoCompleto(p.numero) ? ' hecho' : '');
      var alcanzable = p.numero <= state.paso || pasoCompleto(p.numero - 1);
      return '<li class="paso-chip' + estado + '">' +
        '<button type="button" data-paso="' + p.numero + '"' + (alcanzable ? '' : ' disabled') + '>' +
        '<span class="paso-num">' + (estado === ' hecho' ? '✓' : p.numero) + '</span>' + esc(p.nombre) + '</button></li>';
    }).join('');
  }

  /* ---------- Render: paso 2, actividades ---------- */

  function renderResumenTema() {
    el.resumenTema.innerHTML =
      '<div class="tema-chip">' +
      '<b>' + esc(state.nombre || 'Sin tema') + '</b>' +
      '<span class="tema-datos">' + plural(state.actividades.length, 'actividad', 'actividades') +
      ' · ' + plural(totalPreguntas(), 'pregunta', 'preguntas') + '</span>' +
      '<button type="button" class="link-btn" data-paso="1">Cambiar tema</button></div>';
  }

  function renderPlan() {
    var cuantos = PLAN.formatos === '*' ? Object.keys(F.tipos).length : PLAN.formatos.length;
    var totales = PLAN.formatosTotales || Object.keys(F.tipos).length;
    el.planBox.innerHTML =
      '<span class="plan-tag">Plan ' + esc(PLAN.nombre) + '</span>' +
      '<span>🎲 ' + cuantos + ' de ' + totales + ' actividades</span>' +
      '<span>🧩 ' + (topeActividades() > 0 ? 'Hasta ' + topeActividades() + ' por paquete' : 'Sin tope por paquete') + '</span>' +
      '<span>👥 Hasta ' + PLAN.participantes + ' participantes</span>' +
      // "Guardados", no "activos a la vez": desde que el contenido se separó de
      // las partidas, los paquetes son permanentes y solo los borra su dueño.
      '<span>📦 ' + (PLAN.paquetes > 0 ? PLAN.paquetes + ' paquetes guardados' : 'Paquetes sin límite') + '</span>' +
      '<span>' + (PLAN.imagenes ? '🖼️ Imágenes en las preguntas' : '🔒 Sin imágenes en el plan gratis') + '</span>' +
      '<span>' + (PLAN.informe ? '📊 Informe con aciertos y gráficos' : '🔒 Informe detallado en los planes de pago') + '</span>' +
      (esDePago() ? '<a class="link-btn" href="' + window.LudiaRutas.base + '#planes">Ver los planes</a>' : '');
  }

  function renderSlots() {
    var html = state.actividades.map(function (a) {
      var tipo = F.tipos[a.tipo];
      var errores = itemsConError(a);
      var n = a.items.length;
      return '<button type="button" class="slot' + (a.uid === state.activa ? ' is-active' : '') + '" data-action="abrir-actividad" data-uid="' + a.uid + '">' +
        '<span class="slot-icon" aria-hidden="true">' + tipo.icono + '</span>' +
        '<span><span class="slot-name">' + esc(F.vacio(a.titulo) ? tipo.nombre : a.titulo) + '</span><br>' +
        '<span class="slot-meta">' + esc(tipo.nombre) + ' · ' + plural(n, 'pregunta', 'preguntas') + '</span></span>' +
        '<span class="slot-state ' + (errores ? 'warn">Revisar' : 'ok">Lista') + '</span></button>';
    }).join('');

    if (!state.actividades.length) {
      html = '<div class="slot-empty">Aún no hay actividades. Elige un formato para empezar.</div>';
    }
    html += lleno()
      ? '<div class="slot-empty">Llegaste a las ' + topeActividades() + ' actividades de tu plan ' + esc(PLAN.nombre) +
        '. <a href="' + window.LudiaRutas.app('suscripcion.php') + '">Pro no tiene tope</a>.</div>'
      : '<button type="button" class="btn btn-ghost btn-block" data-action="ver-catalogo">+ Agregar actividad</button>';
    el.slots.innerHTML = html;
  }

  /**
   * La URL del vídeo es de la ACTIVIDAD, no de cada pregunta: un solo vídeo con
   * varias preguntas repartidas por el minuto. Por eso vive aquí arriba y no en
   * los campos del ítem.
   *
   * Se acepta pegar cualquier forma: la URL larga, la corta, el <iframe> de
   * «Compartir → Insertar» o el identificador suelto.
   */
  function campoVideo(a) {
    if (a.tipo !== 'video_preguntas') return '';

    var crudo = a.video || '';
    // La vista va en su propia caja para poder refrescarla sin redibujar el
    // campo: escribir no redibuja el formulario —si lo hiciera se perdería el
    // cursor a cada letra— así que el aviso y la vista previa se actualizan
    // aparte, en `refrescarVideo()`.
    return '<div class="field"><label for="act-video">Vídeo de YouTube</label>' +
      '<input class="input" id="act-video" type="text" maxlength="300" data-act="video" value="' + esc(crudo) + '" ' +
      'placeholder="https://www.youtube.com/watch?v=...">' +
      '<div data-video-caja>' + vistaVideo(crudo) + '</div></div>';
  }

  /** El aviso y la vista previa que acompañan al campo del vídeo. */
  function vistaVideo(crudo) {
    var id = F.idYouTube(crudo || '');
    var aviso = (crudo && !id)
      ? '<span class="hint error-hint">No reconozco ese enlace. Pega la dirección del vídeo de YouTube.</span>'
      : '<span class="hint">Pega el enlace de YouTube, o el código de «Compartir → Insertar».</span>';

    var vista = id
      ? '<div class="video-vista" data-video-id="' + esc(id) + '">' +
        '<iframe src="https://www.youtube.com/embed/' + esc(id) + '" ' +
        'title="Vista previa del vídeo" frameborder="0" allowfullscreen loading="lazy"></iframe></div>'
      : '';

    return aviso + vista;
  }

  /**
   * Refresca la vista del vídeo mientras se escribe.
   *
   * El iframe solo se rehace si el identificador CAMBIÓ: recrearlo con cada
   * tecla pediría el vídeo a YouTube una vez por letra mientras se pega la
   * dirección.
   */
  function refrescarVideo(crudo) {
    var caja = root.querySelector('[data-video-caja]');
    if (!caja) return;

    var id = F.idYouTube(crudo || '');
    var marco = caja.querySelector('[data-video-id]');
    if (marco && marco.getAttribute('data-video-id') === id) {
      // Mismo vídeo: solo puede haber cambiado el aviso.
      var pista = caja.querySelector('.hint');
      if (pista) pista.className = 'hint';
      return;
    }

    caja.innerHTML = vistaVideo(crudo);
  }

  function renderCatalogo() {
    var html = '<div class="catalog-head"><span class="eyebrow">' + (state.actividades.length ? 'Agregar actividad' : 'Primera actividad') + '</span>' +
      '<h1>¿Qué actividad quieres para «' + esc(state.nombre) + '»?</h1>' +
      '<p>Un mismo tema puede combinar preguntas, verdadero o falso, llenar huecos, sopa de letras, mural y más.</p></div>';

    if (lleno()) {
      html += '<div class="notice">⚠️ <span>Tu plan ' + esc(PLAN.nombre) + ' permite ' + topeActividades() +
        ' actividades por paquete. Elimina una, o pásate a Pro, que no tiene tope.</span></div>';
    }

    if (esDePago()) {
      var incluidos = PLAN.formatos === '*' ? Object.keys(F.tipos).length : PLAN.formatos.length;
      html += '<div class="catalog-plan"><span><b>Tu plan gratis incluye ' + incluidos + ' de los ' +
        Object.keys(F.tipos).length + ' formatos.</b> Los demás aparecen con candado.</span>' +
        '<a class="btn btn-ghost btn-sm" href="' + window.LudiaRutas.base + '#planes">Ver los planes</a></div>';
    }

    F.grupos.forEach(function (grupo) {
      var tarjetas = Object.keys(F.tipos).filter(function (k) { return F.tipos[k].grupo === grupo.clave; }).map(function (clave) {
        var t = F.tipos[clave];
        var permitido = formatoDelPlan(clave);
        var etiqueta = permitido
          ? (t.puntua ? '<span class="fc-tag puntua">Puntúa</span>' : '<span class="fc-tag opina">Sin puntaje</span>')
          : '<span class="fc-tag plan">🔒 Plan Estándar</span>';
        // El bloqueado no se deshabilita: así se puede tocar y explicar por qué.
        return '<button type="button" class="format-card' + (permitido ? '' : ' bloqueado') + '"' +
          ' data-action="' + (permitido ? 'agregar' : 'formato-bloqueado') + '" data-tipo="' + clave + '"' +
          (lleno() && permitido ? ' disabled' : '') + '>' +
          etiqueta + '<span class="fc-icon" aria-hidden="true">' + t.icono + '</span>' +
          '<span class="fc-name">' + esc(t.nombre) + '</span><span class="fc-desc">' + esc(t.descripcion) + '</span></button>';
      }).join('');
      html += '<div class="catalog-group"><h2>' + esc(grupo.nombre) + '</h2><p>' + esc(grupo.descripcion) + '</p><div class="catalog-grid">' + tarjetas + '</div></div>';
    });

    el.main.innerHTML = html;
  }

  function renderEditor(a) {
    var tipo = F.tipos[a.tipo];
    var indice = state.item;
    var puedeAgregar = a.items.length < LIMITE.itemsPorActividad;

    var tiempos = F.tiempos.map(function (s) {
      return '<option value="' + s + '"' + (a.tiempo === s ? ' selected' : '') + '>' + s + ' segundos</option>';
    }).join('');

    var pills = a.items.map(function (_, i) {
      return '<button type="button" class="item-pill' + (i === indice ? ' is-active' : '') + (erroresItem(a, i).length ? ' has-error' : '') + '"' +
        ' data-action="ir-item" data-index="' + i + '" aria-label="Pregunta ' + (i + 1) + '"' + (i === indice ? ' aria-current="true"' : '') + '>' + (i + 1) + '</button>';
    }).join('');

    el.main.innerHTML =
      '<div class="editor-head"><div class="editor-type"><span class="slot-icon" aria-hidden="true">' + tipo.icono + '</span>' +
      '<div><h1>' + esc(tipo.nombre) + '</h1><p>' + esc(tipo.descripcion) + '</p></div></div>' +
      '<span><button type="button" class="link-btn" data-action="ver-catalogo">+ Otra actividad</button>' +
      '<button type="button" class="link-btn danger" data-action="eliminar-actividad">Eliminar</button></span></div>' +

      /* Aquí se pedía un "Título de la actividad". Se quitó el 18/09/2026: el
         nombre lo pone el formato, y el encabezado de arriba ya lo muestra en
         grande. Escribir "Quiz" dentro de una pantalla que ya dice «Quiz» era
         pedir el mismo dato dos veces. */
      '<div class="panel">' +
      campoVideo(a) +
      /* Las diapositivas no tienen tiempo: en vivo las pasas tú cuando
         terminas de explicar (decisión del 2026-10-08). */
      (tipo.lectura
        ? '<p class="hint" style="margin-bottom:14px">⏸ Sin tiempo: en vivo, cada ' + (a.tipo === 'pagina' ? 'diapositiva' : 'pantalla') +
          ' se queda hasta que pulses «Siguiente». Ideal para explicar.</p>'
        : '<div class="field"><label for="act-tiempo">Tiempo por pregunta</label>' +
          '<select class="select" id="act-tiempo" data-act="tiempo" data-type="number">' + tiempos + '</select></div>') +

      '<span class="field-label">' + (a.tipo === 'pagina' ? 'Diapositivas' : tipo.lectura ? 'Pantallas' : 'Preguntas') +
      ' (' + a.items.length + ' de ' + LIMITE.itemsPorActividad + ')</span>' +
      '<div class="items-bar">' + pills +
      (puedeAgregar ? '<button type="button" class="item-pill add" data-action="agregar-item" aria-label="Agregar pregunta">+</button>' : '') + '</div>' +

      '<div class="item-card"><div class="item-card-head"><strong>Pregunta ' + (indice + 1) + '</strong><span>' +
      (puedeAgregar ? '<button type="button" class="link-btn" data-action="duplicar-item">Duplicar</button>' : '') +
      '<button type="button" class="link-btn danger" data-action="quitar-item"' + (a.items.length <= 1 ? ' disabled' : '') + '>Quitar</button></span></div>' +
      E.campos(a.tipo, a.items[indice], { key: a.uid + '-' + indice }) +
      '<ul class="errors" id="item-errores" aria-live="polite"></ul></div></div>';

    renderErrores(a);
  }

  function renderErrores(a) {
    var lista = document.getElementById('item-errores');
    if (!lista) return;
    var errores = erroresItem(a, state.item);
    lista.className = 'errors' + (errores.length ? '' : ' ok');
    lista.innerHTML = errores.length
      ? errores.map(function (e) { return '<li>• ' + esc(e) + '</li>'; }).join('')
      : '<li>✓ Pregunta lista</li>';

    root.querySelectorAll('.item-pill[data-index]').forEach(function (pill) {
      pill.classList.toggle('has-error', erroresItem(a, Number(pill.getAttribute('data-index'))).length > 0);
    });
  }

  function renderPreview() {
    var a = actividadActiva();
    el.preview.innerHTML = a
      ? E.vistaPrevia(a, state.item)
      : '<div class="preview-empty"><b>👀</b>Aquí verás lo que ve tu público en su celular mientras editas.</div>';
  }

  /* ---------- Render: paso 3, revisión ---------- */

  function renderRevision() {
    var filas = state.actividades.map(function (a, i) {
      var tipo = F.tipos[a.tipo];
      return '<li class="revision-fila">' +
        '<span class="slot-icon" aria-hidden="true">' + tipo.icono + '</span>' +
        '<span><b>' + esc(a.titulo || tipo.nombre) + '</b>' +
        '<span class="slot-meta">' + esc(tipo.nombre) + ' · ' +
        (tipo.lectura
          ? plural(a.items.length, 'pantalla', 'pantallas') + ' · sin tiempo, la pasas tú'
          : plural(a.items.length, 'pregunta', 'preguntas') + ' · ' + a.tiempo + ' s cada una') + '</span></span>' +
        '<button type="button" class="link-btn" data-editar="' + a.uid + '">Editar</button></li>';
    }).join('');

    el.revision.innerHTML =
      '<div class="panel revision-resumen">' +
      '<div class="revision-dato"><span>Tema</span><b>' + esc(state.nombre) + '</b></div>' +
      '<div class="revision-dato"><span>Actividades</span><b>' + state.actividades.length + '</b></div>' +
      '<div class="revision-dato"><span>Preguntas</span><b>' + totalPreguntas() + '</b></div>' +
      '<div class="revision-dato"><span>Duración estimada</span><b>~' + minutosEstimados() + ' min</b></div>' +
      '</div>' +
      '<ul class="revision-lista">' + filas + '</ul>' +
      // Cómo se usa se decide después, en "Mis paquetes": aquí solo se crea.
      '<p class="hint">Al crearlo podrás <b>jugarlo en vivo</b> o <b>enviarlo</b> para que cada ' +
      'estudiante lo haga por su cuenta. Eso se elige desde «Mis paquetes», cuando quieras.</p>';
  }

  /* ---------- Render general ---------- */

  function mostrarPaso() {
    for (var p = 1; p <= ULTIMO_PASO; p++) {
      document.getElementById('paso-' + p).hidden = (p !== state.paso);
    }
    el.atras.hidden = state.paso === 1;
    el.siguiente.hidden = state.paso === ULTIMO_PASO;
    el.continuar.hidden = state.paso !== ULTIMO_PASO;
  }

  function renderBarra() {
    var falta = faltaEnPaso(state.paso);
    el.siguiente.disabled = !!falta;
    el.continuar.disabled = !!(faltaEnPaso(1) || faltaEnPaso(2));
    el.status.textContent = falta
      ? 'Para seguir: ' + falta
      : (state.paso === ULTIMO_PASO
        ? (editando()
          ? '✓ Listo · se guarda en el mismo paquete, con su código y su QR'
          : '✓ Todo listo · el borrador se borra al cerrar la pestaña')
        : '✓ Puedes continuar');
  }

  function render() {
    var a = actividadActiva();
    if (!a) state.activa = null;
    else if (state.item >= a.items.length) state.item = a.items.length - 1;

    renderPasos();
    mostrarPaso();

    if (state.paso === 1) {
      el.nombre.value = state.nombre;
      el.audiencia.value = state.audiencia;
    }
    if (state.paso === 2) {
      renderResumenTema();
      renderPlan();
      renderSlots();
      if (a) renderEditor(a); else renderCatalogo();
      renderPreview();
    }
    if (state.paso === 3) renderRevision();

    renderBarra();
  }

  // Mientras se escribe: sin reconstruir el formulario (conserva el foco).
  function refrescar() {
    var a = actividadActiva();
    if (state.paso === 2) {
      renderResumenTema();
      renderSlots();
      if (a) renderErrores(a);
      renderPreview();
    }
    renderPasos();
    renderBarra();
    guardar();
  }

  /* ---------- Utilidades de ruta ("pares.1.izquierda") ---------- */

  function segmentos(ruta) {
    return ruta.split('.').map(function (s) { return /^\d+$/.test(s) ? Number(s) : s; });
  }
  function leer(obj, ruta) {
    return segmentos(ruta).reduce(function (o, k) { return o == null ? o : o[k]; }, obj);
  }
  function escribir(obj, ruta, valor) {
    var partes = segmentos(ruta);
    var ultimo = partes.pop();
    var destino = partes.reduce(function (o, k) { return o[k]; }, obj);
    destino[ultimo] = valor;
  }

  function convertir(input) {
    var tipo = input.getAttribute('data-type');
    if (tipo === 'number') return Number(input.value);
    if (tipo === 'bool') return input.value === 'true';
    if (tipo === 'check') return input.checked;
    return input.value;
  }

  function filaNueva(ruta) {
    var clave = ruta.split('.').pop();
    if (clave === 'pares') return { izquierda: '', derecha: '' };
    if (clave === 'entradas') return { palabra: '', pista: '' };
    if (clave === 'tarjetas') return { titulo: '', texto: '' };
    if (clave === 'categorias') return { nombre: '', elementos: ['', ''] };
    return '';
  }

  function primerCampo(ruta, indice) {
    var clave = ruta.split('.').pop();
    var sufijo = clave === 'pares' ? '.izquierda' : clave === 'entradas' ? '.palabra'
      : clave === 'tarjetas' ? '.titulo' : clave === 'categorias' ? '.nombre' : '';
    return '[data-bind="' + ruta + '.' + indice + sufijo + '"]';
  }

  function enfocar(selector) {
    var campo = root.querySelector(selector);
    if (campo) campo.focus();
  }

  /* ---------- Eventos de escritura ---------- */

  function alEscribir(evento) {
    var t = evento.target;
    var a = actividadActiva();

    if (t.hasAttribute('data-sesion')) {
      state[t.getAttribute('data-sesion')] = t.value;
    } else if (t.hasAttribute('data-act') && a) {
      a[t.getAttribute('data-act')] = convertir(t);
      // La vista del vídeo se refresca sola, sin redibujar el formulario.
      if (t.getAttribute('data-act') === 'video') refrescarVideo(t.value);
    } else if (t.hasAttribute('data-bind') && a) {
      var item = a.items[state.item];
      var ruta = t.getAttribute('data-bind');
      if (t.getAttribute('data-type') === 'toggle-index') {
        var indice = Number(t.value);
        var marcadas = leer(item, ruta).filter(function (i) { return i !== indice; });
        if (t.checked) marcadas.push(indice);
        escribir(item, ruta, marcadas.sort());
      } else {
        escribir(item, ruta, convertir(t));
      }
    } else {
      return;
    }

    // Un <select> puede cambiar la ayuda o la forma del formulario: ahí sí se redibuja.
    if (t.tagName === 'SELECT' && t.hasAttribute('data-bind')) {
      render();
      guardar();
      var mismo = t.id && document.getElementById(t.id);
      if (mismo) mismo.focus();
      return;
    }

    refrescar();
  }

  root.addEventListener('input', alEscribir);
  root.addEventListener('change', alEscribir);

  /* ---------- Clics ---------- */

  root.addEventListener('click', function (evento) {
    var boton = evento.target.closest('button');
    if (!boton || boton.disabled) return;

    // Navegación por la barra de pasos y atajos ("Cambiar tema", "Editar").
    if (boton.hasAttribute('data-paso')) { irA(Number(boton.getAttribute('data-paso'))); return; }
    if (boton.hasAttribute('data-editar')) {
      state.activa = boton.getAttribute('data-editar');
      state.item = 0;
      irA(2);   // el paso de actividades
      return;
    }
    if (!boton.hasAttribute('data-action')) return;

    var accion = boton.getAttribute('data-action');
    var a = actividadActiva();
    var item = a ? a.items[state.item] : null;
    var enfoque = null;

    switch (accion) {
      case 'ver-catalogo':
        state.activa = null;
        break;

      case 'formato-bloqueado':
        el.status.textContent = '🔒 «' + F.tipos[boton.getAttribute('data-tipo')].nombre +
          '» es de los planes Estándar y Pro. Tu plan gratis incluye los otros ' +
          (PLAN.formatos === '*' ? '' : PLAN.formatos.length + ' ') + 'formatos.';
        return;

      case 'agregar': {
        if (lleno()) return;
        var clave = boton.getAttribute('data-tipo');
        if (!formatoDelPlan(clave)) return;
        var tipo = F.tipos[clave];
        var nueva = {
          uid: 'a' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
          tipo: clave,
          // El nombre se asume del formato: quien elige "Sopa de letras" no
          // tiene que volver a escribir "Sopa de letras". Se guarda de verdad
          // (no es un texto de relleno) porque el juego, la proyección y los
          // informes lo muestran, y vacío salía « · pregunta 1 de 3».
          titulo: tipo.nombre,
          tiempo: tipo.puntua ? 20 : 30,
          items: [tipo.nuevoItem()]
        };
        state.actividades.push(nueva);
        state.activa = nueva.uid;
        state.item = 0;
        // Directo a la pregunta: es lo único que hay que escribir.
        enfoque = '.item-card [data-bind]';
        break;
      }

      case 'abrir-actividad':
        state.activa = boton.getAttribute('data-uid');
        state.item = 0;
        break;

      case 'eliminar-actividad':
        if (!a || !window.confirm('¿Eliminar esta actividad y todas sus preguntas?')) return;
        state.actividades = state.actividades.filter(function (x) { return x.uid !== a.uid; });
        state.activa = null;
        break;

      case 'ir-item':
        state.item = Number(boton.getAttribute('data-index'));
        break;

      case 'quitar-imagen': {
        var conImagen = a.items[state.item];
        var dondeEsta = boton.getAttribute('data-destino');

        if (dondeEsta) {
          borrarEnServidor(leerRuta(conImagen, dondeEsta + 'Archivo'));
          escribirRuta(conImagen, dondeEsta, '');
          escribirRuta(conImagen, dondeEsta + 'Archivo', '');
          escribirRuta(conImagen, dondeEsta + 'ArchivoPrevio', '');
        } else {
          borrarEnServidor(conImagen.imagenArchivo);
          delete conImagen.imagen;
          delete conImagen.imagenArchivo;
          delete conImagen.imagenArchivoPrevio;
        }
        break;
      }

      case 'agregar-item':
        a.items.push(F.tipos[a.tipo].nuevoItem());
        state.item = a.items.length - 1;
        enfoque = '.item-card [data-bind]';
        break;

      case 'duplicar-item':
        a.items.splice(state.item + 1, 0, JSON.parse(JSON.stringify(item)));
        state.item += 1;
        break;

      case 'quitar-item':
        if (a.items.length <= 1) return;
        a.items.splice(state.item, 1);
        state.item = Math.max(0, state.item - 1);
        break;

      case 'agregar-fila': {
        var key = boton.getAttribute('data-key');
        var lista = leer(item, key);
        lista.push(filaNueva(key));
        enfoque = primerCampo(key, lista.length - 1);
        break;
      }

      case 'quitar-fila': {
        var k = boton.getAttribute('data-key');
        var i = Number(boton.getAttribute('data-index'));
        leer(item, k).splice(i, 1);
        if (k === 'opciones' && typeof item.correcta === 'number') {
          if (item.correcta === i) item.correcta = 0;
          else if (item.correcta > i) item.correcta -= 1;
        }
        if (k === 'opciones' && Array.isArray(item.correctas)) {
          item.correctas = item.correctas.filter(function (c) { return c !== i; }).map(function (c) { return c > i ? c - 1 : c; });
        }
        break;
      }

      default:
        return;
    }

    render();
    guardar();

    // En móvil el editor queda debajo del panel: llevar la vista hasta él.
    var cambiaVista = accion === 'agregar' || accion === 'abrir-actividad' || accion === 'ver-catalogo' || accion === 'eliminar-actividad';
    if (cambiaVista && window.matchMedia('(max-width: 820px)').matches) {
      var reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      el.main.scrollIntoView({ block: 'start', behavior: reducir ? 'auto' : 'smooth' });
    }

    if (enfoque) enfocar(enfoque);
  });

  /* ---------- Imagen de la pregunta ---------- */

  function avisoImagen(texto, clase) {
    var estado = document.getElementById('img-estado');
    if (!estado) return;
    estado.textContent = texto || 'Se ve arriba de la pregunta. JPG, PNG, GIF o WebP.';
    estado.className = 'hint' + (clase ? ' ' + clase : '');
  }

  // El input de archivo vive dentro del formulario del ítem (lo dibuja editor.js).
  root.addEventListener('change', function (evento) {
    var entrada = evento.target;
    if (entrada.getAttribute('data-action') !== 'subir-imagen') return;

    var archivo = entrada.files && entrada.files[0];
    var a = actividadActiva();
    if (!archivo || !a) return;

    avisoImagen('Subiendo «' + archivo.name + '»…', 'subiendo');
    var cuerpo = new FormData();
    cuerpo.append('imagen', archivo);

    fetch(window.LudiaRutas.api('imagen-subir.php'), { method: 'POST', body: cuerpo })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        entrada.value = '';
        if (datos.ingresar) {
          location.href = datos.ingresar + '?volver=' + encodeURIComponent(location.pathname);
          return;
        }
        if (!datos.ok) {
          avisoImagen(datos.error, 'error');
          return;
        }

        var item = a.items[state.item];
        // `data-destino` dice DÓNDE va la imagen: sin él es la de la pregunta,
        // con él es la de un lado concreto de una pareja («pares.0.imagen_izq»).
        var destino = entrada.getAttribute('data-destino');

        if (destino) {
          var antesEn = leerRuta(item, destino);
          escribirRuta(item, destino, datos.imagen.url);
          escribirRuta(item, destino + 'Archivo', datos.imagen.archivo);
          if (antesEn) borrarEnServidor(leerRuta(item, destino + 'ArchivoPrevio'));
          escribirRuta(item, destino + 'ArchivoPrevio', datos.imagen.archivo);
        } else {
          var anterior = item.imagen;
          item.imagen = datos.imagen.url;
          item.imagenArchivo = datos.imagen.archivo;
          if (anterior && item.imagenArchivoPrevio !== datos.imagen.archivo) {
            borrarEnServidor(item.imagenArchivoPrevio);
          }
          item.imagenArchivoPrevio = datos.imagen.archivo;
        }

        render();
        guardar();
      })
      .catch(function () {
        entrada.value = '';
        avisoImagen('No se pudo subir la imagen. Revisa tu conexión.', 'error');
      });
  });

  /**
   * Leer y escribir por ruta con puntos: «pares.0.imagen_izq».
   *
   * Hacen falta porque una imagen ya no vive solo en `item.imagen`: puede estar
   * en un lado de una pareja, y el manejador de subida no sabe de antemano
   * dónde. Crean los tramos que falten al escribir.
   */
  function leerRuta(obj, ruta) {
    return ruta.split('.').reduce(function (o, tramo) {
      return (o === null || o === undefined) ? undefined : o[tramo];
    }, obj);
  }

  function escribirRuta(obj, ruta, valor) {
    var tramos = ruta.split('.');
    var ultimo = tramos.pop();
    var donde = tramos.reduce(function (o, tramo) {
      if (o[tramo] === null || o[tramo] === undefined) o[tramo] = {};
      return o[tramo];
    }, obj);
    donde[ultimo] = valor;
  }

  /** Borra del servidor una imagen que ya no usa ninguna pregunta. */
  function borrarEnServidor(archivo) {
    if (!archivo) return;
    fetch(window.LudiaRutas.api('imagen-eliminar.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ archivo: archivo })
    }).catch(function () { /* si falla, queda como archivo suelto: no rompe nada */ });
  }

  /* ---------- Cargar actividades desde Excel ---------- */

  var importadas = [];

  function avisoExcel(texto, tipo) {
    el.excelEstado.textContent = texto;
    el.excelEstado.className = 'mensaje ' + (tipo || 'error');
    el.excelEstado.hidden = !texto;
  }

  if (el.archivo) {
    el.archivo.addEventListener('change', function () {
      var archivo = el.archivo.files && el.archivo.files[0];
      if (!archivo) return;

      avisoExcel('Leyendo «' + archivo.name + '»…', 'ok');
      var cuerpo = new FormData();
      cuerpo.append('archivo', archivo);

      fetch(window.LudiaRutas.api('plantilla-importar.php'), { method: 'POST', body: cuerpo })
        .then(function (r) { return r.json(); })
        .then(function (datos) {
          el.archivo.value = '';   // permite volver a subir el mismo archivo

          if (datos.ingresar) {
            location.href = datos.ingresar + '?volver=' + encodeURIComponent(location.pathname);
            return;
          }
          if (!datos.ok) {
            avisoExcel(datos.error + (datos.avisos && datos.avisos.length ? ' · ' + datos.avisos[0] : ''));
            return;
          }

          importadas = datos.actividades;
          avisoExcel('');
          mostrarImportadas(datos);
        })
        .catch(function () {
          el.archivo.value = '';
          avisoExcel('No se pudo subir el archivo. Revisa tu conexión.');
        });
    });
  }

  function mostrarImportadas(datos) {
    var filas = datos.actividades.map(function (a) {
      var tipo = F.tipos[a.tipo];
      return '<li class="revision-fila">' +
        '<span class="slot-icon" aria-hidden="true">' + (tipo ? tipo.icono : '📄') + '</span>' +
        '<span><b>' + esc(a.titulo) + '</b>' +
        '<span class="slot-meta">' + esc(tipo ? tipo.nombre : a.tipo) + ' · ' +
        plural(a.items.length, 'pregunta', 'preguntas') + '</span></span></li>';
    }).join('');

    var avisos = datos.avisos && datos.avisos.length
      ? '<div class="importar-avisos"><b>Revisa estas filas (el resto sí se importa):</b><ul>' +
        datos.avisos.slice(0, 8).map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') +
        (datos.avisos.length > 8 ? '<li>…y ' + (datos.avisos.length - 8) + ' más</li>' : '') + '</ul></div>'
      : '';

    el.importarCuerpo.innerHTML =
      '<p class="cuenta-sub">' + plural(datos.total, 'actividad', 'actividades') + ' · ' +
      plural(datos.preguntas, 'pregunta', 'preguntas') + '</p>' +
      '<ul class="revision-lista" style="margin-top:14px">' + filas + '</ul>' + avisos;

    if (typeof el.dialogoImportar.showModal === 'function') el.dialogoImportar.showModal();
  }

  el.dialogoImportar.addEventListener('click', function (evento) {
    if (evento.target.closest('[data-cerrar-importar]') || evento.target === el.dialogoImportar) {
      el.dialogoImportar.close();
      return;
    }

    if (evento.target.closest('[data-importar-aceptar]')) {
      importadas.forEach(function (a) {
        a.uid = 'a' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        state.actividades.push(a);
      });
      var cuantas = importadas.length;
      importadas = [];
      el.dialogoImportar.close();
      state.activa = null;
      render();
      guardar();
      avisoExcel('✓ Se agregaron ' + plural(cuantas, 'actividad', 'actividades') + ' desde tu Excel.', 'ok');
    }
  });

  /* ---------- Crear el paquete ---------- */

  el.continuar.addEventListener('click', function () {
    if (el.continuar.disabled) return;

    var esEdicion = editando();
    var etiqueta = esEdicion ? 'Guardar cambios →' : 'Guardar paquete →';

    function devolverBoton(mensaje) {
      if (el.dialogo.open) el.dialogo.close();
      el.continuar.disabled = false;
      el.continuar.textContent = etiqueta;
      if (mensaje) el.status.textContent = '⚠️ ' + mensaje;
    }

    el.continuar.disabled = true;
    el.continuar.textContent = 'Guardando…';
    if (typeof el.dialogo.showModal === 'function') el.dialogo.showModal();

    var cuerpo = {
      nombre: state.nombre,
      audiencia: state.audiencia,
      modo: state.modo,
      actividades: state.actividades
    };
    if (esEdicion) cuerpo.id = state.paqueteId;

    // Un solo endpoint, y solo para el contenido: aquí ya no se crea ningún
    // código ni sala. Jugar o enviar es otra acción, y cada vez abre su propia
    // sesión con una copia de este contenido.
    fetch(window.LudiaRutas.api('paquete-guardar.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cuerpo)
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (datos.ingresar) {
          location.href = datos.ingresar + '?volver=' + encodeURIComponent(location.pathname);
          return;
        }
        // Se llegó al tope de paquetes del plan.
        if (datos.mis_sesiones) {
          devolverBoton(datos.error);
          if (window.confirm(datos.error + '\n\n¿Quieres ver tus paquetes?')) location.href = datos.mis_sesiones;
          return;
        }
        // Un formato que el plan no incluye (el servidor siempre lo revisa).
        if (datos.planes) {
          devolverBoton(datos.error);
          if (window.confirm(datos.error + '\n\n¿Quieres ver los planes?')) location.href = datos.planes;
          return;
        }
        if (!datos.ok) throw new Error(datos.error || 'No pudimos guardar el paquete.');

        // El borrador ya cumplió: si se queda, la próxima vez reaparece.
        try { sessionStorage.removeItem(CLAVE_BORRADOR); } catch (e) { /* sin almacenamiento */ }
        // Guardar termina en la lista de paquetes: es ahí donde se decide si se
        // juega en vivo o se envía.
        location.href = datos.url_paquetes;
      })
      .catch(function (error) { devolverBoton(error.message); });
  });

  el.dialogo.addEventListener('click', function (evento) {
    if (evento.target.closest('[data-close-dialog]') || evento.target === el.dialogo) el.dialogo.close();
  });

  /* ---------- Inicio ---------- */

  el.audiencia.innerHTML = F.audiencias.map(function (x) {
    return '<option value="' + x.clave + '">' + esc(x.nombre) + '</option>';
  }).join('');

  render();
})();
