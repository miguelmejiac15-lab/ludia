/* =====================================================================
   Ludia — motor de los JUEGOS DE ACCIÓN, dibujados en canvas.

   Cada juego recibe las opciones de una pregunta y avisa cuál eligió el
   estudiante; jugar.js la envía y el servidor califica. La respuesta
   correcta NUNCA llega al celular, así que ningún juego la conoce.

   Lo común vive aquí (lienzo nítido en cualquier pantalla, bucle con tiempo
   real, cielo, nubes, colinas, confeti, sonidos generados, texto que se
   ajusta) y cada juego solo pone su mecánica:

     avion   — «Vuela a la respuesta»: atravesar la nube correcta.
     globos  — «Globos»: reventar el globo correcto antes de que se escape.
     atrapa  — «Atrapa la correcta»: tocar al animalito que asoma con ella.

   Arte propio: nada de imágenes ni sonidos externos.
   ===================================================================== */
window.LudiaArcade = (function () {
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

  /* Sonidos generados con Web Audio: no hay archivos que descargar. */
  var audio = null;
  function sonar(frecuencia, duracion, tipo, volumen, subida) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime;
      o.type = tipo || 'sine';
      o.frequency.setValueAtTime(frecuencia, t);
      o.frequency.exponentialRampToValueAtTime(frecuencia * (subida || 1.8), t + duracion);
      g.gain.setValueAtTime(volumen || 0.12, t);
      g.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
      o.connect(g); g.connect(audio.destination);
      o.start(t); o.stop(t + duracion);
    } catch (e) { /* sin sonido no pasa nada */ }
  }

  /* ---------- Base común a todos los juegos ---------- */

  function base(marco, textos, alElegir) {
    var lienzo = marco.querySelector('canvas');
    var b = {
      marco: marco,
      lienzo: lienzo,
      ctx: lienzo.getContext('2d'),
      W: 0, H: 0, tiempo: 0,
      vivo: true,
      reducido: !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches),
      opciones: textos.map(function (t, i) { return { texto: String(t || '').trim(), i: i }; })
        .filter(function (o) { return o.texto !== ''; }),
      chispas: [],
      fondo: [],
      alRedimensionar: null,
      limpiezas: []
    };

    b.medir = function () {
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      var antes = { W: b.W || 1, H: b.H || 1 };
      b.W = Math.max(260, marco.clientWidth);
      b.H = Math.round(Math.min(460, Math.max(280, b.W * (b.proporcion || 0.72))));
      lienzo.width = Math.round(b.W * dpr);
      lienzo.height = Math.round(b.H * dpr);
      lienzo.style.height = b.H + 'px';
      b.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      if (b.alRedimensionar && antes.W > 1) b.alRedimensionar(b.W / antes.W, b.H / antes.H);
    };
    b.medir();
    b.escuchar = function (objetivo, evento, fn, opciones) {
      objetivo.addEventListener(evento, fn, opciones);
      b.limpiezas.push(function () { objetivo.removeEventListener(evento, fn, opciones); });
    };
    b.escuchar(window, 'resize', b.medir);

    b.apagar = function () {
      if (!b.vivo) return;
      b.vivo = false;
      b.limpiezas.forEach(function (fn) { fn(); });
    };

    /** Avisa la elección una sola vez, con una pausa para ver la explosión. */
    var elegida = false;
    b.elegir = function (op) {
      if (elegida) return;
      elegida = true;
      marco.setAttribute('data-elegida', String(op.i));
      setTimeout(function () { if (b.vivo) alElegir(op.i); }, 650);
    };
    b.yaEligio = function () { return elegida; };

    /** Arranca el bucle; `paso(dt)` mueve y dibuja. Se apaga solo si el marco sale de la página. */
    b.correr = function (paso) {
      var ultimo = null;
      function cuadro(ahora) {
        if (!b.vivo) return;
        if (!document.body.contains(lienzo)) { b.apagar(); return; }
        var dt = ultimo === null ? 0 : Math.min(0.05, (ahora - ultimo) / 1000);
        ultimo = ahora;
        b.tiempo += dt;
        paso(dt);
        requestAnimationFrame(cuadro);
      }
      requestAnimationFrame(cuadro);
    };

    /** Posición del puntero en coordenadas del lienzo. */
    b.punto = function (ev) {
      var r = lienzo.getBoundingClientRect();
      return { x: (ev.clientX - r.left) * (b.W / r.width), y: (ev.clientY - r.top) * (b.H / r.height) };
    };

    /* ---- Dibujo compartido ---- */

    b.cielo = function (arriba, abajo) {
      var ctx = b.ctx, g = ctx.createLinearGradient(0, 0, 0, b.H);
      g.addColorStop(0, arriba || '#5BB8FF'); g.addColorStop(0.65, abajo || '#BEE6FF'); g.addColorStop(1, '#E9F7FF');
      ctx.fillStyle = g; ctx.fillRect(0, 0, b.W, b.H);
      var sx = b.W - 46, sy = 40;
      var halo = ctx.createRadialGradient(sx, sy, 6, sx, sy, 60);
      halo.addColorStop(0, 'rgba(255,214,90,.9)'); halo.addColorStop(1, 'rgba(255,214,90,0)');
      ctx.fillStyle = halo; ctx.beginPath(); ctx.arc(sx, sy, 60, 0, 7); ctx.fill();
      ctx.fillStyle = '#FFD24D'; ctx.beginPath(); ctx.arc(sx, sy, 20, 0, 7); ctx.fill();
    };

    b.nubeForma = function (x, y, w, h) {
      var ctx = b.ctx, r = h / 2;
      ctx.beginPath();
      ctx.moveTo(x - w / 2 + r, y + h / 2);
      ctx.lineTo(x + w / 2 - r, y + h / 2);
      ctx.arc(x + w / 2 - r, y, r, Math.PI / 2, -Math.PI / 2, true);
      ctx.arc(x + w * 0.16, y - h * 0.42, h * 0.42, 0, Math.PI, true);
      ctx.arc(x - w * 0.14, y - h * 0.36, h * 0.5, 0.1, Math.PI, true);
      ctx.arc(x - w / 2 + r, y, r, -Math.PI / 2, Math.PI / 2, true);
      ctx.closePath();
    };

    /* Nubitas del fondo: dan sensación de viento. */
    for (var k = 0; k < 6; k++) {
      b.fondo.push({ x: Math.random() * b.W, y: 20 + Math.random() * b.H * 0.45, s: 0.4 + Math.random() * 0.5 });
    }
    b.nubesFondo = function (dt, viento) {
      var ctx = b.ctx;
      ctx.fillStyle = 'rgba(255,255,255,.75)';
      b.fondo.forEach(function (f) {
        f.x -= viento * dt * f.s;
        if (f.x < -80) { f.x = b.W + 60; f.y = 20 + Math.random() * b.H * 0.45; }
        b.nubeForma(f.x, f.y, 90 * f.s, 34 * f.s); ctx.fill();
      });
    };

    b.colinas = function (desfase, alto, color, onda) {
      var ctx = b.ctx;
      ctx.fillStyle = color;
      ctx.beginPath(); ctx.moveTo(0, b.H);
      for (var x = 0; x <= b.W + 10; x += 10) {
        ctx.lineTo(x, b.H - alto - Math.sin((x + desfase) / onda) * alto * 0.35 - Math.sin((x + desfase) / (onda * 0.43)) * alto * 0.12);
      }
      ctx.lineTo(b.W, b.H); ctx.closePath(); ctx.fill();
    };

    b.explotar = function (x, y, color, cantidad) {
      for (var c = 0; c < (cantidad || 22); c++) {
        var ang = Math.random() * Math.PI * 2, rap = 60 + Math.random() * 180;
        b.chispas.push({ x: x, y: y, vx: Math.cos(ang) * rap, vy: Math.sin(ang) * rap, r: 3 + Math.random() * 7, vida: 1,
          color: c % 3 ? '#FFFFFF' : color });
      }
    };
    b.dibujarChispas = function (dt) {
      var ctx = b.ctx;
      b.chispas.forEach(function (c) { c.x += c.vx * dt; c.y += c.vy * dt; c.vy += 220 * dt; c.vida -= dt * 1.4; });
      b.chispas = b.chispas.filter(function (c) { return c.vida > 0; });
      b.chispas.forEach(function (c) {
        ctx.globalAlpha = Math.max(0, c.vida); ctx.fillStyle = c.color;
        ctx.beginPath(); ctx.arc(c.x, c.y, c.r, 0, 7); ctx.fill();
      });
      ctx.globalAlpha = 1;
    };

    /** Parte un texto en líneas que caben en `ancho`. */
    b.envolver = function (texto, ancho, fuente) {
      var ctx = b.ctx;
      ctx.font = '800 ' + fuente + 'px Manrope, system-ui, sans-serif';
      var palabras = texto.split(/\s+/), lineas = [], linea = '';
      palabras.forEach(function (p) {
        var prueba = linea ? linea + ' ' + p : p;
        if (ctx.measureText(prueba).width > ancho && linea) { lineas.push(linea); linea = p; }
        else linea = prueba;
      });
      if (linea) lineas.push(linea);
      return lineas;
    };
    /** Achica la letra hasta que el texto quepa en `maxLineas` dentro de la caja. */
    b.ajustar = function (texto, ancho, alto, maxLineas, fuenteMax) {
      var fuente = fuenteMax, lineas;
      do {
        lineas = b.envolver(texto, ancho, fuente);
        if (lineas.length <= maxLineas && lineas.length * fuente * 1.15 <= alto) break;
        fuente -= 1;
      } while (fuente > 10);
      return { fuente: fuente, lineas: lineas.slice(0, maxLineas) };
    };
    b.escribir = function (ajuste, x, y, color, alinear) {
      var ctx = b.ctx;
      ctx.fillStyle = color; ctx.font = '800 ' + ajuste.fuente + 'px Manrope, system-ui, sans-serif';
      ctx.textAlign = alinear || 'left'; ctx.textBaseline = 'middle';
      var alto = ajuste.lineas.length * ajuste.fuente * 1.15;
      ajuste.lineas.forEach(function (l, i) {
        ctx.fillText(l, x, y - alto / 2 + ajuste.fuente * 0.58 + i * ajuste.fuente * 1.15);
      });
    };
    b.letra = function (op, x, y, r) {
      var ctx = b.ctx;
      ctx.fillStyle = COLORES[op.i % COLORES.length];
      ctx.beginPath(); ctx.arc(x, y, r, 0, 7); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(r * 1.1) + 'px Fredoka, system-ui, sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(LETRAS[op.i], x, y + 1);
    };

    return b;
  }

  /* =================================================================
     ✈️ Vuela a la respuesta
     El avión se mueve con el dedo (o ↑ ↓) y elige atravesando una nube.
     Las nubes llegan en oleadas, cada vez en otro orden y más rápido.
     ================================================================= */
  function avion(b, piloto) {
    var ctx = b.ctx;
    var nave = { y: b.H / 2, objetivo: b.H / 2, inclinacion: 0 };
    var nubes = [], humo = [];
    var oleada = 0, recorrido = 0, elegidaEn = 0;
    var teclas = {};

    b.alRedimensionar = function (fx, fy) {
      nave.y *= fy; nave.objetivo *= fy;
      nubes.forEach(function (n) { n.x *= fx; n.y *= fy; });
    };
    var tamano = function () { return Math.max(26, Math.min(46, b.H * 0.11)); };
    var xNave = function () { return b.W * 0.17; };
    var velocidad = function () { return b.W * (b.reducido ? 0.11 : 0.17) * Math.min(1.7, 1 + oleada * 0.07); };

    function lanzarOleada() {
      var n = b.opciones.length, arriba = 16, abajo = b.H * 0.16;
      var carril = (b.H - arriba - abajo) / n;
      var alto = Math.min(96, carril * 0.74);
      var ancho = Math.min(230, Math.max(120, b.W * 0.4));
      barajar(b.opciones).forEach(function (o, i) {
        nubes.push({
          op: o, x: b.W + ancho / 2 + 20 + (i % 2) * 26, y: arriba + carril * (i + 0.5), w: ancho, h: alto,
          texto: b.ajustar(o.texto, ancho - 52, alto * 0.8, 3, Math.min(17, Math.max(11, alto * 0.24))),
          viva: true, bamboleo: Math.random() * 6
        });
      });
      oleada++;
    }

    function apuntar(ev) {
      var s = tamano();
      nave.objetivo = Math.max(s / 2, Math.min(b.H - s / 2, b.punto(ev).y));
      if (ev.cancelable) ev.preventDefault();
    }
    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      if (b.lienzo.setPointerCapture) { try { b.lienzo.setPointerCapture(ev.pointerId); } catch (e) {} }
      apuntar(ev);
      sonar(220, 0.05, 'triangle', 0.03); // despierta el audio en móviles
    });
    b.escuchar(b.lienzo, 'pointermove', function (ev) {
      if (ev.pointerType === 'mouse' || ev.buttons || ev.pressure > 0) apuntar(ev);
    });
    function alTeclado(ev) {
      var sube = ev.key === 'ArrowUp' || ev.key === 'w' || ev.key === 'W';
      var baja = ev.key === 'ArrowDown' || ev.key === 's' || ev.key === 'S';
      if (!sube && !baja) return;
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      teclas[sube ? 'arriba' : 'abajo'] = ev.type === 'keydown';
      ev.preventDefault();
    }
    b.escuchar(document, 'keydown', alTeclado);
    b.escuchar(document, 'keyup', alTeclado);

    function nubeRespuesta(n) {
      var y = n.y + Math.sin(b.tiempo * 2 + n.bamboleo) * 3;
      ctx.save();
      ctx.shadowColor = 'rgba(42,33,64,.18)'; ctx.shadowBlur = 12; ctx.shadowOffsetY = 5;
      ctx.fillStyle = '#FFFFFF';
      b.nubeForma(n.x, y + n.h * 0.12, n.w, n.h * 0.76);
      ctx.fill();
      ctx.restore();
      var cx = n.x - n.w / 2 + 22, r = Math.min(14, n.h * 0.2);
      b.letra(n.op, cx, y + n.h * 0.12, r);
      b.escribir(n.texto, cx + r + 8, y + n.h * 0.12, '#2A2140');
    }

    function dibujarNave(x, y, s) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(nave.inclinacion);
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); ctx.moveTo(-s * 0.62, -s * 0.05); ctx.lineTo(-s * 0.86, -s * 0.5); ctx.lineTo(-s * 0.58, -s * 0.42); ctx.lineTo(-s * 0.4, -s * 0.08); ctx.fill();
      ctx.fillStyle = '#7B62FF';
      ctx.beginPath(); ctx.ellipse(-s * 0.05, -s * 0.14, s * 0.36, s * 0.09, -0.25, 0, 7); ctx.fill();
      var g = ctx.createLinearGradient(0, -s * 0.3, 0, s * 0.3);
      g.addColorStop(0, '#FF8A70'); g.addColorStop(1, '#E8492C');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.ellipse(0, 0, s * 0.68, s * 0.27, 0, 0, 7); ctx.fill();
      ctx.fillStyle = '#DFF3FF';
      ctx.beginPath(); ctx.arc(s * 0.12, -s * 0.2, s * 0.24, Math.PI, 0); ctx.fill();
      ctx.font = Math.round(s * 0.42) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
      ctx.fillText(piloto, s * 0.12, -s * 0.12);
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); ctx.ellipse(-s * 0.02, s * 0.1, s * 0.42, s * 0.11, 0.2, 0, 7); ctx.fill();
      ctx.fillStyle = '#2A2140';
      ctx.beginPath(); ctx.arc(s * 0.7, 0, s * 0.07, 0, 7); ctx.fill();
      ctx.fillStyle = 'rgba(42,33,64,.55)';
      ctx.beginPath(); ctx.ellipse(s * 0.74, 0, s * 0.04, s * 0.34 * Math.abs(Math.sin(b.tiempo * 40)), 0, 0, 7); ctx.fill();
      ctx.restore();
    }

    b.correr(function (dt) {
      var s = tamano(), xa = xNave(), v = velocidad(), eligio = b.yaEligio();
      if (teclas.arriba) nave.objetivo = Math.max(s / 2, nave.objetivo - b.H * 1.1 * dt);
      if (teclas.abajo) nave.objetivo = Math.min(b.H - s / 2, nave.objetivo + b.H * 1.1 * dt);
      var antes = nave.y;
      nave.y += (nave.objetivo - nave.y) * Math.min(1, dt * 9);
      var vy = dt ? (nave.y - antes) / dt : 0;
      nave.inclinacion += (Math.max(-0.45, Math.min(0.45, vy / 600)) - nave.inclinacion) * Math.min(1, dt * 8);

      if (!eligio) {
        recorrido += v * dt;
        // Una oleada nueva cuando la anterior ya pasó la mitad de la pantalla.
        var enCamino = nubes.filter(function (n) { return n.viva && n.x > b.W * 0.45; }).length;
        if (!enCamino && b.tiempo > 0.9) lanzarOleada();
      }

      nubes.forEach(function (n) {
        n.x -= v * dt * (eligio ? 0.35 : 1);
        if (eligio || !n.viva) return;
        var dx = Math.abs(n.x - xa), dy = Math.abs(n.y + n.h * 0.12 - nave.y);
        if (dx < n.w / 2 + s * 0.45 && dy < n.h * 0.38 + s * 0.18) {
          n.viva = false;
          elegidaEn = b.tiempo;
          b.explotar(n.x, n.y, COLORES[n.op.i % COLORES.length]);
          sonar(520, 0.18, 'sine', 0.14);
          b.elegir(n.op);
        }
      });
      nubes = nubes.filter(function (n) { return n.x > -n.w && n.viva; });

      if (Math.random() < dt * 30) humo.push({ x: xa - s * 0.7, y: nave.y + (Math.random() - 0.5) * 4, r: 3 + Math.random() * 3, vida: 1 });
      humo.forEach(function (h) { h.x -= v * dt * 0.8; h.r += dt * 10; h.vida -= dt * 1.6; });
      humo = humo.filter(function (h) { return h.vida > 0; });

      b.cielo();
      b.nubesFondo(dt, v * 0.25);
      b.colinas(recorrido * 0.35, b.H * 0.13, '#8FD9B6', 150);
      b.colinas(recorrido * 0.7, b.H * 0.08, '#1FA98A', 90);
      humo.forEach(function (h) { ctx.fillStyle = 'rgba(255,255,255,' + (h.vida * 0.8) + ')'; ctx.beginPath(); ctx.arc(h.x, h.y, h.r, 0, 7); ctx.fill(); });
      nubes.forEach(nubeRespuesta);
      b.dibujarChispas(dt);
      dibujarNave(eligio ? xa + (b.tiempo - elegidaEn) * b.W * 0.9 : xa, nave.y, s);
    });
  }

  /* =================================================================
     🎈 Globos
     Los globos suben con las respuestas; se toca el correcto para
     reventarlo. Si se escapan todos, sale otra tanda en otro orden y un
     poco más rápida. El personaje del estudiante mira desde la colina.
     ================================================================= */
  function globos(b, piloto) {
    var ctx = b.ctx;
    var lista = [], tanda = 0, esperaTanda = 0.4, festejo = 0;

    b.alRedimensionar = function (fx, fy) {
      lista.forEach(function (g) { g.x *= fx; g.y *= fy; g.w *= fx; g.h *= fx; });
    };
    var subida = function () { return b.H * (b.reducido ? 0.1 : 0.16) * Math.min(1.8, 1 + tanda * 0.09); };

    function soltarTanda() {
      var n = b.opciones.length;
      var columnas = Math.min(n, b.W < 420 ? 2 : 3);
      var ancho = Math.min(124, (b.W - 24) / columnas * 0.74);
      var orden = barajar(b.opciones);
      orden.forEach(function (o, i) {
        var col = i % columnas, fila = Math.floor(i / columnas);
        var w = ancho, h = ancho * 1.12;
        var hueco = (b.W - 24) / columnas;
        lista.push({
          op: o, w: w, h: h,
          x: 12 + hueco * (col + 0.5) + (Math.random() - 0.5) * hueco * 0.15,
          y: b.H + h * 0.6 + fila * h * 1.45 + Math.random() * 30,
          fase: Math.random() * 6,
          texto: b.ajustar(o.texto, w * 0.72, h * 0.42, 3, Math.min(18, Math.max(11, w * 0.13))),
          viva: true
        });
      });
      tanda++;
    }

    function tocar(ev) {
      if (b.yaEligio()) return;
      var p = b.punto(ev);
      // De arriba hacia abajo en el dibujo: gana el globo que se ve delante.
      for (var i = lista.length - 1; i >= 0; i--) {
        var g = lista[i];
        var dx = (p.x - g.xv) / (g.w / 2), dy = (p.y - g.yv) / (g.h / 2);
        if (g.viva && dx * dx + dy * dy <= 1.15) {
          g.viva = false;
          b.explotar(g.xv, g.yv, COLORES[g.op.i % COLORES.length], 30);
          sonar(700, 0.12, 'square', 0.08, 0.4);
          festejo = 1;
          b.elegir(g.op);
          if (ev.cancelable) ev.preventDefault();
          return;
        }
      }
    }
    b.escuchar(b.lienzo, 'pointerdown', tocar);
    // Teclado: la letra de la opción revienta su globo (A, B, C…).
    b.escuchar(document, 'keydown', function (ev) {
      if (b.yaEligio() || ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      var indice = LETRAS.indexOf(String(ev.key || '').toUpperCase());
      var g = lista.filter(function (x) { return x.viva && x.op.i === indice && x.yv < b.H && x.yv > 0; })[0];
      if (!g) return;
      ev.preventDefault();
      g.viva = false;
      b.explotar(g.xv, g.yv, COLORES[g.op.i % COLORES.length], 30);
      sonar(700, 0.12, 'square', 0.08, 0.4);
      festejo = 1;
      b.elegir(g.op);
    });

    function dibujarGlobo(g) {
      var x = g.xv, y = g.yv, w = g.w, h = g.h, color = COLORES[g.op.i % COLORES.length];
      // Cuerda
      ctx.strokeStyle = 'rgba(42,33,64,.45)'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.moveTo(x, y + h / 2 + 4);
      for (var k = 1; k <= 6; k++) ctx.lineTo(x + Math.sin(b.tiempo * 3 + g.fase + k) * 4, y + h / 2 + 4 + k * 9);
      ctx.stroke();
      // Nudo
      ctx.fillStyle = color;
      ctx.beginPath(); ctx.moveTo(x - 6, y + h / 2 + 6); ctx.lineTo(x + 6, y + h / 2 + 6); ctx.lineTo(x, y + h / 2 - 2); ctx.fill();
      // Globo
      var grad = ctx.createRadialGradient(x - w * 0.24, y - h * 0.12, w * 0.02, x, y, w * 0.62);
      grad.addColorStop(0, 'rgba(255,255,255,.85)'); grad.addColorStop(0.12, color); grad.addColorStop(1, color);
      ctx.fillStyle = grad;
      ctx.beginPath(); ctx.ellipse(x, y, w / 2, h / 2, 0, 0, 7); ctx.fill();
      // Letra y texto
      b.letra(g.op, x, y - h * 0.3, Math.min(13, w * 0.1));
      b.escribir(g.texto, x, y + h * 0.04, '#FFFFFF', 'center');
    }

    b.correr(function (dt) {
      var v = subida(), eligio = b.yaEligio();
      var vivos = lista.filter(function (g) { return g.viva && g.y > -g.h; });
      if (!eligio && vivos.length === 0) {
        esperaTanda -= dt;
        if (esperaTanda <= 0) { soltarTanda(); esperaTanda = 0.5; }
      }
      lista.forEach(function (g) {
        g.y -= v * dt * (eligio ? 2.4 : 1);
        g.xv = g.x + Math.sin(b.tiempo * 1.6 + g.fase) * g.w * 0.08;
        g.yv = g.y + Math.sin(b.tiempo * 2.2 + g.fase) * 3;
      });
      lista = lista.filter(function (g) { return g.viva && g.y > -g.h * 1.2; });
      festejo = Math.max(0, festejo - dt);

      b.cielo('#7CC6FF', '#D3EEFF');
      b.nubesFondo(dt, b.W * 0.03);
      b.colinas(b.tiempo * 4, b.H * 0.12, '#8FD9B6', 160);
      b.colinas(b.tiempo * 8, b.H * 0.07, '#1FA98A', 100);
      // El personaje del estudiante, en la colina; salta al reventar un globo.
      var tam = Math.max(30, Math.min(48, b.H * 0.12));
      ctx.font = tam + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
      ctx.fillText(piloto, b.W * 0.1, b.H - b.H * 0.06 - Math.abs(Math.sin(b.tiempo * (festejo ? 14 : 3))) * (festejo ? 18 : 3));
      lista.forEach(dibujarGlobo);
      b.dibujarChispas(dt);
    });
  }

  /* =================================================================
     🐹 Atrapa la correcta
     En un jardín con madrigueras se asoman animalitos con un cartel. Hay
     que tocar el que lleva la respuesta correcta antes de que se esconda.
     Salen de a pocos, en hoyos al azar; cuando han salido todas las
     respuestas, la ronda siguiente va un poco más rápida.
     ================================================================= */
  var ANIMALES = ['🐹', '🐰', '🦔', '🐿️', '🐻', '🐼', '🐨', '🐸', '🐱', '🦊'];

  function atrapa(b, piloto) {
    var ctx = b.ctx;
    // Más alto que los otros: caben tres filas de madrigueras con su cartel.
    b.proporcion = 1.08;
    b.medir();

    var hoyos = [], asomados = [], cola = [], ronda = 0, espera = 0.5, festejo = 0, golpes = [];

    function armarHoyos() {
      var columnas = b.W < 420 ? 2 : 3, filas = 3;
      var arriba = b.H * 0.2, alto = (b.H - arriba - 8) / filas;
      hoyos = [];
      for (var f = 0; f < filas; f++) {
        for (var c = 0; c < columnas; c++) {
          hoyos.push({
            x: (b.W / columnas) * (c + 0.5) + (f % 2 ? 1 : -1) * (columnas === 2 ? b.W * 0.04 : 0),
            y: arriba + alto * (f + 0.78),
            w: Math.min(110, b.W / columnas * 0.62),
            alto: alto
          });
        }
      }
    }
    armarHoyos();
    b.alRedimensionar = function () { armarHoyos(); };

    var tiempoArriba = function () { return (b.reducido ? 3.2 : 2.4) / Math.min(1.8, 1 + ronda * 0.1); };

    function asomar() {
      if (!cola.length) { cola = barajar(b.opciones); ronda++; }
      var libres = hoyos.filter(function (h) { return !asomados.some(function (a) { return a.hoyo === h; }); });
      if (!libres.length) return;
      // Nunca la misma respuesta dos veces a la vez (pasa al empezar ronda).
      var visibles = asomados.map(function (x) { return x.op.i; });
      var pos = -1;
      for (var k = 0; k < cola.length; k++) { if (visibles.indexOf(cola[k].i) === -1) { pos = k; break; } }
      if (pos === -1) return;
      var hoyo = libres[Math.floor(Math.random() * libres.length)];
      var op = cola.splice(pos, 1)[0];
      var ancho = Math.min(hoyo.w * 1.5, b.W * 0.46);
      asomados.push({
        op: op, hoyo: hoyo, t: 0, dura: tiempoArriba(),
        animal: ANIMALES[Math.floor(Math.random() * ANIMALES.length)],
        cartel: { w: ancho, h: Math.min(54, hoyo.alto * 0.5) },
        texto: b.ajustar(op.texto, ancho - 34, Math.min(54, hoyo.alto * 0.5) - 8, 2, 14),
        viva: true
      });
    }

    /** Cuánto asoma (0 escondido, 1 fuera), con subida y bajada suaves. */
    function altura(a) {
      var sube = 0.22, baja = 0.22;
      if (a.t < sube) return a.t / sube;
      if (a.t > a.dura - baja) return Math.max(0, (a.dura - a.t) / baja);
      return 1;
    }

    function zona(a) {
      var tam = Math.min(54, a.hoyo.alto * 0.52);
      var sale = altura(a);
      var cy = a.hoyo.y - tam * 0.62 * sale;
      var cartelY = cy - tam * 0.5 - a.cartel.h / 2 - 4;
      var cartelX = Math.max(6, Math.min(b.W - 6 - a.cartel.w, a.hoyo.x - a.cartel.w / 2));
      return { tam: tam, cy: cy, cartelY: cartelY, cartelX: cartelX, sale: sale };
    }

    function atrapar(a) {
      var z = zona(a);
      a.viva = false;
      a.atrapado = b.tiempo;
      golpes.push({ x: a.hoyo.x, y: z.cy, t: 0 });
      b.explotar(a.hoyo.x, z.cartelY, COLORES[a.op.i % COLORES.length], 26);
      sonar(330, 0.16, 'triangle', 0.14, 2.4);
      festejo = 1;
      b.elegir(a.op);
    }

    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      if (b.yaEligio()) return;
      var p = b.punto(ev);
      for (var i = asomados.length - 1; i >= 0; i--) {
        var a = asomados[i];
        if (!a.viva) continue;
        var z = zona(a);
        if (z.sale < 0.35) continue;
        var enCartel = p.x >= z.cartelX - 6 && p.x <= z.cartelX + a.cartel.w + 6 && Math.abs(p.y - z.cartelY) <= a.cartel.h / 2 + 6;
        var enAnimal = Math.abs(p.x - a.hoyo.x) <= z.tam * 0.7 && p.y >= z.cy - z.tam * 0.7 && p.y <= a.hoyo.y + 6;
        if (enCartel || enAnimal) {
          atrapar(a);
          if (ev.cancelable) ev.preventDefault();
          return;
        }
      }
    });
    // Teclado: la letra atrapa a quien la lleve, si está asomado.
    b.escuchar(document, 'keydown', function (ev) {
      if (b.yaEligio() || ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      var indice = LETRAS.indexOf(String(ev.key || '').toUpperCase());
      var a = asomados.filter(function (x) { return x.viva && x.op.i === indice && altura(x) > 0.35; })[0];
      if (!a) return;
      ev.preventDefault();
      atrapar(a);
    });

    function jardin() {
      var g = ctx.createLinearGradient(0, 0, 0, b.H);
      g.addColorStop(0, '#7CC6FF'); g.addColorStop(0.16, '#CFEFFF'); g.addColorStop(0.161, '#7ED98C'); g.addColorStop(1, '#3FB56A');
      ctx.fillStyle = g; ctx.fillRect(0, 0, b.W, b.H);
      // Franjas de césped recién cortado
      ctx.fillStyle = 'rgba(255,255,255,.07)';
      for (var x = -b.H; x < b.W; x += 46) {
        ctx.beginPath(); ctx.moveTo(x, b.H); ctx.lineTo(x + 23, b.H); ctx.lineTo(x + 23 + b.H * 0.5, b.H * 0.16); ctx.lineTo(x + b.H * 0.5, b.H * 0.16); ctx.fill();
      }
      // Seto del fondo
      ctx.fillStyle = '#2E9E58';
      for (var k = 0; k <= b.W; k += 26) { ctx.beginPath(); ctx.arc(k, b.H * 0.16, 16, Math.PI, 0); ctx.fill(); }
      // Florecitas
      for (var f = 0; f < 9; f++) {
        var fx = (f * 97.3) % b.W, fy = b.H * 0.2 + ((f * 53.7) % (b.H * 0.75));
        ctx.fillStyle = ['#FFD24D', '#FF8FB1', '#FFFFFF'][f % 3];
        ctx.beginPath(); ctx.arc(fx, fy, 3, 0, 7); ctx.fill();
      }
    }

    function hoyoAtras(h) {
      ctx.fillStyle = '#5A3A22';
      ctx.beginPath(); ctx.ellipse(h.x, h.y, h.w / 2, h.w * 0.16, 0, 0, 7); ctx.fill();
      ctx.fillStyle = '#2B1A10';
      ctx.beginPath(); ctx.ellipse(h.x, h.y + 2, h.w / 2 - 6, h.w * 0.12, 0, 0, 7); ctx.fill();
    }
    function hoyoDelante(h) {
      ctx.fillStyle = '#7A5233';
      ctx.beginPath(); ctx.ellipse(h.x, h.y + 3, h.w / 2, h.w * 0.16, 0, 0, Math.PI); ctx.fill();
    }

    function dibujarAnimal(a) {
      var z = zona(a), h = a.hoyo;
      if (z.sale <= 0) return;
      // El animal sale del hoyo: se recorta por debajo del borde.
      ctx.save();
      ctx.beginPath(); ctx.rect(h.x - h.w, 0, h.w * 2, h.y + 2); ctx.clip();
      ctx.font = Math.round(z.tam) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(a.animal, h.x, z.cy + (1 - z.sale) * z.tam);
      ctx.restore();
    }

    /* Los carteles van al final, encima de todo: si no, el borde de un hoyo
       de la fila de arriba tapa el cartel del que asoma más abajo. */
    function dibujarCartel(a) {
      var z = zona(a), h = a.hoyo;
      if (z.sale < 0.3) return;
      // Palito y cartel con la respuesta
      ctx.globalAlpha = Math.min(1, (z.sale - 0.3) / 0.4);
      ctx.strokeStyle = '#8A5A35'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.moveTo(h.x + z.tam * 0.35, z.cy); ctx.lineTo(h.x + z.tam * 0.35, z.cartelY + a.cartel.h / 2); ctx.stroke();
      var cx = z.cartelX, cy = z.cartelY - a.cartel.h / 2;
      ctx.save();
      ctx.shadowColor = 'rgba(42,33,64,.25)'; ctx.shadowBlur = 8; ctx.shadowOffsetY = 3;
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath();
      if (ctx.roundRect) ctx.roundRect(cx, cy, a.cartel.w, a.cartel.h, 12); else ctx.rect(cx, cy, a.cartel.w, a.cartel.h);
      ctx.fill();
      ctx.restore();
      b.letra(a.op, cx + 15, z.cartelY, 10);
      b.escribir(a.texto, cx + 30, z.cartelY, '#2A2140');
      ctx.globalAlpha = 1;
    }

    b.correr(function (dt) {
      var eligio = b.yaEligio();
      asomados.forEach(function (a) { a.t += dt * (eligio && a.viva ? 2 : 1); });
      asomados = asomados.filter(function (a) { return a.viva ? a.t < a.dura : b.tiempo - a.atrapado < 0.5; });
      if (!eligio) {
        espera -= dt;
        var maximo = Math.min(3, b.opciones.length);
        if (espera <= 0 && asomados.filter(function (a) { return a.viva; }).length < maximo) {
          asomar();
          espera = tiempoArriba() / maximo;
        }
      }
      festejo = Math.max(0, festejo - dt);

      jardin();
      // El personaje del estudiante mira desde el seto; salta al atrapar.
      var tamP = Math.max(26, Math.min(40, b.H * 0.09));
      ctx.font = tamP + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
      ctx.fillText(piloto, b.W - 26, b.H * 0.16 - 2 - Math.abs(Math.sin(b.tiempo * (festejo ? 14 : 2.5))) * (festejo ? 16 : 3));
      hoyos.forEach(hoyoAtras);
      var visibles = asomados.filter(function (a) { return a.viva; });
      visibles.forEach(dibujarAnimal);
      hoyos.forEach(hoyoDelante);
      visibles.forEach(dibujarCartel);
      // Estrellitas del «¡te atrapé!»
      golpes.forEach(function (g) { g.t += dt; });
      golpes = golpes.filter(function (g) { return g.t < 0.8; });
      golpes.forEach(function (g) {
        ctx.globalAlpha = 1 - g.t / 0.8;
        ctx.font = '22px system-ui, "Segoe UI Emoji", sans-serif'; ctx.textAlign = 'center';
        for (var k = 0; k < 3; k++) {
          var ang = b.tiempo * 6 + k * 2.1;
          ctx.fillText('⭐', g.x + Math.cos(ang) * 26, g.y - 10 + Math.sin(ang) * 10);
        }
        ctx.globalAlpha = 1;
      });
      b.dibujarChispas(dt);
    });
  }

  var JUEGOS = { avion: avion, globos: globos, atrapa: atrapa };

  /**
   * @param {string} tipo            'avion' | 'globos' | 'atrapa'
   * @param {HTMLElement} marco      contenedor con un <canvas>
   * @param {string[]} textos        las opciones, en su orden original
   * @param {function(number)} alElegir  recibe el índice ORIGINAL elegido
   * @param {string} [piloto]        emoji del personaje del estudiante
   */
  function montar(tipo, marco, textos, alElegir, piloto) {
    if (!JUEGOS[tipo] || !marco) return null;
    var b = base(marco, textos, alElegir);
    JUEGOS[tipo](b, piloto || '🦊');
    return { apagar: b.apagar };
  }

  return { montar: montar, juegos: Object.keys(JUEGOS) };
})();
