<?php
/**
 * "Olvidé mi contraseña": se pide el enlace de restablecimiento.
 *
 * La respuesta es SIEMPRE la misma, exista o no la cuenta. Decir "ese correo
 * no está registrado" convierte esta pantalla en un comprobador de clientes
 * para cualquiera que pase por aquí.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Recuperar.php';

auth_iniciar();

if (auth_hay_sesion()) {
    header('Location: ' . base_url('app/crear.php'));
    exit;
}

$error = null;
$enviado = false;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'El formulario caducó. Inténtalo otra vez.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ese correo no parece válido.';
    } else {
        // El resultado trae el enlace cuando se generó uno, pero aquí NO se
        // muestra: quien lo pide por la web no debe verlo en pantalla, o
        // cualquiera podría restablecer la cuenta de otro escribiendo su
        // correo. Mientras no haya correo saliente, lo entrega el panel.
        recuperar_pedir($email);
        $enviado = true;
    }
}

$page = [
    'title'       => 'Recuperar contraseña — Ludia',
    'description' => 'Pide un enlace para volver a entrar a tu cuenta de Ludia.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <section class="panel cuenta-card">
      <span class="eyebrow">Tu cuenta</span>
      <h1>¿Olvidaste tu contraseña?</h1>

      <?php if ($enviado): ?>
        <p class="mensaje ok" role="status">
          Si ese correo tiene una cuenta, ya hay un enlace para volver a entrar.
        </p>
        <p class="cuenta-sub">
          El enlace dura una hora y sirve una sola vez. Si no te llega en unos minutos,
          escríbenos y te ayudamos a entrar.
        </p>
        <p class="cuenta-pie">
          <a href="<?= e(base_url('app/ingresar.php')) ?>">Volver a ingresar</a>
        </p>
      <?php else: ?>
        <p class="cuenta-sub">Escribe tu correo y te mandamos un enlace para poner una nueva.</p>

        <?php if ($error): ?>
          <p class="mensaje error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('app/clave-olvidada.php')) ?>" class="cuenta-form">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">

          <div class="field">
            <label for="email">Correo</label>
            <input class="input" id="email" name="email" type="email" required autocomplete="email"
                   value="<?= e($email) ?>" placeholder="tucorreo@ejemplo.com">
          </div>

          <button class="btn btn-primary btn-block" type="submit">Enviarme el enlace</button>
        </form>

        <p class="cuenta-pie">
          ¿Te acordaste? <a href="<?= e(base_url('app/ingresar.php')) ?>">Ingresa</a>
        </p>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
