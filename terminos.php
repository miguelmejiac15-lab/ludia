<?php
/**
 * Términos y condiciones (20/11/2026).
 *
 * Misma regla que privacidad.php: solo se escribe lo que la plataforma hace de
 * verdad. Pagos por adelantado con Wompi y sin cobro automático (Pagos.php:
 * el plan vence solo en `pagado_hasta`); el contenido es de quien lo crea; quien
 * participa no tiene cuenta. Es un BORRADOR hasta que lo revise un abogado.
 */

require __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Términos y condiciones — Ludia.click',
    'description' => 'Las reglas de uso de Ludia: cuentas, planes y pagos por adelantado sin cobro automático, tu contenido, el uso con estudiantes y cómo contactarnos.',
    'canonica'    => '/terminos.php',
];

$legal = config('legal', []);
$responsable = trim((string) ($legal['responsable'] ?? ''));
$correo = trim((string) ($legal['correo'] ?? '')) ?: 'ludia.click@gmail.com';

require __DIR__ . '/includes/partials/header.php';
?>

<main id="contenido" class="legal-doc">
  <div class="wrap wrap-estrecho">
    <p class="eyebrow">Legal</p>
    <h1>Términos y condiciones de uso</h1>
    <p class="legal-fecha">Última actualización: 20 de noviembre de 2026</p>

    <div class="aviso aviso-borrador" role="note">
      <strong>Este documento es un borrador.</strong> Describe con exactitud cómo funciona
      la plataforma hoy, pero todavía no lo ha revisado un abogado.
    </div>

    <h2>1. Qué es Ludia y quién la ofrece</h2>
    <p>
      Ludia (ludia.click) es una plataforma para crear actividades y juegos educativos y
      jugarlos con un grupo, en vivo, por enlace o descargados para otra plataforma.
      <?php if ($responsable !== ''): ?>La ofrece <strong><?= e($responsable) ?></strong>.<?php endif; ?>
      Para cualquier asunto de estos términos, escribe a
      <a href="mailto:<?= e($correo) ?>"><?= e($correo) ?></a>.
    </p>

    <h2>2. Dos papeles distintos</h2>
    <ul>
      <li><strong>Quien crea</strong> (docente, capacitador, creador de contenido) tiene una cuenta y acepta estos términos al registrarse.</li>
      <li><strong>Quien participa</strong> no tiene cuenta: entra con un código o un enlace y escribe un nombre. Lo que se guarda de esa persona está en la <a href="<?= e(base_url('privacidad.php')) ?>">política de privacidad</a>.</li>
    </ul>

    <h2>3. Tu cuenta</h2>
    <p>
      Eres responsable de lo que se haga con tu cuenta y de mantener segura tu contraseña. Si
      crees que alguien entró sin permiso, cámbiala y escríbenos.
    </p>

    <h2>4. Planes y pagos</h2>
    <ul>
      <li>Hay un plan <strong>gratis</strong> y planes de pago, con los precios y límites publicados en la <a href="<?= e(base_url('/#planes')) ?>">página de planes</a>, en pesos colombianos.</li>
      <li>Los planes de pago se pagan <strong>por adelantado</strong>, por un mes o por un año, a través de <strong>Wompi</strong>. Ludia no ve ni guarda los datos de tu tarjeta o de tu cuenta.</li>
      <li><strong>No hay cobro automático.</strong> Cuando termina el periodo pagado, la cuenta vuelve sola al plan gratis. Para seguir, se paga de nuevo.</li>
      <li>Si un cobro salió mal o tienes un problema con un pago, escríbenos a <a href="mailto:<?= e($correo) ?>"><?= e($correo) ?></a> y lo revisamos.</li>
      <li>La licencia Escuela para instituciones se acuerda y se factura aparte; ver <a href="<?= e(base_url('escuela.php')) ?>">licencia Escuela</a>.</li>
    </ul>

    <h2>5. Tu contenido es tuyo</h2>
    <p>
      Las preguntas, palabras e imágenes que subes siguen siendo tuyas. Le das a Ludia permiso
      solo para guardarlas y mostrarlas a quienes juegan tus actividades. Eres responsable de
      tener derecho a usar lo que subes (por ejemplo, las imágenes) y de que sea adecuado para
      tu grupo. Lo que exportas a SCORM o HTML puedes usarlo en tus propias plataformas.
    </p>

    <h2>6. Uso con estudiantes</h2>
    <p>
      Ludia está pensada para usarse en clase, también con menores de edad, y por eso no les pide
      datos personales. Te pedimos no escribir en las actividades información sensible de tus
      estudiantes y usar solo su nombre o un apodo para que entren a jugar.
    </p>

    <h2>7. Lo que no está permitido</h2>
    <ul>
      <li>Publicar contenido ilegal, ofensivo o que vulnere derechos de otros.</li>
      <li>Intentar entrar a cuentas ajenas, saturar el servicio o saltarse los límites de los planes.</li>
      <li>Usar Ludia para recoger datos personales de quienes participan.</li>
    </ul>
    <p>Si esto ocurre, podemos retirar el contenido o suspender la cuenta.</p>

    <h2>8. Disponibilidad</h2>
    <p>
      Hacemos lo posible para que Ludia funcione siempre, pero puede haber interrupciones por
      mantenimiento o fallas. La plataforma se ofrece tal como está y sigue mejorando: algunas
      funciones pueden cambiar con el tiempo.
    </p>

    <h2>9. Cambios en estos términos</h2>
    <p>
      Si estos términos cambian, cambiará la fecha del encabezado. Los cambios importantes se
      avisarán dentro de la plataforma.
    </p>

    <p class="legal-volver"><a href="<?= e(base_url('/')) ?>">← Volver al inicio</a></p>
  </div>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
