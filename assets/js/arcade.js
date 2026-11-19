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
     laberinto — «Laberinto»: llegar a la puerta correcta sin que te pillen.
     tren    — «Tren de respuestas»: soltar la caja en el vagón correcto.
     catapulta — «Catapulta»: lanzarse con la honda contra el cartel correcto.
     salta   — «Salta y choca»: golpear desde abajo el bloque correcto.
     mina    — «Mina de bloques»: picar hasta la gema correcta.
     obstaculos — «Carrera de obstáculos»: cruzar el arco correcto, saltar el resto.
     nave    — «Nave espacial»: disparar al asteroide correcto.

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
    /*
     * Partida continua (juego enviado): al elegir, el juego NO se cierra.
     * jugar.js llama a `nueva()` con la pregunta siguiente y a `resultado()`
     * con lo que dijo el servidor; el avión sigue volando y la velocidad se
     * acumula a lo largo de toda la partida.
     */
    b.nueva = function (textos, ronda) {
      b.opciones = textos.map(function (t, i) { return { texto: String(t || '').trim(), i: i }; })
        .filter(function (o) { return o.texto !== ''; });
      b.ronda = Math.max(b.ronda + 1, Number(ronda) || 0);
      elegida = false;
      marco.removeAttribute('data-elegida');
      if (b.alNuevaPregunta) b.alNuevaPregunta();
    };
    b.mensaje = null;
    b.resultado = function (correcta, puntos) {
      b.mensaje = { ok: !!correcta, texto: correcta ? '✓ +' + (puntos || 0) : '✗ Era otra', t: 1.3 };
      if (correcta) sonar(880, 0.22, 'sine', 0.12, 1.3); else sonar(200, 0.3, 'triangle', 0.08, 0.6);
    };
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
        if (b.mensaje) {
          b.mensaje.t -= dt;
          var m = b.mensaje, ctx = b.ctx, sube = (1.3 - m.t) * 18;
          ctx.globalAlpha = Math.max(0, Math.min(1, m.t * 2));
          ctx.font = '800 ' + Math.max(22, Math.round(b.H * 0.1)) + 'px Fredoka, system-ui, sans-serif';
          ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
          ctx.lineWidth = 7; ctx.strokeStyle = '#FFFFFF'; ctx.strokeText(m.texto, b.W / 2, b.H * 0.42 - sube);
          ctx.fillStyle = m.ok ? '#0EA371' : '#E8492C'; ctx.fillText(m.texto, b.W / 2, b.H * 0.42 - sube);
          ctx.globalAlpha = 1;
          if (m.t <= 0) b.mensaje = null;
        }
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
    var nubes = [], humo = [], tormentas = [], rayas = [];
    var oleada = 0, recorrido = 0, elegidaEn = 0;
    var teclas = {};
    var aturdido = 0, avisoRapido = 0, avisoTormenta = 0, ritmoAvisado = 1;

    b.alRedimensionar = function (fx, fy) {
      nave.y *= fy; nave.objetivo *= fy;
      nubes.forEach(function (n) { n.x *= fx; n.y *= fy; });
    };
    b.alNuevaPregunta = function () {
      nubes.forEach(function (n) { b.explotar(n.x, n.y, '#FFFFFF', 6); });
      nubes = [];
    };
    var tamano = function () { return Math.max(26, Math.min(46, b.H * 0.11)); };
    var xNave = function () { return b.W * 0.17; };
    /*
     * El ritmo sube poco a poco —con cada tanda de nubes y con cada pregunta—
     * pero con tope: a 1,6 veces la velocidad inicial sigue siendo jugable
     * para un niño. Pedido expreso: que emocione, no que sea imposible.
     */
    var ritmo = function () { return Math.min(1.6, 1 + b.ronda * 0.06 + Math.max(0, oleada - 1) * 0.05); };
    var velocidad = function () {
      var v = b.W * (b.reducido ? 0.11 : 0.15) * ritmo();
      return aturdido > 0 ? v * 0.5 : v;
    };

    function lanzarOleada() {
      var n = b.opciones.length, arriba = 16, abajo = b.H * 0.16;
      var carril = (b.H - arriba - abajo) / n;
      var alto = Math.min(96, carril * 0.74);
      var ancho = Math.min(230, Math.max(120, b.W * 0.4));
      // Desde la segunda tanda llegan tormentas por delante: hay que
      // esquivarlas antes de elegir. Nunca tapan todos los carriles.
      var nTormentas = oleada === 0 ? 0 : Math.min(n - 1, oleada >= 3 ? 2 : 1);
      var carriles = barajar(b.opciones.map(function (_, k) { return k; })).slice(0, nTormentas);
      var adelanto = nTormentas ? Math.max(150, b.W * 0.45) : 0;
      carriles.forEach(function (k) {
        tormentas.push({ x: b.W + 40, y: arriba + carril * (k + 0.5), r: Math.min(34, carril * 0.38), tocada: false, fase: Math.random() * 6 });
      });
      // El aviso sale solo cuando el ritmo subió de verdad (no en cada tanda).
      if (oleada >= 2 && ritmo() - ritmoAvisado >= 0.1) { avisoRapido = 1.4; ritmoAvisado = ritmo(); }
      // Intercaladas: cada nube llega a su tiempo, en su carril y en otro
      // orden cada tanda. Todas juntas eran un muro; así hay que leer y
      // decidir una por una, que es lo que da la emoción.
      var turnos = barajar(b.opciones.map(function (_, k) { return k; }));
      var paso = Math.max(ancho * 0.7, b.W * 0.32);
      barajar(b.opciones).forEach(function (o, i) {
        nubes.push({
          op: o, x: b.W + adelanto + ancho / 2 + 20 + turnos[i] * paso, y: arriba + carril * (i + 0.5), w: ancho, h: alto,
          texto: b.ajustar(o.texto, ancho - 52, alto * 0.8, 3, Math.min(17, Math.max(11, alto * 0.24))),
          viva: true, bamboleo: Math.random() * 6
        });
      });
      oleada++;
    }

    function apuntar(ev) {
      if (aturdido > 0) return;
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

    function dibujarTormenta(t) {
      var y = t.y + Math.sin(b.tiempo * 3 + t.fase) * 4;
      // Lluvia
      ctx.strokeStyle = 'rgba(80,110,160,.55)'; ctx.lineWidth = 2;
      for (var k = -2; k <= 2; k++) {
        var lx = t.x + k * t.r * 0.35, ly = y + t.r * 0.5 + ((b.tiempo * 120 + k * 17) % (t.r * 0.8));
        ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(lx - 3, ly + 8); ctx.stroke();
      }
      ctx.fillStyle = t.tocada ? '#9AA3B5' : '#5E6678';
      b.nubeForma(t.x, y, t.r * 2.4, t.r * 1.3);
      ctx.fill();
      ctx.font = Math.round(t.r * 0.95) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('⚡', t.x + Math.sin(b.tiempo * 20) * 1.5, y + 2);
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
      aturdido = Math.max(0, aturdido - dt);
      avisoRapido = Math.max(0, avisoRapido - dt);
      avisoTormenta = Math.max(0, avisoTormenta - dt);
      if (aturdido > 0) teclas = {};
      if (teclas.arriba) nave.objetivo = Math.max(s / 2, nave.objetivo - b.H * 1.1 * dt);
      if (teclas.abajo) nave.objetivo = Math.min(b.H - s / 2, nave.objetivo + b.H * 1.1 * dt);
      var antes = nave.y;
      nave.y += (nave.objetivo - nave.y) * Math.min(1, dt * 9);
      var vy = dt ? (nave.y - antes) / dt : 0;
      nave.inclinacion += (Math.max(-0.45, Math.min(0.45, vy / 600)) - nave.inclinacion) * Math.min(1, dt * 8);

      if (b.continuo) eligio = false;   // la partida sigue: no se va volando
      if (!b.yaEligio()) {
        recorrido += v * dt;
        // Una oleada nueva cuando la anterior ya pasó la mitad de la pantalla.
        var enCamino = nubes.filter(function (n) { return n.viva && n.x > b.W * 0.3; }).length;
        if (!enCamino && b.tiempo > 0.9) lanzarOleada();
      }

      tormentas.forEach(function (t) {
        t.x -= v * dt * (eligio ? 0.35 : 1.08);
        if (eligio || t.tocada) return;
        var dx = t.x - xa, dy = t.y - nave.y;
        if (Math.sqrt(dx * dx + dy * dy) < t.r * 0.95 + s * 0.3) {
          // ¡Tormenta! Se sacude y pierde un momento; no cuenta como respuesta.
          t.tocada = true;
          aturdido = 1;
          avisoTormenta = 1.2;
          nave.objetivo = nave.y;
          b.explotar(xa, nave.y, '#FFD24D', 14);
          sonar(140, 0.35, 'sawtooth', 0.07, 0.5);
        }
      });
      tormentas = tormentas.filter(function (t) { return t.x > -t.r * 3; });

      nubes.forEach(function (n) {
        n.x -= v * dt * (eligio ? 0.35 : 1);
        // Aturdido no elige: una respuesta por accidente sería injusto.
        if (b.yaEligio() || !n.viva || aturdido > 0) return;
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
      // Rayas de velocidad cuando ya va rápido.
      if (ritmo() > 1.12 && !eligio && Math.random() < dt * 14) rayas.push({ x: b.W + 20, y: Math.random() * b.H * 0.8, l: 30 + Math.random() * 50 });
      rayas.forEach(function (r) { r.x -= v * dt * 2.2; });
      rayas = rayas.filter(function (r) { return r.x > -r.l; });
      ctx.strokeStyle = 'rgba(255,255,255,.7)'; ctx.lineWidth = 2;
      rayas.forEach(function (r) { ctx.beginPath(); ctx.moveTo(r.x, r.y); ctx.lineTo(r.x + r.l, r.y); ctx.stroke(); });
      tormentas.forEach(dibujarTormenta);
      nubes.forEach(nubeRespuesta);
      b.dibujarChispas(dt);
      var sacudida = aturdido > 0 ? Math.sin(b.tiempo * 60) * 4 : 0;
      if (!(aturdido > 0 && Math.floor(b.tiempo * 12) % 2 === 0)) {
        dibujarNave((eligio ? xa + (b.tiempo - elegidaEn) * b.W * 0.9 : xa) + sacudida, nave.y, s);
      }
      // Avisos
      function aviso(texto, color, y, alfa) {
        ctx.globalAlpha = Math.min(1, alfa);
        ctx.font = '800 ' + Math.max(15, Math.round(b.H * 0.06)) + 'px Fredoka, system-ui, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.lineWidth = 5; ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.strokeText(texto, b.W / 2, y);
        ctx.fillStyle = color; ctx.fillText(texto, b.W / 2, y);
        ctx.globalAlpha = 1;
      }
      if (avisoRapido > 0 && !eligio) aviso('⚡ ¡Más rápido!', '#E8492C', b.H * 0.12 - (1.4 - avisoRapido) * 10, avisoRapido);
      if (avisoTormenta > 0) aviso('¡Tormenta! Esquívalas', '#3F4A60', b.H * 0.5, avisoTormenta);
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
    b.alNuevaPregunta = function () { lista = []; esperaTanda = 0.2; tanda++; };
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

    b.alNuevaPregunta = function () { asomados = []; cola = []; espera = 0.3; };

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

  /* =================================================================
     🌀 Laberinto
     El personaje del estudiante sale del centro de un laberinto de setos
     y elige llegando a una de las puertas de las esquinas. Lo persiguen
     dos abejas, más lentas que él: si lo alcanzan, vuelve al centro (sin
     vidas que perder: nadie se queda sin responder). Un laberinto nuevo en
     cada pregunta, con algunos atajos para poder escapar.
     Se mueve tocando hacia dónde ir, deslizando el dedo, con la cruceta o
     con las flechas del teclado.
     ================================================================= */
  function laberinto(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 1;
    b.medir();

    var N = 15;                      // casillas por lado (impar)
    var CENTRO = 7;
    var muro = [];                   // muro[y][x] = true si es seto
    var esquinas = barajar([[1, 1], [N - 2, 1], [1, N - 2], [N - 2, N - 2]]);
    var puertas = b.opciones.slice(0, 4).map(function (op, i) { return { op: op, x: esquinas[i][0], y: esquinas[i][1] }; });

    // Laberinto perfecto por retroceso, y luego unos atajos.
    function cavar() {
      var yy, xx;
      muro = [];
      for (yy = 0; yy < N; yy++) { muro.push([]); for (xx = 0; xx < N; xx++) muro[yy].push(true); }
      var pila = [[1, 1]], visto = {};
      muro[1][1] = false; visto['1,1'] = true;
      while (pila.length) {
        var c = pila[pila.length - 1];
        var vecinos = barajar([[2, 0], [-2, 0], [0, 2], [0, -2]]).map(function (d) { return [c[0] + d[0], c[1] + d[1], d]; })
          .filter(function (v) { return v[0] > 0 && v[1] > 0 && v[0] < N - 1 && v[1] < N - 1 && !visto[v[0] + ',' + v[1]]; });
        if (!vecinos.length) { pila.pop(); continue; }
        var v = vecinos[0];
        muro[c[1] + v[2][1] / 2][c[0] + v[2][0] / 2] = false;
        muro[v[1]][v[0]] = false;
        visto[v[0] + ',' + v[1]] = true;
        pila.push([v[0], v[1]]);
      }
      for (var k = 0; k < 18; k++) {
        var ax = 1 + Math.floor(Math.random() * (N - 2)), ay = 1 + Math.floor(Math.random() * (N - 2));
        if ((ax + ay) % 2 === 1 && muro[ay][ax]) muro[ay][ax] = false;
      }
      // Plaza libre en el centro para arrancar con calma.
      for (var cy2 = CENTRO - 1; cy2 <= CENTRO + 1; cy2++) for (var cx2 = CENTRO - 1; cx2 <= CENTRO + 1; cx2++) muro[cy2][cx2] = false;
    }

    /** ¿Se llega a esta puerta desde el centro sin pisar ninguna otra? */
    function alcanzable(meta) {
      var visto = {}, cola = [[CENTRO, CENTRO]];
      visto[CENTRO + ',' + CENTRO] = true;
      while (cola.length) {
        var c = cola.shift();
        if (c[0] === meta.x && c[1] === meta.y) return true;
        for (var d = 0; d < 4; d++) {
          var nx = c[0] + [1, -1, 0, 0][d], ny = c[1] + [0, 0, 1, -1][d], clave = nx + ',' + ny;
          if (nx < 0 || ny < 0 || nx >= N || ny >= N || muro[ny][nx] || visto[clave]) continue;
          if (puertas.some(function (p) { return p !== meta && p.x === nx && p.y === ny; })) continue;
          visto[clave] = true; cola.push([nx, ny]);
        }
      }
      return false;
    }
    function todasAlcanzables() { return puertas.every(alcanzable); }

    /*
     * Cada puerta es un FONDO DE SACO: con dos salidas sería un paso, y un
     * niño la elegiría sin querer al cruzarla (o, peor, otra respuesta solo
     * se alcanzaría atravesándola: pasó en 8 de cada 100 laberintos). Se
     * tapa una salida si con eso todas siguen alcanzables; si el laberinto
     * no se deja arreglar, se genera otro.
     */
    for (var intento = 0; intento < 40; intento++) {
      cavar();
      puertas.forEach(function (p) {
        var salidas = [[1, 0], [-1, 0], [0, 1], [0, -1]].filter(function (d) {
          var nx = p.x + d[0], ny = p.y + d[1];
          return nx > 0 && ny > 0 && nx < N - 1 && ny < N - 1 && !muro[ny][nx];
        });
        barajar(salidas).slice(1).forEach(function (d) {
          muro[p.y + d[1]][p.x + d[0]] = true;
          if (!todasAlcanzables()) muro[p.y + d[1]][p.x + d[0]] = false;
        });
      });
      if (todasAlcanzables()) break;
    }

    function puertaEn(px, py) { return puertas.filter(function (p) { return p.x === px && p.y === py; })[0]; }

    var DIRS = { arriba: [0, -1], abajo: [0, 1], izquierda: [-1, 0], derecha: [1, 0] };
    var CONTRARIA = { arriba: 'abajo', abajo: 'arriba', izquierda: 'derecha', derecha: 'izquierda' };
    function libre(px, py, paraAbeja) {
      if (px < 0 || py < 0 || px >= N || py >= N || muro[py][px]) return false;
      // Las abejas no se acercan a las puertas: si pudieran quedarse en la
      // entrada, un niño podría no llegar nunca a la respuesta.
      return !(paraAbeja && puertas.some(function (p) { return Math.abs(p.x - px) <= 1 && Math.abs(p.y - py) <= 1; }));
    }

    var jugador = { x: CENTRO, y: CENTRO, px: CENTRO, py: CENTRO, dir: null, quiere: null, avance: 0, invulnerable: 0 };
    var abejas = [
      { x: CENTRO, y: 1, px: CENTRO, py: 1, dir: null, avance: 0 },
      { x: CENTRO, y: N - 2, px: CENTRO, py: N - 2, dir: null, avance: 0 }
    ].filter(function (a) { return !muro[a.y][a.x]; });
    abejas.forEach(function (a) { a.casa = [a.x, a.y]; });
    var atrapadoEn = -10, festejo = 0, abejasQuietas = 1.5;
    // Solo lectura, para las pruebas automáticas: el mapa y dónde está cada uno.
    b.marco.ludiaLaberinto = { muro: muro, puertas: puertas, jugador: jugador, abejas: abejas };

    var vJugador = b.reducido ? 3.6 : 5;
    var vAbeja = b.reducido ? 2 : 2.9;

    function geometria() {
      var lado = Math.min(b.W, b.H) - 8;
      var t = lado / N;
      return { t: t, ox: (b.W - t * N) / 2, oy: (b.H - t * N) / 2 };
    }

    /* ---------- Controles ---------- */
    function pedir(dir) {
      if (!DIRS[dir]) return;
      jugador.quiere = dir;
      // Darse la vuelta a medio pasillo es inmediato, como en cualquier
      // laberinto: si no, el personaje tendría que llegar a la casilla
      // siguiente para poder regresar.
      if (jugador.dir && dir === CONTRARIA[jugador.dir] && jugador.avance > 0) {
        var d = DIRS[jugador.dir];
        jugador.x += d[0]; jugador.y += d[1];
        jugador.avance = 1 - jugador.avance;
        jugador.dir = dir;
        return;
      }
      // Tolerancia en los cruces: si el giro se pide apenas pasado uno y por
      // ahí había salida, se vuelve al cruce y se gira. Sin esto, un dedo un
      // poco lento se salta todos los desvíos.
      var q = DIRS[dir];
      if (jugador.dir && dir !== jugador.dir && jugador.avance < 0.45 && libre(jugador.x + q[0], jugador.y + q[1])) {
        jugador.avance = 0;
        jugador.dir = dir;
      }
    }
    var inicio = null;
    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      var p = b.punto(ev), g = geometria();
      inicio = p;
      // Tocar: ir hacia donde se tocó, por el eje con más distancia.
      var dx = p.x - (g.ox + (jugador.px + 0.5) * g.t), dy = p.y - (g.oy + (jugador.py + 0.5) * g.t);
      if (Math.abs(dx) > g.t * 0.4 || Math.abs(dy) > g.t * 0.4) {
        pedir(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'derecha' : 'izquierda') : (dy > 0 ? 'abajo' : 'arriba'));
      }
      if (b.lienzo.setPointerCapture) { try { b.lienzo.setPointerCapture(ev.pointerId); } catch (e) {} }
      sonar(220, 0.04, 'triangle', 0.02);
      if (ev.cancelable) ev.preventDefault();
    });
    b.escuchar(b.lienzo, 'pointermove', function (ev) {
      if (!inicio || !(ev.buttons || ev.pressure > 0)) return;
      var p = b.punto(ev), dx = p.x - inicio.x, dy = p.y - inicio.y;
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 16) return;
      pedir(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'derecha' : 'izquierda') : (dy > 0 ? 'abajo' : 'arriba'));
      inicio = p;
    });
    b.escuchar(b.lienzo, 'pointerup', function () { inicio = null; });
    [].forEach.call(b.marco.querySelectorAll('[data-dir]'), function (boton) {
      b.escuchar(boton, 'pointerdown', function (ev) { pedir(boton.getAttribute('data-dir')); ev.preventDefault(); });
      b.escuchar(boton, 'click', function (ev) { pedir(boton.getAttribute('data-dir')); ev.preventDefault(); });
    });
    b.escuchar(document, 'keydown', function (ev) {
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
      var mapa = { ArrowUp: 'arriba', ArrowDown: 'abajo', ArrowLeft: 'izquierda', ArrowRight: 'derecha', w: 'arriba', s: 'abajo', a: 'izquierda', d: 'derecha' };
      var dir = mapa[ev.key];
      if (!dir) return;
      ev.preventDefault();
      pedir(dir);
    });

    /* ---------- Movimiento por casillas ---------- */

    /** Avanza a alguien por la cuadrícula. `elegir` decide la dirección en cada casilla. */
    function mover(quien, velocidad, dt, elegir) {
      if (quien.dir === null) {
        quien.dir = elegir(quien);
        if (quien.dir === null) return;
      }
      quien.avance += velocidad * dt;
      while (quien.avance >= 1) {
        quien.avance -= 1;
        var d = DIRS[quien.dir];
        quien.x += d[0]; quien.y += d[1];
        var siguiente = elegir(quien);
        if (siguiente === null) { quien.dir = null; quien.avance = 0; break; }
        quien.dir = siguiente;
      }
      var dd = quien.dir ? DIRS[quien.dir] : [0, 0];
      quien.px = quien.x + dd[0] * quien.avance;
      quien.py = quien.y + dd[1] * quien.avance;
    }

    function elegirJugador(j) {
      if (j.quiere) {
        var q = DIRS[j.quiere];
        if (libre(j.x + q[0], j.y + q[1])) { return j.quiere; }
      }
      if (j.dir) {
        var d = DIRS[j.dir];
        if (libre(j.x + d[0], j.y + d[1])) return j.dir;
      }
      return null;
    }

    /** Distancias desde el jugador por los pasillos (búsqueda en anchura). */
    function distancias() {
      var dist = {}, cola = [[jugador.x, jugador.y]];
      dist[jugador.x + ',' + jugador.y] = 0;
      while (cola.length) {
        var c = cola.shift();
        Object.keys(DIRS).forEach(function (k) {
          var nx = c[0] + DIRS[k][0], ny = c[1] + DIRS[k][1], clave = nx + ',' + ny;
          if (libre(nx, ny, true) && dist[clave] === undefined) { dist[clave] = dist[c[0] + ',' + c[1]] + 1; cola.push([nx, ny]); }
        });
      }
      return dist;
    }
    var mapaDist = distancias(), recalcular = 0;

    function elegirAbeja(a) {
      var opciones = Object.keys(DIRS).filter(function (k) {
        return libre(a.x + DIRS[k][0], a.y + DIRS[k][1], true);
      });
      if (!opciones.length) return null;
      var sinVolver = opciones.filter(function (k) { return k !== CONTRARIA[a.dir]; });
      if (sinVolver.length) opciones = sinVolver;
      // Casi siempre se acerca; a veces se distrae con una flor.
      if (Math.random() < 0.6) {
        opciones.sort(function (p, q) {
          var dp = mapaDist[(a.x + DIRS[p][0]) + ',' + (a.y + DIRS[p][1])], dq = mapaDist[(a.x + DIRS[q][0]) + ',' + (a.y + DIRS[q][1])];
          return (dp === undefined ? 999 : dp) - (dq === undefined ? 999 : dq);
        });
        return opciones[0];
      }
      return opciones[Math.floor(Math.random() * opciones.length)];
    }

    /* ---------- Dibujo ---------- */

    function tablero(g) {
      ctx.fillStyle = '#7ED98C'; ctx.fillRect(0, 0, b.W, b.H);
      for (var yy = 0; yy < N; yy++) {
        for (var xx = 0; xx < N; xx++) {
          var x0 = g.ox + xx * g.t, y0 = g.oy + yy * g.t;
          if (muro[yy][xx]) {
            ctx.fillStyle = '#2E9E58';
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x0 + 0.5, y0 + 0.5, g.t - 1, g.t - 1, g.t * 0.3); else ctx.rect(x0, y0, g.t, g.t);
            ctx.fill();
            ctx.fillStyle = 'rgba(255,255,255,.12)';
            ctx.fillRect(x0 + g.t * 0.2, y0 + g.t * 0.18, g.t * 0.6, g.t * 0.14);
          } else {
            ctx.fillStyle = (xx + yy) % 2 ? '#F6E7C8' : '#F1DFBB';
            ctx.fillRect(x0, y0, g.t, g.t);
          }
        }
      }
      // Puertas
      puertas.forEach(function (p) {
        var cx = g.ox + (p.x + 0.5) * g.t, cy = g.oy + (p.y + 0.5) * g.t;
        var pulso = 1 + Math.sin(b.tiempo * 4 + p.op.i) * 0.06;
        ctx.fillStyle = COLORES[p.op.i % COLORES.length];
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(cx - g.t * 0.5 * pulso, cy - g.t * 0.5 * pulso, g.t * pulso, g.t * pulso, g.t * 0.25); else ctx.rect(cx - g.t / 2, cy - g.t / 2, g.t, g.t);
        ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(g.t * 0.7) + 'px Fredoka, system-ui, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(LETRAS[p.op.i], cx, cy + 1);
      });
    }

    function emoji(texto, cx, cy, tam) {
      ctx.font = Math.round(tam) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(texto, cx, cy);
    }

    b.correr(function (dt) {
      var g = geometria(), eligio = b.yaEligio();
      if (!eligio) {
        var antes = jugador.x + ',' + jugador.y;
        mover(jugador, vJugador, dt, elegirJugador);
        if (jugador.x + ',' + jugador.y !== antes) { recalcular = 0; mapaDist = distancias(); }
        var puerta = puertaEn(jugador.x, jugador.y);
        if (puerta) {
          b.explotar(g.ox + (puerta.x + 0.5) * g.t, g.oy + (puerta.y + 0.5) * g.t, COLORES[puerta.op.i % COLORES.length], 34);
          sonar(523, 0.2, 'sine', 0.14, 1.5);
          festejo = 1;
          b.elegir(puerta.op);
        }
        abejasQuietas = Math.max(0, abejasQuietas - dt);
        if (!abejasQuietas) abejas.forEach(function (a) { mover(a, vAbeja, dt, elegirAbeja); });
        jugador.invulnerable = Math.max(0, jugador.invulnerable - dt);
        abejas.forEach(function (a) {
          if (jugador.invulnerable > 0) return;
          if (Math.abs(a.px - jugador.px) < 0.6 && Math.abs(a.py - jugador.py) < 0.6) {
            // ¡Pillado! De vuelta al centro, sin perder nada más que tiempo.
            jugador.x = jugador.px = CENTRO; jugador.y = jugador.py = CENTRO;
            jugador.dir = jugador.quiere = null; jugador.avance = 0;
            jugador.invulnerable = 1.6;
            atrapadoEn = b.tiempo;
            // Las abejas vuelven a su colmena y esperan un poco: si se
            // quedaran en el centro, atraparían al personaje en bucle.
            abejas.forEach(function (ab) {
              ab.x = ab.px = ab.casa[0]; ab.y = ab.py = ab.casa[1]; ab.dir = null; ab.avance = 0;
            });
            abejasQuietas = 1.2;
            // Dificultad que se adapta: cada vez que lo pillan, las abejas
            // van un poco más lentas en esta pregunta. Que nadie se atasque.
            vAbeja = Math.max(1.4, vAbeja * 0.82);
            mapaDist = distancias();
            sonar(160, 0.3, 'sawtooth', 0.06, 0.6);
          }
        });
      }
      festejo = Math.max(0, festejo - dt);

      tablero(g);
      abejas.forEach(function (a, i) {
        emoji('🐝', g.ox + (a.px + 0.5) * g.t + Math.sin(b.tiempo * 9 + i) * g.t * 0.08,
          g.oy + (a.py + 0.5) * g.t + Math.cos(b.tiempo * 7 + i) * g.t * 0.08, g.t * 0.95);
      });
      var parpadea = jugador.invulnerable > 0 && Math.floor(b.tiempo * 10) % 2 === 0;
      if (!parpadea) {
        emoji(piloto, g.ox + (jugador.px + 0.5) * g.t, g.oy + (jugador.py + 0.5) * g.t - (festejo ? Math.abs(Math.sin(b.tiempo * 14)) * g.t * 0.4 : 0), g.t * 1.05);
      }
      if (b.tiempo - atrapadoEn < 1.2) {
        ctx.globalAlpha = 1 - (b.tiempo - atrapadoEn) / 1.2;
        var aviso = '¡Bzzz! Otra vez desde el centro';
        ctx.font = '800 ' + Math.max(12, Math.round(g.t * 0.62)) + 'px Fredoka, system-ui, sans-serif';
        var anchoAviso = Math.min(b.W - 16, ctx.measureText(aviso).width + 24);
        ctx.fillStyle = 'rgba(42,33,64,.82)';
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(b.W / 2 - anchoAviso / 2, b.H / 2 - g.t * 2.2, anchoAviso, g.t * 1.2, g.t * 0.6); else ctx.rect(b.W / 2 - anchoAviso / 2, b.H / 2 - g.t * 2.2, anchoAviso, g.t * 1.2);
        ctx.fill();
        ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(aviso, b.W / 2, b.H / 2 - g.t * 1.6);
        ctx.globalAlpha = 1;
      }
      b.dibujarChispas(dt);
    });
  }

  /* =================================================================
     🚂 Tren de respuestas
     Un tren con un vagón por respuesta pasa bajo una grúa. Se toca (o
     Espacio) para soltar la caja: hay que calcular para que caiga en el
     vagón correcto mientras el tren avanza. Si cae al suelo, sale otra.
     Cada vuelta, los vagones cambian de orden y el tren va un poco más
     rápido (con tope, para que siga siendo jugable).
     ================================================================= */
  function tren(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 0.86;
    b.medir();

    var vuelta = 0, avisoVuelta = 0, avisoCasi = 0;
    var tren = null, caja = null, rotas = [], humo = [], festejo = 0;

    var suelo = function () { return b.H * 0.84; };
    var anchoVagon = function () { return Math.min(140, Math.max(96, b.W * 0.34)); };
    var altoVagon = function () { return Math.min(78, b.H * 0.2); };
    var velocidad = function () { return b.W * (b.reducido ? 0.08 : 0.11) * Math.min(1.6, 1 + b.ronda * 0.05 + vuelta * 0.08); };
    var xGrua = function () { return b.W * 0.5; };
    var yGrua = function () { return b.H * 0.13; };

    function nuevoTren() {
      var ancho = anchoVagon(), alto = altoVagon();
      var vagones = barajar(b.opciones).map(function (o) {
        return { op: o, texto: b.ajustar(o.texto, ancho - 40, alto * 0.62, 2, 15) };
      });
      tren = { x: b.W + 30, vagones: vagones, ancho: ancho, alto: alto, hueco: 14, loco: Math.min(96, ancho * 0.8) };
      if (vuelta > 0) avisoVuelta = 1.3;
    }
    function nuevaCaja() { caja = { y: yGrua() + 26, vy: 0, cae: false, x: xGrua() }; }
    nuevoTren();
    nuevaCaja();
    b.alRedimensionar = function () { nuevaCaja(); };

    function soltar(ev) {
      if (b.yaEligio() || !caja || caja.cae) return;
      caja.cae = true;
      sonar(300, 0.08, 'triangle', 0.05);
      if (ev && ev.cancelable) ev.preventDefault();
    }
    b.escuchar(b.lienzo, 'pointerdown', soltar);
    b.escuchar(document, 'keydown', function (ev) {
      if (document.activeElement && /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(document.activeElement.tagName)) return;
      if (ev.key === ' ' || ev.key === 'Enter' || ev.key === 'ArrowDown') soltar(ev);
    });

    /** Dónde está cada vagón ahora (la locomotora va delante, a la izquierda). */
    function vagonesEn() {
      var x = tren.x + tren.loco + tren.hueco;
      return tren.vagones.map(function (v) {
        var r = { v: v, x: x, y: suelo() - 16 - tren.alto, w: tren.ancho, h: tren.alto };
        x += tren.ancho + tren.hueco;
        return r;
      });
    }
    function largoTren() { return tren.loco + tren.hueco + tren.vagones.length * (tren.ancho + tren.hueco); }

    function rueda(x, y, r) {
      ctx.fillStyle = '#2A2140'; ctx.beginPath(); ctx.arc(x, y, r, 0, 7); ctx.fill();
      ctx.strokeStyle = '#9AA3B5'; ctx.lineWidth = 2;
      var a = -tren.x / r;
      ctx.beginPath(); ctx.moveTo(x + Math.cos(a) * r * 0.7, y + Math.sin(a) * r * 0.7); ctx.lineTo(x - Math.cos(a) * r * 0.7, y - Math.sin(a) * r * 0.7); ctx.stroke();
    }

    function dibujarLocomotora() {
      var x = tren.x, w = tren.loco, base = suelo() - 16, h = tren.alto * 1.05;
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(x + w * 0.42, base - h, w * 0.58, h, 10); else ctx.rect(x + w * 0.42, base - h, w * 0.58, h); ctx.fill();
      ctx.fillStyle = '#E8492C';
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(x, base - h * 0.62, w * 0.62, h * 0.62, 12); else ctx.rect(x, base - h * 0.62, w * 0.62, h * 0.62); ctx.fill();
      ctx.fillStyle = '#2A2140'; ctx.fillRect(x + w * 0.12, base - h * 0.95, w * 0.14, h * 0.36);
      ctx.fillStyle = '#DFF3FF';
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(x + w * 0.52, base - h * 0.86, w * 0.38, h * 0.4, 8); else ctx.rect(x + w * 0.52, base - h * 0.86, w * 0.38, h * 0.4); ctx.fill();
      ctx.font = Math.round(h * 0.34) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(piloto, x + w * 0.71, base - h * 0.64 + Math.sin(b.tiempo * 8) * 1.5);
      rueda(x + w * 0.2, base, 9); rueda(x + w * 0.52, base, 9); rueda(x + w * 0.82, base, 9);
    }

    function dibujarVagon(r) {
      var color = COLORES[r.v.op.i % COLORES.length];
      ctx.fillStyle = 'rgba(42,33,64,.18)'; ctx.fillRect(r.x - tren.hueco, r.y + r.h - 10, tren.hueco, 5);
      ctx.fillStyle = color;
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(r.x, r.y, r.w, r.h, 10); else ctx.rect(r.x, r.y, r.w, r.h); ctx.fill();
      ctx.fillStyle = 'rgba(255,255,255,.92)';
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(r.x + 6, r.y + 8, r.w - 12, r.h - 18, 8); else ctx.rect(r.x + 6, r.y + 8, r.w - 12, r.h - 18); ctx.fill();
      b.letra(r.v.op, r.x + 20, r.y + r.h * 0.46, 10);
      b.escribir(r.v.texto, r.x + 34, r.y + r.h * 0.46, '#2A2140');
      rueda(r.x + r.w * 0.22, r.y + r.h + 4, 8); rueda(r.x + r.w * 0.78, r.y + r.h + 4, 8);
    }

    function dibujarGrua() {
      var x = xGrua(), y = yGrua();
      ctx.fillStyle = '#F29A0D'; ctx.fillRect(0, y - 14, b.W, 10);
      ctx.fillStyle = '#2A2140'; ctx.fillRect(x - 14, y - 18, 28, 16);
      ctx.strokeStyle = '#2A2140'; ctx.lineWidth = 2;
      if (caja && !caja.cae) { ctx.beginPath(); ctx.moveTo(x, y - 2); ctx.lineTo(caja.x, caja.y - 16); ctx.stroke(); }
    }

    function dibujarCaja(c) {
      var lado = 30, oscila = c.cae ? 0 : Math.sin(b.tiempo * 3) * 0.12;
      ctx.save(); ctx.translate(c.x, c.y); ctx.rotate(oscila);
      ctx.fillStyle = '#B7793F'; ctx.fillRect(-lado / 2, -lado / 2, lado, lado);
      ctx.strokeStyle = '#7A5233'; ctx.lineWidth = 3; ctx.strokeRect(-lado / 2, -lado / 2, lado, lado);
      ctx.beginPath(); ctx.moveTo(-lado / 2, -lado / 2); ctx.lineTo(lado / 2, lado / 2); ctx.stroke();
      ctx.fillStyle = '#fff'; ctx.font = '800 18px Fredoka, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('?', 0, 1);
      ctx.restore();
    }

    b.correr(function (dt) {
      var eligio = b.yaEligio(), v = velocidad();
      tren.x -= v * dt * (eligio ? 1.6 : 1);
      if (tren.x + largoTren() < -20 && !eligio) { vuelta++; nuevoTren(); }
      avisoVuelta = Math.max(0, avisoVuelta - dt);
      avisoCasi = Math.max(0, avisoCasi - dt);
      festejo = Math.max(0, festejo - dt);
      if (Math.random() < dt * 8) humo.push({ x: tren.x + tren.loco * 0.19, y: suelo() - 16 - tren.alto * 1.05, r: 5, vida: 1 });
      humo.forEach(function (h) { h.y -= 24 * dt; h.x += 10 * dt; h.r += 9 * dt; h.vida -= dt * 0.9; });
      humo = humo.filter(function (h) { return h.vida > 0; });

      if (caja && caja.cae && !eligio) {
        caja.vy += b.H * 2.2 * dt;
        caja.y += caja.vy * dt;
        var r = vagonesEn().filter(function (w) { return caja.x > w.x + 4 && caja.x < w.x + w.w - 4; })[0];
        if (r && caja.y + 15 >= r.y + 8) {
          b.explotar(caja.x, r.y, COLORES[r.v.op.i % COLORES.length], 28);
          sonar(523, 0.2, 'sine', 0.14, 1.5);
          festejo = 1;
          caja = null;
          b.elegir(r.v.op);
        } else if (caja.y + 15 >= suelo()) {
          // ¡Al suelo! Se rompe y sale otra: solo se pierde tiempo.
          for (var k = 0; k < 8; k++) rotas.push({ x: caja.x, y: suelo() - 6, vx: (Math.random() - 0.5) * 160, vy: -80 - Math.random() * 120, vida: 1 });
          sonar(160, 0.2, 'square', 0.05, 0.6);
          avisoCasi = 1.1;
          caja = null;
          setTimeout(function () { if (b.vivo && !b.yaEligio()) nuevaCaja(); }, 500);
        }
      }
      rotas.forEach(function (p) { p.x += p.vx * dt; p.vy += 500 * dt; p.y += p.vy * dt; p.vida -= dt * 1.5; });
      rotas = rotas.filter(function (p) { return p.vida > 0; });

      b.cielo();
      b.nubesFondo(dt, v * 0.15);
      b.colinas(b.tiempo * 6, b.H * 0.3, '#8FD9B6', 170);
      // Vía
      ctx.fillStyle = '#7ED98C'; ctx.fillRect(0, suelo() - 6, b.W, b.H - suelo() + 6);
      ctx.fillStyle = '#A0703F';
      for (var t = ((tren.x % 22) + 22) % 22 - 22; t < b.W; t += 22) ctx.fillRect(t, suelo() - 8, 12, 6);
      ctx.fillStyle = '#5E6678'; ctx.fillRect(0, suelo() - 12, b.W, 3);
      humo.forEach(function (h) { ctx.fillStyle = 'rgba(255,255,255,' + (h.vida * 0.85) + ')'; ctx.beginPath(); ctx.arc(h.x, h.y, h.r, 0, 7); ctx.fill(); });
      vagonesEn().forEach(dibujarVagon);
      dibujarLocomotora();
      dibujarGrua();
      if (caja) dibujarCaja(caja);
      rotas.forEach(function (p) { ctx.globalAlpha = Math.max(0, p.vida); ctx.fillStyle = '#B7793F'; ctx.fillRect(p.x, p.y, 6, 6); ctx.globalAlpha = 1; });
      b.dibujarChispas(dt);
      function aviso(texto, color, y, alfa) {
        ctx.globalAlpha = Math.min(1, alfa);
        ctx.font = '800 ' + Math.max(15, Math.round(b.H * 0.06)) + 'px Fredoka, system-ui, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.lineWidth = 5; ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.strokeText(texto, b.W / 2, y);
        ctx.fillStyle = color; ctx.fillText(texto, b.W / 2, y);
        ctx.globalAlpha = 1;
      }
      if (avisoVuelta > 0 && !eligio) aviso('🚂 ¡Otra vuelta, más rápido!', '#E8492C', b.H * 0.32, avisoVuelta);
      if (avisoCasi > 0) aviso('¡Casi! Suelta otra caja', '#3F4A60', b.H * 0.4, avisoCasi);
      if (!eligio && caja && !caja.cae && b.tiempo < 4) aviso('👆 Toca para soltar la caja', '#5B3DF5', b.H * 0.32, Math.min(1, 4 - b.tiempo));
    });
  }

  /* =================================================================
     🎯 Catapulta
     Se tensa la honda arrastrando hacia atrás y se suelta: el personaje
     del estudiante sale volando hacia los carteles de las respuestas, en
     islas flotantes a distintas alturas. Acertar un cartel es elegirlo; si
     no toca ninguno, vuelve a la honda. Una línea de puntos ayuda a apuntar.
     ================================================================= */
  function catapulta(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 0.82;
    b.medir();

    var suelo = function () { return b.H * 0.88; };
    var radio = function () { return Math.max(14, Math.min(22, b.H * 0.055)); };
    var maxTiro = function () { return b.H * 0.24; };
    // La honda queda lo bastante adentro para que, tensada al máximo hacia
    // atrás, el personaje no se salga por el borde.
    var ancla = function () { return { x: Math.max(b.W * 0.17, maxTiro() + radio() + 6), y: suelo() - b.H * 0.2 }; };
    var gravedad = function () { return b.H * 1.5; };
    // La mayor tensión alcanza un poco más allá del cartel más lejano.
    var fuerza = function () { return Math.sqrt(gravedad() * b.W * 1.05) / maxTiro(); };

    var carteles = [], bola = null, tirando = null, intentos = 0, avisoFallo = 0, festejo = 0;

    function armar() {
      var n = b.opciones.length;
      var columnas = n <= 2 ? n : 2, filas = Math.ceil(n / columnas);
      var x0 = b.W * 0.36, ancho = b.W - x0 - 8;
      var w = Math.min(150, ancho / columnas - 8), h = Math.min(56, b.H * 0.15);
      var alto = suelo() - b.H * 0.12;
      carteles = barajar(b.opciones).map(function (o, i) {
        var c = i % columnas, f = Math.floor(i / columnas);
        var x = x0 + (ancho / columnas) * (c + 0.5) - w / 2;
        var y = b.H * 0.1 + (alto / filas) * f + (c % 2 ? alto / filas * 0.35 : 0);
        return { op: o, x: x, y: y, w: w, h: h, texto: b.ajustar(o.texto, w - 44, h - 10, 2, 14) };
      });
    }
    armar();
    b.alRedimensionar = function () { armar(); bola = null; };

    function lista() { var a = ancla(); return { x: a.x, y: a.y, vx: 0, vy: 0, vuela: false }; }
    bola = lista();

    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      if (b.yaEligio() || (bola && bola.vuela)) return;
      if (b.lienzo.setPointerCapture) { try { b.lienzo.setPointerCapture(ev.pointerId); } catch (e) {} }
      tirando = b.punto(ev);
      sonar(220, 0.04, 'triangle', 0.02);
      if (ev.cancelable) ev.preventDefault();
    });
    function tension(p) {
      var a = ancla(), dx = p.x - a.x, dy = p.y - a.y, d = Math.sqrt(dx * dx + dy * dy) || 1;
      var m = Math.min(maxTiro(), d);
      return { dx: dx / d * m, dy: dy / d * m };
    }
    b.escuchar(b.lienzo, 'pointermove', function (ev) {
      if (!tirando) return;
      var t = tension(b.punto(ev)), a = ancla();
      bola.x = a.x + t.dx; bola.y = a.y + t.dy;
    });
    b.escuchar(b.lienzo, 'pointerup', function () {
      if (!tirando) return;
      tirando = null;
      var a = ancla(), dx = bola.x - a.x, dy = bola.y - a.y;
      if (Math.sqrt(dx * dx + dy * dy) < maxTiro() * 0.12) { bola = lista(); return; }
      bola.vx = -dx * fuerza(); bola.vy = -dy * fuerza(); bola.vuela = true;
      intentos++;
      sonar(420, 0.15, 'sine', 0.1, 1.6);
    });

    function puntosGuia() {
      var a = ancla(), vx = -(bola.x - a.x) * fuerza(), vy = -(bola.y - a.y) * fuerza(), ps = [];
      for (var t = 0.06; t < 0.7; t += 0.06) ps.push({ x: bola.x + vx * t, y: bola.y + vy * t + gravedad() * t * t / 2 });
      return ps;
    }

    function isla(x, y, w) {
      ctx.fillStyle = '#A0703F';
      ctx.beginPath(); ctx.moveTo(x - 6, y); ctx.lineTo(x + w + 6, y); ctx.lineTo(x + w * 0.6, y + 26); ctx.lineTo(x + w * 0.35, y + 30); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#1FA98A'; ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(x - 8, y - 6, w + 16, 10, 5); else ctx.rect(x - 8, y - 6, w + 16, 10); ctx.fill();
    }

    function dibujarCartel(c) {
      isla(c.x, c.y + c.h + 10, c.w);
      ctx.fillStyle = '#8A5A35'; ctx.fillRect(c.x + c.w / 2 - 3, c.y + c.h, 6, 12);
      ctx.save(); ctx.shadowColor = 'rgba(42,33,64,.25)'; ctx.shadowBlur = 8; ctx.shadowOffsetY = 3;
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(c.x, c.y, c.w, c.h, 12); else ctx.rect(c.x, c.y, c.w, c.h); ctx.fill();
      ctx.restore();
      ctx.strokeStyle = COLORES[c.op.i % COLORES.length]; ctx.lineWidth = 3;
      ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(c.x + 1.5, c.y + 1.5, c.w - 3, c.h - 3, 11); else ctx.rect(c.x, c.y, c.w, c.h); ctx.stroke();
      b.letra(c.op, c.x + 16, c.y + c.h / 2, 10);
      b.escribir(c.texto, c.x + 31, c.y + c.h / 2, '#2A2140');
    }

    function dibujarHonda() {
      var a = ancla();
      ctx.strokeStyle = '#7A5233'; ctx.lineWidth = 7; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(a.x, suelo()); ctx.lineTo(a.x, a.y + 26); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(a.x, a.y + 26); ctx.lineTo(a.x - 16, a.y - 8); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(a.x, a.y + 26); ctx.lineTo(a.x + 16, a.y - 8); ctx.stroke();
      return a;
    }

    b.correr(function (dt) {
      var eligio = b.yaEligio(), r = radio();
      avisoFallo = Math.max(0, avisoFallo - dt);
      festejo = Math.max(0, festejo - dt);
      if (bola && bola.vuela && !eligio) {
        bola.vy += gravedad() * dt;
        bola.x += bola.vx * dt; bola.y += bola.vy * dt;
        var golpe = carteles.filter(function (c) {
          return bola.x + r > c.x && bola.x - r < c.x + c.w && bola.y + r > c.y && bola.y - r < c.y + c.h;
        })[0];
        if (golpe) {
          b.explotar(bola.x, bola.y, COLORES[golpe.op.i % COLORES.length], 34);
          sonar(523, 0.2, 'sine', 0.14, 1.5);
          festejo = 1;
          bola.vuela = false; bola.vx = bola.vy = 0;
          b.elegir(golpe.op);
        } else if (bola.y > suelo() || bola.x > b.W + 40 || bola.x < -40) {
          b.explotar(Math.min(b.W - 10, Math.max(10, bola.x)), Math.min(bola.y, suelo()), '#B7793F', 10);
          sonar(150, 0.2, 'square', 0.05, 0.6);
          avisoFallo = 1.1;
          bola = lista();
        }
      }

      b.cielo('#7CC6FF', '#D3EEFF');
      b.nubesFondo(dt, b.W * 0.02);
      b.colinas(0, b.H * 0.16, '#8FD9B6', 160);
      ctx.fillStyle = '#7ED98C'; ctx.fillRect(0, suelo(), b.W, b.H - suelo());
      carteles.forEach(dibujarCartel);
      var a = dibujarHonda();
      if (tirando && bola) {
        ctx.fillStyle = 'rgba(42,33,64,.45)';
        puntosGuia().forEach(function (p, k) { ctx.beginPath(); ctx.arc(p.x, p.y, Math.max(1.5, 4 - k * 0.25), 0, 7); ctx.fill(); });
      }
      if (bola) {
        if (!bola.vuela) {
          ctx.strokeStyle = '#2A2140'; ctx.lineWidth = 3;
          ctx.beginPath(); ctx.moveTo(a.x - 16, a.y - 8); ctx.lineTo(bola.x, bola.y); ctx.lineTo(a.x + 16, a.y - 8); ctx.stroke();
        }
        ctx.fillStyle = '#FFFFFF';
        ctx.beginPath(); ctx.arc(bola.x, bola.y, r, 0, 7); ctx.fill();
        ctx.strokeStyle = '#5B3DF5'; ctx.lineWidth = 3; ctx.stroke();
        ctx.save(); ctx.translate(bola.x, bola.y); if (bola.vuela) ctx.rotate(b.tiempo * 8);
        ctx.font = Math.round(r * 1.3) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(piloto, 0, 1);
        ctx.restore();
      }
      b.dibujarChispas(dt);
      function aviso(texto, color, y, alfa) {
        ctx.globalAlpha = Math.min(1, alfa);
        ctx.font = '800 ' + Math.max(14, Math.round(b.H * 0.055)) + 'px Fredoka, system-ui, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.lineWidth = 5; ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.strokeText(texto, b.W * 0.3, y);
        ctx.fillStyle = color; ctx.fillText(texto, b.W * 0.3, y);
        ctx.globalAlpha = 1;
      }
      if (!intentos && !tirando && !eligio) aviso('👆 Arrastra hacia atrás y suelta', '#5B3DF5', b.H * 0.12, 1);
      if (avisoFallo > 0) aviso('¡Uy! Otra vez', '#3F4A60', b.H * 0.12, avisoFallo);
    });
  }

  /* Avisos grandes con borde blanco, compartidos por los juegos nuevos. */
  function aviso(b, texto, color, x, y, alfa) {
    var ctx = b.ctx;
    ctx.globalAlpha = Math.max(0, Math.min(1, alfa));
    // Se achica hasta caber en el ancho (en celular el texto se salía).
    var fuente = Math.max(14, Math.round(b.H * 0.055));
    do {
      ctx.font = '800 ' + fuente + 'px Fredoka, system-ui, sans-serif';
      if (ctx.measureText(texto).width <= b.W - 24) break;
      fuente--;
    } while (fuente > 10);
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.lineWidth = 5; ctx.strokeStyle = 'rgba(255,255,255,.92)'; ctx.strokeText(texto, x, y);
    ctx.fillStyle = color; ctx.fillText(texto, x, y);
    ctx.globalAlpha = 1;
  }
  function emoji(b, texto, x, y, tam) {
    // Color opaco: el navegador pinta el emoji con la transparencia del
    // último fillStyle, y tras una sombra el personaje salía fantasma.
    b.ctx.fillStyle = '#000';
    b.ctx.font = Math.round(tam) + 'px system-ui, "Segoe UI Emoji", "Apple Color Emoji", sans-serif';
    b.ctx.textAlign = 'center'; b.ctx.textBaseline = 'middle';
    b.ctx.fillText(texto, x, y);
  }
  function caja(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x, y, w, h, r); else ctx.rect(x, y, w, h);
  }
  /** Botones táctiles (◀ ▲ ▶…) que jugar.js pone bajo el lienzo. */
  function botones(b, alPulsar, alSoltar) {
    [].forEach.call(b.marco.querySelectorAll('[data-dir]'), function (boton) {
      b.escuchar(boton, 'pointerdown', function (ev) { alPulsar(boton.getAttribute('data-dir')); ev.preventDefault(); });
      ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (t) {
        b.escuchar(boton, t, function () { if (alSoltar) alSoltar(boton.getAttribute('data-dir')); });
      });
    });
  }
  function teclaLibre() {
    return !(document.activeElement && /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(document.activeElement.tagName));
  }

  /* =================================================================
     🍄 Salta y choca (plataformas)
     El personaje corre por un nivel más ancho que la pantalla (la cámara
     lo sigue) y salta para golpear desde abajo el bloque de la respuesta
     correcta. Ruedan cocos: si lo alcanzan, se cae un momento.
     ================================================================= */
  function salta(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 0.8;
    b.medir();
    var suelo = function () { return b.H * 0.86; };
    var tam = function () { return Math.max(28, Math.min(42, b.H * 0.11)); };
    var g = function () { return b.H * 2.8; };
    var bloques = [], largo = 0, camara = 0, cocos = [], proximoCoco = 2.5, aturdido = 0, avisoCoco = 0, festejo = 0;
    var mover = { izquierda: false, derecha: false };
    var jugador = { x: 0, y: 0, vy: 0, enSuelo: true, mira: 1 };

    function armar() {
      var paso = Math.max(b.W * 0.56, 180), w = Math.min(150, paso * 0.72), h = Math.min(56, b.H * 0.16);
      bloques = barajar(b.opciones).map(function (o, i) {
        return { op: o, x: b.W * 0.42 + i * paso, y: suelo() - b.H * 0.4 - h, w: w, h: h, salto: 0,
          texto: b.ajustar(o.texto, w - 40, h - 10, 2, 14) };
      });
      largo = b.W * 0.42 + bloques.length * paso + b.W * 0.25;
      jugador.x = b.W * 0.16; jugador.y = suelo(); jugador.vy = 0; jugador.enSuelo = true;
    }
    armar();
    b.alRedimensionar = function () { armar(); };

    function saltar() {
      if (aturdido > 0 || !jugador.enSuelo || b.yaEligio()) return;
      jugador.vy = -Math.sqrt(2 * g() * b.H * 0.46);
      jugador.enSuelo = false;
      sonar(380, 0.12, 'square', 0.04, 1.8);
    }
    b.escuchar(b.lienzo, 'pointerdown', function (ev) { saltar(); if (ev.cancelable) ev.preventDefault(); });
    botones(b, function (d) { if (d === 'arriba') saltar(); else mover[d] = true; }, function (d) { if (d !== 'arriba') mover[d] = false; });
    function teclado(ev) {
      if (!teclaLibre()) return;
      var abajo = ev.type === 'keydown';
      if (ev.key === 'ArrowLeft' || ev.key === 'a') mover.izquierda = abajo;
      else if (ev.key === 'ArrowRight' || ev.key === 'd') mover.derecha = abajo;
      else if (abajo && (ev.key === 'ArrowUp' || ev.key === 'w' || ev.key === ' ')) saltar();
      else return;
      ev.preventDefault();
    }
    b.escuchar(document, 'keydown', teclado);
    b.escuchar(document, 'keyup', teclado);

    b.correr(function (dt) {
      var t = tam(), eligio = b.yaEligio();
      aturdido = Math.max(0, aturdido - dt); avisoCoco = Math.max(0, avisoCoco - dt); festejo = Math.max(0, festejo - dt);
      var vx = 0;
      if (!eligio && aturdido <= 0) {
        if (mover.izquierda) vx -= b.W * 0.5;
        if (mover.derecha) vx += b.W * 0.5;
      }
      if (vx) jugador.mira = vx > 0 ? 1 : -1;
      jugador.x = Math.max(t / 2, Math.min(largo - t / 2, jugador.x + vx * dt));
      var antes = jugador.y;
      jugador.vy += g() * dt;
      jugador.y += jugador.vy * dt;
      jugador.enSuelo = false;
      bloques.forEach(function (bl) {
        var encima = jugador.x > bl.x - t * 0.3 && jugador.x < bl.x + bl.w + t * 0.3;
        // Golpe desde abajo: la cabeza toca la base del bloque.
        if (encima && jugador.vy < 0 && antes - t >= bl.y + bl.h - 2 && jugador.y - t < bl.y + bl.h) {
          jugador.y = bl.y + bl.h + t; jugador.vy = 60;
          bl.salto = 1;
          if (!eligio) {
            b.explotar(bl.x + bl.w / 2 - camara, bl.y, COLORES[bl.op.i % COLORES.length], 30);
            sonar(660, 0.2, 'square', 0.08, 1.5);
            festejo = 1;
            b.elegir(bl.op);
          }
        }
        // Aterrizar encima del bloque: sirve de plataforma.
        if (encima && jugador.vy > 0 && antes <= bl.y + 1 && jugador.y >= bl.y) { jugador.y = bl.y; jugador.vy = 0; jugador.enSuelo = true; }
        bl.salto = Math.max(0, bl.salto - dt * 4);
      });
      if (jugador.y >= suelo()) { jugador.y = suelo(); jugador.vy = 0; jugador.enSuelo = true; }
      camara += (Math.max(0, Math.min(largo - b.W, jugador.x - b.W * 0.4)) - camara) * Math.min(1, dt * 6);

      // Cocos que ruedan desde la derecha.
      proximoCoco -= dt;
      if (!eligio && proximoCoco <= 0) {
        cocos.push({ x: camara + b.W + 20, r: Math.max(11, t * 0.38), giro: 0 });
        proximoCoco = (b.reducido ? 5 : 3.4) - Math.min(1.2, b.ronda * 0.15);
      }
      cocos.forEach(function (c) {
        c.x -= b.W * (b.reducido ? 0.18 : 0.26) * dt; c.giro -= dt * 8;
        var dx = Math.abs(c.x - jugador.x), alto = suelo() - jugador.y;
        if (!eligio && aturdido <= 0 && dx < c.r + t * 0.3 && alto < c.r * 1.6) {
          aturdido = 0.9; avisoCoco = 1; jugador.vy = -Math.sqrt(2 * g() * b.H * 0.1); jugador.x = Math.max(t, jugador.x - b.W * 0.12);
          sonar(150, 0.25, 'sawtooth', 0.06, 0.6);
        }
      });
      cocos = cocos.filter(function (c) { return c.x > camara - 40; });

      // Dibujo
      b.cielo('#7CC6FF', '#D3EEFF');
      b.nubesFondo(dt, 0);
      b.colinas(camara * 0.3, b.H * 0.22, '#8FD9B6', 170);
      ctx.fillStyle = '#A0703F'; ctx.fillRect(0, suelo(), b.W, b.H - suelo());
      ctx.fillStyle = '#1FA98A'; ctx.fillRect(0, suelo(), b.W, 10);
      ctx.fillStyle = 'rgba(255,255,255,.15)';
      for (var k = -((camara * 1) % 40); k < b.W; k += 40) ctx.fillRect(k, suelo() + 18, 18, 6);
      bloques.forEach(function (bl) {
        var x = bl.x - camara, y = bl.y - Math.sin(bl.salto * Math.PI) * 10, color = COLORES[bl.op.i % COLORES.length];
        if (x > b.W + 20 || x + bl.w < -20) return;
        ctx.fillStyle = color; caja(ctx, x, y, bl.w, bl.h, 12); ctx.fill();
        ctx.fillStyle = 'rgba(0,0,0,.18)'; caja(ctx, x, y + bl.h - 7, bl.w, 7, 6); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,.94)'; caja(ctx, x + 5, y + 5, bl.w - 10, bl.h - 14, 9); ctx.fill();
        b.letra(bl.op, x + 18, y + bl.h / 2 - 3, 10);
        b.escribir(bl.texto, x + 33, y + bl.h / 2 - 3, '#2A2140');
      });
      cocos.forEach(function (c) {
        ctx.save(); ctx.translate(c.x - camara, suelo() - c.r); ctx.rotate(c.giro);
        ctx.fillStyle = '#7A5233'; ctx.beginPath(); ctx.arc(0, 0, c.r, 0, 7); ctx.fill();
        ctx.fillStyle = '#5A3A22'; ctx.beginPath(); ctx.arc(c.r * 0.3, -c.r * 0.2, c.r * 0.18, 0, 7); ctx.fill();
        ctx.beginPath(); ctx.arc(-c.r * 0.25, -c.r * 0.3, c.r * 0.14, 0, 7); ctx.fill();
        ctx.restore();
      });
      var jx = jugador.x - camara;
      ctx.fillStyle = 'rgba(42,33,64,.18)'; ctx.beginPath(); ctx.ellipse(jx, suelo() + 2, t * 0.4, 5, 0, 0, 7); ctx.fill();
      if (!(aturdido > 0 && Math.floor(b.tiempo * 12) % 2)) {
        ctx.save(); ctx.translate(jx, jugador.y - t / 2); ctx.scale(jugador.mira, 1);
        if (aturdido > 0) ctx.rotate(Math.sin(b.tiempo * 30) * 0.3);
        emoji(b, piloto, 0, 0, t);
        ctx.restore();
      }
      b.dibujarChispas(dt);
      if (avisoCoco > 0) aviso(b, '¡Cuidado con los cocos! Sáltalos', '#3F4A60', b.W / 2, b.H * 0.12, avisoCoco);
      else if (b.tiempo < 4 && !eligio) aviso(b, '◀ ▶ para correr · toca o ▲ para saltar', '#5B3DF5', b.W / 2, b.H * 0.12, Math.min(1, 4 - b.tiempo));
    });
  }

  /* =================================================================
     ⛏️ Mina de bloques
     Bajo el césped hay bloques; abajo del todo, gemas con las respuestas.
     Se pica hacia donde se toca (o con las flechas) hasta llegar a la gema
     correcta. Algunas rocas son más duras. La leyenda dice qué es cada gema.
     ================================================================= */
  function mina(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 0.95;
    b.medir();
    var C = 9, F = 7;                       // columnas, filas (la 0 es el césped)
    var celdas = [], gemas = [], jugador = { c: 4, f: 0, y: 0 }, golpes = [], festejo = 0;

    (function armar() {
      var cols = barajar([0, 2, 4, 6, 8, 1, 3, 5, 7]).slice(0, b.opciones.length);
      for (var f = 0; f < F; f++) {
        celdas.push([]);
        for (var c = 0; c < C; c++) {
          var duro = f > 1 && Math.random() < 0.22;
          celdas[f].push(f === 0 ? null : { vida: duro ? 3 : 1, max: duro ? 3 : 1, duro: duro });
        }
      }
      barajar(b.opciones).forEach(function (o, i) {
        gemas.push({ op: o, c: cols[i], f: F - 1 });
        celdas[F - 1][cols[i]] = { gema: true, op: o };
      });
      jugador.c = Math.floor(C / 2);
    })();

    function geo() {
      var t = Math.min(b.W / C, b.H / (F + 0.6));
      return { t: t, ox: (b.W - t * C) / 2, oy: b.H - t * F - 4 };
    }
    function solido(c, f) { return f >= 0 && f < F && c >= 0 && c < C && celdas[f][c] !== null; }

    function actuar(dc, df) {
      if (b.yaEligio()) return;
      var c = jugador.c + dc, f = jugador.f + df;
      if (c < 0 || c >= C || f < 0 || f >= F) return;
      var celda = celdas[f][c];
      if (celda && celda.gema) {
        var g = geo();
        b.explotar(g.ox + (c + 0.5) * g.t, g.oy + (f + 0.5) * g.t, COLORES[celda.op.i % COLORES.length], 36);
        sonar(784, 0.25, 'sine', 0.14, 1.4);
        festejo = 1;
        b.elegir(celda.op);
        return;
      }
      if (celda) {
        celda.vida--;
        var g2 = geo();
        golpes.push({ x: g2.ox + (c + 0.5) * g2.t, y: g2.oy + (f + 0.5) * g2.t, t: 0 });
        sonar(celda.vida > 0 ? 200 : 260, 0.08, 'square', 0.05, 0.7);
        if (celda.vida <= 0) {
          celdas[f][c] = null;
          b.explotar(g2.ox + (c + 0.5) * g2.t, g2.oy + (f + 0.5) * g2.t, celda.duro ? '#8D93A6' : '#C8A27A', 10);
        }
        return;
      }
      // Moverse a un hueco (subir solo si hay apoyo para no flotar).
      if (df < 0 && !solido(jugador.c, jugador.f + 1) && jugador.f + 1 < F) return;
      jugador.c = c; jugador.f = f;
    }

    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      var p = b.punto(ev), g = geo();
      var dx = p.x - (g.ox + (jugador.c + 0.5) * g.t), dy = p.y - (g.oy + (jugador.f + 0.5) * g.t);
      if (Math.abs(dx) < g.t * 0.4 && Math.abs(dy) < g.t * 0.4) return;
      if (Math.abs(dx) > Math.abs(dy)) actuar(dx > 0 ? 1 : -1, 0); else actuar(0, dy > 0 ? 1 : -1);
      if (ev.cancelable) ev.preventDefault();
    });
    botones(b, function (d) { actuar({ izquierda: -1, derecha: 1 }[d] || 0, { arriba: -1, abajo: 1 }[d] || 0); });
    b.escuchar(document, 'keydown', function (ev) {
      if (!teclaLibre()) return;
      var m = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[ev.key];
      if (!m) return;
      ev.preventDefault(); actuar(m[0], m[1]);
    });
    b.marco.ludiaMina = { celdas: celdas, gemas: gemas, jugador: jugador };

    b.correr(function (dt) {
      var g = geo();
      festejo = Math.max(0, festejo - dt);
      // Gravedad por casillas: si debajo hay hueco, cae.
      if (!b.yaEligio() && jugador.f + 1 < F && !solido(jugador.c, jugador.f + 1)) {
        jugador.y += dt * 8;
        if (jugador.y >= 1) { jugador.y = 0; jugador.f++; }
      } else jugador.y = 0;

      ctx.fillStyle = '#9FD8FF'; ctx.fillRect(0, 0, b.W, g.oy + g.t);
      ctx.fillStyle = '#6B4A2F'; ctx.fillRect(0, g.oy + g.t, b.W, b.H);
      for (var f = 0; f < F; f++) {
        for (var c = 0; c < C; c++) {
          var x = g.ox + c * g.t, y = g.oy + f * g.t, celda = celdas[f][c];
          if (f === 0) { ctx.fillStyle = '#1FA98A'; ctx.fillRect(x, y + g.t * 0.82, g.t, g.t * 0.18); continue; }
          if (!celda) { ctx.fillStyle = '#4A3322'; ctx.fillRect(x, y, g.t, g.t); continue; }
          if (celda.gema) {
            ctx.fillStyle = '#4A3322'; ctx.fillRect(x, y, g.t, g.t);
            var brillo = 1 + Math.sin(b.tiempo * 4 + c) * 0.06;
            ctx.save(); ctx.translate(x + g.t / 2, y + g.t / 2); ctx.scale(brillo, brillo);
            ctx.fillStyle = COLORES[celda.op.i % COLORES.length];
            ctx.beginPath(); ctx.moveTo(0, -g.t * 0.4); ctx.lineTo(g.t * 0.36, -g.t * 0.08); ctx.lineTo(0, g.t * 0.4); ctx.lineTo(-g.t * 0.36, -g.t * 0.08); ctx.closePath(); ctx.fill();
            ctx.fillStyle = 'rgba(255,255,255,.45)'; ctx.beginPath(); ctx.moveTo(0, -g.t * 0.4); ctx.lineTo(g.t * 0.12, -g.t * 0.08); ctx.lineTo(-g.t * 0.2, -g.t * 0.08); ctx.closePath(); ctx.fill();
            ctx.restore();
            ctx.fillStyle = '#fff'; ctx.font = '800 ' + Math.round(g.t * 0.36) + 'px Fredoka, system-ui, sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(LETRAS[celda.op.i], x + g.t / 2, y + g.t / 2 + 1);
            continue;
          }
          var base = celda.duro ? '#8D93A6' : (f === 1 ? '#C8A27A' : '#B88F66');
          ctx.fillStyle = base; caja(ctx, x + 1, y + 1, g.t - 2, g.t - 2, g.t * 0.18); ctx.fill();
          ctx.fillStyle = 'rgba(255,255,255,.18)'; ctx.fillRect(x + g.t * 0.18, y + g.t * 0.16, g.t * 0.5, g.t * 0.12);
          if (celda.vida < celda.max) {
            ctx.strokeStyle = 'rgba(42,33,64,.55)'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(x + g.t * 0.3, y + g.t * 0.2); ctx.lineTo(x + g.t * 0.5, y + g.t * 0.5); ctx.lineTo(x + g.t * 0.35, y + g.t * 0.8); ctx.stroke();
          }
        }
      }
      golpes.forEach(function (h) { h.t += dt; });
      golpes = golpes.filter(function (h) { return h.t < 0.3; });
      golpes.forEach(function (h) { emoji(b, '💥', h.x, h.y, g.t * (0.5 + h.t)); });
      var jx = g.ox + (jugador.c + 0.5) * g.t, jy = g.oy + (jugador.f + 0.5 + jugador.y) * g.t;
      emoji(b, piloto, jx, jy - (festejo ? Math.abs(Math.sin(b.tiempo * 14)) * g.t * 0.4 : 0), g.t * 0.9);
      emoji(b, '⛏️', jx + g.t * 0.38, jy - g.t * 0.25 + Math.sin(b.tiempo * 10) * 2, g.t * 0.5);
      b.dibujarChispas(dt);
      if (b.tiempo < 5 && !b.yaEligio()) aviso(b, '👆 Toca un bloque junto a ti para picarlo', '#5B3DF5', b.W / 2, g.oy - 4 + g.t * 0.4, Math.min(1, 5 - b.tiempo));
    });
  }

  /* =================================================================
     🧗 Carrera de obstáculos
     El personaje corre solo. Las respuestas son ARCOS en el camino:
     atravesar uno es elegirlo; saltarlo es esquivarlo. Entre arco y arco
     hay charcos y vallas que también hay que saltar. Si se moja o tropieza,
     pierde un momento. Se salta tocando la pantalla, con ▲ o Espacio.
     ================================================================= */
  function obstaculos(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 0.75;
    b.medir();
    var suelo = function () { return b.H * 0.8; };
    var tam = function () { return Math.max(28, Math.min(40, b.H * 0.12)); };
    var g = function () { return b.H * 3; };
    var jugador = { y: 0, vy: 0, enSuelo: true }, pista = [], recorrido = 0, cola = [], aturdido = 0, avisoTxt = '', avisoT = 0, vueltas = 0;
    var xJugador = function () { return b.W * 0.2; };
    var velocidad = function () { return b.W * (b.reducido ? 0.2 : 0.27) * Math.min(1.5, 1 + vueltas * 0.08 + b.ronda * 0.05) * (aturdido > 0 ? 0.4 : 1); };

    function siguienteArco() {
      if (!cola.length) { cola = barajar(b.opciones); vueltas++; }
      return cola.shift();
    }
    /** Añade al final de la pista un obstáculo y luego un arco. */
    function extender() {
      var fin = pista.length ? pista[pista.length - 1].x + pista[pista.length - 1].w : b.W * 0.9;
      var separa = Math.max(b.W * 0.42, 170);
      var tipo = Math.random() < 0.5 ? 'charco' : 'valla';
      pista.push({ tipo: tipo, x: fin + separa * 0.5, w: tipo === 'charco' ? Math.max(46, b.W * 0.13) : 18, h: tam() * 0.7 });
      var op = siguienteArco();
      var ancho = Math.min(150, b.W * 0.4), alto = b.H * 0.23;
      pista.push({ tipo: 'arco', op: op, x: fin + separa * 1.2, w: 24, h: alto, cartel: ancho,
        texto: b.ajustar(op.texto, ancho - 40, 38, 2, 14) });
    }
    while (pista.length < 6) extender();

    function saltar() {
      if (!jugador.enSuelo || b.yaEligio()) return;
      jugador.vy = -Math.sqrt(2 * g() * b.H * 0.34);
      jugador.enSuelo = false;
      sonar(420, 0.1, 'square', 0.04, 1.8);
    }
    b.escuchar(b.lienzo, 'pointerdown', function (ev) { saltar(); if (ev.cancelable) ev.preventDefault(); });
    botones(b, function (d) { if (d === 'arriba') saltar(); });
    b.escuchar(document, 'keydown', function (ev) {
      if (!teclaLibre()) return;
      if (ev.key === ' ' || ev.key === 'ArrowUp' || ev.key === 'w') { ev.preventDefault(); saltar(); }
    });

    function avisar(txt) { avisoTxt = txt; avisoT = 1.1; }

    b.correr(function (dt) {
      var t = tam(), eligio = b.yaEligio(), v = velocidad(), xj = xJugador();
      aturdido = Math.max(0, aturdido - dt); avisoT = Math.max(0, avisoT - dt);
      if (!eligio) recorrido += v * dt;
      jugador.vy += g() * dt; jugador.y += jugador.vy * dt;
      if (jugador.y >= 0) { jugador.y = 0; jugador.vy = 0; jugador.enSuelo = true; }

      pista.forEach(function (o) {
        if (!eligio) o.x -= v * dt;
        if (o.hecho || eligio) return;
        // Arcos y vallas cuentan al cruzar su centro; los charcos, mientras
        // se está encima. Con toda la zona, saltar un arco rozaba su borde.
        var centro = o.x + o.w / 2;
        var tocando = o.tipo === 'charco' ? (xj > o.x + 4 && xj < o.x + o.w - 4) : Math.abs(xj - centro) < 8;
        if (!tocando) return;
        var altura = -jugador.y;                 // cuánto se elevó
        if (o.tipo === 'arco' && altura < o.h * 0.75) {
          // Lo atraviesa: elige esta respuesta.
          o.hecho = true;
          b.explotar(o.x, suelo() - o.h / 2, COLORES[o.op.i % COLORES.length], 34);
          sonar(660, 0.22, 'sine', 0.14, 1.5);
          b.elegir(o.op);
        } else if (o.tipo === 'charco' && altura < 4) {
          o.hecho = true; aturdido = 0.9; avisar('¡Splash! Salta los charcos');
          sonar(180, 0.3, 'sine', 0.06, 0.5);
        } else if (o.tipo === 'valla' && altura < o.h * 0.9) {
          o.hecho = true; aturdido = 0.9; avisar('¡Uy! Salta las vallas');
          jugador.vy = -Math.sqrt(2 * g() * b.H * 0.06); jugador.enSuelo = false;
          sonar(150, 0.25, 'sawtooth', 0.05, 0.6);
        } else if (o.tipo === 'arco' && !o.saltado) {
          o.saltado = true;   // lo esquivó por encima
        }
      });
      pista = pista.filter(function (o) { return o.x + Math.max(o.w, o.cartel || 0) > -40; });
      while (pista.length < 6) extender();

      b.cielo('#7CC6FF', '#D3EEFF');
      b.nubesFondo(dt, eligio ? 0 : v * 0.2);
      b.colinas(recorrido * 0.3, b.H * 0.25, '#8FD9B6', 160);
      ctx.fillStyle = '#F1DFBB'; ctx.fillRect(0, suelo(), b.W, b.H - suelo());
      ctx.fillStyle = '#1FA98A'; ctx.fillRect(0, suelo(), b.W, 8);
      ctx.fillStyle = 'rgba(160,112,63,.25)';
      for (var k = -(recorrido % 36); k < b.W; k += 36) ctx.fillRect(k, suelo() + 20, 16, 5);
      pista.forEach(function (o) {
        if (o.x > b.W + 160) return;
        if (o.tipo === 'charco') {
          ctx.fillStyle = '#5BB8FF'; ctx.beginPath(); ctx.ellipse(o.x + o.w / 2, suelo() + 6, o.w / 2, 9, 0, 0, 7); ctx.fill();
          ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.fillRect(o.x + o.w * 0.3, suelo() + 3, o.w * 0.2, 3);
        } else if (o.tipo === 'valla') {
          ctx.fillStyle = '#E8590C'; ctx.fillRect(o.x, suelo() - o.h, 5, o.h); ctx.fillRect(o.x + o.w - 5, suelo() - o.h, 5, o.h);
          ctx.fillStyle = '#FFFFFF'; ctx.fillRect(o.x - 4, suelo() - o.h, o.w + 8, 7);
          ctx.fillStyle = '#E8590C'; ctx.fillRect(o.x + 2, suelo() - o.h, 6, 7); ctx.fillRect(o.x + o.w - 8, suelo() - o.h, 6, 7);
        } else {
          var color = COLORES[o.op.i % COLORES.length], cx = o.x + o.w / 2;
          ctx.strokeStyle = color; ctx.lineWidth = 8; ctx.lineCap = 'round';
          ctx.beginPath(); ctx.moveTo(cx - 22, suelo()); ctx.lineTo(cx - 22, suelo() - o.h + 20);
          ctx.arc(cx, suelo() - o.h + 20, 22, Math.PI, 0); ctx.lineTo(cx + 22, suelo()); ctx.stroke();
          var cy = suelo() - o.h - 34, x0 = cx - o.cartel / 2;
          ctx.fillStyle = o.saltado ? 'rgba(255,255,255,.55)' : '#FFFFFF';
          caja(ctx, x0, cy - 22, o.cartel, 44, 10); ctx.fill();
          ctx.strokeStyle = color; ctx.lineWidth = 3; caja(ctx, x0 + 1.5, cy - 20.5, o.cartel - 3, 41, 9); ctx.stroke();
          b.letra(o.op, x0 + 16, cy, 10);
          b.escribir(o.texto, x0 + 31, cy, '#2A2140');
        }
      });
      var jy = suelo() + jugador.y - t / 2;
      ctx.fillStyle = 'rgba(42,33,64,.18)'; ctx.beginPath(); ctx.ellipse(xj, suelo() + 2, t * 0.4, 5, 0, 0, 7); ctx.fill();
      ctx.save(); ctx.translate(xj, jy);
      ctx.rotate(aturdido > 0 ? Math.sin(b.tiempo * 30) * 0.35 : (jugador.enSuelo ? Math.sin(b.tiempo * 18) * 0.08 : -0.15));
      emoji(b, piloto, 0, 0, t);
      ctx.restore();
      b.dibujarChispas(dt);
      if (avisoT > 0) aviso(b, avisoTxt, '#3F4A60', b.W / 2, b.H * 0.1, avisoT);
      else if (b.tiempo < 4 && !eligio) aviso(b, 'Cruza el arco correcto · salta los demás', '#5B3DF5', b.W / 2, b.H * 0.1, Math.min(1, 4 - b.tiempo));
    });
  }

  /* =================================================================
     🚀 Nave espacial
     Asteroides con las respuestas bajan intercalados. La nave se mueve
     (arrastrando, con ◀ ▶ o las flechas) y dispara (tocando, 🔥 o
     Espacio): el primer asteroide con respuesta que alcanza el láser es
     la elegida. Los meteoritos pequeños no cuentan: hay que esquivarlos o
     destruirlos; si chocan, la nave queda aturdida un momento.
     ================================================================= */
  function nave(b, piloto) {
    var ctx = b.ctx;
    b.proporcion = 1.05;
    b.medir();
    var estrellas = [], rocas = [], meteoros = [], laseres = [], tanda = 0, aturdido = 0, avisoT = 0, recarga = 0;
    var nave = { x: b.W / 2, objetivo: b.W / 2 }, mover = { izquierda: false, derecha: false };
    for (var k = 0; k < 50; k++) estrellas.push({ x: Math.random() * b.W, y: Math.random() * b.H, v: 0.3 + Math.random(), r: Math.random() * 1.6 + 0.4 });
    var yNave = function () { return b.H * 0.88; };
    var caida = function () { return b.H * (b.reducido ? 0.06 : 0.085) * Math.min(1.6, 1 + tanda * 0.1 + b.ronda * 0.05); };

    b.alNuevaPregunta = function () {
      rocas.forEach(function (r) { b.explotar(r.x, r.y, '#9AA3B5', 6); });
      rocas = [];
    };

    function soltarTanda() {
      var n = b.opciones.length, r = Math.min(b.W * 0.17, 58);
      var carriles = barajar(b.opciones.map(function (_, i) { return i; }));
      barajar(b.opciones).forEach(function (o, i) {
        var x = r + 8 + (b.W - 2 * r - 16) * (n === 1 ? 0.5 : carriles[i] / (n - 1));
        rocas.push({ op: o, x: x, y: -r - i * r * 2.6, r: r, giro: Math.random() * 6, texto: b.ajustar(o.texto, r * 1.4, r * 0.8, 2, 13) });
      });
      tanda++;
    }

    function disparar() {
      if (aturdido > 0 || recarga > 0 || b.yaEligio()) return;
      laseres.push({ x: nave.x, y: yNave() - 24 });
      recarga = 0.35;
      sonar(880, 0.08, 'square', 0.04, 0.5);
    }
    var arrastre = null;
    b.escuchar(b.lienzo, 'pointerdown', function (ev) {
      if (b.lienzo.setPointerCapture) { try { b.lienzo.setPointerCapture(ev.pointerId); } catch (e) {} }
      arrastre = { x: b.punto(ev).x, movio: false };
      if (ev.cancelable) ev.preventDefault();
    });
    b.escuchar(b.lienzo, 'pointermove', function (ev) {
      if (!arrastre) return;
      var p = b.punto(ev);
      if (Math.abs(p.x - arrastre.x) > 8) arrastre.movio = true;
      if (arrastre.movio && aturdido <= 0) nave.objetivo = Math.max(24, Math.min(b.W - 24, p.x));
    });
    b.escuchar(b.lienzo, 'pointerup', function () {
      // Un toque sin arrastrar dispara; arrastrar solo mueve.
      if (arrastre && !arrastre.movio) disparar();
      arrastre = null;
    });
    botones(b, function (d) { if (d === 'arriba') disparar(); else mover[d] = true; }, function (d) { if (d !== 'arriba') mover[d] = false; });
    function teclado(ev) {
      if (!teclaLibre()) return;
      var abajo = ev.type === 'keydown';
      if (ev.key === 'ArrowLeft' || ev.key === 'a') mover.izquierda = abajo;
      else if (ev.key === 'ArrowRight' || ev.key === 'd') mover.derecha = abajo;
      else if (abajo && (ev.key === ' ' || ev.key === 'ArrowUp')) disparar();
      else return;
      ev.preventDefault();
    }
    b.escuchar(document, 'keydown', teclado);
    b.escuchar(document, 'keyup', teclado);
    b.marco.ludiaNave = { nave: nave, rocas: function () { return rocas; } };

    function dibujarNave(x, y) {
      ctx.save(); ctx.translate(x, y);
      if (aturdido > 0) ctx.rotate(Math.sin(b.tiempo * 30) * 0.25);
      ctx.fillStyle = '#FFB020'; ctx.beginPath(); ctx.moveTo(-9, 22); ctx.lineTo(0, 34 + Math.random() * 10); ctx.lineTo(9, 22); ctx.fill();
      ctx.fillStyle = '#5B3DF5';
      ctx.beginPath(); ctx.moveTo(-26, 22); ctx.lineTo(-12, 2); ctx.lineTo(-12, 22); ctx.fill();
      ctx.beginPath(); ctx.moveTo(26, 22); ctx.lineTo(12, 2); ctx.lineTo(12, 22); ctx.fill();
      ctx.fillStyle = '#FF6A4D'; caja(ctx, -13, -24, 26, 48, 13); ctx.fill();
      ctx.fillStyle = '#DFF3FF'; ctx.beginPath(); ctx.arc(0, -6, 10, 0, 7); ctx.fill();
      emoji(b, piloto, 0, -5, 14);
      ctx.restore();
    }

    b.correr(function (dt) {
      var eligio = b.yaEligio(), yn = yNave();
      aturdido = Math.max(0, aturdido - dt); avisoT = Math.max(0, avisoT - dt); recarga = Math.max(0, recarga - dt);
      if (!eligio && aturdido <= 0) {
        if (mover.izquierda) nave.objetivo = Math.max(24, nave.objetivo - b.W * 0.9 * dt);
        if (mover.derecha) nave.objetivo = Math.min(b.W - 24, nave.objetivo + b.W * 0.9 * dt);
      }
      nave.x += (nave.objetivo - nave.x) * Math.min(1, dt * 10);
      if (!eligio && !rocas.some(function (r) { return r.y < b.H * 0.45; })) soltarTanda();
      if (!eligio && Math.random() < dt * (0.45 + tanda * 0.05)) meteoros.push({ x: 12 + Math.random() * (b.W - 24), y: -12, r: 7 + Math.random() * 5 });

      var v = caida();
      rocas.forEach(function (r) { r.y += v * dt; r.giro += dt * 0.6; });
      meteoros.forEach(function (m) { m.y += v * 2.2 * dt; });
      laseres.forEach(function (l) { l.y -= b.H * 1.6 * dt; });
      laseres.forEach(function (l) {
        if (l.fuera || eligio) return;
        rocas.forEach(function (r) {
          if (l.fuera || eligio) return;
          if (Math.abs(l.x - r.x) < r.r * 0.9 && Math.abs(l.y - r.y) < r.r) {
            l.fuera = true;
            b.explotar(r.x, r.y, COLORES[r.op.i % COLORES.length], 36);
            sonar(523, 0.25, 'sine', 0.14, 1.5);
            r.fuera = true;
            b.elegir(r.op);
          }
        });
        meteoros.forEach(function (m) {
          if (!l.fuera && Math.abs(l.x - m.x) < m.r + 4 && Math.abs(l.y - m.y) < m.r + 6) {
            l.fuera = true; m.fuera = true;
            b.explotar(m.x, m.y, '#9AA3B5', 8);
            sonar(300, 0.08, 'triangle', 0.04, 0.5);
          }
        });
      });
      meteoros.forEach(function (m) {
        if (m.fuera || eligio || aturdido > 0) return;
        if (Math.abs(m.x - nave.x) < m.r + 16 && Math.abs(m.y - yn) < m.r + 22) {
          m.fuera = true; aturdido = 1; avisoT = 1.1;
          b.explotar(nave.x, yn, '#FFD24D', 12);
          sonar(140, 0.35, 'sawtooth', 0.07, 0.5);
        }
      });
      rocas = rocas.filter(function (r) { return !r.fuera && r.y < b.H + r.r; });
      meteoros = meteoros.filter(function (m) { return !m.fuera && m.y < b.H + 20; });
      laseres = laseres.filter(function (l) { return !l.fuera && l.y > -20; });

      var fondo = ctx.createLinearGradient(0, 0, 0, b.H);
      fondo.addColorStop(0, '#1A1238'); fondo.addColorStop(1, '#3B2A7A');
      ctx.fillStyle = fondo; ctx.fillRect(0, 0, b.W, b.H);
      ctx.fillStyle = '#FF8FB1'; ctx.beginPath(); ctx.arc(b.W * 0.82, b.H * 0.2, b.W * 0.09, 0, 7); ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.5)'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.ellipse(b.W * 0.82, b.H * 0.2, b.W * 0.15, b.W * 0.035, -0.3, 0, 7); ctx.stroke();
      estrellas.forEach(function (s) {
        s.y += s.v * v * 0.5 * dt; if (s.y > b.H) { s.y = 0; s.x = Math.random() * b.W; }
        ctx.fillStyle = 'rgba(255,255,255,' + (0.5 + Math.sin(b.tiempo * 3 + s.x) * 0.3) + ')';
        ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 7); ctx.fill();
      });
      rocas.forEach(function (r) {
        ctx.save(); ctx.translate(r.x, r.y); ctx.rotate(Math.sin(r.giro) * 0.15);
        ctx.fillStyle = '#7D7393';
        ctx.beginPath();
        for (var a = 0; a < 10; a++) {
          var ang = a / 10 * Math.PI * 2, rr = r.r * (0.9 + ((a * 7) % 3) * 0.05);
          ctx[a ? 'lineTo' : 'moveTo'](Math.cos(ang) * rr, Math.sin(ang) * rr);
        }
        ctx.closePath(); ctx.fill();
        ctx.strokeStyle = COLORES[r.op.i % COLORES.length]; ctx.lineWidth = 4; ctx.stroke();
        ctx.restore();
        b.letra(r.op, r.x, r.y - r.r * 0.55, Math.min(11, r.r * 0.22));
        b.escribir(r.texto, r.x, r.y + r.r * 0.12, '#FFFFFF', 'center');
      });
      meteoros.forEach(function (m) {
        ctx.fillStyle = 'rgba(255,176,32,.35)'; ctx.beginPath(); ctx.moveTo(m.x - m.r * 0.6, m.y - m.r); ctx.lineTo(m.x, m.y - m.r * 3.5); ctx.lineTo(m.x + m.r * 0.6, m.y - m.r); ctx.fill();
        ctx.fillStyle = '#A88F7A'; ctx.beginPath(); ctx.arc(m.x, m.y, m.r, 0, 7); ctx.fill();
      });
      ctx.fillStyle = '#4FD8B8';
      laseres.forEach(function (l) { caja(ctx, l.x - 2.5, l.y - 14, 5, 18, 3); ctx.fill(); });
      if (!(aturdido > 0 && Math.floor(b.tiempo * 12) % 2)) dibujarNave(nave.x, yn);
      b.dibujarChispas(dt);
      if (avisoT > 0) aviso(b, '¡Meteorito! Esquívalos', '#FFFFFF', b.W / 2, b.H * 0.5, avisoT);
      else if (b.tiempo < 4 && !eligio) aviso(b, 'Arrastra para moverte · toca para disparar', '#5B3DF5', b.W / 2, b.H * 0.5, Math.min(1, 4 - b.tiempo));
    });
  }

  var JUEGOS = { avion: avion, globos: globos, atrapa: atrapa, laberinto: laberinto, tren: tren, catapulta: catapulta,
    salta: salta, mina: mina, obstaculos: obstaculos, nave: nave };

  /**
   * @param {string} tipo            'avion' | 'globos' | 'atrapa' | 'laberinto'
   * @param {HTMLElement} marco      contenedor con un <canvas>
   * @param {string[]} textos        las opciones, en su orden original
   * @param {function(number)} alElegir  recibe el índice ORIGINAL elegido
   * @param {string} [piloto]        emoji del personaje del estudiante
   */
  function montar(tipo, marco, textos, alElegir, piloto, extra) {
    if (!JUEGOS[tipo] || !marco) return null;
    var b = base(marco, textos, alElegir);
    // Número de pregunta dentro de la actividad: el ritmo sube de una a otra.
    b.ronda = Math.max(0, Number(extra && extra.ronda) || 0);
    b.continuo = !!(extra && extra.continuo);
    JUEGOS[tipo](b, piloto || '🦊');
    return {
      apagar: b.apagar,
      nueva: function (textos, ronda) { b.nueva(textos, ronda); },
      resultado: function (correcta, puntos) { b.resultado(correcta, puntos); },
      marco: marco
    };
  }

  return { montar: montar, juegos: Object.keys(JUEGOS) };
})();
