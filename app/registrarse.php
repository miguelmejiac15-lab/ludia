<?php
/**
 * Crear cuenta gratis. Al registrarse queda con la sesión abierta.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Sesiones.php';

auth_iniciar();

$destino = auth_ruta_interna($_GET['volver'] ?? $_POST['volver'] ?? '', base_url('app/crear.php'));

if (auth_hay_sesion()) {
    header('Location: ' . $destino);
    exit;
}

$perfiles = [
    'docente'           => 'Docente',
    'capacitador'       => 'Capacitador o RR. HH.',
    'creador_contenido' => 'Creador de contenido',
    'comunidad'         => 'Comunidad o evento',
    'otro'              => 'Otro',
];

$error = null;
$valores = ['nombre' => '', 'email' => '', 'perfil' => 'docente'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $valores['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $valores['email']  = trim((string) ($_POST['email'] ?? ''));
    $valores['perfil'] = (string) ($_POST['perfil'] ?? 'otro');

    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'El formulario caducó. Inténtalo otra vez.';
    } else {
        $resultado = auth_crear_cuenta(
            $valores['nombre'],
            $valores['email'],
            (string) ($_POST['password'] ?? ''),
            $valores['perfil']
        );
        if ($resultado['ok']) {
            header('Location: ' . $destino);
            exit;
        }
        $error = $resultado['error'];
    }
}

$page = [
    'title'       => 'Crear cuenta gratis — Ludia',
    'description' => 'Crea tu cuenta gratuita de Ludia y arma sesiones para tu público.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <section class="panel cuenta-card">
      <span class="eyebrow">Plan gratis</span>
      <h1>Crea tu cuenta</h1>
      <p class="cuenta-sub">Sin tarjeta. Incluye <?= SESION_MAX_PLAN_GRATIS ?> paquetes guardados, hasta <?= SESION_MAX_PARTICIPANTES ?> participantes y juégalos las veces que quieras.</p>

      <?php if ($error): ?>
        <p class="mensaje error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('app/registrarse.php')) ?>" class="cuenta-form">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="volver" value="<?= e($destino) ?>">

        <div class="field">
          <label for="nombre">Tu nombre</label>
          <input class="input" id="nombre" name="nombre" type="text" required maxlength="120"
                 autocomplete="name" value="<?= e($valores['nombre']) ?>" placeholder="Ej. Miguel Mejía">
        </div>

        <div class="field">
          <label for="email">Correo</label>
          <input class="input" id="email" name="email" type="email" required maxlength="190"
                 autocomplete="email" value="<?= e($valores['email']) ?>" placeholder="tucorreo@ejemplo.com">
        </div>

        <div class="field">
          <label for="password">Contraseña</label>
          <input class="input" id="password" name="password" type="password" required
                 minlength="<?= AUTH_MIN_PASSWORD ?>" autocomplete="new-password">
          <span class="hint">Mínimo <?= AUTH_MIN_PASSWORD ?> caracteres.</span>
        </div>

        <div class="field">
          <label for="perfil">¿Qué haces?</label>
          <select class="select" id="perfil" name="perfil">
            <?php foreach ($perfiles as $clave => $nombre): ?>
              <option value="<?= e($clave) ?>"<?= $valores['perfil'] === $clave ? ' selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Crear cuenta gratis</button>
      </form>

      <p class="cuenta-pie">¿Ya tienes cuenta?
        <a href="<?= e(base_url('app/ingresar.php')) ?>?volver=<?= e(urlencode($destino)) ?>">Ingresa aquí</a>
      </p>
    </section>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
