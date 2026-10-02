<?php
/**
 * Vuelta desde Mercado Pago.
 *
 * Esta página NO activa nada: el usuario suele volver antes de que llegue la
 * confirmación del cobro, y fiarse de la URL de retorno sería regalar el plan
 * a quien la escriba a mano. Quien otorga el acceso es el webhook.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Suscripciones.php';

$usuario = auth_requerir();
$suscripcion = suscripcion_de_usuario((int) $usuario['id']);
$yaActiva = suscripcion_vigente($suscripcion);

$page = [
    'title'       => 'Gracias por suscribirte — Ludia',
    'description' => 'Estamos confirmando tu pago con Mercado Pago.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <div class="panel vacio">
      <?php if ($yaActiva): ?>
        <b aria-hidden="true">🎉</b>
        <h1>¡Listo! Tu plan <?= e(plan_nombre(plan_de($usuario))) ?> está activo</h1>
        <p>Ya tienes el catálogo completo, imágenes en las preguntas y el informe de cada sesión.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>">Crear un paquete</a>
          <a class="btn btn-ghost" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver mi suscripción</a>
        </div>
      <?php else: ?>
        <b aria-hidden="true">⏳</b>
        <h1>Estamos confirmando tu pago</h1>
        <p>
          Mercado Pago nos avisa en cuanto lo aprueba: suele tardar unos segundos, y con algunos
          medios (como el efectivo) puede tardar más. No hace falta que pagues otra vez.
        </p>
        <p class="cuenta-sub">Esta página no activa nada por sí sola; el plan se enciende cuando llega la confirmación.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver el estado</a>
          <a class="btn btn-ghost" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
