<?php
/**
 * Ludia — portada.
 * El copy describe lo que la plataforma hace hoy: armar paquetes de actividades
 * y compartirlos. Nada de IA: esa decisión se tomó el 2026-09-15 y la
 * generación automática queda para los planes de pago, más adelante.
 */

require __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Ludia.click — Juegos y actividades para gamificar tus clases',
    // 134 caracteres. Google corta alrededor de 155, así que lo que va después
    // no lo lee nadie: por eso lo que importa —que el participante no se
    // registra y que el precio está en pesos— va delante, no al final.
    'description' => 'Crea paquetes de actividades y juegos para gamificar tus clases: avión, laberinto, nave, quiz y más. Tu grupo juega sin registrarse. En pesos.',
    'home'        => true,
];

$urlCrear = base_url('app/crear.php');

require_once __DIR__ . '/includes/Planes.php';
require_once __DIR__ . '/includes/Juegos.php';

// Cuántos formatos y juegos hay: salen del catálogo, no de un número escrito
// a mano que quede viejo al sumar uno nuevo.
$totalFormatos = count(plan_formatos('pro'));
$totalJuegos   = count(JUEGO_PLANTILLAS);
// Foto del salón (opcional): si se sube, es el fondo del hero.
$fotoSalon = is_file(__DIR__ . '/assets/img/home/salon.webp');

// Los juegos de acción que se muestran con captura real.
$juegosAccion = [
    ['img' => 'celular-avion',     'w' => 648, 'h' => 933,  'nombre' => '✈️ Vuela a la respuesta', 'texto' => 'Esquiva las nubes falsas y atraviesa la correcta. Cada vez más rápido.'],
    ['img' => 'celular-laberinto', 'w' => 648, 'h' => 1162, 'nombre' => '🌀 Laberinto',            'texto' => 'Llega a la puerta de la respuesta escapando de las abejas.'],
    ['img' => 'celular-catapulta', 'w' => 648, 'h' => 978,  'nombre' => '🎯 Catapulta',            'texto' => 'Tensa la honda y dale al cartel correcto de la torre.'],
    ['img' => 'celular-nave',      'w' => 648, 'h' => 1155, 'nombre' => '🚀 Nave espacial',        'texto' => 'Dispara al asteroide de la respuesta y esquiva los meteoritos.'],
];
// El tamaño real de cada captura, para que el navegador reserve su sitio.
foreach ($juegosAccion as &$j) {
    [$j['w'], $j['h']] = @getimagesize(__DIR__ . '/assets/img/home/' . $j['img'] . '.webp') ?: [$j['w'], $j['h']];
}
unset($j);

$audiencias = [
    ['icono' => '🧑‍🏫', 'tono' => 'violet', 'titulo' => 'Docentes',                'texto' => 'De básica, media y universidad. Convierte el tema de la clase en un repaso jugable.'],
    ['icono' => '🏢',   'tono' => 'mint',   'titulo' => 'Capacitadores y RR. HH.', 'texto' => 'Dinamiza inducciones y talleres corporativos sin depender de un diseñador.'],
    ['icono' => '🎙️',  'tono' => 'sun',    'titulo' => 'Creadores de contenido',  'texto' => 'Añade un momento interactivo a tus lives, webinars o publicaciones.'],
    ['icono' => '🎉',   'tono' => 'violet', 'titulo' => 'Comunidades y eventos',   'texto' => 'Iglesias, clubes, familias: una dinámica rápida sin curva de aprendizaje.'],
];

$pasos = [
    [
        'titulo' => 'Crea un paquete o un juego',
        'texto'  => 'Un paquete reúne las actividades que quieras sobre un tema. Un juego es más rápido: escribes tus palabras una vez y Ludia arma el resto.',
    ],
    [
        'titulo' => 'Escribe tu contenido',
        'texto'  => 'Preguntas, parejas de palabras o imágenes. En los paquetes también puedes cargarlas desde una plantilla de Excel.',
    ],
    [
        'titulo' => 'Proyecta o envía, y a jugar',
        'texto'  => 'En vivo con un código en el proyector, o por enlace para jugar desde casa. Tu público entra sin crear cuenta y, al final, salen las posiciones y el informe.',
    ],
];

