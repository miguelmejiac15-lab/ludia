<?php
/**
 * Ludia — portada.
 * El copy describe lo que la plataforma hace hoy: armar paquetes de actividades
 * y compartirlos. Nada de IA: esa decisión se tomó el 2026-09-15 y la
 * generación automática queda para los planes de pago, más adelante.
 */

require __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Ludia — Convierte tu tema en un juego para tu grupo',
    // 134 caracteres. Google corta alrededor de 155, así que lo que va después
    // no lo lee nadie: por eso lo que importa —que el participante no se
    // registra y que el precio está en pesos— va delante, no al final.
    'description' => 'Crea quiz, sopa de letras, crucigramas y más, y compártelos con un código. Tus participantes juegan sin registrarse. Precios en pesos.',
    'home'        => true,
];

$urlCrear = base_url('app/crear.php');

$audiencias = [
    ['icono' => '🧑‍🏫', 'tono' => 'violet', 'titulo' => 'Docentes',                'texto' => 'De básica, media y universidad. Convierte el tema de la clase en un repaso jugable.'],
    ['icono' => '🏢',   'tono' => 'mint',   'titulo' => 'Capacitadores y RR. HH.', 'texto' => 'Dinamiza inducciones y talleres corporativos sin depender de un diseñador.'],
    ['icono' => '🎙️',  'tono' => 'sun',    'titulo' => 'Creadores de contenido',  'texto' => 'Añade un momento interactivo a tus lives, webinars o publicaciones.'],
    ['icono' => '🎉',   'tono' => 'violet', 'titulo' => 'Comunidades y eventos',   'texto' => 'Iglesias, clubes, familias: una dinámica rápida sin curva de aprendizaje.'],
];

$pasos = [
    [
        'titulo' => 'Elige el tipo y el tema',
        'texto'  => 'En vivo para proyectar, o para enviar y que cada quien juegue a su ritmo. Luego escribes el tema: «Las sumas», «Inducción de seguridad»…',
    ],
    [
        'titulo' => 'Agrega tus actividades',
        'texto'  => 'Todas las que quieras dentro del mismo tema: un quiz, un verdadero o falso, una sopa de letras, un mural. Puedes cargarlas desde una plantilla de Excel.',
    ],
    [
        'titulo' => 'Comparte y juega',
        'texto'  => 'Recibes un código de 6 dígitos, un enlace y un QR. Tu público entra sin crear cuenta y, al final, salen las posiciones y el informe.',
    ],
];

$valores = [
    ['icono' => '🎲', 'titulo' => '22 formatos distintos',    'texto' => 'Quiz, verdadero o falso, completar, relacionar, clasificar, ordenar, sopa de letras, crucigrama, anagrama, mural colaborativo y más.'],
    ['icono' => '📽️', 'titulo' => 'Dos formas de jugar',      'texto' => 'Proyectado en clase con un código en pantalla, o enviado por enlace para resolverlo desde casa.'],
    // Esto no es solo una comodidad, es el argumento de privacidad más fuerte
    // que tiene el producto, y estaba contado como si fuera un detalle de
    // usabilidad. A un colegio que pregunta "¿qué datos de mis estudiantes se
    // guardan?" hay que poder responderle en una línea: el nombre, y nada más.
    ['icono' => '🙋', 'titulo' => 'Tus participantes no dejan datos', 'texto' => 'Entran con el código o el QR, eligen su personaje y juegan: sin cuenta, sin correo y sin contraseña. Lo único que se guarda de ellos es el nombre con el que entran. Solo quien crea necesita cuenta.'],
    ['icono' => '📊', 'titulo' => 'Cargas desde Excel',        'texto' => 'Descargas la plantilla, la llenas con tus preguntas y la subes: se crean todas las actividades de una vez.'],
    ['icono' => '🖼️', 'titulo' => 'Con tus imágenes',          'texto' => 'Ilustra las preguntas con fotos o diagramas. Se ven en el celular de cada participante y en la proyección.'],
    ['icono' => '💵', 'titulo' => 'Precios en pesos',          'texto' => 'Planes pensados para el bolsillo de un docente latinoamericano, no convertidos desde dólares.'],
];

/**
 * Los planes salen de la base de datos: si el administrador cambia un precio o
 * un tope desde el panel, la portada lo refleja sin tocar código.
 *
 * Tres se venden por usuario y se pagan con tarjeta. El cuarto, Escuela, es una
 * licencia anual para una institución: da lo mismo que Pro, pero se factura al
 * colegio y NO pasa por la pasarela (ver Colegios.php).
 */
