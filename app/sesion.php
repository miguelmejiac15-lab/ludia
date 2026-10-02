<?php
/**
 * Sala del anfitrión: código de acceso, enlace, QR y participantes en vivo.
 * El control de la sesión se autoriza con el anfitrion_token que el editor
 * guardó en este navegador al crearla.
 */

require_once __DIR__ . '/../includes/Sesiones.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';

// La sala es del anfitrión: exige cuenta. El público entra por app/unirse.php.
$usuario = auth_requerir();

// ¿Esta sesión dejará informe al cerrarla? Depende del plan del dueño, no del
// modo: el contenido ya no caduca, lo que caduca es la sesión.
$guardaInforme = plan_permite($usuario, 'informe');

$codigo = preg_replace('/\D/', '', (string) ($_GET['c'] ?? ''));
$sesion = strlen($codigo) === 6 ? sesion_por_codigo($codigo) : null;

$page = [
    'title'       => $sesion ? 'Sala · ' . $sesion['nombre'] . ' — Ludia' : 'Sala no encontrada — Ludia',
    'description' => 'Comparte el código o el QR para que tu público entre a la sesión.',
    'home'        => false,
];

$extraStyles  = ['css/sesion.css', 'css/jugar.css'];
$extraScripts = ['js/vendor/qrcode.min.js', 'js/personaje.js', 'js/sala.js'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<?php if (!$sesion): ?>
  <main id="contenido" class="page-simple">
    <div class="wrap">
      <span class="eyebrow">Sala no encontrada</span>
      <h1>Esta sesión ya no está disponible.</h1>
      <p>El código no existe o la sesión caducó. En el plan gratis las sesiones no se guardan: duran <?= SESION_HORAS_VIDA ?> horas desde que se crean.</p>
      <div class="hero-cta">
        <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>">Crear una sesión nueva</a>
        <a class="btn btn-ghost" href="<?= e(base_url('/')) ?>">Volver al inicio</a>
      </div>
    </div>
  </main>
<?php else: ?>
  <main id="contenido" class="sala"
        data-sala
        data-codigo="<?= e($sesion['codigo']) ?>"
        data-max="<?= (int) $sesion['max_participantes'] ?>"
        data-modo="<?= e($sesion['modo']) ?>"
        <?php /* Quien es dueño de la sesión es su anfitrión, y el token se lo
                 entrega el servidor. Antes vivía SOLO en el localStorage del
                 navegador que la creó: lanzarla desde la lista de paquetes, o
                 limpiar el navegador, dejaba al profesor sin control de su
                 propia sala. */ ?>
        <?php if ($usuario && (int) $sesion['usuario_id'] === (int) $usuario['id']): ?>
        data-token="<?= e($sesion['anfitrion_token']) ?>"
        <?php endif; ?>
        data-estado="<?= e($sesion['estado']) ?>">

    <div class="wrap sala-grid" id="lobby">
      <!-- Acceso: código, enlace y QR -->
      <section class="panel acceso">
        <span class="eyebrow"><?= $sesion['modo'] === 'enviar' ? 'Paquete enviado' : 'Sala de espera' ?></span>
        <h1><?= e($sesion['nombre']) ?></h1>
        <p class="sala-sub"><?= (int) $sesion['total_actividades'] ?> actividades · hasta <?= (int) $sesion['max_participantes'] ?> participantes</p>
        <?php if ($sesion['modo'] === 'enviar'): ?>
          <p class="sala-sub">Comparte el enlace o el QR: cada quien lo juega a su ritmo, sin que tengas que estar presente.</p>
        <?php endif; ?>

        <div class="codigo-caja">
          <span class="codigo-label">Código de entrada</span>
          <strong class="codigo" id="codigo"><?= e(substr($sesion['codigo'], 0, 3) . ' ' . substr($sesion['codigo'], 3)) ?></strong>
          <span class="codigo-pie">En <b><?= e($_SERVER['HTTP_HOST'] ?? 'localhost') ?><?= e(base_url('app/unirse.php')) ?></b></span>
        </div>

        <div class="qr-caja">
          <div class="qr" id="qr" role="img" aria-label="Código QR para entrar a la sesión"></div>
          <p class="hint">Escanea el QR o abre el enlace para entrar.</p>
        </div>

        <div class="enlace-fila">
          <input class="input" id="enlace" type="text" readonly aria-label="Enlace para entrar">
          <button class="btn btn-ghost btn-sm" type="button" id="btn-copiar">Copiar</button>
        </div>
        <p class="hint" id="aviso-copia" hidden>Enlace copiado ✓</p>
      </section>

      <!-- Participantes conectados -->
      <section class="panel participantes-panel">
        <div class="panel-head">
          <h2 class="panel-title"><?= $sesion['modo'] === 'enviar' ? 'Avance de tu público' : 'Participantes' ?></h2>
          <span class="contador" id="contador">0 de <?= (int) $sesion['max_participantes'] ?></span>
        </div>
        <div class="participantes" id="participantes" aria-live="polite"></div>
        <div class="progreso-lista" id="progreso" hidden></div>

        <?php /* Equipos: solo en vivo y solo antes de empezar. Rearmarlos a
                 mitad de partida movería puntos ya ganados de un equipo a otro
                 y el marcador dejaría de cuadrar, así que el servidor lo
                 rechaza y aquí ni se ofrece. */ ?>
        <?php if ($sesion['modo'] !== 'enviar'): ?>
          <div class="equipos-caja" id="equipos-caja" hidden>
            <div class="equipos-barra">
              <span class="equipos-titulo">Equipos</span>
              <label class="equipos-cuantos" for="equipos-cuantos">
                ¿Cuántos?
                <select class="select select-sm" id="equipos-cuantos">
                  <option value="2">2</option>
                  <option value="3" selected>3</option>
                  <option value="4">4</option>
                  <option value="5">5</option>
                  <option value="6">6</option>
                </select>
              </label>
              <button class="btn btn-ghost btn-sm" type="button" id="btn-equipos-azar">🎲 Al azar</button>
              <button class="btn btn-ghost btn-sm" type="button" id="btn-equipos-mano">✋ Yo los armo</button>
              <button class="link-btn" type="button" id="btn-equipos-deshacer" hidden>Deshacer</button>
            </div>
            <p class="hint" id="equipos-aviso">Reparte a tu grupo en equipos: los puntos de cada quien suman al suyo.</p>
            <div class="equipos-lista" id="equipos-lista"></div>
          </div>
        <?php endif; ?>

        <p class="hint" id="estado-sala">Esperando a que entre tu público…</p>
      </section>
    </div>

    <!-- Pregunta proyectada mientras la sesión está en curso -->
    <div class="wrap">
      <section class="panel proyeccion" id="proyeccion" hidden>
        <div class="proy-top">
          <span class="proy-titulo" id="proy-titulo"></span>
          <span class="proy-crono" id="proy-crono">—</span>
          <span class="proy-conteo" id="proy-conteo">0 respuestas</span>
        </div>
        <div id="proy-cuerpo"></div>
        <div class="proy-barra" aria-hidden="true"><span id="proy-barra" style="width:0%"></span></div>

        <!-- Marcador en vivo: quién va ganando, mientras se juega. -->
        <aside class="marcador" id="marcador" aria-live="polite" hidden></aside>
      </section>

      <!-- Posiciones e informe al terminar -->
      <section class="panel proyeccion" id="resultado" hidden>
        <span class="eyebrow">Resultados</span>
        <h2 class="proy-pregunta" style="margin-top:12px">Posiciones finales</h2>
        <div class="podio" id="podio"></div>
        <div class="informe" id="informe"></div>
      </section>
    </div>

    <div class="builder-bar">
      <div class="inner">
        <span class="status" id="bar-status"><?= $guardaInforme
          ? 'Al cerrarla quedará su informe en «Informes» · tu paquete no se toca'
          : 'Esta sesión no deja informe · caduca en ' . SESION_HORAS_VIDA . ' horas' ?></span>
        <span class="acciones-sala">
          <button class="btn btn-ghost" type="button" id="btn-terminar" hidden>Terminar</button>
          <button class="btn btn-primary" type="button" id="btn-comenzar" disabled>Comenzar sesión</button>
          <button class="btn btn-primary" type="button" id="btn-siguiente" hidden>Siguiente →</button>
        </span>
      </div>
    </div>
  </main>
<?php endif; ?>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
