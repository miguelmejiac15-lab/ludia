<?php
/**
 * Una tarjeta de «Mis paquetes»: sirve para paquetes y para juegos, que son
 * la misma fila con distinta clase. Espera $p, $guardaInformes y $puedeExportar.
 */
$esJuego = ($p['clase'] ?? 'paquete') === 'juego';
?>
          <?php
          $abierto     = (int) $p['abiertas'] > 0;
          $esEnviar    = $p['modo_abierto'] === 'enviar';
          $informes    = (int) $p['informes'];
          ?>
          <li class="panel sesion-item" data-paquete="<?= (int) $p['id'] ?>">
            <div class="sesion-datos">
              <span class="sesion-codigo sesion-codigo-paquete" aria-hidden="true"><?= $esJuego ? '🎲' : '📦' ?></span>
              <div>
                <b><?= e($p['nombre']) ?></b>
                <span class="sesion-meta">
                  <?php if ($esJuego): ?>
                    Juego · se juega como <?= e(JUEGO_PLANTILLAS[$p['plantilla']]['nombre'] ?? (string) $p['plantilla']) ?> ·
                  <?php else: ?>
                    <?= (int) $p['total_actividades'] ?> actividades ·
                  <?php endif; ?>
                  editado <?= e(date('d/m H:i', strtotime((string) $p['updated_at']))) ?>
                  <?php if ($informes > 0): ?>
                    · <a href="<?= e(base_url('app/informes.php')) ?>?p=<?= (int) $p['id'] ?>"><?= $informes ?> <?= $informes === 1 ? 'informe' : 'informes' ?></a>
                  <?php endif; ?>
                </span>
                <?php if ($abierto): ?>
                  <span class="sesion-nota">
                    Tiene una sesión abierta (<?= e($esEnviar ? 'enviada' : 'en vivo') ?>) ·
                    <a href="<?= e(base_url('app/sesion.php')) ?>?c=<?= e((string) $p['codigo_abierto']) ?>">ver la sala</a>
                  </span>
                <?php endif; ?>
                <?php if (!$guardaInformes): ?>
                  <span class="sesion-nota">En el plan gratis no queda registro de lo que hagan tus estudiantes.</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="sesion-acciones">
              <?php /* Editar SIEMPRE: cada sesión jugada guarda su propia copia
                       del contenido, así que cambiarlo ahora no toca ni un
                       informe de los ya emitidos. */ ?>
              <?php if ($esJuego): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/juego.php')) ?>?id=<?= (int) $p['id'] ?>" data-abrir-juego>🎲 Abrir y cambiar juego</a>
              <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/crear.php')) ?>?editar=<?= (int) $p['id'] ?>">✏️ Editar</a>
              <?php endif; ?>

              <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>">
                <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="accion" value="enviar">
                <button class="btn btn-ghost btn-sm" type="submit" data-enviar
                        title="Genera un enlace para que cada estudiante lo haga por su cuenta">📨 Enviar</button>
              </form>

              <?php if ($puedeExportar): ?>
                <span class="exportar-grupo">
                  <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/exportar.php')) ?>?p=<?= (int) $p['id'] ?>&amp;formato=scorm"
                     title="Paquete SCORM para subir a Moodle">📦 SCORM</a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/exportar.php')) ?>?p=<?= (int) $p['id'] ?>&amp;formato=html"
                     title="Un archivo HTML que funciona sin internet">💾 HTML</a>
                </span>
              <?php endif; ?>

              <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>">
                <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="accion" value="jugar">
                <button class="btn btn-primary btn-sm" type="submit" data-jugar>▶ Jugar en vivo</button>
              </form>

              <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>">
                <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="accion" value="duplicar">
                <button class="link-btn" type="submit" data-duplicar
                        title="Una copia con las mismas actividades">⧉ Duplicar</button>
              </form>

              <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>"
                    onsubmit="return confirm('¿Eliminar <?= $esJuego ? 'este juego' : 'este paquete' ?>? Sus informes se conservan, pero el contenido no se puede recuperar.');">
                <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="accion" value="eliminar">
                <button class="link-btn danger" type="submit">Eliminar</button>
              </form>
            </div>
          </li>
