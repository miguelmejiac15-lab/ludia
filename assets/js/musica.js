/* =====================================================================
   Ludia — música para el proyector en vivo (19/10/2026).

   Se genera en el navegador con Web Audio: no hay archivos de audio ni
   música con derechos de autor (la de Kahoot es suya). Dos ambientes:
     · sala   → tranquila, mientras entra la gente.
     · juego  → con más ritmo, mientras corre una pregunta.
   Y efectos sueltos: tic de los últimos segundos, fin del tiempo y la
   fanfarria del podio.

   Solo suena en la pantalla del docente (sala.js). Los navegadores no dejan
   sonar nada hasta que la persona toca la página: el contexto de audio se
   despierta con el primer clic, que en la sala siempre ocurre («Comenzar»,
   «Siguiente» o el propio botón de música).
   ===================================================================== */
window.LudiaMusica = (function () {
  'use strict';

  var CLAVE = 'ludia_musica';
  var ctx = null, maestro = null, ruido = null;
  var activa = true;
  try { activa = localStorage.getItem(CLAVE) !== 'no'; } catch (e) { /* sin almacenamiento */ }

  var modo = null;        // 'sala' | 'juego' | null
  var reloj = null;
  var siguiente = 0;      // instante (s) del próximo paso de la secuencia
  var paso = 0;

  function asegurar() {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return false;
    if (!ctx) {
      ctx = new AC();
      maestro = ctx.createGain();
      maestro.gain.value = 0.22;
      maestro.connect(ctx.destination);
      // Un segundo de ruido blanco, reutilizado para platillos y redoblante.
      ruido = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
      var d = ruido.getChannelData(0);
      for (var i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
    }
    if (ctx.state === 'suspended') ctx.resume();
    return true;
  }

  // Despertar el audio con el primer gesto en la página.
  ['pointerdown', 'keydown'].forEach(function (tipo) {
    document.addEventListener(tipo, function () {
      if (activa && modo && asegurar() && !reloj) arrancar();
    }, { passive: true });
  });

  /* ---------- Instrumentos ---------- */

  function nota(frec, t, dur, onda, vol, filtro) {
    var o = ctx.createOscillator();
    var g = ctx.createGain();
    o.type = onda || 'triangle';
    o.frequency.setValueAtTime(frec, t);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol || 0.2, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    var salida = g;
    if (filtro) {
      var f = ctx.createBiquadFilter();
      f.type = 'lowpass';
      f.frequency.value = filtro;
      g.connect(f);
      salida = f;
    }
    o.connect(g);
    salida.connect(maestro);
    o.start(t);
    o.stop(t + dur + 0.05);
  }

  function bombo(t, vol) {
    var o = ctx.createOscillator();
    var g = ctx.createGain();
    o.frequency.setValueAtTime(140, t);
    o.frequency.exponentialRampToValueAtTime(45, t + 0.12);
    g.gain.setValueAtTime(vol || 0.6, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
    o.connect(g); g.connect(maestro);
    o.start(t); o.stop(t + 0.2);
  }

  function platillo(t, vol, dur) {
    var s = ctx.createBufferSource();
    s.buffer = ruido;
    var f = ctx.createBiquadFilter();
    f.type = 'highpass';
    f.frequency.value = 7000;
    var g = ctx.createGain();
    g.gain.setValueAtTime(vol || 0.08, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + (dur || 0.05));
    s.connect(f); f.connect(g); g.connect(maestro);
    s.start(t); s.stop(t + (dur || 0.05) + 0.02);
  }

  function palmada(t, vol) {
    var s = ctx.createBufferSource();
    s.buffer = ruido;
    var f = ctx.createBiquadFilter();
    f.type = 'bandpass';
    f.frequency.value = 1800;
    var g = ctx.createGain();
    g.gain.setValueAtTime(vol || 0.18, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.12);
    s.connect(f); f.connect(g); g.connect(maestro);
    s.start(t); s.stop(t + 0.14);
  }

  /* ---------- Composición ---------- */

  var hz = function (midi) { return 440 * Math.pow(2, (midi - 69) / 12); };

  // Cuatro acordes alegres en Do mayor: Do · Sol · La menor · Fa.
  var ACORDES = [[60, 64, 67], [55, 59, 62], [57, 60, 64], [53, 57, 60]];
  var BAJOS = [36, 31, 33, 29];
  // Melodía pentatónica (siempre suena bien sobre esos acordes).
  var MELODIA = [72, 74, 76, 79, 76, 74, 72, 69, 72, 76, 79, 81, 79, 76, 74, 72];

  var AMBIENTES = {
    sala:  { bpm: 104, paso: function (t, p) {
      var compas = Math.floor(p / 16) % 4, en = p % 16;
      var ac = ACORDES[compas];
      if (en % 4 === 0) bombo(t, 0.35);
      if (en % 4 === 2) platillo(t, 0.05);
      if (en === 0 || en === 8) nota(hz(BAJOS[compas]), t, 0.5, 'sine', 0.32);
      if (en % 2 === 0) nota(hz(ac[(en / 2) % 3] + 12), t, 0.28, 'triangle', 0.09);
      if (en % 4 === 0 && compas % 2 === 1) nota(hz(MELODIA[(p / 4) % 16]), t, 0.35, 'sine', 0.07);
    } },
    juego: { bpm: 132, paso: function (t, p) {
      var compas = Math.floor(p / 16) % 4, en = p % 16;
      var ac = ACORDES[compas];
      if (en % 4 === 0) bombo(t, 0.55);
      if (en === 4 || en === 12) palmada(t, 0.14);
      if (en % 2 === 1) platillo(t, 0.06);
      if (en % 2 === 0) nota(hz(BAJOS[compas] + (en % 4 === 2 ? 12 : 0)), t, 0.18, 'square', 0.1, 900);
      nota(hz(ac[en % 3] + 12), t, 0.12, 'square', 0.035, 2400);
      if (en % 4 === 0) nota(hz(MELODIA[(p / 2) % 16]), t, 0.22, 'triangle', 0.06);
    } }
  };

  /* ---------- Secuenciador ---------- */

  function arrancar() {
    if (!ctx || !modo) return;
    detenerReloj();
    siguiente = ctx.currentTime + 0.06;
    reloj = setInterval(function () {
      var amb = AMBIENTES[modo];
      if (!amb || !activa) return;
      var semicorchea = 60 / amb.bpm / 4;
      // Se programa un poco por delante: los temporizadores del navegador no
      // son exactos, el reloj del audio sí.
      while (siguiente < ctx.currentTime + 0.12) {
        amb.paso(siguiente, paso);
        paso++;
        siguiente += semicorchea;
      }
    }, 25);
  }

  function detenerReloj() {
    if (reloj) clearInterval(reloj);
    reloj = null;
  }

  function poner(nuevo) {
    if (modo === nuevo) return;
    modo = nuevo;
    paso = 0;
    detenerReloj();
    if (modo && activa && asegurar()) arrancar();
  }

  /* ---------- Efectos ---------- */

  function tic(ultimo) {
    if (!activa || !asegurar()) return;
    var t = ctx.currentTime;
    nota(ultimo ? 1320 : 990, t, 0.09, 'square', 0.08, 4000);
  }

  function finDelTiempo() {
    if (!activa || !asegurar()) return;
    var t = ctx.currentTime;
    [67, 64, 60].forEach(function (m, i) { nota(hz(m), t + i * 0.11, 0.3, 'triangle', 0.16); });
    platillo(t + 0.33, 0.12, 0.6);
  }

  function fanfarria() {
    if (!activa || !asegurar()) return;
    var t = ctx.currentTime;
    [60, 64, 67, 72].forEach(function (m, i) {
      nota(hz(m), t + i * 0.12, 0.25, 'square', 0.07, 3000);
      nota(hz(m - 12), t + i * 0.12, 0.25, 'triangle', 0.1);
    });
    [72, 76, 79].forEach(function (m) { nota(hz(m), t + 0.5, 1.2, 'triangle', 0.09); });
    bombo(t + 0.5, 0.6);
    platillo(t + 0.5, 0.14, 1.2);
  }

  return {
    sala: function () { poner('sala'); },
    juego: function () { poner('juego'); },
    parar: function () { poner(null); },
    tic: tic,
    finDelTiempo: finDelTiempo,
    fanfarria: fanfarria,
    activa: function () { return activa; },
    /** Prende o apaga la música; la elección se recuerda en este navegador. */
    alternar: function () {
      activa = !activa;
      try { localStorage.setItem(CLAVE, activa ? 'si' : 'no'); } catch (e) { /* nada */ }
      if (activa) {
        if (modo && asegurar()) arrancar();
      } else {
        detenerReloj();
      }
      return activa;
    }
  };
})();
