/* Ludia — comportamiento común del sitio (JS vanilla, sin build step). */
(function () {
  'use strict';

  // Menú móvil de la barra superior
  var topbar = document.querySelector('.topbar');
  var toggle = topbar && topbar.querySelector('.menu-toggle');
  if (!toggle) return;

  function setOpen(open) {
    topbar.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  }

  toggle.addEventListener('click', function () {
    setOpen(!topbar.classList.contains('is-open'));
  });

  topbar.querySelectorAll('.nav-links a').forEach(function (link) {
    link.addEventListener('click', function () { setOpen(false); });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && topbar.classList.contains('is-open')) {
      setOpen(false);
      toggle.focus();
    }
  });

  document.addEventListener('click', function (event) {
    if (!topbar.contains(event.target)) setOpen(false);
  });
})();

/* =====================================================================
   Animaciones (08/10/2026). Los estilos están en css/animaciones.css.
   Si el sistema pide menos movimiento, nada de esto se activa: el sitio se
   ve exactamente como antes.
   ===================================================================== */
(function () {
  'use strict';

  var quieto = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Utilidades para las pantallas de juego. `marcar` vuelve a disparar una
   * animación de CSS aunque la clase ya estuviera puesta.
   */
  window.LudiaAnim = {
    quieto: quieto,

    marcar: function (elemento, clase, ms) {
      if (!elemento || quieto) return;
      elemento.classList.remove(clase);
      void elemento.offsetWidth;   // fuerza a reiniciar la animación
      elemento.classList.add(clase);
      setTimeout(function () { elemento.classList.remove(clase); }, ms || 900);
    },

    /** Lluvia de confeti con los colores de la marca. Se borra sola. */
    confeti: function (cuantos) {
      if (quieto) return;
      var colores = ['#FF6A4D', '#5B3DF5', '#1FA98A', '#FFB020', '#9C8CFF'];
      var capa = document.createDocumentFragment();
      var piezas = [];
      for (var i = 0; i < (cuantos || 80); i++) {
        var p = document.createElement('i');
        p.className = 'confeti';
        p.setAttribute('aria-hidden', 'true');
        p.style.left = (Math.random() * 100) + 'vw';
        p.style.background = colores[i % colores.length];
        p.style.setProperty('--dx', ((Math.random() - .5) * 30) + 'vw');
        p.style.setProperty('--giro', (360 + Math.random() * 720) * (Math.random() < .5 ? -1 : 1) + 'deg');
        p.style.setProperty('--dur', (2 + Math.random() * 1.8) + 's');
        p.style.animationDelay = (Math.random() * .6) + 's';
        capa.appendChild(p);
        piezas.push(p);
      }
      document.body.appendChild(capa);
      setTimeout(function () { piezas.forEach(function (p) { p.remove(); }); }, 4600);
    }
  };

  /* ---------- Aparecer al bajar, solo en la portada ---------- */
  if (quieto || !('IntersectionObserver' in window)) return;

  var bloques = document.querySelectorAll(
    'main > section:not(.hero) .section-head, main > section:not(.hero) article,' +
    'main > section:not(.hero) .step, main > section:not(.hero) .value-card, .cta-final > *'
  );
  if (!bloques.length) return;

  // El retraso escalonado se cuenta dentro de cada grupo de hermanos.
  bloques.forEach(function (b) {
    var hermanos = Array.prototype.filter.call(b.parentNode.children, function (h) { return h.tagName === b.tagName; });
    b.style.setProperty('--i', Math.min(hermanos.indexOf(b), 6));
    b.classList.add('revela');
  });

  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (e) {
      if (!e.isIntersecting) return;
      var b = e.target;
      b.classList.add('visible');
      observador.unobserve(b);
      setTimeout(function () { b.classList.remove('revela', 'visible'); }, 700 + Number(b.style.getPropertyValue('--i') || 0) * 80);
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: .1 });

  bloques.forEach(function (b) { observador.observe(b); });
})();
