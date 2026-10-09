<?php
/**
 * Pie común.
 *
 * Los enlaces legales van aquí y no en el menú de arriba a propósito: es donde
 * los busca quien quiere comprobar que detrás hay alguien, y arriba competirían
 * con lo que sí lleva a probar el producto.
 *
 * Solo se enlaza lo que existe. Un pie con «Términos» apuntando a una página en
 * blanco da menos confianza que un pie sin «Términos».
 */
?>
<footer class="site-footer">
  <div class="wrap">
    <nav class="footer-enlaces" aria-label="Enlaces del pie">
      <a href="<?= e(base_url('enfoque.php')) ?>">Cómo está pensada</a>
      <a href="<?= e(base_url('privacidad.php')) ?>">Privacidad</a>
      <?php if (!empty($hayMedicion)): ?>
        <a href="<?= e(base_url('privacidad.php')) ?>#cookies" data-cookies-preferencias>Cookies</a>
      <?php endif; ?>
    </nav>
    <div class="footer-meta">
      <span>© <?= date('Y') ?> Ludia</span>
      <span class="status-badge">🛠️ Versión en desarrollo</span>
    </div>
  </div>
</footer>

<?php if (!empty($hayMedicion)): ?>
<?php /* Aviso de cookies. Ni Google Analytics ni el píxel de Meta se cargan
         hasta que la persona pulse «Aceptar»; con «Rechazar» no se carga nada
         y se borran sus cookies. La elección se guarda en este navegador y se
         cambia desde «Cookies», en el pie. */ ?>
<div class="aviso-cookies" id="aviso-cookies" role="dialog" aria-live="polite" aria-label="Cookies" hidden>
  <p>
    Usamos cookies de <b>Google Analytics</b> para saber cuántas personas visitan Ludia, y de <b>Meta</b>
    para medir nuestros anuncios en Facebook e Instagram. Solo en estas páginas públicas: nunca dentro de las actividades.
    <a href="<?= e(base_url('privacidad.php')) ?>#cookies">Más información</a>
  </p>
  <div class="aviso-cookies-botones">
    <button class="btn btn-ghost btn-sm" type="button" data-cookies="no">Rechazar</button>
    <button class="btn btn-primary btn-sm" type="button" data-cookies="si">Aceptar</button>
  </div>
</div>
<script>
(function () {
  'use strict';
  var ID = <?= json_encode($gaId) ?>;
  var PIXEL = <?= json_encode($metaPixel) ?>;
  // «_v2» desde que se añadió Meta (09/10/2026): quien aceptó antes lo hizo
  // solo para Analytics, así que se le vuelve a preguntar.
  var CLAVE = 'ludia_cookies_v2';
  var aviso = document.getElementById('aviso-cookies');

  function leer() { try { return localStorage.getItem(CLAVE); } catch (e) { return null; } }
  function guardar(v) { try { localStorage.setItem(CLAVE, v); } catch (e) {} }

  function cargar() {
    if (window.__ludiaGa) return;
    window.__ludiaGa = true;
    if (PIXEL) cargarMeta();
    if (!ID) return;
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ID);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    // Sin señales de Google ni personalización de anuncios: solo contar visitas.
    window.gtag('config', ID, { allow_google_signals: false, allow_ad_personalization_signals: false });
  }

  // El código oficial de Meta, sin la parte <noscript>: esa dispara el píxel
  // sin JavaScript y por tanto sin haber pedido permiso.
  function cargarMeta() {
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', PIXEL);
    window.fbq('track', 'PageView');
  }

  // Al rechazar, fuera las cookies de Analytics (_ga, _ga_XXXX) y de Meta
  // (_fbp, _fbc) de este dominio.
  function borrarCookiesGa() {
    document.cookie.split(';').forEach(function (c) {
      var nombre = c.split('=')[0].trim();
      if (nombre.indexOf('_ga') !== 0 && nombre !== '_fbp' && nombre !== '_fbc') return;
      [location.hostname, '.' + location.hostname.replace(/^www\./, '')].forEach(function (d) {
        document.cookie = nombre + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=' + d;
      });
      document.cookie = nombre + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    });
  }

  var eleccion = leer();
  if (eleccion === 'si') cargar();
  else if (eleccion === null) aviso.hidden = false;

  aviso.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-cookies]');
    if (!boton) return;
    var acepta = boton.getAttribute('data-cookies') === 'si';
    guardar(acepta ? 'si' : 'no');
    aviso.hidden = true;
    if (acepta) cargar(); else borrarCookiesGa();
  });

  document.addEventListener('click', function (evento) {
    if (!evento.target.closest('[data-cookies-preferencias]')) return;
    evento.preventDefault();
    aviso.hidden = false;
  });
})();
</script>
<?php endif; ?>
</body>
</html>
