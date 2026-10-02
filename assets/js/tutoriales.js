/* =====================================================================
   Ludia — mini tutoriales animados.

   Se abren solos la primera vez que alguien entra a su cuenta y se pueden
   volver a ver desde el menú ("¿Cómo funciona?"). La decisión de no volver a
   mostrarlos se guarda en este navegador (localStorage), que es donde
   corresponde: es una preferencia de vista, no un dato de la cuenta.
   ===================================================================== */
(function () {
  'use strict';

  var caja = document.querySelector('[data-tutoriales]');
  if (!caja) return;

  var CLAVE = 'ludia.tutoriales.vistos';

  function yaLosVio() {
    try { return localStorage.getItem(CLAVE) === '1'; } catch (e) { return false; }
  }
  function recordarQueLosVio() {
    try { localStorage.setItem(CLAVE, '1'); } catch (e) { /* sin almacenamiento */ }
  }

  var ultimoFoco = null;

  function abrir() {
    ultimoFoco = document.activeElement;
    caja.hidden = false;
    document.body.style.overflow = 'hidden';
    var cerrar = caja.querySelector('[data-tuto-cerrar]');
    if (cerrar) cerrar.focus();
  }

  function cerrar() {
    caja.hidden = true;
    document.body.style.overflow = '';
    if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
  }

  /* ---------- Cambiar de tutorial ---------- */
  caja.addEventListener('click', function (evento) {
    var tab = evento.target.closest('[data-tuto-tab]');
    if (tab) {
      var cual = tab.getAttribute('data-tuto-tab');
      caja.querySelectorAll('[data-tuto-tab]').forEach(function (t) {
        var activo = t === tab;
        t.classList.toggle('activo', activo);
        t.setAttribute('aria-selected', String(activo));
      });
      caja.querySelectorAll('[data-tuto-video]').forEach(function (v) {
        v.hidden = v.getAttribute('data-tuto-video') !== cual;
      });
      return;
    }

    // Cerrar: con la ✕ o tocando fuera de la caja.
    if (evento.target.closest('[data-tuto-cerrar]') || evento.target === caja) {
      cerrar();
    }
  });

  caja.addEventListener('change', function (evento) {
    if (evento.target.closest('[data-tuto-nover]')) {
      if (evento.target.checked) recordarQueLosVio();
    }
  });

  document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape' && !caja.hidden) cerrar();
  });

  /* ---------- Abrir desde el menú ---------- */
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-abrir-tutoriales]');
    if (boton) {
      evento.preventDefault();
      abrir();
    }
  });

  // La primera visita los muestra solos.
  if (!yaLosVio()) {
    abrir();
    recordarQueLosVio();
  }
})();
