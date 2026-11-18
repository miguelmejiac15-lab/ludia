<?php
/**
 * Armado del paquete, en tres pasos:
 *   1. Tema y público
 *   2. Actividades del tema (las sub-actividades)
 *   3. Revisar y guardar
 *
 * Aquí solo se trabaja el CONTENIDO. Cómo se usa —jugarlo en vivo o enviarlo—
 * se decide después, desde "Mis paquetes", y cada vez que se hace nace una
 * sesión aparte con su propio código.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Paquetes.php';
require_once __DIR__ . '/../includes/Planes.php';

// Crear paquetes exige cuenta; jugar no.
$usuario = auth_requerir();

/**
 * "Editar" desde Mis paquetes: se precarga el paquete tal como está.
 *
 * Ya no hay condiciones: un paquete se puede editar SIEMPRE, lo hayan jugado o
 * no. Cada sesión guarda su propia copia del contenido, así que cambiarlo ahora
 * no toca ni un informe de los que ya se emitieron.
 */
$editar = null;
$idEditar = (int) ($_GET['editar'] ?? 0);
if ($idEditar > 0) {
    $paquete = paquete_de($idEditar, (int) $usuario['id']);

    // Un juego se edita en su propia pantalla: aquí se rompería su contenido.
    if ($paquete && ($paquete['clase'] ?? 'paquete') === 'juego') {
        header('Location: ' . base_url('app/juego.php') . '?id=' . (int) $paquete['id'] . '&editar=1');
        exit;
    }

    if ($paquete) {
        $editar = [
            'id'          => (int) $paquete['id'],
            'nombre'      => $paquete['nombre'],
            'audiencia'   => $paquete['audiencia'],
            'actividades' => paquete_actividades($paquete),
        ];
    }
}

/*
 * Antes de armar nada: ¿un paquete o un juego? (18/11/2026)
 * Paquete = una clase con varias pantallas. Juego = una lista de palabras que
 * se juega de muchas formas (app/juego.php). Se salta al editar y con
 * ?nuevo=paquete, que es a donde lleva la tarjeta de la izquierda.
 */
if (!$editar && ($_GET['nuevo'] ?? '') !== 'paquete') {
    $page = [
        'title'       => 'Crear — Ludia',
        'description' => 'Crea un paquete para tu clase o un juego con tus palabras.',
        'home'        => false,
    ];
    $extraStyles = ['css/crear.css', 'css/juego.css'];
    require LUDIA_ROOT . '/includes/partials/header.php';
    ?>
    <main id="contenido" class="juego-pagina">
      <div class="wrap">
        <div class="paso-head">
          <span class="eyebrow">Crear</span>
          <h1>¿Qué quieres crear hoy?</h1>
        </div>
        <div class="elegir-crear">
          <a class="elegir-op" href="<?= e(base_url('app/crear.php')) ?>?nuevo=paquete" data-elegir="paquete">
            <span class="elegir-icono" aria-hidden="true">📚</span>
            <h2>Un paquete</h2>
            <p>Una clase completa: varias pantallas seguidas.</p>
            <ul>
              <li>Página informativa, preguntas, vídeo, mural, encuesta…</li>
              <li>En vivo o por enlace, con informe.</li>
            </ul>
            <span class="btn btn-ghost">Crear paquete →</span>
          </a>
          <a class="elegir-op juego" href="<?= e(base_url('app/juego.php')) ?>" data-elegir="juego">
            <span class="elegir-icono" aria-hidden="true">🎲</span>
            <h2>Un juego <span class="elegir-nuevo">Nuevo</span></h2>
            <p>Escribes tus palabras una vez y las juegas de muchas formas.</p>
            <ul>
              <li>Relacionar, memoria, quiz, sopa de letras…</li>
              <li>Cambias de juego con un clic, sin reescribir nada.</li>
            </ul>
            <span class="btn btn-primary">Crear juego →</span>
          </a>
        </div>
      </div>
    </main>
    <?php
    require LUDIA_ROOT . '/includes/partials/footer.php';
    exit;
}

$page = [
    'title'       => $editar ? 'Editar paquete — Ludia' : 'Crear paquete — Ludia',
    'description' => 'Arma un paquete de actividades para tu grupo: quiz, verdadero o falso, sopa de letras, mural y más.',
    'home'        => false,
];

