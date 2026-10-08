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

  function pintarJuego(datos) {
    mostrarPaso('juego');
    var actividad = datos.actividad;
    if (!actividad) return;

    var clave = actividad.indice + '-' + actividad.item_indice;
    var esNueva = clave !== preguntaActual;
    if (esNueva) {
      preguntaActual = clave;
      finCronometro = Date.now() + actividad.restante * 1000;
      J.reiniciar();
    }

    // Una diapositiva no tiene tiempo: se queda hasta que el docente avanza
    // (en vivo) o hasta que el estudiante pulsa «Continuar» (enviado).
    var lectura = !!actividad.lectura;
    el.juegoCrono.classList.toggle('lectura', lectura);

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
      el.juegoPaso.textContent = escapar(actividad.titulo) + ' · ' +
        donde + ' de ' + datos.mi_avance.total + ' del paquete';
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
      el.juegoCrono.textContent = modo === 'enviar' ? '📖 A tu ritmo' : '📖 Mira y escucha';
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
    var estado = datos.sesion.estado;

    if (estado === 'terminada') { pintarFinal(datos); return; }
    if (datos.yo && datos.yo.terminado) { pintarFinal(datos); return; }
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
  } else if (!codigoInicial) {
    el.codigo.focus();
  }
})();
