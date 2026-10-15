<?php
/**
 * Página de un enlace de cortesía o de descuento: /app/invitacion.php?c=CODIGO
 *
 * Recorrido:
 *  · Sin cuenta: «Crear mi cuenta» (o «Ya tengo cuenta»). El código queda en la
 *    sesión como PENDIENTE y, al volver aquí ya con la cuenta abierta, el
 *    beneficio se activa solo: es lo que la persona fue a buscar.
 *  · Con la cuenta abierta y sin pendiente (abrió el enlace estando dentro):
 *    un botón «Activar en mi cuenta», por POST con CSRF.
 *
 * Que el canje automático solo ocurra con el código pendiente en SU sesión
 * evita que un enlace activado por otro (una imagen, un precargador) gaste el
 * beneficio sin que la persona lo haya pedido.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Invitaciones.php';

auth_iniciar();

$codigo = strtolower(trim((string) ($_GET['c'] ?? $_POST['c'] ?? '')));
$inv = $codigo !== '' ? invitacion_por_codigo($codigo) : null;
$problema = invitacion_problema($inv);
$usuario = auth_usuario();
$aviso = null;
$avisoOk = false;

$aquiMismo = base_url('app/invitacion.php') . '?c=' . rawurlencode($codigo);

if (!$problema && $usuario) {
    $pendiente = ($_SESSION['invitacion_pendiente'] ?? '') === $codigo;
    $pidio = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && auth_csrf_valido($_POST['csrf'] ?? null);

    if ($pendiente || $pidio) {
        unset($_SESSION['invitacion_pendiente']);
        $r = invitacion_canjear((int) $usuario['id'], $codigo);
        $_SESSION['invitacion_aviso'] = $r;
        // PRG: recargar la página no vuelve a intentar el canje.
        header('Location: ' . $aquiMismo);
        exit;
    }
}

if (!empty($_SESSION['invitacion_aviso'])) {
    $aviso = (string) $_SESSION['invitacion_aviso']['mensaje'];
    $avisoOk = (bool) $_SESSION['invitacion_aviso']['ok'];
    unset($_SESSION['invitacion_aviso']);
}

// Sin cuenta: se recuerda el código para activarlo al volver.
if (!$problema && !$usuario) {
    $_SESSION['invitacion_pendiente'] = $codigo;
}

$page = [
    'title'       => 'Una invitación para ti — Ludia',
    'description' => 'Activa tu beneficio en Ludia.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <div class="panel vacio">
      <?php if ($aviso !== null): ?>
        <b aria-hidden="true"><?= $avisoOk ? '🎉' : '🙈' ?></b>
        <h1><?= $avisoOk ? '¡Beneficio activado!' : 'No se pudo activar' ?></h1>
        <p><?= e($aviso) ?></p>
        <div class="hero-cta">
          <?php if ($avisoOk && $inv && $inv['tipo'] === 'descuento'): ?>
            <a class="btn btn-primary" href="<?= e(base_url('app/suscripcion.php')) ?>">Elegir mi plan con descuento</a>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>">Crear mi primer paquete</a>
          <?php endif; ?>
          <a class="btn btn-ghost" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver mi plan</a>
        </div>

      <?php elseif ($problema): ?>
        <b aria-hidden="true">🔗</b>
        <h1>Este enlace no sirve</h1>
        <p><?= e($problema) ?></p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e(base_url('app/registrarse.php')) ?>">Crear cuenta gratis</a>
        </div>

      <?php else: ?>
        <b aria-hidden="true"><?= $inv['tipo'] === 'cortesia' ? '🎁' : '🏷️' ?></b>
        <h1><?= $inv['tipo'] === 'cortesia' ? 'Te regalamos Ludia Pro' : 'Tienes un descuento en Ludia' ?></h1>
        <p>Con esta invitación recibes <strong><?= e(invitacion_descripcion($inv)) ?></strong>.</p>
        <?php if ($inv['tipo'] === 'cortesia'): ?>
          <p class="cuenta-sub">Todas las actividades, imágenes, informes y más participantes. Al terminar vuelves al plan gratis sin perder nada de lo que creaste. No te pedimos tarjeta.</p>
        <?php else: ?>
          <p class="cuenta-sub">Vale para un solo pago: el primero que hagas, mensual o anual. El precio ya rebajado lo verás al elegir tu plan.</p>
        <?php endif; ?>

        <div class="hero-cta">
          <?php if ($usuario): ?>
            <form method="post" action="<?= e($aquiMismo) ?>">
              <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
              <input type="hidden" name="c" value="<?= e($codigo) ?>">
              <button class="btn btn-primary" type="submit">Activar en mi cuenta (<?= e($usuario['email']) ?>)</button>
            </form>
          <?php else: ?>
            <a class="btn btn-primary" href="<?= e(base_url('app/registrarse.php')) ?>?volver=<?= e(urlencode($aquiMismo)) ?>">Crear mi cuenta</a>
            <a class="btn btn-ghost" href="<?= e(base_url('app/ingresar.php')) ?>?volver=<?= e(urlencode($aquiMismo)) ?>">Ya tengo cuenta</a>
          <?php endif; ?>
        </div>
        <?php if (!empty($inv['expira_at'])): ?>
          <p class="hint">Válido hasta el <?= e(date('d/m/Y', strtotime((string) $inv['expira_at']))) ?>.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
