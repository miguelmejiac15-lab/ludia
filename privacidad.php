<?php
/**
 * Política de Privacidad y Tratamiento de Datos Personales.
 *
 * Regla que rige este archivo: **aquí solo se escribe lo que el código hace de
 * verdad**. Cada afirmación de esta página se puede comprobar leyendo el
 * proyecto, y si mañana cambia el código hay que cambiar también este texto.
 * Una política que promete más de lo que el programa cumple no protege a
 * nadie; solo deja por escrito un incumplimiento.
 *
 * De dónde sale cada dato que se menciona:
 *   - cuentas de quien crea ........ `usuarios`, `intentos_ingreso`, `recuperaciones`
 *   - quien participa .............. `participantes`, `respuestas`
 *   - lista del salón .............. `curso_estudiantes` (licencia Escuela)
 *   - cobros ....................... `suscripciones`, `pagos`
 *   - cookie de sesión ............. `auth_iniciar()` en includes/Auth.php
 *   - terceros ..................... Google Fonts (header.php), Google, Wompi, YouTube
 */

require __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Política de Privacidad — Ludia',
    'description' => 'Qué datos guarda Ludia y cuáles no. Quien participa en una actividad no crea cuenta: de él solo se guarda el nombre con el que entra.',
    'canonica'    => '/privacidad.php',
];

$legal = config('legal', []);
$responsable = trim((string) ($legal['responsable'] ?? ''));
$correoLegal = trim((string) ($legal['correo'] ?? ''));
$completo    = $responsable !== '' && $correoLegal !== '';

require __DIR__ . '/includes/partials/header.php';
?>