$valores = [
    ['icono' => '🎲', 'titulo' => $totalFormatos . ' formatos de actividad', 'texto' => 'Quiz, verdadero o falso, completar, relacionar, clasificar, ordenar, sopa de letras, crucigrama, mural colaborativo, vídeo con preguntas y más.'],
    ['icono' => '🎮', 'titulo' => $totalJuegos . ' juegos para gamificar', 'texto' => 'Las mismas palabras se juegan como avión, laberinto, nave espacial, catapulta, memoria o quiz. Cambias de juego con un clic, sin reescribir nada.'],
    ['icono' => '📽️', 'titulo' => 'Tres formas de usarlo',    'texto' => 'Proyectado en clase con un código en pantalla, enviado por enlace para resolverlo desde casa, o descargado para tu web o tu plataforma (Moodle y otras).'],
    // Esto no es solo una comodidad, es el argumento de privacidad más fuerte
    // que tiene el producto, y estaba contado como si fuera un detalle de
    // usabilidad. A un colegio que pregunta "¿qué datos de mis estudiantes se
    // guardan?" hay que poder responderle en una línea: el nombre, y nada más.
    ['icono' => '🙋', 'titulo' => 'Tus participantes no dejan datos', 'texto' => 'Entran con el código o el QR, eligen su personaje y juegan: sin cuenta, sin correo y sin contraseña. Lo único que se guarda de ellos es el nombre con el que entran. Solo quien crea necesita cuenta.'],
    ['icono' => '📊', 'titulo' => 'Cargas desde Excel',        'texto' => 'Descargas la plantilla, la llenas con tus preguntas y la subes: se crean todas las actividades de una vez.'],
    ['icono' => '🖼️', 'titulo' => 'Con tus imágenes',          'texto' => 'Ilustra las preguntas con fotos o diagramas. Se ven en el celular de cada participante y en la proyección.'],
    ['icono' => '🏆', 'titulo' => 'Puntos, marcador y podio',  'texto' => 'Cada respuesta suma según el acierto y la rapidez. El marcador se ve en el proyector mientras juegan, y también por equipos.'],
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
$todasLasActividades = count(plan_formatos('pro'));

$planes = [];
foreach (planes_todos() as $clave => $p) {
    $suyas = count(plan_formatos($clave));
    $gratis = $clave === 'gratis';
    $escuela = $clave === 'escuela';

    // Juegos: las plantillas son formatos, así que el plan que las incluye sale
    // del mismo catálogo. Los de acción (avión, laberinto…) se cuentan aparte
    // porque son lo que más se nota en clase.
    $susJuegos = array_values(array_intersect(array_keys(JUEGO_PLANTILLAS), plan_formatos($clave)));
    $deAccion = array_intersect($susJuegos, ['avion', 'globos', 'atrapa', 'laberinto', 'tren', 'catapulta', 'salta', 'mina', 'obstaculos', 'nave']);

    $items = [
        ['si', $suyas >= $todasLasActividades
            ? 'Las ' . $todasLasActividades . ' actividades, completas'
            : $suyas . ' de las ' . $todasLasActividades . ' actividades'],
        ['si', count($susJuegos) >= $totalJuegos
            ? 'Los ' . $totalJuegos . ' juegos para gamificar'
            : count($susJuegos) . ' de los ' . $totalJuegos . ' juegos para gamificar'],
        [$deAccion ? 'si' : 'no', 'Juegos de acción: avión, laberinto, nave, catapulta…'],
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
        $accion = ['url' => base_url('escuela.php'), 'texto' => 'Pedir información'];
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

/*
 * Preguntas frecuentes: las dudas reales de un docente que llega desde Google.
 * Van en la página y, con las mismas palabras, en FAQPage para los buscadores.
 */
$preguntas = [
    ['¿Qué es Ludia?', 'Ludia (ludia.click) es una plataforma educativa para gamificar las clases. Creas paquetes de actividades —quiz, sopa de letras, crucigrama, mural, vídeo con preguntas— o juegos a partir de tus palabras —avión, laberinto, nave espacial, catapulta— y tu grupo los juega desde el celular.'],
    ['¿Mis estudiantes necesitan crear una cuenta?', 'No. Entran con un código de seis dígitos, un QR o un enlace, eligen su personaje y juegan. De ellos solo se guarda el nombre con el que entran. Solo quien crea las actividades necesita cuenta.'],
    ['¿Es gratis?', 'Sí, hay un plan gratis para siempre con el que puedes dar clase de verdad. Los planes de pago, en pesos colombianos y sin cobro automático, suman los juegos de acción, todas las actividades, las imágenes y el informe de cada sesión.'],
    ['¿Sirve como alternativa a Kahoot o Wordwall?', 'Sí: como en Kahoot, puedes jugar en vivo con el proyector, con puntos y marcador; como en Wordwall, escribes tus palabras una vez y las juegas en muchos juegos distintos. Y todo en español, con precios en pesos.'],
    ['¿Se puede jugar sin estar en clase?', 'Sí. Además de jugar en vivo, puedes enviar el enlace para que cada estudiante juegue desde casa a su ritmo, y ver cómo va cada uno.'],
    ['¿Funciona con Moodle u otra plataforma?', 'Sí. En el plan Pro descargas tus paquetes y juegos en formato SCORM, que se sube a Moodle y a otras plataformas, o como una página HTML que funciona sin internet.'],
    ['¿Para qué edades sirve?', 'Para primaria, secundaria y universidad, y también para capacitaciones en empresas, talleres y eventos. Tú decides el contenido y el nivel.'],
];

$fichaPreguntas = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn (array $p): array => [
        '@type'          => 'Question',
        'name'           => $p[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p[1]],
    ], $preguntas),
];

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
    'publisher'           => ['@id' => url_absoluta('/') . '#organizacion'],
];