require_once __DIR__ . '/includes/Planes.php';

$todasLasActividades = count(plan_formatos('pro'));

$planes = [];
foreach (planes_todos() as $clave => $p) {
    $suyas = count(plan_formatos($clave));
    $gratis = $clave === 'gratis';
    $escuela = $clave === 'escuela';

    $items = [
        ['si', $suyas >= $todasLasActividades
            ? 'Las ' . $todasLasActividades . ' actividades, completas'
            : $suyas . ' de las ' . $todasLasActividades . ' actividades'],
        // "Guardados", no "activos": los paquetes son permanentes desde que el
        // contenido se separó de las partidas.
        ['si', $p['max_paquetes'] === 0
            ? 'Paquetes guardados sin límite'
            : 'Hasta ' . $p['max_paquetes'] . ' paquetes guardados'],
        ['si', $p['max_actividades'] === 0
            ? 'Actividades por paquete sin límite'
            : 'Hasta ' . $p['max_actividades'] . ' actividades por paquete'],
        ['si', 'Hasta ' . $p['max_participantes'] . ' participantes'],
        [$p['imagenes'] ? 'si' : 'no', 'Imágenes en las preguntas'],
        [$p['informe'] ? 'si' : 'no', 'Informe con aciertos y gráficos'],
    ];

    if (!$gratis) {
        $items[] = ['si', 'Las actividades nuevas, según salgan'];
    }

    // Lo que de verdad distingue a Escuela no es un tope más alto: es que deja
    // de ser una cuenta suelta. Sin esto, la tarjeta no explica qué se compra.
    if ($escuela) {
        $items[] = ['si', 'Un coordinador da de alta a sus profesores'];
        $items[] = ['si', 'Cursos con lista de estudiantes y progreso de cada uno'];
        $items[] = ['si', 'Una sola factura al año para toda la institución'];
    }

    // Pagar el año sale más barato: se anuncia como el equivalente mensual,
    // que es lo que la gente compara, con el total y el ahorro debajo.
    //
    // Escuela queda fuera de este cálculo a propósito: no tiene precio mensual
    // con el que comparar (`precio` es 0), así que el "ahorro" sería una
    // división contra cero disfrazada de oferta.
    $anual = (int) ($p['precio_anual'] ?? 0);
    $ahorro = (!$gratis && !$escuela && $p['precio'] > 0 && $anual > 0)
        ? (int) round((1 - $anual / ($p['precio'] * 12)) * 100)
        : 0;

    // Cada plan trae SU acción, en vez de deducirla del texto del precio.
    // Antes el botón se decidía con `$plan['precio'] === '$0'`, y Escuela
    // —que no tiene precio mensual— habría caído en «Empezar gratis», justo
    // lo contrario de lo que debe decir.
    if ($gratis) {
        $accion = ['url' => base_url('app/registrarse.php'), 'texto' => 'Empezar gratis'];
    } elseif ($escuela) {
        $accion = ['url' => base_url('app/colegio.php'), 'texto' => 'Hablar con nosotros'];
    } else {
        $accion = ['url' => base_url('app/suscripcion.php'), 'texto' => 'Suscribirme a ' . $p['nombre']];
    }

    $planes[] = [
        'nombre'    => $p['nombre'],
        'accion'    => $accion,
        'precio'    => $escuela
            ? '$' . number_format((int) ($p['precio_anual'] ?? 0), 0, ',', '.')
            : ($gratis ? '$0' : '$' . number_format($p['precio'], 0, ',', '.')),
        'periodo'   => $escuela ? '/ año' : ($gratis ? null : '/ mes'),
        'anual'     => $ahorro > 0
            ? 'O $' . number_format((int) round($anual / 12), 0, ',', '.') . '/mes pagando el año ($'
              . number_format($anual, 0, ',', '.') . ') · ahorras ' . $ahorro . ' %'
            : null,
        'destacado' => $p['destacado'],
        'insignia'  => $p['destacado'] ? '★ Recomendado' : null,
        'resumen'   => $p['resumen'],
        'items'     => $items,
        // Pro ya NO es "sin techo": tiene 50 paquetes, porque por encima va la
        // licencia Escuela. Lo que sí sigue sin tope son las actividades por
        // paquete, y eso es lo que se destaca aquí.
        'salto'     => $p['destacado']
            ? 'Sin tope de actividades por paquete, el catálogo entero y exportar a SCORM o HTML.'
            : null,
        'nota'      => match (true) {
            $gratis  => 'Tus paquetes se quedan guardados: los borras tú cuando quieras.',
            $escuela => 'Hasta 30 cuentas de profesor. Se factura a la institución, sin tarjeta.',
            default  => 'Precio en pesos colombianos, sin conversión desde dólares.',
        },
    ];
}