<main id="contenido" class="legal-doc">
  <div class="wrap wrap-estrecho">

    <p class="eyebrow">Legal</p>
    <h1>Política de Privacidad y Tratamiento de Datos Personales</h1>
    <p class="legal-fecha">Última actualización: 30 de septiembre de 2026</p>

    <div class="aviso aviso-borrador" role="note">
      <strong>Este documento es un borrador.</strong> Describe con exactitud lo que
      la plataforma hace hoy, pero todavía no lo ha revisado un abogado. Antes de
      cobrar a una institución conviene que lo haga.
    </div>

    <?php if (!$completo): ?>
      <div class="aviso aviso-falta" role="note">
        <strong>Falta identificar al responsable.</strong> Una política sin alguien
        que responda por ella no sirve. Los datos se completan en
        <code>config/config.php</code>, en el bloque <code>legal</code>.
      </div>
    <?php endif; ?>

    <h2>1. Quién responde por tus datos</h2>
    <?php if ($completo): ?>
      <p>
        El responsable del tratamiento es <strong><?= e($responsable) ?></strong><?php
          if (!empty($legal['documento'])): ?>, identificado con <?= e((string) $legal['documento']) ?><?php endif;
          if (!empty($legal['ciudad'])): ?>, con domicilio en <?= e((string) $legal['ciudad']) ?><?php endif; ?>.
        Para cualquier asunto de esta política, escribe a
        <a href="mailto:<?= e($correoLegal) ?>"><?= e($correoLegal) ?></a>.
      </p>
    <?php else: ?>
      <p>Pendiente de completar.</p>
    <?php endif; ?>

    <h2>2. Lo más importante, primero</h2>
    <p>
      <strong>Quien participa en una actividad no crea una cuenta.</strong> Entra con
      un código de seis dígitos, escribe un nombre y juega. Ludia no le pide correo,
      ni contraseña, ni fecha de nacimiento, ni teléfono. Esto es deliberado: la
      mayoría de quienes responden en un salón de clase son menores de edad, y la
      forma más segura de cuidar sus datos es no pedirlos.
    </p>
    <p>
      Quien sí tiene cuenta es <strong>quien crea el contenido</strong>: el docente,
      el capacitador o el creador. Esta política distingue todo el tiempo entre esos
      dos papeles, porque los datos que se guardan de cada uno son muy distintos.
    </p>

    <h2>3. Qué se guarda de quien participa</h2>
    <ul>
      <li><strong>El nombre que escribe al entrar</strong>, tal cual lo teclea, con un máximo de 40 caracteres.</li>
      <li><strong>El personaje que elige</strong> (un muñeco de colores, no una fotografía).</li>
      <li><strong>Sus respuestas</strong>, cuántas acertó, los puntos que obtuvo y cuántos segundos tardó.</li>
      <li>La fecha y hora en que respondió.</li>
    </ul>
    <p>
      Y nada más. <strong>No se guarda su correo, ni su documento, ni su teléfono, ni
      su dirección, ni su foto.</strong> El nombre lo escribe la propia persona, o lo
      ha escrito antes su profesor en la lista del salón; Ludia no lo contrasta con
      ningún registro oficial ni lo comparte con nadie.
    </p>

    <h2>4. Qué se guarda de quien crea una cuenta</h2>
    <ul>
      <li>Nombre y correo electrónico.</li>
      <li>La contraseña, <strong>siempre cifrada</strong> y de forma que no se puede revertir. Quien entra con Google no tiene contraseña guardada en absoluto.</li>
      <li>El tipo de perfil que indicó al registrarse (docente, capacitador, creador de contenido u otro).</li>
      <li>Su plan, los permisos que tenga y la fecha de su último acceso.</li>
      <li>El contenido que crea: sus paquetes de actividades y las imágenes que suba.</li>
      <li>Si falla la contraseña varias veces seguidas, el correo y la hora de esos intentos, durante el tiempo que dura el bloqueo de seguridad.</li>
      <li>Si pide recuperar la contraseña, un código temporal que caduca.</li>
    </ul>

    <h2>5. La lista del salón, en la licencia para instituciones</h2>
    <p>
      Un colegio con licencia puede crear cursos y escribir en cada uno la lista de
      sus estudiantes. De cada estudiante se guarda <strong>únicamente el nombre</strong>
      que escribe su profesor, más una versión simplificada de ese nombre (en
      minúsculas y sin tildes) que sirve para reconocerlo cuando entra a jugar, de
      modo que «jose perez» encuentre a «José Pérez».
    </p>
    <p>
      Los estudiantes de esa lista <strong>no tienen cuenta ni contraseña</strong> y no
      reciben ningún correo. Cuando se quita a un estudiante de un curso, su nombre se
      desactiva en lugar de borrarse, para que el historial de lo que ya respondió no
      quede incompleto y atribuido a nadie.
    </p>

    <h2>6. Cobros</h2>
    <p>
      Los pagos los procesa <strong>Wompi</strong> (de Bancolombia), con tarjeta, PSE,
      Nequi o Bancolombia. Ludia <strong>nunca ve ni guarda el número de tu tarjeta ni
      los datos de tu cuenta</strong>: esos datos viajan directamente a Wompi y se rigen
      por su propia política. Ludia tampoco te cobra de forma automática.
    </p>
    <p>
      De cada pago, Ludia guarda el identificador que le asigna Wompi, el
      estado, el importe y la moneda, y además <strong>la respuesta completa que envía
      Wompi</strong>, que según el caso puede incluir el nombre, el correo y el
      documento de identidad de quien pagó. Se conserva para poder resolver
      reclamaciones y cuadrar cuentas.
    </p>

    <h2>7. Cookies y lo que queda en tu navegador</h2>
    <p>
      Ludia <strong>no usa cookies de publicidad ni herramientas de analítica</strong>.
      No hay rastreadores de terceros siguiéndote por el sitio.
    </p>
    <ul>
      <li>
        <strong>Una cookie técnica de sesión</strong> (<code>ludia_sesion</code>), solo
        para quien tiene cuenta. Sirve para mantenerte dentro mientras navegas. No la
        puede leer el JavaScript de la página y se borra al cerrar sesión.
      </li>
      <li>
        <strong>Almacenamiento local en tu navegador</strong>, para recordar el
        personaje que elegiste, la actividad en la que ibas y si ya viste los
        tutoriales. Esto <em>no viaja al servidor</em>: se queda en tu dispositivo y
        puedes borrarlo vaciando los datos del sitio.
      </li>
    </ul>

    <h2>8. A quién más llegan datos</h2>
    <p>Ludia no vende ni cede datos a nadie. Estos son todos los terceros que intervienen:</p>
    <ul>
      <li><strong>Google Fonts.</strong> Las tipografías se cargan desde los servidores de Google, así que al abrir cualquier página de Ludia tu dirección IP llega a Google. Ocurre en todas las páginas, aunque no tengas cuenta.</li>
      <li><strong>Google.</strong> Solo si usas el botón «Entrar con Google», y únicamente para comprobar tu identidad y tu correo.</li>
      <li><strong>Wompi.</strong> Solo al pagar.</li>
      <li><strong>YouTube.</strong> Solo si la actividad que estás resolviendo incluye un vídeo; en ese caso el reproductor lo sirve YouTube con sus propias condiciones.</li>
    </ul>

    <h2>9. Cuánto tiempo se conserva</h2>
    <ul>
      <li><strong>Los paquetes de actividades</strong> se quedan hasta que su autor los borre.</li>
      <li><strong>Las partidas del plan gratuito caducan a las 24 horas</strong> y con ellas desaparecen las respuestas de quienes participaron.</li>
      <li>En los planes de pago, la partida se conserva como informe mientras su autor la mantenga.</li>
      <li>Los registros de cobros se conservan por obligación contable.</li>
      <li>Que una suscripción o una licencia venza <strong>no borra nada</strong>: el contenido queda guardado y vuelve a estar disponible si se renueva.</li>
    </ul>

    <h2>10. Tus derechos</h2>
    <p>
      Conforme a la <strong>Ley 1581 de 2012</strong> y al <strong>Decreto 1377 de
      2013</strong> de Colombia, puedes conocer, actualizar y rectificar tus datos,
      pedir prueba de la autorización que diste, ser informado del uso que se les da,
      presentar quejas ante la Superintendencia de Industria y Comercio, revocar la
      autorización y solicitar que se supriman tus datos cuando no exista un deber
      legal de conservarlos.
    </p>
    <p>
      Los datos de niñas, niños y adolescentes reciben la protección reforzada que
      exige el artículo 7 de esa ley. Por eso Ludia no les pide datos de contacto:
      el nombre con el que entran a una actividad es lo único que se guarda de ellos.
    </p>
    <?php if ($completo): ?>
      <p>Para ejercer cualquiera de estos derechos, escribe a <a href="mailto:<?= e($correoLegal) ?>"><?= e($correoLegal) ?></a>.</p>
    <?php endif; ?>

    <h2>11. Si algo cambia</h2>
    <p>
      Cuando esta política cambie, cambiará también la fecha del encabezado. Si el
      cambio afecta a lo que se guarda de quien participa, se avisará dentro de la
      plataforma y no solo aquí.
    </p>

    <p class="legal-volver"><a href="<?= e(base_url('/')) ?>">← Volver al inicio</a></p>
  </div>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
