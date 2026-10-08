<?php
/**
 * Cabecera común. Antes de incluirla, define $page:
 *   $page = ['title' => '...', 'description' => '...', 'home' => true|false];
 */

require_once LUDIA_ROOT . '/includes/Auth.php';

$page += ['title' => 'Ludia', 'description' => '', 'home' => false];
$usuarioActual = auth_usuario();

/**
 * Qué se deja indexar.
 *
 * Todo lo que vive en /app/ queda FUERA de los buscadores, sin excepción y sin
 * que haya que acordarse en cada página nueva. El motivo no es de SEO: en una
 * sala de juego aparecen los nombres de quienes participan —niños, escritos por
 * su profesor— y en el curso está la lista del salón. Que haga falta un código
 * para llegar no es lo mismo que pedir que no se guarde.
 *
 * Se decide aquí, por la RUTA, y no con una bandera en cada archivo, porque una
 * bandera se olvida justo en la página que la necesitaba. Una página de /app/
 * que algún día sí deba indexarse puede pedirlo con $page['indexable'] = true.
 */
$enApp   = str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/app/');
$noIndex = $page['noindex'] ?? ($enApp && empty($page['indexable']));

// En la home los enlaces del menú son anclas locales; en otras páginas apuntan a la home.
$anchor = static fn (string $id): string => ($page['home'] ? '' : base_url('/')) . '#' . $id;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title']) ?></title>
  <meta name="description" content="<?= e($page['description']) ?>">
  <meta name="theme-color" content="#FBF6EE" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#1A1530" media="(prefers-color-scheme: dark)">
<?php if ($noIndex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <?php /* La canónica va ABSOLUTA: una relativa no sirve de nada, que es
           justo para lo que existe la etiqueta. `url_absoluta()` respeta
           `app.base_url` cuando esté puesto el dominio real. */ ?>
  <link rel="canonical" href="<?= e(url_absoluta($page['canonica'] ?? '/')) ?>">
<?php endif; ?>

  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Ludia">
  <meta property="og:locale" content="es_LA">
  <meta property="og:title" content="<?= e($page['title']) ?>">
  <meta property="og:description" content="<?= e($page['description']) ?>">

  <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap">

  <link rel="stylesheet" href="<?= e(asset('css/ludia.css')) ?>">
  <?php foreach ($extraStyles ?? [] as $style): ?>
  <link rel="stylesheet" href="<?= e(asset($style)) ?>">
  <?php endforeach; ?>
  <script>
    /* Rutas del sitio para el JS: funcionan igual en /LUDIRA (XAMPP) que en la raíz de un dominio. */
    window.LudiaRutas = {
      base: <?= json_encode(base_url('/'), JSON_UNESCAPED_SLASHES) ?>,
      api: function (archivo) { return this.base + 'api/' + archivo; },
      app: function (archivo) { return this.base + 'app/' + archivo; }
    };
  </script>
  <script src="<?= e(asset('js/main.js')) ?>" defer></script>
  <?php foreach ($extraScripts ?? [] as $script): ?>
  <script src="<?= e(asset($script)) ?>" defer></script>
  <?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>

<header class="topbar">
  <div class="wrap">
    <a class="logo" href="<?= e(base_url('/')) ?>" aria-label="Ludia, ir al inicio">
      <span class="logo-mark" aria-hidden="true">L</span>Ludia
    </a>

    <nav class="nav-links" id="menu-principal" aria-label="Principal">
      <a href="<?= e($anchor('como-funciona')) ?>">Cómo funciona</a>
      <a href="<?= e($anchor('para-quien')) ?>">Para quién es</a>
      <a href="<?= e($anchor('planes')) ?>">Planes</a>
      <?php if ($usuarioActual): ?>
        <a href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
        <?php /* Visible en todos los planes: al gratis la página le explica qué
                 se guarda en los de pago, que es más útil que esconder el
                 enlace y que no se entienda qué se está comprando. */ ?>
        <a href="<?= e(base_url('app/informes.php')) ?>">Informes</a>
        <a href="<?= e(base_url('app/suscripcion.php')) ?>">Mi plan</a>
        <?php /* "Ver tutorial", no "¿Cómo funciona?": ya hay un enlace con ese
                 nombre apuntando a la portada y se confundían. */ ?>
        <?php /* Solo para quien pertenece a un colegio: a una cuenta suelta el
                 enlace no le diría nada. El permiso real lo comprueba la
                 página; esto es cosmética. */ ?>
        <?php if (!empty($usuarioActual['colegio_id'])): ?>
          <a href="<?= e(base_url('app/colegio.php')) ?>">Mi colegio</a>
        <?php endif; ?>
        <a href="<?= e(base_url('app/mis-sesiones.php')) ?>#tuto" data-abrir-tutoriales>Ver tutorial</a>
      <?php endif; ?>
      <?php if (($usuarioActual['rol'] ?? '') === 'admin'): ?>
        <a href="<?= e(base_url('app/admin.php')) ?>">Panel</a>
      <?php endif; ?>
    </nav>

    <div class="topbar-actions">
      <?php if ($usuarioActual): ?>
        <span class="cuenta-chip" title="<?= e($usuarioActual['email']) ?>">
          <span class="cuenta-inicial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($usuarioActual['nombre'], 0, 1))) ?></span>
          <span class="cuenta-nombre"><?= e($usuarioActual['nombre']) ?></span>
        </span>
        <form method="post" action="<?= e(base_url('app/salir.php')) ?>" class="form-salir">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <button class="link-btn" type="submit">Salir</button>
        </form>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/ingresar.php')) ?>">Ingresar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('app/crear.php')) ?>">Probar gratis</a>
      <?php endif; ?>
      <button class="menu-toggle" type="button" aria-controls="menu-principal" aria-expanded="false" aria-label="Abrir menú">
        <svg class="icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
  </div>
</header>
<?php
/* Aviso de renovación. Wompi no cobra solo, así que sin este aviso la cuenta
   caería a gratis sin que nadie se lo hubiera dicho, y el sitio aún no puede
   mandar correos. Solo dentro de /app/ y solo para planes de pago: a una
   cuenta gratis no le cuesta ni la consulta. La propia página de pago no lo
   repite, porque ya muestra los días que quedan. */
if ($enApp && $usuarioActual && ($usuarioActual['plan'] ?? 'gratis') !== 'gratis'
    && !str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/suscripcion.php')) {
    require_once LUDIA_ROOT . '/includes/Suscripciones.php';
    $diasPlan = suscripcion_dias_restantes(suscripcion_de_usuario((int) $usuarioActual['id']));
    if ($diasPlan !== null && $diasPlan <= SUSCRIPCION_AVISO_DIAS): ?>
  <p class="aviso-renovar" role="status">
    <?= $diasPlan <= 1 ? 'Tu plan vence mañana.' : 'Tu plan vence en ' . (int) $diasPlan . ' días.' ?>
    <a href="<?= e(base_url('app/suscripcion.php')) ?>">Renovar</a>
  </p>
<?php endif;
}
?>