require __DIR__ . '/includes/partials/header.php';
?>
<script type="application/ld+json">
<?= json_encode($fichaBuscadores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
<script type="application/ld+json">
<?= json_encode($fichaPreguntas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<main id="contenido">
  <!-- Hero -->
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">🧩 Paquetes de actividades · 🎮 Juegos</span>
        <h1>Gamifica tu clase:<br>tu tema, convertido en <em>juego</em>.</h1>
        <p class="hero-sub">Arma <b>paquetes</b> con <?= (int) $totalFormatos ?> tipos de actividad, o crea un <b>juego</b> escribiendo tus palabras una sola vez: se juega como avión, laberinto, nave espacial y <?= (int) $totalJuegos - 3 ?> formas más. Con puntos, marcador y podio. Tu grupo entra con un código, sin crear cuenta.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e($urlCrear) ?>">Empezar gratis</a>
          <a class="btn btn-ghost" href="#como-funciona">Ver cómo funciona</a>
        </div>
        <div class="hero-meta">
          <div class="avatars" aria-hidden="true"><span>🧑‍🏫</span><span>🎙️</span><span>🧑‍💼</span></div>
          <span>Primaria, secundaria, universidad y capacitaciones</span>
        </div>
      </div>

      <?php /* Capturas REALES de Ludia (assets/img/home): el proyector con el
               marcador y el celular de un estudiante jugando. Si se sube una foto
               del salón (salon.webp), va de fondo. */ ?>
      <figure class="hero-visual">
        <?php if ($fotoSalon): ?>
          <img class="hero-fondo hero-foto" src="<?= e(asset('img/home/salon.webp')) ?>" alt="Niños jugando con Ludia en el salón: el laberinto en la pantalla grande y en sus celulares" width="1600" height="893" fetchpriority="high">
        <?php else: ?>
          <img class="hero-fondo" src="<?= e(asset('img/home/proyector-marcador.webp')) ?>" alt="El proyector de la clase con la pregunta, las cuatro opciones y el marcador «Van ganando»" width="1608" height="1197" fetchpriority="high">
        <?php endif; ?>
        <img class="hero-celular" src="<?= e(asset('img/home/celular-avion.webp')) ?>" alt="El celular de un estudiante jugando «Vuela a la respuesta»: el avión atraviesa la nube con la capital correcta" width="648" height="933">
        <span class="hero-burbuja b1" aria-hidden="true">✓ +870</span>
        <span class="hero-burbuja b2" aria-hidden="true">🏆 1.º Mateo · 2 840</span>
      </figure>
    </div>
  </section>

  <!-- Dos formas de crear -->
  <section class="dos-caminos" id="crear">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Qué puedes crear</span>
        <h2>Un paquete completo o un juego en dos minutos.</h2>
        <p>Los dos se juegan en vivo con el proyector o se envían por enlace. Tú eliges según la clase.</p>
      </div>
      <div class="caminos-grid">
        <article class="camino">
          <div class="camino-icono" aria-hidden="true">📚</div>
          <h3>Paquete de actividades</h3>
          <p>Todo un tema en una sola sesión: mezcla los formatos que quieras, con imágenes y vídeo.</p>
          <ul class="camino-lista">
            <li><?= (int) $totalFormatos ?> formatos: quiz, completar, ordenar, clasificar, sopa de letras, crucigrama, mural, vídeo con preguntas…</li>
            <li>Diapositivas para explicar entre actividad y actividad</li>
            <li>Carga masiva desde una plantilla de Excel</li>
            <li>Informe con aciertos por pregunta</li>
          </ul>
          <a class="btn btn-ghost" href="<?= e(base_url('app/crear.php?nuevo=paquete')) ?>">Crear un paquete</a>
        </article>
        <article class="camino camino-juego">
          <div class="camino-icono" aria-hidden="true">🎮</div>
          <h3>Juego para gamificar</h3>
          <p>Escribe parejas de palabras (o pon imágenes) y Ludia arma el juego. Como en una consola, pero con tu tema.</p>
          <ul class="camino-lista">
            <li><?= (int) $totalJuegos ?> juegos con el mismo contenido: avión, laberinto, nave, catapulta, globos, memoria…</li>
            <li>Cambias de juego con un clic, sin reescribir nada</li>
            <li>En vivo eliges en cada paso con qué juego seguir</li>
            <li>Enviado, cada estudiante escoge cómo jugar</li>
          </ul>
          <a class="btn btn-primary" href="<?= e(base_url('app/juego.php')) ?>">Crear un juego</a>
        </article>
      </div>
    </div>
  </section>

  <!-- Tres formas de usarlo -->
  <section class="tres-formas" id="formas">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Cómo lo usas</span>
        <h2>En vivo, por enlace o en tu propia plataforma.</h2>
        <p>Lo que creas una vez sirve para la clase presencial, para la tarea en casa y para tu aula virtual.</p>
      </div>
      <div class="formas-grid">
        <article class="forma forma-vivo">
          <div class="forma-icono" aria-hidden="true">📽️</div>
          <h3>Juega en vivo</h3>
          <p>Proyecta el código o el QR y tu grupo entra desde el celular. Ves las respuestas llegar y el marcador en la pantalla grande.</p>
          <ul>
            <li>Tú marcas el ritmo, pregunta a pregunta</li>
            <li>En un juego, eliges en cada paso con cuál seguir: nave, parejas, laberinto…</li>
            <li>Por equipos, si quieres</li>
          </ul>
        </article>
        <article class="forma forma-enlace">
          <div class="forma-icono" aria-hidden="true">🔗</div>
          <h3>Envía el enlace</h3>
          <p>Para la tarea o el repaso en casa: compártelo por WhatsApp, correo o tu aula virtual, y cada quien juega a su ritmo.</p>
          <ul>
            <li>Ves el avance de cada estudiante</li>
            <li>En un juego, cada uno escoge cómo jugarlo</li>
            <li>Sin cuenta ni contraseña para tus estudiantes</li>
          </ul>
        </article>
        <article class="forma forma-descarga">
          <div class="forma-icono" aria-hidden="true">⬇️</div>
          <h3>Descárgalo para tu plataforma</h3>
          <p>Publica tus paquetes <b>y tus juegos</b> en tu web, tu blog o tu aula virtual, aunque allí no haya Ludia.</p>
          <ul>
            <li>SCORM para Moodle y otras plataformas: la nota llega al libro de calificaciones</li>
            <li>Una página HTML que funciona sin internet</li>
            <li>Los juegos se descargan como quiz, relacionar o verdadero o falso; los de acción (avión, laberinto…) se juegan en Ludia · plan Pro</li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <!-- Los juegos, con capturas reales -->
  <section class="juegos-vitrina" id="juegos">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Así se juega</span>
        <h2>Juegos de verdad, en el celular de cada estudiante.</h2>
        <p>La pregunta sale grande arriba y la respuesta se elige jugando. El servidor califica y suma los puntos al instante.</p>
      </div>
      <div class="vitrina-grid">
        <?php foreach ($juegosAccion as $j): ?>
          <figure class="vitrina-item">
            <img src="<?= e(asset('img/home/' . $j['img'] . '.webp')) ?>" alt="Captura de <?= e(trim(preg_replace('/^\S+\s/u', '', $j['nombre']))) ?> en el celular" width="<?= (int) $j['w'] ?>" height="<?= (int) $j['h'] ?>" loading="lazy">
            <figcaption><b><?= e($j['nombre']) ?></b><?= e($j['texto']) ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="vitrina-proyector">
        <img src="<?= e(asset('img/home/proyector-marcador.webp')) ?>" alt="El proyector durante la partida: la pregunta, las opciones y el marcador con los cinco primeros" width="1608" height="1197" loading="lazy">
        <div>
          <h3>🏆 El marcador, en el proyector</h3>
          <p>Mientras juegan desde el celular, la clase ve la pregunta y quién va ganando. Al final, el podio. Y si juegan por equipos, el marcador es por equipo.</p>
          <a class="btn btn-primary" href="<?= e($urlCrear) ?>">Probar gratis</a>
        </div>
      </div>
    </div>
  </section>

  <!-- En el aula -->
  <section class="en-clase" id="en-clase">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">En el aula</span>
        <h2>Así se vive una clase con Ludia.</h2>
        <p>De primaria a la universidad: la pantalla grande para todos, el celular para cada quien, y tú decides qué juego sigue.</p>
      </div>
      <div class="en-clase-grid">
        <figure class="en-clase-foto ancha">
          <img src="<?= e(asset('img/home/clase-avion.webp')) ?>" alt="Estudiantes de primaria frente a la pantalla jugando «Vuela a la respuesta» mientras la profesora sigue la partida" width="1400" height="781" loading="lazy">
          <figcaption>✈️ En vivo, la clase entera juega la misma pregunta.</figcaption>
        </figure>
        <figure class="en-clase-foto">
          <img src="<?= e(asset('img/home/clase-vf.webp')) ?>" alt="Estudiantes de secundaria respondiendo un verdadero o falso desde sus celulares" width="800" height="1433" loading="lazy">
          <figcaption>🎓 Secundaria y educación superior.</figcaption>
        </figure>
        <figure class="en-clase-foto">
          <img src="<?= e(asset('img/home/clase-celulares.webp')) ?>" alt="Jóvenes jugando en sus celulares mientras el profesor proyecta el juego" width="800" height="1433" loading="lazy">
          <figcaption>📱 Del colegio a la universidad: cada quien desde su celular, sin crear cuenta.</figcaption>
        </figure>
      </div>
      <p class="en-clase-nota">Fotos ilustrativas creadas con IA. Las capturas de los juegos de más arriba son reales.</p>
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
        <p>El plan gratis sirve para dar clase de verdad, no para una demostración. Los de pago suman los juegos de acción, el catálogo completo, las imágenes y el informe de cada sesión.</p>
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

  <!-- Preguntas frecuentes -->
  <section class="faq" id="preguntas">
    <div class="wrap wrap-estrecho">
      <div class="section-head">
        <span class="eyebrow">Preguntas frecuentes</span>
        <h2>Lo que suelen preguntar los docentes.</h2>
      </div>
      <?php foreach ($preguntas as [$pregunta, $respuesta]): ?>
        <details class="faq-item">
          <summary><?= e($pregunta) ?></summary>
          <p><?= e($respuesta) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- CTA final -->
  <section>
    <div class="wrap">
      <div class="cta-final">
        <h2>Tu próxima clase puede ser un juego.</h2>
        <p>Crea un paquete o un juego, proyecta el código y deja que tu grupo juegue. Al terminar tendrás el podio y el informe.</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="<?= e($urlCrear) ?>">Empezar gratis</a>
          <a class="btn btn-ghost" href="#como-funciona">Ver cómo funciona</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
