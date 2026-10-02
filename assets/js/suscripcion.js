/* =====================================================================
   Ludia — contratar un plan: pide el enlace de pago y lleva a Mercado Pago.
   El plan NO se activa aquí: eso ocurre cuando Mercado Pago confirma el cobro.
   ===================================================================== */
(function () {
  'use strict';

  var raiz = document.querySelector('[data-suscripcion]');
  if (!raiz) return;

  var error = document.getElementById('pago-error');

  function avisar(texto) {
    error.textContent = texto;
    error.hidden = !texto;
  }

  /* ---------- Mensual o anual ---------- */

  var periodo = 'mensual';

  raiz.addEventListener('click', function (evento) {
    var opcion = evento.target.closest('[data-periodo]');
    if (!opcion) return;

    periodo = opcion.getAttribute('data-periodo');

    [].forEach.call(raiz.querySelectorAll('[data-periodo]'), function (b) {
      var activo = b === opcion;
      b.classList.toggle('activo', activo);
      b.setAttribute('aria-checked', activo ? 'true' : 'false');
    });

    // Cada tarjeta muestra el precio del periodo elegido. Si un plan no se
    // vende por año no tiene bloque anual: se queda con el mensual y ya.
    [].forEach.call(raiz.querySelectorAll('[data-precio-mensual]'), function (caja) {
      caja.hidden = periodo === 'anual' && !!caja.parentNode.querySelector('[data-precio-anual]');
    });
    [].forEach.call(raiz.querySelectorAll('[data-precio-anual]'), function (caja) {
      caja.hidden = periodo !== 'anual';
    });

    // El botón dice qué se va a pagar. Con el anual mostrado como equivalente
    // mensual, un "Suscribirme" a secas deja creer que el cobro es mensual.
    [].forEach.call(raiz.querySelectorAll('[data-contratar]'), function (b) {
      // Solo se respeta el texto de "Es tu plan actual". Antes esto miraba
      // `b.disabled`, que significa DOS cosas —ese caso y "los pagos no están
      // configurados"—, así que el cartel se quedaba mudo sin motivo.
      if (b.hasAttribute('data-plan-actual')) return;
      var texto = b.getAttribute(periodo === 'anual' ? 'data-texto-anual' : 'data-texto-mensual');
      if (texto) b.textContent = texto;
    });
  });

  raiz.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-contratar]');
    if (!boton || boton.disabled) return;

    var plan = boton.getAttribute('data-contratar');
    var etiqueta = boton.textContent;

    boton.disabled = true;
    boton.textContent = 'Abriendo el pago…';
    avisar('');

    fetch(window.LudiaRutas.api('suscripcion-crear.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ plan: plan, periodo: periodo })
    })
      .then(function (r) { return r.json(); })
      .then(function (datos) {
        if (datos.ingresar) {
          location.href = datos.ingresar + '?volver=' + encodeURIComponent(location.pathname);
          return;
        }
        if (!datos.ok || !datos.enlace) throw new Error(datos.error || 'No pudimos abrir el pago.');
        location.href = datos.enlace;
      })
      .catch(function (e) {
        boton.disabled = false;
        boton.textContent = etiqueta;
        avisar('⚠️ ' + e.message);
      });
  });
})();
