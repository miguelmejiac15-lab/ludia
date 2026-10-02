<?php
/**
 * Ingreso a la cuenta. Formulario POST clásico (funciona sin JavaScript),
 * con token CSRF y redirección a donde el usuario quería ir.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Google.php';

auth_iniciar();

$destino = auth_ruta_interna($_GET['volver'] ?? $_POST['volver'] ?? '', base_url('app/crear.php'));

if (auth_hay_sesion()) {
    header('Location: ' . $destino);
    exit;
}

$error = null;
$email = '';

// Motivo dejado por el ingreso con Google (google-entrar.php / google-volver.php).
// Sin este puente, los mensajes que esas páginas redactan no los vería nadie:
// redirigen aquí y el error se quedaría en la sesión para siempre.
if (!empty($_SESSION['ingreso_error'])) {
    $error = (string) $_SESSION['ingreso_error'];
    unset($_SESSION['ingreso_error']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'El formulario caducó. Inténtalo otra vez.';
    } else {
        $resultado = auth_ingresar($email, (string) ($_POST['password'] ?? ''));
        if ($resultado['ok']) {
            header('Location: ' . $destino);
            exit;
        }
        $error = $resultado['error'];
    }
}

$page = [
    'title'       => 'Ingresar — Ludia',
    'description' => 'Entra a tu cuenta de Ludia para crear y proyectar sesiones.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <section class="panel cuenta-card">
      <span class="eyebrow">Tu cuenta</span>
      <h1>Ingresa para crear sesiones</h1>
      <p class="cuenta-sub">Tu público no necesita cuenta: entra con el código o el QR.</p>

      <?php if ($error): ?>
        <p class="mensaje error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>

      <?php /* Entrar con Google. Solo aparece si hay credenciales: sin ellas el
               botón llevaría a un callejón, y entrar con contraseña sigue
               funcionando igual. Va ARRIBA del formulario porque quien tiene
               cuenta de Google no quiere leer dos campos antes de verlo. */ ?>
      <?php if (google_configurado()): ?>
        <a class="btn btn-ghost btn-block btn-google"
           href="<?= e(base_url('app/google-entrar.php')) ?>?volver=<?= e(urlencode($destino)) ?>">
          <span class="btn-google-logo" aria-hidden="true">G</span>
          Entrar con Google
        </a>
        <p class="separador-o"><span>o con tu correo</span></p>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('app/ingresar.php')) ?>" class="cuenta-form">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="volver" value="<?= e($destino) ?>">

        <div class="field">
          <label for="email">Correo</label>
          <input class="input" id="email" name="email" type="email" required autocomplete="email"
                 value="<?= e($email) ?>" placeholder="tucorreo@ejemplo.com">
        </div>

        <div class="field">
          <label for="password">Contraseña</label>
          <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
      </form>

      <p class="cuenta-pie">
        <a href="<?= e(base_url('app/clave-olvidada.php')) ?>">¿Olvidaste tu contraseña?</a>
      </p>

      <p class="cuenta-pie">¿Aún no tienes cuenta?
        <a href="<?= e(base_url('app/registrarse.php')) ?>?volver=<?= e(urlencode($destino)) ?>">Créala gratis</a>
      </p>
    </section>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