$extraStyles  = ['css/crear.css'];
$extraScripts = ['js/formatos.js', 'js/editor.js', 'js/crear.js'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<script>
  /* Lo que incluye el plan (para candados y avisos) y, si se está editando, el
     paquete que se va a modificar. Autorizar sigue siendo cosa del servidor.
     Va antes que los scripts con defer, así que ellos ya lo encuentran. */
  window.LudiaPlan = <?= json_encode(plan_para_js($usuario), JSON_UNESCAPED_UNICODE) ?>;
  window.LudiaEditarPaquete = <?= json_encode($editar, JSON_UNESCAPED_UNICODE) ?>;
</script>

<main id="contenido" data-builder>

  <!-- Barra de pasos -->
  <nav class="pasos" aria-label="Pasos para armar el paquete">
    <ol class="wrap pasos-lista" id="pasos"></ol>
  </nav>

  <!-- Paso 1: tema y público -->
  <section class="wrap paso" id="paso-1" hidden>
    <div class="paso-head">
      <span class="eyebrow">Paso 1 de 3</span>
      <h1>¿Sobre qué tema es?</h1>
      <p>El tema agrupa todas las actividades del paquete. Por ejemplo: «Las sumas» o «Inducción de seguridad».</p>
    </div>
    <div class="panel paso-form">
      <div class="field">
        <label for="sesion-nombre">Tema del paquete</label>
        <input class="input" id="sesion-nombre" type="text" maxlength="80" data-sesion="nombre"
               placeholder="Ej. Las sumas">
        <span class="hint">Es lo que verá tu público al entrar.</span>
      </div>
      <div class="field">
        <label for="sesion-audiencia">¿Para quién es?</label>
        <select class="select" id="sesion-audiencia" data-sesion="audiencia"></select>
      </div>
    </div>
  </section>

  <!-- Paso 2: actividades del tema -->
  <section class="paso" id="paso-2" hidden>
    <div class="wrap resumen-tema" id="resumen-tema"></div>

    <div class="builder">
      <aside class="side-col" aria-label="Actividades del paquete">
        <div class="panel">
          <h2 class="panel-title">Actividades del tema</h2>
          <p class="hint" style="margin-bottom:12px">Agrega todas las que quieras: preguntas, verdadero o falso, llenar huecos, sopa de letras…</p>
          <div class="slots" id="slots"></div>
        </div>
        <div class="panel">
          <h2 class="panel-title">Cargar desde Excel</h2>
          <p class="hint" style="margin-bottom:12px">Descarga la plantilla, llénala con tus preguntas y súbela: se crean todas las actividades de una vez.</p>
          <a class="btn btn-ghost btn-block" href="<?= e(base_url('app/plantilla.php')) ?>">⬇ Descargar plantilla</a>
          <label class="btn btn-ghost btn-block" for="archivo-excel" style="margin-top:8px">⬆ Subir plantilla llena</label>
          <input id="archivo-excel" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
          <p class="mensaje" id="excel-estado" hidden></p>
        </div>

        <div class="panel">
          <h2 class="panel-title">Tu plan</h2>
          <div class="plan-box" id="plan-box"></div>
        </div>
      </aside>

      <section class="main-col" id="main-col" aria-live="polite"></section>

      <aside class="preview-col" aria-label="Vista del participante">
        <div class="panel">
          <h2 class="panel-title">Vista del participante</h2>
          <div class="phone"><div class="phone-screen" id="preview"></div></div>
        </div>
      </aside>
    </div>
  </section>

  <!-- Paso 3: revisar y crear -->
  <section class="wrap paso" id="paso-3" hidden>
    <div class="paso-head">
      <span class="eyebrow">Paso 3 de 3</span>
      <h1>Revisa tu paquete</h1>
      <p>Si todo está bien, guárdalo. Después eliges si lo juegas en vivo o lo envías a tus estudiantes, las veces que quieras.</p>
    </div>
    <div id="revision"></div>
  </section>

  <noscript>
    <div class="wrap"><div class="panel">Para armar un paquete necesitas tener JavaScript activado.</div></div>
  </noscript>
</main>

<div class="builder-bar">
  <div class="inner">
    <span class="status" id="bar-status">Borrador temporal en este navegador</span>
    <span class="acciones-paso">
      <button class="btn btn-ghost" type="button" id="btn-atras" hidden>← Atrás</button>
      <button class="btn btn-primary" type="button" id="btn-siguiente">Siguiente →</button>
      <button class="btn btn-primary" type="button" id="btn-continuar" hidden><?= $editar ? 'Guardar cambios →' : 'Guardar paquete →' ?></button>
    </span>
  </div>
</div>

<dialog class="dialog" id="dialog-importar" aria-labelledby="dialog-importar-titulo">
  <span class="eyebrow">Desde tu Excel</span>
  <h2 id="dialog-importar-titulo" style="margin-top:12px">Esto encontramos en tu archivo</h2>
  <div id="importar-cuerpo"></div>
  <div class="hero-cta">
    <button class="btn btn-primary" type="button" data-importar-aceptar>Agregar al paquete</button>
    <button class="btn btn-ghost" type="button" data-cerrar-importar>Cancelar</button>
  </div>
</dialog>

<dialog class="dialog" id="dialog-siguiente" aria-labelledby="dialog-siguiente-titulo">
  <span class="eyebrow">Guardando tu paquete</span>
  <h2 id="dialog-siguiente-titulo" style="margin-top:12px">Guardando el contenido</h2>
  <p>Después eliges si lo juegas en vivo o se lo envías a tus estudiantes.</p>
  <div class="hero-cta">
    <button class="btn btn-ghost" type="button" data-close-dialog>Seguir editando</button>
  </div>
</dialog>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
