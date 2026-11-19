/* =====================================================================
   Ludia — herramientas de SORTEO para la clase: 🎡 Ruleta y 🎁 Cajas.

   No se califican: sirven para que salga una palabra, una pregunta o un
   estudiante al azar y la clase responda en voz alta. Cada elemento puede
   llevar una respuesta, que se descubre con un botón cuando el docente quiere.

   Las usa el proyector (sala.js) y, en un paquete enviado, el celular
   (jugar.js). En vivo el celular solo dice «Mira la pantalla».
   ===================================================================== */
window.LudiaSorteo = (function () {
  'use strict';

  var COLORES = ['#FF3B5C', '#3B6BFF', '#0EA371', '#F29A0D', '#8B5CF6', '#EC4899', '#0EA5E9', '#E8590C'];

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function barajar(lista) {
    var a = lista.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  var audio = null;
  function tic(f, d, v) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime;
      o.type = 'triangle'; o.frequency.setValueAtTime(f, t);
      g.gain.setValueAtTime(v || 0.05, t); g.gain.exponentialRampToValueAtTime(0.0001, t + d);
      o.connect(g); g.connect(audio.destination); o.start(t); o.stop(t + d);
    } catch (e) { /* sin sonido */ }
  }
  var reducido = !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);

  function limpiar(item) {
    return (item.elementos || []).map(function (e) {
      return { texto: String(e.texto || '').trim(), respuesta: String(e.respuesta || '').trim() };
    }).filter(function (e) { return e.texto !== ''; });
  }

  /** Lo que salió: el texto en grande y, si tiene, la respuesta escondida. */
  function resultado(caja, elemento, extra) {
    caja.innerHTML = '<p class="sorteo-salio">' + esc(elemento.texto) + '</p>' +
      (elemento.respuesta
        ? '<button type="button" class="btn btn-ghost btn-sm" data-ver-respuesta>👀 Ver respuesta</button>' +
          '<p class="sorteo-respuesta" hidden>' + esc(elemento.respuesta) + '</p>'
        : '') + (extra || '');
    if (window.LudiaAnim) window.LudiaAnim.marcar(caja, 'entra', 700);
    var ver = caja.querySelector('[data-ver-respuesta]');
    if (ver) ver.addEventListener('click', function () {
      caja.querySelector('.sorteo-respuesta').hidden = false;
      ver.hidden = true;
    });
  }

  /* ---------- 🎡 Ruleta ---------- */

  function ruleta(marco, item, grande) {
    var lista = limpiar(item);
    marco.innerHTML = '<div class="sorteo-ruleta' + (grande ? ' grande' : '') + '">' +
      '<div class="sorteo-rueda"><canvas aria-hidden="true"></canvas><span class="sorteo-flecha" aria-hidden="true"></span></div>' +
      '<div class="sorteo-lado">' +
      (item.instruccion ? '<p class="sorteo-instruccion">' + esc(item.instruccion) + '</p>' : '') +
      '<button type="button" class="btn btn-primary sorteo-girar" data-girar>🎡 Girar</button>' +
      '<div class="sorteo-resultado" aria-live="polite"></div></div></div>';
    var lienzo = marco.querySelector('canvas'), ctx = lienzo.getContext('2d');
    var boton = marco.querySelector('[data-girar]'), salida = marco.querySelector('.sorteo-resultado');
    var giro = 0, girando = false, lado = 0;

    function medir() {
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      lado = Math.min(grande ? 540 : 340, marco.querySelector('.sorteo-rueda').clientWidth || 300);
      lienzo.width = lado * dpr; lienzo.height = lado * dpr;
      lienzo.style.width = lado + 'px'; lienzo.style.height = lado + 'px';
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      dibujar();
    }

    function dibujar() {
      var n = lista.length, r = lado / 2 - 6, c = lado / 2, seg = Math.PI * 2 / Math.max(1, n);
      ctx.clearRect(0, 0, lado, lado);
      ctx.save(); ctx.shadowColor = 'rgba(42,33,64,.3)'; ctx.shadowBlur = 16;
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(c, c, r + 4, 0, 7); ctx.fill(); ctx.restore();
      for (var i = 0; i < n; i++) {
        var a0 = giro + i * seg;
        ctx.fillStyle = COLORES[i % COLORES.length];
        if (n % COLORES.length === 1 && i === n - 1) ctx.fillStyle = COLORES[3];
        ctx.beginPath(); ctx.moveTo(c, c); ctx.arc(c, c, r, a0, a0 + seg); ctx.closePath(); ctx.fill();
        // Texto a lo largo del gajo, recortado si no cabe.
        ctx.save();
        ctx.translate(c, c); ctx.rotate(a0 + seg / 2);
        var fuente = Math.max(10, Math.min(lado * 0.055, (seg * r * 0.55)));
        ctx.font = '800 ' + Math.round(fuente) + 'px Manrope, system-ui, sans-serif';
        ctx.fillStyle = '#fff'; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        var texto = lista[i].texto, maximo = r * 0.72;
        while (ctx.measureText(texto).width > maximo && texto.length > 2) texto = texto.slice(0, -2) + '…';
        ctx.fillText(texto, r - 14, 0);
        ctx.restore();
      }
      ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(c, c, r * 0.13, 0, 7); ctx.fill();
      ctx.fillStyle = '#2A2140'; ctx.beginPath(); ctx.arc(c, c, r * 0.06, 0, 7); ctx.fill();
    }

    /** El gajo bajo la flecha (arriba, a −90°). */
    function elegido() {
      var seg = Math.PI * 2 / lista.length;
      var a = ((-Math.PI / 2 - giro) % (Math.PI * 2) + Math.PI * 4) % (Math.PI * 2);
      return Math.floor(a / seg) % lista.length;
    }

    function girar() {
      if (girando || lista.length < 2) return;
      girando = true; boton.disabled = true; salida.innerHTML = '';
      var desde = giro, total = Math.PI * 2 * (reducido ? 2 : 5 + Math.random() * 2) + Math.random() * Math.PI * 2;
      var dura = reducido ? 1400 : 4200, t0 = null, ultimo = elegido();
      function paso(ahora) {
        if (!document.body.contains(lienzo)) return;
        if (t0 === null) t0 = ahora;
        var p = Math.min(1, (ahora - t0) / dura), e = 1 - Math.pow(1 - p, 3);
        giro = desde + total * e;
        dibujar();
        var ahoraEn = elegido();
        if (ahoraEn !== ultimo) { ultimo = ahoraEn; tic(900, 0.03, 0.04); }
        if (p < 1) { requestAnimationFrame(paso); return; }
        girando = false; boton.disabled = false;
        var gano = lista[elegido()];
        tic(660, 0.25, 0.12);
        marco.setAttribute('data-salio', gano.texto);
        resultado(salida, gano, lista.length > 2 ? '<button type="button" class="link-btn" data-quitar>Quitar de la ruleta</button>' : '');
        var quitar = salida.querySelector('[data-quitar]');
        if (quitar) quitar.addEventListener('click', function () {
          lista.splice(lista.indexOf(gano), 1);
          salida.innerHTML = '<p class="hint">Quitado. Quedan ' + lista.length + '.</p>';
          dibujar();
        });
      }
      requestAnimationFrame(paso);
    }

    boton.addEventListener('click', girar);
    lienzo.addEventListener('click', girar);
    window.addEventListener('resize', function alCambiar() {
      if (!document.body.contains(lienzo)) { window.removeEventListener('resize', alCambiar); return; }
      medir();
    });
    medir();
  }

  /* ---------- 🎁 Cajas sorpresa ---------- */

  function cajas(marco, item, grande) {
    var lista = barajar(limpiar(item));
    marco.innerHTML = '<div class="sorteo-cajas' + (grande ? ' grande' : '') + '">' +
      (item.instruccion ? '<p class="sorteo-instruccion">' + esc(item.instruccion) + '</p>' : '') +
      '<div class="sorteo-rejilla">' + lista.map(function (e, i) {
        return '<button type="button" class="sorteo-caja c-' + (i % COLORES.length) + '" data-caja="' + i + '" aria-label="Caja ' + (i + 1) + '">' +
          '<span class="sorteo-tapa" aria-hidden="true"></span><b>' + (i + 1) + '</b></button>';
      }).join('') + '</div>' +
      '<div class="sorteo-resultado" aria-live="polite"></div></div>';
    var salida = marco.querySelector('.sorteo-resultado');
    marco.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-caja]');
      if (!b || b.classList.contains('abierta')) return;
      b.classList.add('abierta');
      b.setAttribute('aria-label', 'Caja ' + (Number(b.getAttribute('data-caja')) + 1) + ', abierta');
      var e = lista[Number(b.getAttribute('data-caja'))];
      tic(520, 0.18, 0.1); setTimeout(function () { tic(780, 0.2, 0.1); }, 120);
      marco.setAttribute('data-salio', e.texto);
      resultado(salida, e);
    });
  }

  /**
   * @param {string} tipo  'ruleta' | 'cajas'
   * @param {HTMLElement} marco
   * @param {object} item  { instruccion, elementos: [{ texto, respuesta }] }
   * @param {boolean} [grande]  en el proyector
   */
  function montar(tipo, marco, item, grande) {
    if (!marco) return;
    if (tipo === 'ruleta') ruleta(marco, item, grande);
    else if (tipo === 'cajas') cajas(marco, item, grande);
  }

  return { montar: montar };
})();
