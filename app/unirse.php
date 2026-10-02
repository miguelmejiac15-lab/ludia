<?php
/**
 * Entrada del participante: código, creación del personaje y sala de espera.
 * No requiere cuenta: el participante queda identificado por un token temporal.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$codigo = preg_replace('/\D/', '', (string) ($_GET['c'] ?? ''));

$page = [
    'title'       => 'Entrar a una sesión — Ludia',
    'description' => 'Crea tu personaje y entra con el código de la sesión.',
    'home'        => false,
];

$extraStyles  = ['css/sesion.css', 'css/jugar.css'];
$extraScripts = ['js/personaje.js', 'js/jugar.js', 'js/unirse.js'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="unirse" data-unirse data-codigo="<?= e($codigo) ?>">
  <div class="wrap">

    <!-- Paso 1: código + personaje -->
    <section class="panel unirse-card" id="paso-acceso">
      <span class="eyebrow">Entrar a una sesión</span>
      <h1>Crea tu personaje</h1>
      <p>Así te verán en la pantalla del anfitrión.</p>

      <div class="field" style="margin-top:18px">
        <label for="campo-codigo">Código de la sesión</label>
        <input class="input codigo-input" id="campo-codigo" type="text" inputmode="numeric" autocomplete="off"
               maxlength="7" placeholder="000000" value="<?= e($codigo) ?>">
      </div>

      <div class="avatar-escena">
        <div id="avatar-vista"></div>
        <span class="avatar-nombre" id="avatar-nombre">Tu personaje</span>
      </div>

      <div class="selector">
        <span id="etiqueta-criatura">Elige tu criatura</span>
        <div class="opciones" id="opciones-criatura" role="group" aria-labelledby="etiqueta-criatura"></div>
      </div>

      <div class="selector">
        <span id="etiqueta-color">Color</span>
        <div class="opciones" id="opciones-color" role="group" aria-labelledby="etiqueta-color"></div>
      </div>

      <div class="selector">
        <span id="etiqueta-accesorio">Accesorio</span>
        <div class="opciones" id="opciones-accesorio" role="group" aria-labelledby="etiqueta-accesorio"></div>
      </div>

      <div class="field" style="margin-top:18px;text-align:left">
        <label for="campo-nombre">Tu nombre en la sesión</label>
        <div class="nombre-fila">
          <input class="input" id="campo-nombre" type="text" maxlength="24" autocomplete="off" placeholder="Ej. Zorro veloz">
          <button class="btn btn-ghost btn-sm" type="button" id="btn-sugerir" aria-label="Sugerir otro nombre">🎲</button>
        </div>
      </div>

      <p class="mensaje error" id="mensaje" hidden></p>

      <div class="hero-cta" style="margin-top:18px">
        <button class="btn btn-primary btn-block" type="button" id="btn-entrar">Entrar a la sesión</button>
      </div>
    </section>

    <!-- Paso 2: sala de espera -->
    <section class="panel unirse-card espera" id="paso-espera" hidden>
      <span class="eyebrow" id="espera-estado">Ya estás dentro</span>
      <h1 id="espera-titulo">Espera a que comience</h1>
      <p id="espera-sub">El anfitrión iniciará la sesión en un momento.</p>

      <div class="avatar-escena">
        <div id="avatar-final"></div>
        <span class="avatar-nombre" id="nombre-final"></span>
        <span class="puntos-espera" aria-hidden="true"><span></span><span></span><span></span></span>
      </div>

      <p class="hint" id="espera-conteo"></p>
      <div class="espera-lista" id="espera-lista"></div>
    </section>

    <!-- Paso 3: jugando -->
    <section class="panel" id="paso-juego" hidden>
      <div class="juego-top">
        <span id="juego-yo"></span>
        <span class="j-crono" id="juego-crono">⏱ —</span>
        <span class="j-puntaje" id="juego-puntaje">⭐ 0</span>
      </div>
      <p class="j-paso" id="juego-paso"></p>
      <div id="juego-cuerpo"></div>
    </section>

    <!-- Paso 4: resultados -->
    <section class="panel unirse-card" id="paso-final" hidden>
      <span class="eyebrow">Sesión terminada</span>
      <h1 id="final-titulo">Gracias por participar</h1>
      <p id="final-sub"></p>
      <div class="podio" id="final-podio"></div>
    </section>

  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
