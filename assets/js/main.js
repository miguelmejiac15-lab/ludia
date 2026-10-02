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
