<?php
/**
 * Mi suscripción: ver el plan actual, contratar uno de pago o cancelar.
 *
 * El cobro es mensual y automático (Mercado Pago). Al cancelar no se pierde
 * nada de inmediato: el plan sigue hasta la fecha ya pagada y, cuando vence,
 * la cuenta vuelve a gratis con sus paquetes guardados pero bloqueados.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Sesiones.php';   // SESION_TIPOS: cuántos formatos hay
require_once __DIR__ . '/../includes/Suscripciones.php';

$usuario = auth_requerir();
$aviso = null;
$avisoTipo = 'ok';

// Cancelar va por POST con CSRF y responde con redirección (patrón PRG).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $_SESSION['suscripcion_aviso'] = ['ok' => false, 'mensaje' => 'El formulario caducó. Inténtalo otra vez.'];
    } else {
        $suscripcion = suscripcion_de_usuario((int) $usuario['id']);
        if (!$suscripcion) {
            $_SESSION['suscripcion_aviso'] = ['ok' => false, 'mensaje' => 'No tienes ninguna suscripción activa.'];
        } else {
            $r = suscripcion_cancelar((int) $suscripcion['id']);
            $_SESSION['suscripcion_aviso'] = $r['ok']
                ? ['ok' => true, 'mensaje' => 'Suscripción cancelada. Conservas tu plan hasta la fecha ya pagada.']
                : ['ok' => false, 'mensaje' => $r['error']];
        }
    }
    header('Location: ' . base_url('app/suscripcion.php'));
    exit;
}

if (!empty($_SESSION['suscripcion_aviso'])) {
    $aviso = $_SESSION['suscripcion_aviso']['mensaje'];
    $avisoTipo = $_SESSION['suscripcion_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['suscripcion_aviso']);
}

$suscripcion = suscripcion_de_usuario((int) $usuario['id']);
$planActual  = plan_de($usuario);
$vigente     = suscripcion_vigente($suscripcion);
$pagosListos = mp_configurado();

$page = [
    'title'       => 'Mi suscripción — Ludia',
    'description' => 'Tu plan, tu próximo cobro y cómo cambiarlo.',
    'home'        => false,
];
$extraStyles  = ['css/cuenta.css'];
$extraScripts = ['js/suscripcion.js'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta" data-suscripcion>
  <div class="wrap">

    <div class="mis-head">
      <div>
        <span class="eyebrow">Tu cuenta</span>
        <h1>Mi suscripción</h1>
        <p class="cuenta-sub">
          Plan actual: <b><?= e(plan_nombre($planActual)) ?></b>
          <?php if ($suscripcion): ?> · <?= e(suscripcion_estado_legible($suscripcion)) ?><?php endif; ?>
        </p>
      </div>
      <a class="btn btn-ghost" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
    </div>

    <?php if ($aviso): ?>
      <p class="mensaje <?= e($avisoTipo) ?>" role="status"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if (!$pagosListos): ?>
      <p class="mensaje error">
        Los pagos todavía no están configurados en este servidor: falta el <code>access_token</code> de Mercado Pago
        en <code>config/config.php</code>. Mientras tanto, un administrador puede activarte el plan a mano.
      </p>
    <?php endif; ?>

    <?php if ($vigente && $suscripcion): ?>
      <section class="panel plan-actual">
        <h2 class="panel-title">Tu plan <?= e(plan_nombre((string) $suscripcion['plan'])) ?></h2>
        <ul class="plan-datos">
          <li><span>Estado</span><b><?= e(suscripcion_estado_legible($suscripcion)) ?></b></li>
          <li><span>Precio</span><b>$<?= number_format((int) $suscripcion['monto'], 0, ',', '.') ?> <?= e($suscripcion['moneda']) ?> / mes</b></li>
          <?php if ($suscripcion['estado'] === 'activa'): ?>
            <li><span>Próximo cobro</span><b><?= e(date('d/m/Y', strtotime((string) $suscripcion['pagado_hasta']))) ?></b></li>
          <?php endif; ?>
          <?php if (!empty($suscripcion['ultimo_pago_at'])): ?>
            <li><span>Último pago</span><b><?= e(date('d/m/Y', strtotime((string) $suscripcion['ultimo_pago_at']))) ?></b></li>
          <?php endif; ?>
        </ul>

        <?php if ($suscripcion['estado'] === 'activa'): ?>
          <form method="post" action="<?= e(base_url('app/suscripcion.php')) ?>"
                onsubmit="return confirm('¿Cancelar la suscripción? Conservas el plan hasta la fecha ya pagada y no se borra ninguno de tus paquetes.');">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <button class="link-btn danger" type="submit">Cancelar la suscripción</button>
          </form>
          <p class="hint">Al cancelar conservas el plan hasta la fecha pagada. Después vuelves a gratis y tus paquetes quedan guardados, aunque bloqueados.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <!-- Elegir plan -->
    <section class="panel">
      <h2 class="panel-title"><?= $vigente ? 'Cambiar de plan' : 'Pasar a un plan de pago' ?></h2>
      <p class="cuenta-sub" style="margin-bottom:16px">Cobro automático con Mercado Pago. Puedes cancelar cuando quieras.</p>

      <?php /* El periodo se elige una vez para las dos tarjetas: tener un
               selector por plan invita a compararlos mal. El descuento se
               calcula del precio, no se escribe a mano. */ ?>
      <div class="periodo-switch" role="radiogroup" aria-label="Cada cuánto pagar">
        <button class="periodo-opcion activo" type="button" role="radio" aria-checked="true" data-periodo="mensual">Mensual</button>
        <button class="periodo-opcion" type="button" role="radio" aria-checked="false" data-periodo="anual">
          Anual <span class="periodo-ahorro">hasta 30 % menos</span>
        </button>
      </div>

      <div class="planes-cuenta">
        <?php /* Escuela NO entra en este recorrido: es lo que se paga con
                 tarjeta, y una licencia de institución se factura. Va en su
                 propio bloque, debajo. */ ?>
        <?php foreach (['estandar', 'pro'] as $clave): ?>
          <?php
          // Todo sale de la tabla `planes`: si el administrador cambia un precio
          // o un tope, esta página lo refleja sin tocar código.
          $p = plan_datos($clave);
          $suyas  = count(plan_formatos($clave));
          $todas  = count(plan_formatos('pro'));
          ?>
          <article class="plan-opcion<?= $p['destacado'] ? ' destacado' : '' ?>">
            <?php if ($p['destacado']): ?><span class="plan-badge">★ Recomendado</span><?php endif; ?>
            <h3><?= e($p['nombre']) ?></h3>
            <?php
            $anual = (int) $p['precio_anual'];
            $descuento = ($p['precio'] > 0 && $anual > 0)
                ? (int) round((1 - $anual / ($p['precio'] * 12)) * 100)
                : 0;
            ?>
            <div class="plan-precio" data-precio-mensual>
              $<?= number_format((int) $p['precio'], 0, ',', '.') ?><span> / mes</span>
            </div>
            <?php if ($anual > 0): ?>
              <div class="plan-precio" data-precio-anual hidden>
                $<?= number_format((int) round($anual / 12), 0, ',', '.') ?><span> / mes</span>
                <span class="plan-anual-pie">
                  $<?= number_format($anual, 0, ',', '.') ?> al año · ahorras <?= $descuento ?> %
                </span>
              </div>
            <?php endif; ?>
            <ul class="plan-list">
              <li><?= $suyas >= $todas ? 'Las ' . $todas . ' actividades, completas' : $suyas . ' de las ' . $todas . ' actividades' ?></li>
              <li><?= $p['max_paquetes'] === 0 ? '<b>Paquetes sin límite</b>' : 'Hasta ' . (int) $p['max_paquetes'] . ' paquetes guardados' ?></li>
              <li><?= $p['max_actividades'] === 0 ? '<b>Actividades por paquete sin límite</b>' : 'Hasta ' . (int) $p['max_actividades'] . ' actividades por paquete' ?></li>
              <li>Hasta <?= (int) $p['max_participantes'] ?> participantes</li>
              <li>Imágenes en las preguntas</li>
              <li>Informe con aciertos y gráficos</li>
              <li>Las actividades nuevas, según salgan</li>
            </ul>
            <?php /* El botón dice QUÉ se va a pagar. Con el precio anual a la
                     vista mostrado como equivalente mensual, un "Suscribirme"
                     a secas deja creer que el cobro es de $11.830 al mes. El
                     texto lo cambia suscripcion.js al alternar el periodo. */ ?>
            <?php /* `data-plan-actual` se marca aparte a propósito: el botón
                     también sale deshabilitado cuando no hay credenciales de
                     pago, y el JS necesita distinguir "este ya es tu plan" —cuyo
                     texto no se toca— de "no se puede pagar todavía", donde el
                     cartel sí debe decir la verdad de lo que se cobraría. */ ?>
            <button class="btn <?= $clave === 'pro' ? 'btn-primary' : 'btn-ghost' ?> btn-block"
                    type="button" data-contratar="<?= e($clave) ?>"
                    <?= $planActual === $clave ? 'data-plan-actual' : '' ?>
                    data-texto-mensual="Suscribirme por mes"
                    data-texto-anual="<?= $anual > 0 ? 'Pagar el año · $' . number_format($anual, 0, ',', '.') : 'Suscribirme por mes' ?>"
                    <?= (!$pagosListos || $planActual === $clave) ? 'disabled' : '' ?>>
              <?= $planActual === $clave ? 'Es tu plan actual' : 'Suscribirme por mes' ?>
            </button>
          </article>
        <?php endforeach; ?>
      </div>

      <p class="mensaje error" id="pago-error" hidden></p>
      <p class="hint" style="margin-top:14px">
        El pago lo procesa Mercado Pago: Ludia no guarda los datos de tu tarjeta.
      </p>
    </section>

    <?php /* La licencia de institución, en su propio bloque y no entre las
             tarjetas de arriba: no se compra pulsando un botón, así que
             mezclarla con las que sí invitaría a esperar un cobro que no
             existe. El precio sale de la tabla, como todo lo demás. */ ?>
    <?php $escuela = plan_datos('escuela'); ?>
    <?php if ((int) $escuela['precio_anual'] > 0): ?>
      <section class="panel">
        <h2 class="panel-title">¿Eres un colegio?</h2>
        <p class="cuenta-sub" style="margin-bottom:14px">
          La licencia <b><?= e($escuela['nombre']) ?></b> cubre a toda la institución:
          un coordinador da de alta a sus profesores, cada profesor arma sus cursos y envía
          los paquetes a su salón, y todos ven el progreso de cada estudiante.
        </p>
        <ul class="plan-datos">
          <li><span>Precio</span><b>$<?= number_format((int) $escuela['precio_anual'], 0, ',', '.') ?> / año</b></li>
          <li><span>Cuentas</span><b>Hasta 30 profesores</b></li>
          <li><span>Cómo se paga</span><b>Se factura, sin tarjeta</b></li>
        </ul>
        <?php if (!empty($usuario['colegio_id'])): ?>
          <a class="btn btn-ghost" href="<?= e(base_url('app/colegio.php')) ?>">Ver mi colegio</a>
        <?php else: ?>
          <p class="hint">Escríbenos y activamos la licencia de tu institución.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