/**
 * Datos estructurados para los buscadores (schema.org).
 *
 * Los precios salen de la MISMA fuente que las tarjetas de más abajo, no de una
 * copia escrita a mano: si mañana se sube un precio desde el panel, lo que ve
 * Google cambia con él. Una ficha que anuncie un precio viejo es peor que no
 * tener ficha.
 *
 * NO se declara `aggregateRating`. Marcar estrellas que nadie ha puesto es
 * exactamente lo que Google penaliza como reseñas inventadas, y además sería
 * mentir. Cuando haya valoraciones reales, entrarán aquí.
 */
$ofertas = [];
foreach (planes_todos() as $clave => $p) {
    $esEscuela = $clave === 'escuela';

    // Escuela no tiene precio mensual: su precio real vive en `precio_anual`.
    $precio = $esEscuela ? (int) ($p['precio_anual'] ?? 0) : (int) $p['precio'];

    $ofertas[] = [
        '@type'         => 'Offer',
        'name'          => $p['nombre'],
        'price'         => (string) $precio,
        'priceCurrency' => $p['moneda'] ?? 'COP',
        'description'   => $p['resumen'],
        'url'           => url_absoluta('/#planes'),
    ];
}

$fichaBuscadores = [
    '@context'            => 'https://schema.org',
    '@type'               => 'SoftwareApplication',
    'name'                => 'Ludia',
    'url'                 => url_absoluta('/'),
    'applicationCategory' => 'EducationalApplication',
    'operatingSystem'     => 'Cualquiera con navegador web',
    'inLanguage'          => 'es',
    'description'         => $page['description'],
    'featureList'         => array_map(
        static fn (array $v): string => $v['titulo'],
        $valores
    ),
    'offers'              => $ofertas,
];

