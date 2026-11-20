<?php
/**
 * Sobre Ludia y su creador (20/11/2026).
 *
 * Existe por dos razones: la confianza (quién está detrás, cómo contactarlo) y
 * los buscadores. «Ludia» es también el nombre de un estudio canadiense de
 * videojuegos, mucho más conocido; esta página y los datos estructurados de
 * header.php le dicen a Google que ludia.click es OTRA cosa: una plataforma
 * educativa hecha por un profesor.
 *
 * Regla: aquí solo va lo que Miguel publica de sí mismo en miguelmejiac.com y
 * en su LinkedIn (capturas del 20/11/2026). Nada de títulos, cifras o
 * instituciones que no estén ahí.
 */

require __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Sobre Ludia y su creador, Miguel Mejía C. — Ludia.click',
    'description' => 'Ludia es una plataforma educativa para gamificar las clases, creada por el profesor Miguel Mejía C. Quién está detrás, por qué existe y cómo contactarnos.',
    'canonica'    => '/sobre.php',
];

$correo = 'ludia.click@gmail.com';
$redes = [
    ['nombre' => 'Instagram', 'url' => 'https://www.instagram.com/profemiguelmejia/', 'icono' => '📸'],
    ['nombre' => 'TikTok',    'url' => 'https://www.tiktok.com/@profemiguelmejia',    'icono' => '🎵'],
    ['nombre' => 'YouTube',   'url' => 'https://www.youtube.com/@profemiguelmejia',   'icono' => '▶️'],
    ['nombre' => 'LinkedIn',  'url' => 'https://www.linkedin.com/in/miguelmejiac/',   'icono' => '💼'],
    ['nombre' => 'Sitio web', 'url' => 'https://www.miguelmejiac.com/',               'icono' => '🌐'],
];

$fichaSobre = [
    '@context'   => 'https://schema.org',
    '@type'      => 'AboutPage',
    'name'       => 'Sobre Ludia',
    'url'        => url_absoluta('/sobre.php'),
    'inLanguage' => 'es',
    'about'      => ['@id' => url_absoluta('/') . '#organizacion'],
    'mainEntity' => [
        '@type'       => 'Person',
        '@id'         => 'https://www.miguelmejiac.com/#persona',
        'name'        => 'Miguel Mejía C.',
        'jobTitle'    => 'Docente universitario, consultor y creador de contenido en IA aplicada y productividad digital',
        'homeLocation'=> ['@type' => 'Place', 'name' => 'Barranquilla, Atlántico, Colombia'],
        'url'         => 'https://www.miguelmejiac.com/',
        'image'       => url_absoluta('/assets/img/miguel-mejia.webp'),
        'sameAs'      => array_column(array_slice($redes, 0, 4), 'url'),
        'knowsAbout'  => ['Tecnología educativa', 'Gamificación', 'Inteligencia artificial aplicada', 'Productividad digital', 'Realidad aumentada en el aula', 'Docencia universitaria'],
        'founderOf'   => ['@id' => url_absoluta('/') . '#organizacion'],
    ],
];

require __DIR__ . '/includes/partials/header.php';
?>
<script type="application/ld+json">
<?= json_encode($fichaSobre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<main id="contenido" class="legal-doc sobre">
  <div class="wrap wrap-estrecho">
    <p class="eyebrow">Sobre Ludia</p>
    <h1>Una plataforma hecha por un profesor, para que la clase se juegue.</h1>

    <p class="sobre-entrada">
      <strong>Ludia</strong> (ludia.click) es una plataforma educativa para gamificar las clases:
      con ella un docente arma <strong>paquetes de actividades</strong> —quiz, sopa de letras, crucigrama,
      mural, vídeo con preguntas y más— o crea <strong>juegos</strong> a partir de sus propias palabras
      —avión, laberinto, nave espacial, catapulta—, y su grupo los juega en vivo con el proyector,
      por enlace desde casa o dentro de su aula virtual. Quien juega no crea cuenta.
    </p>

    <section class="sobre-autor" aria-labelledby="autor-titulo">
      <img src="<?= e(asset('img/miguel-mejia.webp')) ?>" alt="Miguel Mejía C., creador de Ludia" width="480" height="480" loading="lazy">
      <div>
        <p class="eyebrow">Quién está detrás</p>
        <h2 id="autor-titulo">Miguel Mejía C.</h2>
        <p class="sobre-cargo">Docente universitario · Especialista en IA aplicada y productividad digital</p>
        <p>
          Docente, consultor y creador de contenido en Barranquilla, Colombia. Lleva <strong>más de
          25 años</strong> ayudando a docentes, profesionales y organizaciones a trabajar mejor,
          aprender más rápido y aprovechar la tecnología con criterio. Su forma de resumirlo:
          <em>«No enseño herramientas. Enseño la capacidad de evolucionar con ellas.»</em>
        </p>
        <p>
          Ludia nace de ese trabajo con docentes: que convertir el tema de la clase en algo que el grupo
          quiera jugar no cueste horas de preparación, y que lo pueda hacer cualquier profesor, sin
          saber de diseño ni de programación.
        </p>
        <ul class="sobre-redes" aria-label="Redes de Miguel Mejía C.">
          <?php foreach ($redes as $r): ?>
            <li><a href="<?= e($r['url']) ?>" rel="me noopener" target="_blank"><span aria-hidden="true"><?= $r['icono'] ?></span> <?= e($r['nombre']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <h2>Otros proyectos del mismo autor</h2>
    <ul>
      <li><a href="https://actividadesenlinea.com" target="_blank" rel="noopener"><strong>Actividades en Línea</strong></a>: actividades interactivas para preescolar y primaria.</li>
      <li><a href="https://ra.actividadesenlinea.com/public/inicio.html" target="_blank" rel="noopener"><strong>Experimentar</strong></a>: realidad aumentada con hojas y dibujos.</li>
      <li><strong>Fichaula</strong> (próximamente): fichas de aprendizaje para imprimir y para el celular.</li>
    </ul>

    <h2>Cómo está pensada</h2>
    <p>
      Las decisiones de fondo —por qué el estudiante no crea cuenta, qué datos se guardan y cuáles no,
      por qué el informe señala la pregunta más difícil— están explicadas en
      <a href="<?= e(base_url('enfoque.php')) ?>">Cómo está pensada Ludia</a> y en la
      <a href="<?= e(base_url('privacidad.php')) ?>">política de privacidad</a>.
    </p>

    <h2 id="contacto">Contacto</h2>
    <p>
      Para dudas, sugerencias o problemas con un pago, escribe a
      <a href="mailto:<?= e($correo) ?>"><?= e($correo) ?></a>.
      Si eres de un colegio o una universidad y quieres Ludia para toda la institución, mira la
      <a href="<?= e(base_url('escuela.php')) ?>">licencia Escuela</a>.
    </p>

    <p class="sobre-nota">
      Ludia (ludia.click) es una plataforma educativa y no tiene relación con Ludia Inc., el estudio
      canadiense de videojuegos del mismo nombre.
    </p>

    <p class="legal-volver"><a href="<?= e(base_url('/')) ?>">← Volver al inicio</a></p>
  </div>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
