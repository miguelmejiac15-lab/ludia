<?php
/**
 * Fijar una contraseña nueva con el testigo del enlace.
 *
 * El testigo se comprueba ANTES de pintar el formulario: enseñar dos campos de
 * contraseña a quien trae un enlace caducado solo consigue que escriba una
 * clave para nada.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Recuperar.php';

auth_iniciar();

$testigo = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$error = null;
$listo = false;

$sirve = recuperar_usuario_de($testigo) !== null;

if ($sirve && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'El formulario caducó. Inténtalo otra vez.';
    } else {
        $resultado = recuperar_aplicar(
            $testigo,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password2'] ?? '')
        );

        if ($resultado['ok']) {
            $listo = true;
        } else {
            $error = $resultado['error'];
            // Puede haberse quemado el testigo entre la carga y el envío.
            $sirve = recuperar_usuario_de($testigo) !== null;
        }
    }
}

$page = [
    'title'       => 'Nueva contraseña — Ludia',
    'description' => 'Pon una contraseña nueva para tu cuenta de Ludia.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <section class="panel cuenta-card">
      <span class="eyebrow">Tu cuenta</span>

      <?php if ($listo): ?>
        <h1>Listo</h1>
        <p class="mensaje ok" role="status">Tu contraseña quedó cambiada.</p>
        <p class="cuenta-sub">Ya puedes entrar con la nueva.</p>
        <p style="margin-top:18px">
          <a class="btn btn-primary btn-block" href="<?= e(base_url('app/ingresar.php')) ?>">Ingresar</a>
        </p>

      <?php elseif (!$sirve): ?>
        <h1>Ese enlace ya no sirve</h1>
        <p class="cuenta-sub">
          Los enlaces duran una hora y se pueden usar una sola vez. Pide uno nuevo y vuelve a intentarlo.
        </p>
        <p style="margin-top:18px">
          <a class="btn btn-primary btn-block" href="<?= e(base_url('app/clave-olvidada.php')) ?>">Pedir otro enlace</a>
        </p>

      <?php else: ?>
        <h1>Pon una contraseña nueva</h1>
        <p class="cuenta-sub">Al menos <?= (int) AUTH_MIN_PASSWORD ?> caracteres.</p>

        <?php if ($error): ?>
          <p class="mensaje error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('app/clave-nueva.php')) ?>" class="cuenta-form">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <input type="hidden" name="t" value="<?= e($testigo) ?>">

          <div class="field">
            <label for="password">Contraseña nueva</label>
            <input class="input" id="password" name="password" type="password" required
                   autocomplete="new-password" minlength="<?= (int) AUTH_MIN_PASSWORD ?>">
          </div>

          <div class="field">
            <label for="password2">Repítela</label>
            <input class="input" id="password2" name="password2" type="password" required
                   autocomplete="new-password" minlength="<?= (int) AUTH_MIN_PASSWORD ?>">
          </div>

          <button class="btn btn-primary btn-block" type="submit">Guardar la nueva</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
