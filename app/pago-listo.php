<?php
/**
 * Vuelta desde Wompi: llega con ?id=<transacción>.
 *
 * No se fía de la URL: con ese id le pregunta a Wompi el estado real, igual que
 * hace el aviso (webhook). Si Wompi dice APROBADA y el monto cuadra, el plan se
 * activa aquí mismo; si no, lo hará el aviso cuando llegue. Escribir esta URL a
 * mano con un id inventado no da nada: Wompi no lo conoce. Y uno real de otra
 * persona activa SU suscripción, no la de quien lo escribe.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Suscripciones.php';

// La transacción se procesa ANTES de leer la cuenta: auth_usuario() la guarda
// para toda la petición, y leída antes mostraría el plan de antes del pago.
$estado = '';
$transaccionId = trim((string) ($_GET['id'] ?? ''));
if ($transaccionId !== '' && preg_match('/^[A-Za-z0-9\-]{5,80}$/', $transaccionId)) {
    $r = suscripcion_procesar_transaccion($transaccionId);
    $estado = $r['estado'];
}

$usuario = auth_requerir();
$suscripcion = suscripcion_de_usuario((int) $usuario['id']);
$yaActiva = suscripcion_vigente($suscripcion);
$rechazado = in_array($estado, ['DECLINED', 'VOIDED', 'ERROR'], true);

$page = [
    'title'       => 'Tu pago — Ludia',
    'description' => 'El resultado de tu pago con Wompi.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <div class="panel vacio">
      <?php if ($estado === 'APPROVED' && $yaActiva): ?>
        <b aria-hidden="true">🎉</b>
        <h1>¡Listo! Tu plan <?= e(plan_nombre(plan_de($usuario))) ?> está activo</h1>
        <p>Pagado hasta el <b><?= e(date('d/m/Y', strtotime((string) $suscripcion['pagado_hasta']))) ?></b>.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>">Crear un paquete</a>
          <a class="btn btn-ghost" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver mi plan</a>
        </div>
      <?php elseif ($rechazado): ?>
        <b aria-hidden="true">😕</b>
        <h1>El pago no se completó</h1>
        <p>Wompi no aprobó el pago, así que no se cobró nada. Puedes intentarlo otra vez, con el mismo medio o con otro.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/suscripcion.php')) ?>">Intentar de nuevo</a>
        </div>
      <?php else: ?>
        <b aria-hidden="true">⏳</b>
        <h1>Estamos confirmando tu pago</h1>
        <p>
          Wompi nos avisa en cuanto lo aprueba. Suele ser inmediato, pero con PSE o Nequi
          puede tardar unos minutos. No hace falta que pagues otra vez.
        </p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/pago-listo.php') . ($transaccionId !== '' ? '?id=' . rawurlencode($transaccionId) : '')) ?>">Revisar otra vez</a>
          <a class="btn btn-ghost" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver mi plan</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
