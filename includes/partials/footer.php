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
    </nav>
    <div class="footer-meta">
      <span>© <?= date('Y') ?> Ludia</span>
      <span class="status-badge">🛠️ Versión en desarrollo</span>
    </div>
  </div>
</footer>
</body>
</html>
