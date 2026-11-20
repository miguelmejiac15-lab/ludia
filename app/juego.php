<?php
/**
 * Un JUEGO, en tres pasos como en el armado de siempre:
 *   1. Elegir plantilla         juego.php
 *   2. Escribir el contenido    juego.php?plantilla=memoria   ·   juego.php?id=5&editar=1
 *   3. Jugar                    juego.php?id=5
 *
 * En el paso 3 se cambia de plantilla con un clic: el contenido no se toca,
 * solo se vuelve a convertir (includes/Juegos.php). Jugar en vivo y enviar
 * usan los mismos botones que «Mis paquetes», porque un juego ES un paquete.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Juegos.php';

$usuario   = auth_requerir();
$usuarioId = (int) $usuario['id'];

$id = (int) ($_GET['id'] ?? 0);
$juego = null;
if ($id > 0) {
    $juego = paquete_de($id, $usuarioId);
    if (!$juego || !juego_es($juego)) {
        header('Location: ' . base_url('app/mis-sesiones.php'));
        exit;
    }
}

// Cambiar de plantilla (PRG).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $juego) {
    if (auth_csrf_valido($_POST['csrf'] ?? null) && ($_POST['accion'] ?? '') === 'plantilla') {
        $_SESSION['juego_aviso'] = juego_cambiar_plantilla($usuario, $juego, (string) ($_POST['plantilla'] ?? ''));
    }
    header('Location: ' . base_url('app/juego.php') . '?id=' . $id);
    exit;
}

$aviso = $_SESSION['juego_aviso'] ?? null;
unset($_SESSION['juego_aviso']);

$plantillaPedida = (string) ($_GET['plantilla'] ?? '');
if ($juego) {
    $paso = isset($_GET['editar']) ? 2 : 3;
} else {
    $paso = isset(JUEGO_PLANTILLAS[$plantillaPedida]) ? 2 : 1;
}

// Un juego nuevo con una plantilla que el plan no incluye vuelve al catálogo.
if (!$juego && $paso === 2 && !plan_permite_formato($usuario, $plantillaPedida)) {
    $paso = 1;
    $aviso = ['ok' => false, 'mensaje' => JUEGO_PLANTILLAS[$plantillaPedida]['nombre'] . ' es de los planes de pago.'];
}

// Tope de juegos al empezar uno nuevo: mejor avisarlo antes de escribir nada.
$tope = plan_max_paquetes($usuario);
$sinCupo = !$juego && $tope > 0 && juegos_contar($usuarioId) >= $tope;

$contenido = $juego ? juego_contenido($juego) : null;
$tipo      = $juego ? (string) $juego['juego_tipo'] : null;
$plantillas = juego_plantillas_para($tipo, $contenido, $usuario, $juego ? 'juego-' . $juego['id'] : 's');

$page = [
    'title'       => ($juego ? $juego['nombre'] : 'Crear un juego') . ' — Ludia',
    'description' => 'Escribe tus palabras una vez y juégalas de muchas formas: relacionar, memoria, quiz, sopa de letras…',
    'home'        => false,
];
$extraStyles  = ['css/crear.css', 'css/juego.css'];
$extraScripts = $paso === 2 ? ['js/juego.js'] : ($paso === 3 ? ['js/formatos.js', 'js/editor.js'] : []);

require LUDIA_ROOT . '/includes/partials/header.php';

$pasos = ['Elegir plantilla', 'Escribir', 'Jugar'];
?>

<main id="contenido" class="juego-pagina">
  <nav class="pasos" aria-label="Pasos para crear el juego">
    <ol class="wrap pasos-lista">
      <?php foreach ($pasos as $i => $nombre): ?>
        <li class="paso-chip<?= $i + 1 === $paso ? ' actual' : ($i + 1 < $paso ? ' hecho' : '') ?>"<?= $i + 1 === $paso ? ' aria-current="step"' : '' ?>>
          <span class="paso-chip-txt"><span class="paso-num"><?= $i + 1 < $paso ? '✓' : $i + 1 ?></span> <?= e($nombre) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <div class="wrap">
    <?php if ($aviso): ?>
      <p class="mensaje <?= $aviso['ok'] ? 'ok' : 'error' ?>" role="status"><?= e($aviso['mensaje']) ?></p>
    <?php endif; ?>

<?php if ($paso === 1): /* ======================= 1. Elegir plantilla ======================= */ ?>
    <div class="paso-head">
      <span class="eyebrow">🎲 Nuevo juego</span>
      <h1>Elige cómo quieres jugar</h1>
      <p>Escribes tus palabras una sola vez. Después puedes cambiar a cualquier otra plantilla sin reescribir nada.</p>
    </div>

    <?php if ($sinCupo): ?>
      <div class="panel plan-aviso">
        <div><b>Tu plan guarda <?= $tope ?> juegos y ya los tienes.</b>
          <p class="cuenta-sub">Borra uno en «Mis paquetes» o pásate a un plan superior.</p></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver los planes</a>
      </div>
    <?php endif; ?>

    <ul class="plantillas" role="list">
      <?php foreach ($plantillas as $clave => $p): ?>
        <li>
          <?php if ($p['estado'] === 'plan'): ?>
            <a class="plantilla bloqueada" href="<?= e(base_url('app/suscripcion.php')) ?>">
              <span class="plantilla-icono" aria-hidden="true"><?= $p['icono'] ?></span>
              <b><?= e($p['nombre']) ?> <span class="candado" aria-label="Plan de pago">🔒</span></b>
              <small><?= e($p['descripcion']) ?></small>
            </a>
          <?php else: ?>
            <a class="plantilla" href="<?= e(base_url('app/juego.php')) ?>?plantilla=<?= e($clave) ?>" data-plantilla="<?= e($clave) ?>">
              <span class="plantilla-icono" aria-hidden="true"><?= $p['icono'] ?></span>
              <b><?= e($p['nombre']) ?></b>
              <small><?= e($p['descripcion']) ?></small>
            </a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

