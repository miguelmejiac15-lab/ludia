<?php
/**
 * Licencia Escuela: información y formulario de contacto (06/11/2026).
 *
 * La licencia no se compra con tarjeta: se factura a la institución. Hasta ahora
 * «Hablar con nosotros» llevaba a app/colegio.php, que exige cuenta, y un rector
 * que llegaba desde la portada chocaba con un formulario de ingreso.
 *
 * Cada solicitud:
 *  1. se GUARDA siempre (panel → «Solicitudes de colegios»), y
 *  2. se envía por correo a CONTACTO_ESCUELA si el servidor tiene SMTP
 *     configurado (includes/Correo.php). Si el correo falla, no se pierde nada.
 *
 * Contra el spam: un campo trampa que una persona no ve, un tiempo mínimo para
 * llenar el formulario y un tope de envíos por sesión.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Planes.php';
require_once __DIR__ . '/includes/Correo.php';

auth_iniciar();

$cargos = ['Rector(a)', 'Coordinador(a)', 'Docente', 'Directivo', 'Otro'];
$valores = ['contacto' => '', 'cargo' => 'Coordinador(a)', 'institucion' => '', 'ciudad' => '',
            'correo' => '', 'telefono' => '', 'profesores' => '', 'mensaje' => ''];
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($valores as $k => $_) {
        $valor = trim((string) ($_POST[$k] ?? ''));
        // Un texto que no sea UTF-8 válido (un cliente viejo, un robot) hacía
        // que la base lo rechazara con un error 500: se convierte, no se cae.
        if (!mb_check_encoding($valor, 'UTF-8')) {
            $valor = mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');
        }
        $valores[$k] = $valor;
    }

    $enviadas = (int) ($_SESSION['escuela_enviadas'] ?? 0);
    $desde = (int) ($_SESSION['escuela_form_desde'] ?? 0);

    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'El formulario caducó. Inténtalo otra vez.';
    } elseif (trim((string) ($_POST['sitio_web'] ?? '')) !== '' || ($desde > 0 && time() - $desde < 3)) {
        // Trampa para robots: se responde como si todo hubiera ido bien.
        $_SESSION['escuela_aviso'] = true;
        header('Location: ' . base_url('escuela.php') . '#formulario');
        exit;
    } elseif ($enviadas >= 3) {
        $error = 'Ya recibimos tus solicitudes. Te escribiremos pronto.';
    } elseif (mb_strlen($valores['contacto']) < 3) {
        $error = 'Escribe tu nombre.';
    } elseif (mb_strlen($valores['institucion']) < 3) {
        $error = 'Escribe el nombre del colegio o institución.';
    } elseif (!filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribe un correo válido: ahí te respondemos.';
    } else {
        $cargo = in_array($valores['cargo'], $cargos, true) ? $valores['cargo'] : 'Otro';
        $profesores = $valores['profesores'] !== '' ? max(1, min(5000, (int) $valores['profesores'])) : null;

        db()->prepare(
            'INSERT INTO solicitudes_escuela (contacto, cargo, institucion, ciudad, correo, telefono, profesores, mensaje)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($valores['contacto'], 0, 120), $cargo, mb_substr($valores['institucion'], 0, 160),
            mb_substr($valores['ciudad'], 0, 100), mb_substr($valores['correo'], 0, 190),
            mb_substr($valores['telefono'], 0, 40), $profesores, mb_substr($valores['mensaje'], 0, 2000) ?: null,
        ]);
        $id = (int) db()->lastInsertId();

        $texto = "Nueva solicitud de la licencia Escuela (#$id)\n\n"
            . "Institución: {$valores['institucion']}\n"
            . "Ciudad: {$valores['ciudad']}\n"
            . "Contacto: {$valores['contacto']} ($cargo)\n"
            . "Correo: {$valores['correo']}\n"
            . "Teléfono / WhatsApp: {$valores['telefono']}\n"
            . 'Profesores: ' . ($profesores ?? 'sin indicar') . "\n\n"
            . "Mensaje:\n" . ($valores['mensaje'] !== '' ? $valores['mensaje'] : '(sin mensaje)') . "\n\n"
            . "Responde a este correo para escribirle directamente.\n"
            . 'También está en el panel: ' . url_absoluta('app/admin.php') . "?seccion=solicitudes\n";

        $enviado = correo_enviar(
            (string) config('contacto.escuela', 'ludia.click@gmail.com'),
            'Licencia Escuela: ' . $valores['institucion'],
            $texto,
            $valores['correo']
        );
        if ($enviado) {
            db()->prepare('UPDATE solicitudes_escuela SET correo_enviado = 1 WHERE id = ?')->execute([$id]);
        }

        $_SESSION['escuela_enviadas'] = $enviadas + 1;
        $_SESSION['escuela_aviso'] = true;
        header('Location: ' . base_url('escuela.php') . '#formulario');
        exit;
    }
}

$listo = !empty($_SESSION['escuela_aviso']);
unset($_SESSION['escuela_aviso']);
$_SESSION['escuela_form_desde'] = time();

$escuela = plan_datos('escuela');

$page = [
    'title'       => 'Licencia Escuela — Ludia para colegios',
    'description' => 'Ludia para toda la institución: un coordinador, hasta 30 profesores con todas las actividades y el progreso de cada estudiante. Se factura al colegio.',
    'canonica'    => '/escuela.php',
];
$extraStyles = ['css/cuenta.css'];

require __DIR__ . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap escuela-grid">
    <section>
      <span class="eyebrow">🏫 Licencia Escuela</span>
      <h1 class="escuela-titulo">Ludia para todo el colegio</h1>
      <p class="cuenta-sub">Una sola licencia al año para la institución, con todo lo del plan Pro para cada profesor.</p>
      <ul class="escuela-lista">
        <li><b>Hasta 30 profesores</b> con las 23 actividades, imágenes e informes.</li>
        <li><b>Un coordinador</b> da de alta a los profesores desde su panel.</li>
        <li><b>Cursos con lista de estudiantes</b> y el progreso de cada uno.</li>
        <li><b>Sin cuentas para los estudiantes:</b> entran con un código.</li>
        <li><b>Se factura a la institución</b>, sin tarjeta.</li>
      </ul>
      <?php if ((int) $escuela['precio_anual'] > 0): ?>
        <p class="escuela-precio">$<?= number_format((int) $escuela['precio_anual'], 0, ',', '.') ?> <span>al año</span></p>
      <?php endif; ?>
    </section>

    <section class="panel" id="formulario">
      <?php if ($listo): ?>
        <div class="vacio" style="padding:20px 0">
          <b aria-hidden="true">📨</b>
          <h2>¡Gracias! Recibimos tu solicitud</h2>
          <p>Te escribiremos en los próximos días hábiles al correo que nos dejaste.</p>
          <a class="btn btn-ghost" href="<?= e(base_url('/')) ?>">Volver a la portada</a>
        </div>
      <?php else: ?>
        <h2 class="panel-title">Pide información</h2>
        <p class="cuenta-sub" style="margin-bottom:14px">Déjanos tus datos y te contactamos para mostrarte Ludia y armar la licencia de tu institución.</p>
        <?php if ($error): ?><p class="mensaje error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" action="<?= e(base_url('escuela.php')) ?>#formulario" class="cuenta-form">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <?php /* Campo trampa: invisible para una persona, irresistible para un robot. */ ?>
          <div class="campo-trampa" aria-hidden="true">
            <label for="sitio_web">Sitio web</label>
            <input id="sitio_web" name="sitio_web" type="text" tabindex="-1" autocomplete="off">
          </div>
          <div class="field">
            <label for="contacto">Tu nombre</label>
            <input class="input" id="contacto" name="contacto" required maxlength="120" autocomplete="name" value="<?= e($valores['contacto']) ?>">
          </div>
          <div class="field">
            <label for="cargo">Cargo</label>
            <select class="select" id="cargo" name="cargo">
              <?php foreach ($cargos as $c): ?><option<?= $valores['cargo'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="institucion">Colegio o institución</label>
            <input class="input" id="institucion" name="institucion" required maxlength="160" autocomplete="organization" value="<?= e($valores['institucion']) ?>">
          </div>
          <div class="field">
            <label for="ciudad">Ciudad</label>
            <input class="input" id="ciudad" name="ciudad" maxlength="100" autocomplete="address-level2" value="<?= e($valores['ciudad']) ?>">
          </div>
          <div class="field">
            <label for="correo">Correo</label>
            <input class="input" id="correo" name="correo" type="email" required maxlength="190" autocomplete="email" value="<?= e($valores['correo']) ?>">
          </div>
          <div class="field">
            <label for="telefono">Teléfono o WhatsApp <small>(opcional)</small></label>
            <input class="input" id="telefono" name="telefono" type="tel" maxlength="40" autocomplete="tel" value="<?= e($valores['telefono']) ?>">
          </div>
          <div class="field">
            <label for="profesores">¿Cuántos profesores? <small>(aproximado)</small></label>
            <input class="input" id="profesores" name="profesores" type="number" min="1" max="5000" value="<?= e($valores['profesores']) ?>">
          </div>
          <div class="field">
            <label for="mensaje">Mensaje <small>(opcional)</small></label>
            <textarea class="input" id="mensaje" name="mensaje" rows="3" maxlength="2000"><?= e($valores['mensaje']) ?></textarea>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Enviar solicitud</button>
          <p class="hint">Usamos estos datos solo para responderte. <a href="<?= e(base_url('privacidad.php')) ?>">Privacidad</a></p>
        </form>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