require __DIR__ . '/includes/partials/header.php';
?>
<script type="application/ld+json">
<?= json_encode($fichaBuscadores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<main id="contenido">
  <!-- Hero -->
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">🧩 Actividades para jugar en grupo</span>
        <h1>Tu tema,<br>convertido en un <em>juego</em> para tu grupo.</h1>
        <p class="hero-sub">Arma un paquete con las actividades que quieras —quiz, sopa de letras, crucigrama, mural colaborativo— y compártelo con un código o un enlace. Tu público entra sin crear cuenta.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e($urlCrear) ?>">Empezar gratis</a>
          <a class="btn btn-ghost" href="#como-funciona">Ver cómo funciona</a>
        </div>
        <div class="hero-meta">
          <div class="avatars" aria-hidden="true"><span>🧑‍🏫</span><span>🎙️</span><span>🧑‍💼</span></div>
          <span>Para docentes, capacitadores y creadores de contenido</span>
        </div>
      </div>

      <div class="device" aria-hidden="true">
        <div class="device-bar"><i></i><i></i><i></i></div>
        <div class="device-screen">
          <div class="scene scene-1">
            <span class="scene-label">1 · Tu paquete</span>
            <div class="fake-card">
              <b>Las sumas</b>
              <div class="fake-answers">
                <div class="fake-answer">🎯 Quiz · 4 preguntas</div>
                <div class="fake-answer">✏️ Completar la frase</div>
                <div class="fake-answer">🔤 Sopa de letras</div>
              </div>
            </div>
            <div class="fake-chip-row"><span class="fake-chip">En vivo</span><span class="fake-chip">Estudiantes</span></div>
          </div>
          <div class="scene scene-2">
            <div class="fake-pin">
              <span class="scene-label">2 · Comparte</span>
              <b>482 913</b>
              <span>Código, enlace o QR para entrar</span>
              <span class="fake-live-count">● 27 jugando ahora</span>
            </div>
          </div>
          <div class="scene scene-3">
            <span class="scene-label">3 · Resultados</span>
            <div class="fake-card">
              <b>Posiciones</b>
              <div class="fake-answers">
                <div class="fake-answer correct">1 · Zorro veloz — 1 925 pts</div>
                <div class="fake-answer">2 · Búho curioso — 1 480 pts</div>
                <div class="fake-answer">3 · Panda audaz — 1 210 pts</div>
              </div>
            </div>
            <div class="fake-chip-row"><span class="fake-chip">Informe final</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Para quién es -->
  <section id="para-quien">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Para quién es</span>
        <h2>No solo para el aula.</h2>
        <p>Cualquier persona con una audiencia puede convertir su tema en una dinámica en minutos.</p>
      </div>
      <div class="audience-grid">
        <?php foreach ($audiencias as $a): ?>
          <article class="audience-card">
            <div class="audience-icon tone-<?= e($a['tono']) ?>" aria-hidden="true"><?= $a['icono'] ?></div>
            <h3><?= e($a['titulo']) ?></h3>
            <p><?= e($a['texto']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Cómo funciona -->
  <section class="how" id="como-funciona">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Cómo funciona</span>
        <h2>De tu tema al juego, en tres pasos.</h2>
        <p>Tú escribes el contenido; Ludia lo convierte en actividades para jugar y se encarga de repartirlas, cronometrarlas y calificarlas.</p>
      </div>
      <ol class="how-steps">
        <?php foreach ($pasos as $i => $paso): ?>
          <li class="step">
            <div class="step-num" aria-hidden="true"><?= $i + 1 ?></div>
            <h3><?= e($paso['titulo']) ?></h3>
            <p><?= e($paso['texto']) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- Por qué Ludia -->
  <section id="por-que">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Por qué Ludia</span>
        <h2>Lo que de verdad te hace perder tiempo.</h2>
        <p>Armar el material, repartirlo, tomar nota de quién respondió qué. De eso se encarga la plataforma.</p>
      </div>
      <div class="value-grid">
        <?php foreach ($valores as $v): ?>
          <article class="value-card">
            <div class="mark" aria-hidden="true"><?= $v['icono'] ?></div>
            <h3><?= e($v['titulo']) ?></h3>
            <p><?= e($v['texto']) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Planes -->
  <section id="planes">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Planes</span>
        <h2>Empieza gratis. Crece cuando lo necesites.</h2>
        <p>El plan gratis sirve para dar clase de verdad, no para una demostración. Los de pago suman el catálogo completo, las imágenes y el informe de cada sesión.</p>
      </div>
      <div class="pricing-grid">
        <?php foreach ($planes as $plan): ?>
          <article class="plan<?= $plan['destacado'] ? ' featured' : '' ?>">
            <?php if ($plan['insignia']): ?>
              <span class="plan-badge"><?= e($plan['insignia']) ?></span>
            <?php endif; ?>
            <h3 class="plan-name"><?= e($plan['nombre']) ?></h3>
            <p class="plan-resumen"><?= e($plan['resumen']) ?></p>
            <div class="plan-price">
              <?= e($plan['precio']) ?>
              <?php if ($plan['periodo']): ?><span><?= e($plan['periodo']) ?></span><?php endif; ?>
            </div>
            <?php if ($plan['anual']): ?>
              <p class="plan-anual"><?= e($plan['anual']) ?></p>
            <?php endif; ?>
            <ul class="plan-list">
              <?php foreach ($plan['items'] as [$incluido, $texto]): ?>
                <li<?= $incluido === 'no' ? ' class="no"' : '' ?>><?= e($texto) ?></li>
              <?php endforeach; ?>
            </ul>
            <?php if ($plan['salto']): ?>
              <p class="plan-salto"><?= e($plan['salto']) ?></p>
            <?php endif; ?>
            <?php /* Cada plan trae su propia acción: gratis lleva a crear la
                     cuenta, los de pago a suscribirse, y Escuela a hablar con
                     nosotros, porque una licencia de institución se factura y
                     no se cobra con tarjeta. */ ?>
            <a class="btn <?= $plan['destacado'] ? 'btn-primary' : 'btn-ghost' ?> btn-block plan-cta"
               href="<?= e($plan['accion']['url']) ?>">
              <?= e($plan['accion']['texto']) ?>
            </a>
            <?php if ($plan['nota']): ?>
              <p class="plan-note"><?= e($plan['nota']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <?php /* Aquí vivía una banda aparte con la licencia de colegio. Desde el
               18/09/2026 Escuela es un plan más y sale en la rejilla de arriba:
               mantener las dos cosas contaba la misma licencia dos veces, con
               dos precios que podían desincronizarse. */ ?>
    </div>
  </section>

  <!-- CTA final -->
  <section>
    <div class="wrap">
      <div class="cta-final">
        <h2>Tu próxima clase puede ser un juego.</h2>
        <p>Arma el paquete, proyecta el código y deja que tu grupo juegue. Al terminar tendrás las posiciones y el informe.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e($urlCrear) ?>">Empezar gratis</a>
          <a class="btn btn-ghost" href="#como-funciona">Ver cómo funciona</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