<?php elseif ($paso === 2): /* ======================= 2. Escribir ======================= */
    $plantillaActual = $juego ? (string) $juego['plantilla'] : $plantillaPedida;
    $tipoEditor      = $juego ? $tipo : JUEGO_PLANTILLAS[$plantillaActual]['nativo'];
?>
    <script>
      window.LudiaJuego = <?= json_encode([
          'id'        => $juego ? (int) $juego['id'] : 0,
          'titulo'    => $juego ? (string) $juego['nombre'] : '',
          'tipo'      => $tipoEditor,
          'plantilla' => $plantillaActual,
          'nombrePlantilla' => JUEGO_PLANTILLAS[$plantillaActual]['nombre'],
          'contenido' => $contenido,
          'imagenes'  => plan_permite($usuario, 'imagenes'),
          'cambiaTipo'=> !$juego,
          'csrf'      => auth_csrf(),
          'min'       => JUEGO_MIN_PAREJAS,
          'max'       => JUEGO_MAX_ELEMENTOS,
          'volver'    => $juego ? base_url('app/juego.php') . '?id=' . (int) $juego['id'] : base_url('app/juego.php'),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <div class="paso-head">
      <span class="eyebrow"><?= JUEGO_PLANTILLAS[$plantillaActual]['icono'] ?> <?= e(JUEGO_PLANTILLAS[$plantillaActual]['nombre']) ?></span>
      <h1><?= $juego ? 'Edita tu contenido' : 'Escribe tus palabras' ?></h1>
      <p>Solo el contenido: Ludia arma el juego. Después lo cambias a otra plantilla con un clic.</p>
    </div>

    <div class="editor-grid">
      <div class="panel juego-editor" id="juego-editor">
        <p class="hint">Cargando el editor…</p>
      </div>
      <!-- Guía en vivo: cómo armarlo, cómo se ve y en qué juegos sale (assets/js/juego.js) -->
      <aside class="juego-guia" id="juego-guia" aria-label="Ayuda para armar el juego"></aside>
    </div>
    <noscript><div class="panel">Para escribir el juego necesitas tener JavaScript activado.</div></noscript>

<?php else: /* ======================= 3. Jugar ======================= */
    $actividad = paquete_actividades($juego)[0] ?? null;
    $actual    = (string) $juego['plantilla'];
    $total     = count($contenido['items']);
?>
    <script>window.LudiaJuegoActividad = <?= json_encode($actividad, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>

    <div class="juego-cabeza">
      <div>
        <span class="eyebrow">🎲 Juego · <?= $total ?> <?= $tipo === 'parejas' ? ($total === 1 ? 'pareja' : 'parejas') : ($total === 1 ? 'pregunta' : 'preguntas') ?></span>
        <h1><?= e($juego['nombre']) ?></h1>
        <p class="cuenta-sub">Se juega como <b><?= JUEGO_PLANTILLAS[$actual]['icono'] ?> <?= e(JUEGO_PLANTILLAS[$actual]['nombre']) ?></b>. Cámbialo a la derecha cuando quieras.</p>
      </div>
    </div>

    <div class="juego-grid">
      <section class="panel juego-jugar">
        <div class="phone"><div class="phone-screen" id="preview"></div></div>
        <div class="juego-acciones">
          <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int) $juego['id'] ?>">
            <input type="hidden" name="accion" value="jugar">
            <button class="btn btn-primary" type="submit" data-jugar>▶ Jugar en vivo</button>
          </form>
          <form method="post" action="<?= e(base_url('app/mis-sesiones.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int) $juego['id'] ?>">
            <input type="hidden" name="accion" value="enviar">
            <button class="btn btn-ghost" type="submit" data-enviar>📨 Enviar por enlace</button>
          </form>
          <a class="btn btn-ghost" href="<?= e(base_url('app/juego.php')) ?>?id=<?= (int) $juego['id'] ?>&amp;editar=1" data-editar>✏️ Editar contenido</a>
        </div>
      </section>

      <aside class="panel juego-cambiar" aria-labelledby="cambiar-titulo">
        <h2 class="panel-title" id="cambiar-titulo">Cambiar juego</h2>
        <ul class="cambiar-lista" role="list">
          <?php foreach ($plantillas as $clave => $p): ?>
            <li>
              <?php if ($clave === $actual): ?>
                <span class="cambiar-op actual" aria-current="true"><span aria-hidden="true"><?= $p['icono'] ?></span> <?= e($p['nombre']) ?> <small>· ahora</small></span>
              <?php elseif ($p['estado'] === 'ok'): ?>
                <form method="post" action="<?= e(base_url('app/juego.php')) ?>?id=<?= (int) $juego['id'] ?>">
                  <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                  <input type="hidden" name="accion" value="plantilla">
                  <input type="hidden" name="plantilla" value="<?= e($clave) ?>">
                  <button class="cambiar-op" type="submit" data-cambiar="<?= e($clave) ?>"><span aria-hidden="true"><?= $p['icono'] ?></span> <?= e($p['nombre']) ?></button>
                </form>
              <?php elseif ($p['estado'] === 'plan'): ?>
                <a class="cambiar-op deshabilitada" href="<?= e(base_url('app/suscripcion.php')) ?>"><span aria-hidden="true"><?= $p['icono'] ?></span> <?= e($p['nombre']) ?> 🔒<small><?= e($p['motivo']) ?></small></a>
              <?php else: ?>
                <span class="cambiar-op deshabilitada" data-no="<?= e($clave) ?>"><span aria-hidden="true"><?= $p['icono'] ?></span> <?= e($p['nombre']) ?><small><?= e($p['motivo']) ?></small></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var act = window.LudiaJuegoActividad, el = document.getElementById('preview');
        if (act && el && window.LudiaEditor) el.innerHTML = window.LudiaEditor.vistaPrevia(act, 0);
      });
    </script>
<?php endif; ?>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
