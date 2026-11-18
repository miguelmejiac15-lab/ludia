/* =====================================================================
   Ludia — «Vuela a la respuesta»: el primer juego de acción.

   Un avioncito avanza entre nubes que traen las respuestas. El estudiante lo
   sube o lo baja con el dedo (o con ↑ ↓) y ELIGE atravesando una nube. Las
   nubes llegan en oleadas, cada vez en otro orden y un poco más rápido; se
   puede pasar entre ellas para esperar la siguiente.

   Aquí no se sabe cuál es la correcta (nunca viaja al celular): el juego solo
   dice qué opción se eligió y jugar.js la envía al servidor, que califica.

   Arte propio, dibujado en canvas: nada de imágenes ni de sonidos externos.
   ===================================================================== */
window.LudiaAvion = (function () {
  'use strict';

  var COLORES = ['#FF3B5C', '#3B6BFF', '#0EA371', '#F29A0D', '#8B5CF6', '#EC4899'];
  var LETRAS = ['A', 'B', 'C', 'D', 'E', 'F'];

  function barajar(lista) {
    var a = lista.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  /* Sonidos generados (Web Audio): un «pop» al atravesar y un zumbido suave. */
  var audio = null;
  function sonar(frecuencia, duracion, tipo, volumen) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime;
      o.type = tipo || 'sine';
      o.frequency.setValueAtTime(frecuencia, t);
      o.frequency.exponentialRampToValueAtTime(frecuencia * 1.8, t + duracion);
      g.gain.setValueAtTime(volumen || 0.12, t);
      g.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
      o.connect(g); g.connect(audio.destination);
      o.start(t); o.stop(t + duracion);
    } catch (e) { /* sin sonido no pasa nada */ }
  }

  /**
   * @param {HTMLElement} marco      contenedor del juego (lleva un <canvas>)
   * @param {string[]} textos        las opciones, en su orden original
   * @param {function(number)} alElegir  recibe el índice ORIGINAL elegido
   * @param {string} [piloto]       emoji del personaje del estudiante
   */
  function montar(marco, textos, alElegir, piloto) {
    piloto = piloto || '🦊';
    var lienzo = marco.querySelector('canvas');
    var ctx = lienzo.getContext('2d');
    var reducido = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    var opciones = textos.map(function (t, i) { return { texto: String(t || '').trim(), i: i }; })
      .filter(function (o) { return o.texto !== ''; });

    var W = 0, H = 0;
    var avion = { y: 0, objetivo: 0, inclinacion: 0 };
    var nubes = [], fondo = [], humo = [], chispas = [];
    var oleada = 0, recorrido = 0, tiempo = 0;
    var estado = 'vuela';      // vuela → elegida
    var elegidaEn = 0;
    var ultimo = null;
    var vivo = true;

    function medir() {
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      var anchoPrevio = W || 1, altoPrevio = H || 1;
      W = Math.max(260, marco.clientWidth);
      H = Math.round(Math.min(440, Math.max(280, W * 0.72)));
      lienzo.width = Math.round(W * dpr);
      lienzo.height = Math.round(H * dpr);
      lienzo.style.height = H + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      if (avion.y === 0) { avion.y = avion.objetivo = H / 2; }
      else {
        avion.y *= H / altoPrevio; avion.objetivo *= H / altoPrevio;
        nubes.forEach(function (n) { n.x *= W / anchoPrevio; n.y *= H / altoPrevio; });
      }
    }
    medir();

    var tamano = function () { return Math.max(26, Math.min(46, H * 0.11)); };
    var xAvion = function () { return W * 0.17; };
    var velocidad = function () {
      var base = W * (reducido ? 0.11 : 0.17);
      return base * Math.min(1.7, 1 + oleada * 0.07);
    };

    /* Nubitas del fondo, para que se note el movimiento. */
    for (var k = 0; k < 6; k++) {
      fondo.push({ x: Math.random() * W, y: 20 + Math.random() * H * 0.45, s: 0.4 + Math.random() * 0.5 });
    }

    /* ---------- Oleadas de nubes con respuestas ---------- */

    function envolver(texto, ancho, fuente) {
      ctx.font = '800 ' + fuente + 'px Manrope, system-ui, sans-serif';
      var palabras = texto.split(/\s+/), lineas = [], linea = '';
      palabras.forEach(function (p) {
        var prueba = linea ? linea + ' ' + p : p;
        if (ctx.measureText(prueba).width > ancho && linea) { lineas.push(linea); linea = p; }
        else linea = prueba;
      });
      if (linea) lineas.push(linea);
      return lineas;
    }

    function lanzarOleada() {
      var n = opciones.length;
      var arriba = 16, abajo = H * 0.16;
      var carril = (H - arriba - abajo) / n;
      var alto = Math.min(96, carril * 0.74);
      var ancho = Math.min(230, Math.max(120, W * 0.4));
      barajar(opciones).forEach(function (o, i) {
        // El texto se achica hasta caber en 3 líneas.
        var fuente = Math.min(17, Math.max(11, alto * 0.24)), lineas;
        do {
          lineas = envolver(o.texto, ancho - 52, fuente);
          if (lineas.length <= 3 && lineas.length * fuente * 1.15 <= alto * 0.8) break;
          fuente -= 1;
        } while (fuente > 10);
        nubes.push({
          op: o, x: W + ancho / 2 + 20 + (i % 2) * 26, y: arriba + carril * (i + 0.5),
          w: ancho, h: alto, fuente: fuente, lineas: lineas.slice(0, 3), viva: true, bamboleo: Math.random() * 6
        });
      });
      oleada++;
    }

    /* ---------- Controles ---------- */

    function apuntar(ev) {
      var r = lienzo.getBoundingClientRect();
      avion.objetivo = Math.max(tamano() / 2, Math.min(H - tamano() / 2, (ev.clientY - r.top) * (H / r.height)));
    }
    function alTocar(ev) { apuntar(ev); if (ev.cancelable) ev.preventDefault(); }
    lienzo.addEventListener('pointerdown', function (ev) {
      if (lienzo.setPointerCapture) { try { lienzo.setPointerCapture(ev.pointerId); } catch (e) {} }
      alTocar(ev);
      sonar(220, 0.05, 'triangle', 0.03); // despierta el audio en móviles
    });
    lienzo.addEventListener('pointermove', function (ev) {
      if (ev.pointerType === 'mouse' || ev.buttons || ev.pressure > 0) alTocar(ev);
    });
    var teclas = {};
    function alTeclado(ev) {
      if (!vivo) return;
      var arriba = ev.key === 'ArrowUp' || ev.key === 'w' || ev.key === 'W';
      var abajo = ev.key === 'ArrowDown' || ev.key === 's' || ev.key === 'S';
      if (!arriba && !abajo) return;
      // Solo cuando se está jugando: no secuestrar las flechas del resto de la página.
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      teclas[arriba ? 'arriba' : 'abajo'] = ev.type === 'keydown';
      ev.preventDefault();
    }
    document.addEventListener('keydown', alTeclado);
    document.addEventListener('keyup', alTeclado);
    window.addEventListener('resize', medir);

    function apagar() {
      vivo = false;
      document.removeEventListener('keydown', alTeclado);
      document.removeEventListener('keyup', alTeclado);
      window.removeEventListener('resize', medir);
    }

    /* ---------- Dibujo ---------- */

    function cielo() {
      var g = ctx.createLinearGradient(0, 0, 0, H);
      g.addColorStop(0, '#5BB8FF'); g.addColorStop(0.65, '#BEE6FF'); g.addColorStop(1, '#E9F7FF');
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      // Sol
      var sx = W - 46, sy = 40;
      var halo = ctx.createRadialGradient(sx, sy, 6, sx, sy, 60);
      halo.addColorStop(0, 'rgba(255,214,90,.9)'); halo.addColorStop(1, 'rgba(255,214,90,0)');
      ctx.fillStyle = halo; ctx.beginPath(); ctx.arc(sx, sy, 60, 0, 7); ctx.fill();
      ctx.fillStyle = '#FFD24D'; ctx.beginPath(); ctx.arc(sx, sy, 20, 0, 7); ctx.fill();
    }

    function nubeForma(x, y, w, h) {
      var r = h / 2;
      ctx.beginPath();
      ctx.moveTo(x - w / 2 + r, y + h / 2);
      ctx.lineTo(x + w / 2 - r, y + h / 2);
      ctx.arc(x + w / 2 - r, y, r, Math.PI / 2, -Math.PI / 2, true);
      ctx.arc(x + w * 0.16, y - h * 0.42, h * 0.42, 0, Math.PI, true);
      ctx.arc(x - w * 0.14, y - h * 0.36, h * 0.5, 0.1, Math.PI, true);
      ctx.arc(x - w / 2 + r, y, r, -Math.PI / 2, Math.PI / 2, true);
      ctx.closePath();
    }

    function colinas(desfase, alto, color, onda) {
      ctx.fillStyle = color;
      ctx.beginPath(); ctx.moveTo(0, H);
      for (var x = 0; x <= W + 10; x += 10) {
        ctx.lineTo(x, H - alto - Math.sin((x + desfase) / onda) * alto * 0.35 - Math.sin((x + desfase) / (onda * 0.43)) * alto * 0.12);
      }
      ctx.lineTo(W, H); ctx.closePath(); ctx.fill();
    }

    function nubeRespuesta(n) {
      var y = n.y + Math.sin(tiempo * 2 + n.bamboleo) * 3;
      ctx.save();
      ctx.shadowColor = 'rgba(42,33,64,.18)'; ctx.shadowBlur = 12; ctx.shadowOffsetY = 5;
      ctx.fillStyle = '#FFFFFF';
      nubeForma(n.x, y + n.h * 0.12, n.w, n.h * 0.76);
      ctx.fill();
      ctx.restore();
      // Letra de la opción, con su color de siempre.
      var cx = n.x - n.w / 2 + 22, r = Math.min(14, n.h * 0.2);
      ctx.fillStyle = COLORES[n.op.i % COLORES.length];
      ctx.beginPath(); ctx.arc(cx, y + n.h * 0.12, r, 0, 7); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(r * 1.1) + 'px Fredoka, system-ui, sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(LETRAS[n.op.i], cx, y + n.h * 0.12 + 1);
      // Texto
      ctx.fillStyle = '#2A2140'; ctx.font = '800 ' + n.fuente + 'px Manrope, system-ui, sans-serif';
      ctx.textAlign = 'left';
      var altoTexto = n.lineas.length * n.fuente * 1.15;
      n.lineas.forEach(function (l, i) {
        ctx.fillText(l, cx + r + 8, y + n.h * 0.12 - altoTexto / 2 + n.fuente * 0.58 + i * n.fuente * 1.15);
      });
    }

    function dibujarAvion(x, y, s) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(avion.inclinacion);
      // Cola
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); ctx.moveTo(-s * 0.62, -s * 0.05); ctx.lineTo(-s * 0.86, -s * 0.5); ctx.lineTo(-s * 0.58, -s * 0.42); ctx.lineTo(-s * 0.4, -s * 0.08); ctx.fill();
      // Ala de atrás
      ctx.fillStyle = '#7B62FF';
      ctx.beginPath(); ctx.ellipse(-s * 0.05, -s * 0.14, s * 0.36, s * 0.09, -0.25, 0, 7); ctx.fill();
      // Fuselaje
      var g = ctx.createLinearGradient(0, -s * 0.3, 0, s * 0.3);
      g.addColorStop(0, '#FF8A70'); g.addColorStop(1, '#E8492C');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.ellipse(0, 0, s * 0.68, s * 0.27, 0, 0, 7); ctx.fill();
      // Cabina con el piloto
      ctx.fillStyle = '#DFF3FF';
      ctx.beginPath(); ctx.arc(s * 0.12, -s * 0.2, s * 0.24, Math.PI, 0); ctx.fill();
      ctx.font = Math.round(s * 0.42) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
      ctx.fillText(piloto, s * 0.12, -s * 0.12);
      // Ala de delante
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); ctx.ellipse(-s * 0.02, s * 0.1, s * 0.42, s * 0.11, 0.2, 0, 7); ctx.fill();
      // Hélice
      ctx.fillStyle = '#2A2140';
      ctx.beginPath(); ctx.arc(s * 0.7, 0, s * 0.07, 0, 7); ctx.fill();
      ctx.fillStyle = 'rgba(42,33,64,.55)';
      ctx.beginPath(); ctx.ellipse(s * 0.74, 0, s * 0.04, s * 0.34 * Math.abs(Math.sin(tiempo * 40)), 0, 0, 7); ctx.fill();
      ctx.restore();
    }

    /* ---------- Bucle ---------- */

    function paso(ahora) {
      if (!vivo) return;
      // La pregunta cambió o se respondió: el marco ya no está en la página.
      if (!document.body.contains(lienzo)) { apagar(); return; }
      var dt = ultimo === null ? 0 : Math.min(0.05, (ahora - ultimo) / 1000);
      ultimo = ahora;
      tiempo += dt;

      var s = tamano(), xa = xAvion(), v = velocidad();
      if (teclas.arriba) avion.objetivo = Math.max(s / 2, avion.objetivo - H * 1.1 * dt);
      if (teclas.abajo) avion.objetivo = Math.min(H - s / 2, avion.objetivo + H * 1.1 * dt);
      var antes = avion.y;
      avion.y += (avion.objetivo - avion.y) * Math.min(1, dt * 9);
      var vy = dt ? (avion.y - antes) / dt : 0;
      avion.inclinacion += (Math.max(-0.45, Math.min(0.45, vy / 600)) - avion.inclinacion) * Math.min(1, dt * 8);

      if (estado === 'vuela') {
        recorrido += v * dt;
        // Una oleada nueva cuando la anterior ya pasó la mitad de la pantalla.
        var enCamino = nubes.filter(function (n) { return n.viva && n.x > W * 0.45; }).length;
        if (!enCamino && tiempo > 0.9) lanzarOleada();
      }

      nubes.forEach(function (n) {
        n.x -= v * dt * (estado === 'elegida' ? 0.35 : 1);
        if (estado !== 'vuela' || !n.viva) return;
        var dx = Math.abs(n.x - xa), dy = Math.abs(n.y + n.h * 0.12 - avion.y);
        if (dx < n.w / 2 + s * 0.45 && dy < n.h * 0.38 + s * 0.18) {
          estado = 'elegida';
          elegidaEn = tiempo;
          n.viva = false;
          for (var c = 0; c < 22; c++) {
            var ang = Math.random() * Math.PI * 2, rap = 60 + Math.random() * 180;
            chispas.push({ x: n.x, y: n.y, vx: Math.cos(ang) * rap, vy: Math.sin(ang) * rap, r: 4 + Math.random() * 8, vida: 1,
              color: c % 3 ? '#FFFFFF' : COLORES[n.op.i % COLORES.length] });
          }
          sonar(520, 0.18, 'sine', 0.14);
          marco.setAttribute('data-elegida', String(n.op.i));
          setTimeout(function () { if (vivo) alElegir(n.op.i); }, 650);
        }
      });
      nubes = nubes.filter(function (n) { return n.x > -n.w && n.viva; });

      // Estela
      if (Math.random() < dt * 30) humo.push({ x: xa - s * 0.7, y: avion.y + (Math.random() - 0.5) * 4, r: 3 + Math.random() * 3, vida: 1 });
      humo.forEach(function (h) { h.x -= v * dt * 0.8; h.r += dt * 10; h.vida -= dt * 1.6; });
      humo = humo.filter(function (h) { return h.vida > 0; });
      chispas.forEach(function (c) { c.x += c.vx * dt; c.y += c.vy * dt; c.vy += 220 * dt; c.vida -= dt * 1.4; });
      chispas = chispas.filter(function (c) { return c.vida > 0; });
      fondo.forEach(function (f) { f.x -= v * dt * 0.25 * f.s; if (f.x < -80) { f.x = W + 60; f.y = 20 + Math.random() * H * 0.45; } });

      // Pintar
      cielo();
      ctx.fillStyle = 'rgba(255,255,255,.75)';
      fondo.forEach(function (f) { nubeForma(f.x, f.y, 90 * f.s, 34 * f.s); ctx.fill(); });
      colinas(recorrido * 0.35, H * 0.13, '#8FD9B6', 150);
      colinas(recorrido * 0.7, H * 0.08, '#1FA98A', 90);
      humo.forEach(function (h) { ctx.fillStyle = 'rgba(255,255,255,' + (h.vida * 0.8) + ')'; ctx.beginPath(); ctx.arc(h.x, h.y, h.r, 0, 7); ctx.fill(); });
      nubes.forEach(nubeRespuesta);
      chispas.forEach(function (c) { ctx.globalAlpha = Math.max(0, c.vida); ctx.fillStyle = c.color; ctx.beginPath(); ctx.arc(c.x, c.y, c.r, 0, 7); ctx.fill(); });
      ctx.globalAlpha = 1;
      var xDibujo = estado === 'elegida' ? xa + (tiempo - elegidaEn) * W * 0.9 : xa;
      dibujarAvion(xDibujo, avion.y, s);

      requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);

    return { apagar: apagar };
  }

  return { montar: montar };
})();
