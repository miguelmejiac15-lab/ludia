/* =====================================================================
   Ludia — participante: personaje, entrada y juego.

   Dos ritmos, según el paquete:
     · en vivo  → espera al anfitrión; la pregunta la marca él.
     · enviado  → entra jugando y avanza solo al responder.
   Sondeo cada 3 segundos; el cronómetro corre en local entre consultas.
   ===================================================================== */
(function () {
  'use strict';

  var raiz = document.querySelector('[data-unirse]');
  if (!raiz) return;

  var P = window.LudiaPersonaje;

  var A = window.LudiaAnim;   // animaciones (main.js); puede no estar
  var J = window.LudiaJugar;
  var R = window.LudiaRutas;
  var INTERVALO = 3000;
  var CLAVE_PERSONAJE = 'ludia.personaje';

  var el = {
    codigo: document.getElementById('campo-codigo'),
    nombre: document.getElementById('campo-nombre'),
    sugerir: document.getElementById('btn-sugerir'),
    entrar: document.getElementById('btn-entrar'),
    mensaje: document.getElementById('mensaje'),
    vista: document.getElementById('avatar-vista'),
    vistaNombre: document.getElementById('avatar-nombre'),
    acceso: document.getElementById('paso-acceso'),
    espera: document.getElementById('paso-espera'),
    esperaEstado: document.getElementById('espera-estado'),
    esperaTitulo: document.getElementById('espera-titulo'),
    esperaSub: document.getElementById('espera-sub'),
    esperaConteo: document.getElementById('espera-conteo'),
    esperaLista: document.getElementById('espera-lista'),
    avatarFinal: document.getElementById('avatar-final'),
    nombreFinal: document.getElementById('nombre-final'),
    juego: document.getElementById('paso-juego'),
    juegoYo: document.getElementById('juego-yo'),
    juegoCrono: document.getElementById('juego-crono'),
    juegoPuntaje: document.getElementById('juego-puntaje'),
    juegoPaso: document.getElementById('juego-paso'),
    juegoCuerpo: document.getElementById('juego-cuerpo'),
    final: document.getElementById('paso-final'),
    finalTitulo: document.getElementById('final-titulo'),
    finalSub: document.getElementById('final-sub'),
    finalPodio: document.getElementById('final-podio')
  };

  var personaje = cargarPersonaje() || P.nuevo();
  var codigoActual = '';
  var token = '';
  var miNombre = '';
  var modo = 'vivo';
  var finCronometro = 0;
  var preguntaActual = '';

  /* ---------- Personaje ---------- */

  function cargarPersonaje() {
    try {
      var guardado = JSON.parse(localStorage.getItem(CLAVE_PERSONAJE) || 'null');
      return guardado && guardado.criatura ? guardado : null;
    } catch (e) { return null; }
  }

  function guardarPersonaje() {
    try { localStorage.setItem(CLAVE_PERSONAJE, JSON.stringify(personaje)); } catch (e) { /* sin almacenamiento */ }
  }

  function pintarOpciones(contenedor, lista, campo, contenido) {
    contenedor.innerHTML = lista.map(function (op) {
      var activo = personaje[campo] === op.clave;
      return '<button type="button" class="opcion' + (campo === 'color' ? ' color' : '') + (contenido(op) ? '' : ' vacia') + '"' +
        ' data-campo="' + campo + '" data-valor="' + op.clave + '" aria-pressed="' + activo + '"' +
        ' title="' + op.nombre + '" aria-label="' + op.nombre + '"' +
        (op.valor ? ' style="background:' + op.valor + '"' : '') + '>' +
        (contenido(op) || 'Sin') + '</button>';
    }).join('');
  }

  function pintarPersonaje() {
    pintarOpciones(document.getElementById('opciones-criatura'), P.criaturas, 'criatura', function (c) { return c.emoji; });
    pintarOpciones(document.getElementById('opciones-color'), P.colores, 'color', function () { return ' '; });
    pintarOpciones(document.getElementById('opciones-accesorio'), P.accesorios, 'accesorio', function (a) { return a.emoji; });
    el.vista.innerHTML = P.avatar(P.completo(personaje), 'xl');
    el.vistaNombre.textContent = el.nombre.value.trim() || 'Tu personaje';
  }

  raiz.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-campo]');
    if (!boton) return;
    personaje[boton.getAttribute('data-campo')] = boton.getAttribute('data-valor');
    guardarPersonaje();
    pintarPersonaje();
  });

  el.sugerir.addEventListener('click', function () {
    el.nombre.value = P.nombreSugerido(personaje);
    el.vistaNombre.textContent = el.nombre.value;
    el.nombre.focus();
  });

  el.nombre.addEventListener('input', function () {
    el.vistaNombre.textContent = el.nombre.value.trim() || 'Tu personaje';
  });

  el.codigo.addEventListener('input', function () {
    el.codigo.value = el.codigo.value.replace(/\D/g, '').slice(0, 6);
  });

  /* ---------- Entrar ---------- */

  function mostrarMensaje(texto, tipo) {
    el.mensaje.textContent = texto;
    el.mensaje.className = 'mensaje ' + (tipo || 'error');
    el.mensaje.hidden = !texto;
  }

  el.entrar.addEventListener('click', entrar);
  el.nombre.addEventListener('keydown', function (e) { if (e.key === 'Enter') entrar(); });

  function entrar() {
    var codigo = el.codigo.value.replace(/\D/g, '');
    var nombre = el.nombre.value.trim();

    if (codigo.length !== 6) return mostrarMensaje('El código tiene 6 dígitos.');
    if (nombre.length < 2) return mostrarMensaje('Escribe tu nombre (mínimo 2 letras).');

    mostrarMensaje('');
    el.entrar.disabled = true;
    el.entrar.textContent = 'Entrando…';

    fetch(R.api('sesion-unirse.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: codigo, nombre: nombre, personaje: P.completo(personaje) })
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        el.entrar.disabled = false;
        el.entrar.textContent = 'Entrar';
        if (!datos.ok) return mostrarMensaje(datos.error || 'No pudimos entrar.');

        codigoActual = codigo;
        token = datos.token;
        miNombre = datos.nombre;
        recordarToken(codigo, nombre, datos.token);
        mostrarEspera(datos.nombre, datos.sesion.nombre);
        arrancarSondeo();
      })
      .catch(function () {
        el.entrar.disabled = false;
        el.entrar.textContent = 'Entrar';
        mostrarMensaje('No hay conexión con el servidor. Inténtalo de nuevo.');
      });
  }

  function recordarToken(codigo, nombre, valor) {
    try {
      sessionStorage.setItem('ludia.participante.' + codigo, JSON.stringify({ token: valor, nombre: nombre }));
    } catch (e) { /* sin almacenamiento: al recargar tendrá que volver a entrar */ }
  }

  function leerToken(codigo) {
    try {
      return JSON.parse(sessionStorage.getItem('ludia.participante.' + codigo) || 'null');
    } catch (e) { return null; }
  }

  /* ---------- Pasos visibles ---------- */

  function mostrarPaso(cual) {
    var columna = document.getElementById('juego-plantillas');
    if (columna && cual !== 'juego') { columna.hidden = true; raiz.classList.remove('con-plantillas'); }
    if (el.pasoCodigo) el.pasoCodigo.hidden = cual !== 'codigo';
    el.acceso.hidden = cual !== 'acceso';
    el.espera.hidden = cual !== 'espera';
    el.juego.hidden = cual !== 'juego';
    el.final.hidden = cual !== 'final';
  }

  function mostrarEspera(nombre, nombreSesion) {
    miNombre = nombre;
    mostrarPaso('espera');
    el.avatarFinal.innerHTML = P.avatar(P.completo(personaje), 'xl');
    el.nombreFinal.textContent = nombre;
    el.esperaSub.textContent = 'Preparando «' + nombreSesion + '»…';
  }

  function escapar(texto) {
    return String(texto).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Sala de espera (solo en vivo) ---------- */

  function pintarEspera(datos) {
    mostrarPaso('espera');
    el.esperaEstado.textContent = 'Ya estás dentro';
    el.esperaTitulo.textContent = 'Espera a que comience';
    el.esperaSub.textContent = 'El anfitrión iniciará «' + datos.sesion.nombre + '» en un momento.';
    el.esperaConteo.textContent = datos.total === 1
      ? 'Eres la primera persona en entrar.'
      : 'Ya somos ' + datos.total + ' en la sala.';

    // El profesor arma los equipos mientras la gente espera: hay que verlo aquí,
    // antes de empezar, no descubrirlo cuando ya está corriendo el cronómetro.
    if (datos.yo && datos.yo.equipo) {
      el.esperaConteo.innerHTML = escapar(el.esperaConteo.textContent) +
        ' <span class="mi-equipo eq-' + escapar(datos.yo.equipo.color) + '">' +
        escapar(datos.yo.equipo.nombre) + '</span>';
    }

    el.esperaLista.innerHTML = datos.participantes.map(function (p) {
      return '<span class="espera-chip">' + P.avatar(p.personaje, 'sm') + escapar(p.nombre) + '</span>';
    }).join('');
  }

  /* ---------- Juego ---------- */

  /* ---------- Juego enviado: elegir cómo jugar ---------- */

  var firmaEleccion = '';

  /** Pide al servidor jugar otra forma (-1 = volver al menú, 'terminar'). */
  function elegirJuego(actividad) {
    return fetch(R.api('participante-elegir.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: codigoActual, token: token, actividad: actividad })
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (datos.terminado) { pintarFinal({ ranking: datos.ranking || [] }); return; }
        if (!datos.ok && datos.error) {
          el.juegoCuerpo.insertAdjacentHTML('beforeend', '<p class="mensaje error">' + escapar(datos.error) + '</p>');
          return;
        }
        preguntaActual = '';
        firmaEleccion = '';
        consultar();
      });
  }

  function botonCambiar(visible) {
    var boton = document.getElementById('btn-cambiar-juego');
    if (!boton && visible) {
      el.juegoPaso.insertAdjacentHTML('afterend',
        '<button type="button" class="link-btn j-cambiar" id="btn-cambiar-juego" data-ir-plantillas>🔄 Cambiar juego ↓</button>');
      boton = document.getElementById('btn-cambiar-juego');
    }
    if (boton) boton.hidden = !visible;
  }

  /* Columna «Cambiar juego» (como «Cambiar plantilla» en Wordwall): 5 a la
     vista y «Mostrar todo» para el resto. La actual siempre se ve. */
  var lado = document.getElementById('juego-plantillas');
  var verTodas = false, firmaLado = '';

  function pintarLado(eleccion) {
    if (!lado) return;
    raiz.classList.toggle('con-plantillas', !!eleccion);
    lado.hidden = !eleccion;
    if (!eleccion) return;
    var opciones = eleccion.opciones;
    var firma = JSON.stringify(opciones) + eleccion.actual + verTodas;
    if (firma === firmaLado) return;
    firmaLado = firma;
    var visibles = verTodas ? opciones : opciones.filter(function (o, n) { return n < 5 || o.indice === eleccion.actual; });
    lado.innerHTML = '<h2 class="plantillas-titulo" id="plantillas-titulo">Cambiar juego</h2>' +
      '<p class="plantillas-sub">Las mismas preguntas, otro juego.</p>' +
      '<div class="plantillas-lista">' + visibles.map(function (o) {
        var actual = o.indice === eleccion.actual;
        var nota = actual ? 'Jugando' : (o.completo ? '✓ ' + o.puntos + ' pts' : (o.respondidas ? o.respondidas + ' de ' + o.items : ''));
        return '<button type="button" class="plantilla-op' + (actual ? ' actual' : '') + (o.completo ? ' hecho' : '') + '"' +
          ' data-elegir-juego="' + o.indice + '"' + (actual ? ' aria-current="true"' : '') + (o.completo && !actual ? ' disabled' : '') +
          ' title="' + escapar(o.descripcion) + '">' +
          '<span class="plantilla-icono" aria-hidden="true">' + o.icono + '</span>' +
          '<span class="plantilla-nombre">' + escapar(o.nombre) + '</span>' +
          (nota ? '<small>' + nota + '</small>' : '') + '</button>';
      }).join('') + '</div>' +
      (opciones.length > 5
        ? '<button type="button" class="link-btn plantillas-mas" data-ver-plantillas>' +
          (verTodas ? 'Mostrar menos' : 'Mostrar todo (' + opciones.length + ')') + '</button>'
        : '');
  }

  function pintarEleccion(datos) {
    mostrarPaso('juego');
    botonCambiar(false);
    // En el menú ya están todas las opciones: la columna sobra.
    pintarLado(null);
    if (datos.yo) {
      el.juegoYo.innerHTML = P.avatar(datos.yo.personaje, 'sm') + ' ' + escapar(datos.yo.nombre);
      el.juegoPuntaje.textContent = '⭐ ' + datos.yo.puntaje;
    }
    el.juegoCrono.classList.add('lectura');
    el.juegoCrono.setAttribute('data-espera', '');
    el.juegoCrono.textContent = '🎲 Elige';
    el.juegoPaso.textContent = datos.sesion.nombre;

    var opciones = datos.eleccion.opciones;
    // El sondeo repasa cada 3 s: solo se repinta si algo cambió.
    var firma = JSON.stringify(opciones);
    if (firma === firmaEleccion) return;
    firmaEleccion = firma;
    var jugadas = opciones.filter(function (o) { return o.respondidas > 0; }).length;

    el.juegoCuerpo.innerHTML = '<div class="eleccion">' +
      '<h2 class="eleccion-titulo">' + (jugadas ? '¿Jugamos de otra forma?' : '¿Cómo quieres jugar?') + '</h2>' +
      '<p class="eleccion-sub">' + (jugadas
        ? 'Elige otro juego con las mismas preguntas, o termina cuando quieras.'
        : 'Son las mismas preguntas en distintos juegos. Elige el que más te guste.') + '</p>' +
      '<div class="eleccion-lista">' + opciones.map(function (o, n) {
        var estado = o.completo ? '<span class="eleccion-hecho">✓ ' + o.puntos + ' pts</span>'
          : (o.respondidas ? '<span class="eleccion-sigue">Sigue: ' + o.respondidas + ' de ' + o.items + '</span>'
          : (n === 0 && !jugadas ? '<span class="eleccion-recomendado">⭐ Tu profe lo eligió</span>' : ''));
        return '<button type="button" class="eleccion-op' + (o.completo ? ' hecho' : '') + '" data-elegir-juego="' + o.indice + '" title="' + escapar(o.descripcion) + '"' +
          (o.completo ? ' disabled' : '') + '>' +
          '<span class="eleccion-icono" aria-hidden="true">' + o.icono + '</span>' +
          '<span class="eleccion-texto"><b>' + escapar(o.nombre) + '</b><small>' + escapar(o.descripcion) + '</small></span>' +
          estado + '</button>';
      }).join('') + '</div>' +
      (jugadas ? '<button type="button" class="btn btn-ghost btn-block" data-terminar-juego>Terminar y ver mi puntaje</button>' : '') +
      '</div>';
    if (A) A.marcar(el.juegoCuerpo, 'entra', 700);
  }

  function pintarJuego(datos) {
    mostrarPaso('juego');
    botonCambiar(!!datos.eleccion);
    pintarLado(datos.eleccion || null);
    var actividad = datos.actividad;
    if (!actividad) return;

    // En vivo, una pregunta de vídeo cambia de «cerrada» a «abierta» sin
    // cambiar de número: eso cuenta como pregunta nueva (se dibuja y el reloj
    // arranca en ese momento).
    var clave = actividad.indice + '-' + actividad.item_indice + (actividad.abierta === false ? '-c' : '');
    var esNueva = clave !== preguntaActual;
    if (esNueva) {
      preguntaActual = clave;
      finCronometro = Date.now() + actividad.restante * 1000;
      J.reiniciar();
    }

    // Una diapositiva no tiene tiempo: se queda hasta que el docente avanza
    // (en vivo) o hasta que el estudiante pulsa «Continuar» (enviado).
    var lectura = !!actividad.lectura;
    var esperaVideo = !!actividad.proyector && actividad.abierta === false;
    el.juegoCrono.classList.toggle('lectura', lectura || esperaVideo);
    el.juegoCrono.setAttribute('data-espera', esperaVideo ? 'video' : '');

    if (datos.yo) {
      // Con equipos, saber de cuál eres es la mitad del juego: la insignia va
      // junto al nombre, no escondida en otra pantalla.
      var insignia = datos.yo.equipo
        ? ' <span class="mi-equipo eq-' + escapar(datos.yo.equipo.color) + '">' + escapar(datos.yo.equipo.nombre) + '</span>'
        : '';
      el.juegoYo.innerHTML = P.avatar(datos.yo.personaje, 'sm') + ' ' + escapar(datos.yo.nombre) + insignia;
      el.juegoPuntaje.textContent = '⭐ ' + datos.yo.puntaje;
    }

    // En un paquete enviado se muestra el avance propio; en vivo, la pregunta del grupo.
    if (modo === 'enviar' && datos.mi_avance) {
      // La posición viene del servidor: contar respuestas no servía, porque las
      // pantallas de lectura no generan ninguna (y el gratis no las guarda).
      var donde = datos.mi_avance.posicion || (datos.mi_avance.respondidas + 1);
      var forma = datos.eleccion ? datos.eleccion.opciones[datos.eleccion.actual] : null;
      el.juegoPaso.textContent = forma
        ? forma.icono + ' ' + forma.nombre + ' · ' + donde + ' de ' + datos.mi_avance.total
        : escapar(actividad.titulo) + ' · ' + donde + ' de ' + datos.mi_avance.total + ' del paquete';
    } else {
      el.juegoPaso.textContent = escapar(actividad.titulo) + ' · pregunta ' +
        (actividad.item_indice + 1) + ' de ' + actividad.total_items;
    }

    // La diapositiva no cambia entre consultas: se pinta una vez, y así su
    // animación de entrada no se repite cada tres segundos.
    if (lectura && !esNueva) {
      actualizarCrono();
      return;
    }
    J.pintar(el.juegoCuerpo, actividad, datos.mi_respuesta);

    // Las pantallas de lectura (panel, página informativa…) no tienen botón de
    // enviar: en un paquete enviado necesitan uno propio o el estudiante se
    // queda encerrado ahí. Se pregunta a jugar.js para no repetir la lista.
    if (modo === 'enviar' && J.esSoloLectura(actividad.item.tipo) && !el.juegoCuerpo.querySelector('[data-continuar]')) {
      el.juegoCuerpo.insertAdjacentHTML('beforeend',
        '<button class="btn btn-primary btn-block" type="button" data-continuar>Continuar →</button>');
    }

    actualizarCrono();
  }

  function actualizarCrono() {
    if (el.juego.hidden) return;
    if (el.juegoCrono.classList.contains('lectura')) {
      el.juegoCrono.textContent = el.juegoCrono.getAttribute('data-espera') === 'video'
        ? '📺 Mira la pantalla'
        : (modo === 'enviar' ? '📖 A tu ritmo' : '📖 Mira y escucha');
      el.juegoCrono.classList.remove('poco');
      return;
    }
    var restante = Math.max(0, Math.round((finCronometro - Date.now()) / 1000));
    el.juegoCrono.textContent = '⏱ ' + restante + ' s';
    el.juegoCrono.classList.toggle('poco', restante <= 5);
  }

  setInterval(actualizarCrono, 1000);

  /** Pasa a la siguiente pantalla en un paquete enviado (mural y panel). */
  function avanzar() {
    return fetch(R.api('participante-avanzar.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: codigoActual, token: token })
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (datos.ok && datos.avance && datos.avance.terminado) {
          pintarFinal({ ranking: datos.ranking || [] });
          return;
        }
        preguntaActual = '';
        consultar();
      });
  }

  raiz.addEventListener('click', function (evento) {
    if (evento.target.closest('[data-continuar]')) avanzar();
    var opcion = evento.target.closest('[data-elegir-juego]');
    // La que ya se está jugando no hace nada (se reiniciaría el reloj).
    if (opcion && !opcion.disabled && !opcion.hasAttribute('aria-current')) {
      opcion.disabled = true;
      elegirJuego(Number(opcion.getAttribute('data-elegir-juego')));
    }
    if (evento.target.closest('[data-ver-plantillas]')) { verTodas = !verTodas; firmaLado = ''; consultar(); }
    if (evento.target.closest('[data-ir-plantillas]') && lado) lado.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var fin = evento.target.closest('[data-terminar-juego]');
    if (fin) { fin.disabled = true; elegirJuego('terminar'); }
  });

  // jugar.js avisa cuando el participante pulsa "Enviar".
  document.addEventListener('ludia:responder', function (evento) {
    var detalle = evento.detail;
    fetch(R.api('respuesta-enviar.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codigo: codigoActual,
        token: token,
        actividad: detalle.actividad,
        item: detalle.item,
        respuesta: detalle.respuesta
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (!datos.ok) {
          el.juegoCuerpo.insertAdjacentHTML('beforeend', '<p class="mensaje error">' + escapar(datos.error) + '</p>');
          return;
        }

        if (detalle.tipo === 'mural') {
          // El mural admite varios aportes: se confirma y se ofrece seguir.
          preguntaActual = '';
          el.juegoCuerpo.innerHTML = '<div class="j-enviada"><b class="bien">✓ Aporte enviado</b>' +
            '<span>Puedes escribir otro.</span></div>' +
            (modo === 'enviar' ? '<button class="btn btn-primary btn-block" type="button" data-continuar>Continuar →</button>' : '');
          setTimeout(consultar, modo === 'enviar' ? 2500 : 1200);
          return;
        }

        // `data-pregunta` hace que jugar.js no la repinte en el siguiente
        // sondeo; `anima` dispara la celebración (o la sacudida) una sola vez.
        el.juegoCuerpo.innerHTML = '<div class="j-enviada anima" data-pregunta="' + escapar(preguntaActual) + '">' + J.resumenEnviado({
          correcta: datos.correcta, aciertos: datos.aciertos, total: datos.total, puntos: datos.puntos
        }) + '</div>';
        if (datos.puntaje !== undefined) {
          el.juegoPuntaje.textContent = '⭐ ' + datos.puntaje;
          if (datos.puntos > 0 && A) A.marcar(el.juegoPuntaje, 'sube-puntaje', 700);
        }
        if (datos.correcta && A) A.confeti(28);

        // En un paquete enviado, el servidor ya avanzó: se pasa solo tras el aviso.
        if (datos.avance) {
          if (datos.avance.terminado) {
            setTimeout(function () { pintarFinal({ ranking: datos.ranking || [] }); }, 1600);
          } else {
            preguntaActual = '';
            setTimeout(consultar, 1600);
          }
        }
      })
      .catch(function () {
        el.juegoCuerpo.insertAdjacentHTML('beforeend', '<p class="mensaje error">No se pudo enviar. Revisa tu conexión.</p>');
      });
  });

  /* ---------- Resultados ---------- */

  function pintarFinal(datos) {
    mostrarPaso('final');
    detener();

    var ranking = datos.ranking || [];
    var yo = null;
    ranking.forEach(function (f) { if (f.nombre === miNombre) yo = f; });

    el.finalTitulo.textContent = yo && yo.posicion === 1 ? '🏆 ¡Quedaste de primero!' : '¡Terminaste!';
    // Celebración para el podio. pintarFinal corre una sola vez (detiene el sondeo).
    if (yo && yo.posicion <= 3 && A) A.confeti(yo.posicion === 1 ? 140 : 70);
    el.finalSub.textContent = yo
      ? 'Puesto ' + yo.posicion + ' de ' + ranking.length + ' · ' + yo.puntaje + ' puntos · ' + yo.aciertos + ' aciertos'
      : 'Gracias por participar.';

    el.finalPodio.innerHTML = ranking.slice(0, 10).map(function (f) {
      return '<div class="podio-fila' + (f.posicion === 1 ? ' top' : '') + (f.nombre === miNombre ? ' yo' : '') + '">' +
        '<span class="podio-pos">' + f.posicion + '</span>' +
        P.avatar(f.personaje, 'sm') +
        '<span><span class="podio-nombre">' + escapar(f.nombre) + '</span>' +
        '<span class="podio-datos">' + f.aciertos + ' aciertos</span></span>' +
        '<span class="podio-puntos">' + f.puntaje + '</span></div>';
    }).join('');
  }

  /* ---------- Sondeo ---------- */

  function pintarEstado(datos) {
    modo = datos.sesion.modo || 'vivo';
    if (J.ponerModo) J.ponerModo(modo);
    var estado = datos.sesion.estado;

    if (estado === 'terminada') { pintarFinal(datos); return; }
    if (datos.yo && datos.yo.terminado) { pintarFinal(datos); return; }
    if (datos.eleccion && datos.eleccion.eligiendo) { pintarEleccion(datos); return; }
    if (datos.actividad) { pintarJuego(datos); return; }
    pintarEspera(datos);
  }

  var temporizador = null;

  function consultar() {
    fetch(R.api('sesion-estado.php') + '?codigo=' + codigoActual + '&token=' + encodeURIComponent(token), {
      headers: { Accept: 'application/json' }
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) { if (datos.ok) pintarEstado(datos); })
      .catch(function () { /* reintenta en el siguiente ciclo */ });
  }

  function arrancarSondeo() {
    consultar();
    if (!temporizador) temporizador = setInterval(consultar, INTERVALO);
  }

  function detener() {
    if (temporizador) clearInterval(temporizador);
    temporizador = null;
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) detener();
    else if (token && el.final.hidden) arrancarSondeo();
  });

  /* ---------- Primer paso: solo el código ---------- */

  el.pasoCodigo = document.getElementById('paso-codigo');
  var formCodigo = document.getElementById('form-codigo');
  var codigoInicialCampo = document.getElementById('codigo-inicial');
  var mensajeCodigo = document.getElementById('mensaje-codigo');

  function avisoCodigo(texto) {
    mensajeCodigo.textContent = texto;
    mensajeCodigo.hidden = !texto;
  }

  if (formCodigo) {
    // Se ve «123 456», como en la pantalla del salón.
    codigoInicialCampo.addEventListener('input', function () {
      var d = codigoInicialCampo.value.replace(/\D/g, '').slice(0, 6);
      codigoInicialCampo.value = d.length > 3 ? d.slice(0, 3) + ' ' + d.slice(3) : d;
      avisoCodigo('');
    });

    formCodigo.addEventListener('submit', function (evento) {
      evento.preventDefault();
      var codigo = codigoInicialCampo.value.replace(/\D/g, '');
      if (codigo.length !== 6) { avisoCodigo('El código tiene 6 números.'); return; }
      var boton = document.getElementById('btn-codigo');
      boton.disabled = true;
      boton.textContent = 'Buscando…';

      // Se comprueba que la sesión exista ANTES de pedir el personaje: si no,
      // el error llegaba al final, después de elegirlo todo.
      fetch(R.api('sesion-estado.php') + '?codigo=' + codigo)
        .then(function (r) { return r.json(); })
        .then(function (datos) {
          boton.disabled = false;
          boton.textContent = 'Entrar';
          if (!datos.ok) { avisoCodigo('No encontramos ese código. Revisa los números de la pantalla.'); return; }
          if (datos.sesion && datos.sesion.estado === 'terminada') { avisoCodigo('Esa sesión ya terminó.'); return; }
          el.codigo.value = codigo;
          raiz.setAttribute('data-codigo', codigo);
          try { history.replaceState(null, '', location.pathname + '?c=' + codigo); } catch (e) { /* nada */ }
          mostrarPaso('acceso');
          el.nombre.focus();
        })
        .catch(function () {
          boton.disabled = false;
          boton.textContent = 'Entrar';
          avisoCodigo('No hay conexión. Inténtalo otra vez.');
        });
    });
  }

  /* ---------- Inicio ---------- */

  pintarPersonaje();
  if (!el.nombre.value) el.nombre.value = P.nombreSugerido(personaje);
  el.vistaNombre.textContent = el.nombre.value;

  // Si ya había entrado desde esta pestaña, vuelve directo a donde iba.
  var codigoInicial = raiz.getAttribute('data-codigo');
  var guardado = codigoInicial ? leerToken(codigoInicial) : null;
  if (guardado && guardado.token) {
    codigoActual = codigoInicial;
    token = guardado.token;
    miNombre = guardado.nombre;
    mostrarEspera(guardado.nombre, 'tu paquete');
    arrancarSondeo();
  } else if (!codigoInicial && codigoInicialCampo) {
    codigoInicialCampo.focus();
  }
})();
