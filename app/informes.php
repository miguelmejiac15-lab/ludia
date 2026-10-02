<?php
/**
 * Informes: qué pasó en cada sesión jugada o enviada.
 *
 * Es la otra mitad de la separación entre contenido y resultados. "Mis
 * paquetes" guarda lo que preparas; aquí queda lo que ocurrió cada vez que lo
 * usaste, con su fecha, sus participantes y sus aciertos.
 *
 * El informe se arma con el contenido COPIADO dentro de la sesión, nunca
 * leyendo el paquete: por eso sigue cuadrando aunque el paquete se haya
 * editado veinte veces después.
 *
 * Es una función de los planes de pago, y se comprueba en el servidor.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Informes.php';
require_once __DIR__ . '/../includes/Juego.php';
require_once __DIR__ . '/../includes/Planes.php';

$usuario   = auth_requerir();
$usuarioId = (int) $usuario['id'];
$aviso     = null;

// Borrar un informe, por POST con CSRF y con redirección después (PRG).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (auth_csrf_valido($_POST['csrf'] ?? null)) {
        $codigo = preg_replace('/\D/', '', (string) ($_POST['codigo'] ?? ''));
        $_SESSION['informe_aviso'] = $codigo !== '' && informe_eliminar($codigo, $usuarioId)
            ? ['ok' => true, 'mensaje' => 'Informe eliminado.']
            : ['ok' => false, 'mensaje' => 'No encontramos ese informe.'];
    }
    header('Location: ' . base_url('app/informes.php'));
    exit;
}

$avisoTipo = 'ok';
if (!empty($_SESSION['informe_aviso'])) {
    $aviso     = $_SESSION['informe_aviso']['mensaje'];
    $avisoTipo = $_SESSION['informe_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['informe_aviso']);
}

$guardaInformes = plan_permite($usuario, 'informe');

// ?s=CODIGO abre un informe concreto; ?p=ID filtra los de un paquete.
$codigoVer  = preg_replace('/\D/', '', (string) ($_GET['s'] ?? ''));
$paqueteVer = isset($_GET['p']) ? (int) $_GET['p'] : null;

$sesion = ($guardaInformes && strlen($codigoVer) === 6) ? informe_sesion($codigoVer, $usuarioId) : null;
$lista  = ($guardaInformes && !$sesion) ? informes_del_usuario($usuarioId, $paqueteVer ?: null) : [];

/**
 * Semáforo de todo el informe: menta bien, sol regular, coral flojo.
 * Nunca va solo: al lado siempre se imprime la cifra, porque un color sin
 * número no se lee en blanco y negro ni con daltonismo.
 */
$nivel = static function (?int $pct): string {
    if ($pct === null) {
        return '';
    }
    return $pct >= 70 ? 'alto' : ($pct >= 40 ? 'medio' : 'bajo');
};

$informe = [];
$ranking = [];
$preguntasPuntuables = 0;
$totalPreguntas = 0;
$totalRespuestas = 0;
$totalCorrectas = 0;
$masDificil = null;
$pctGlobal = null;
$puntajeLider = 0;

if ($sesion) {
    $informe = juego_informe($sesion);
    $ranking = juego_ranking((int) $sesion['id']);

    foreach ($informe as $actividad) {
        foreach ($actividad['preguntas'] as $pregunta) {
            $totalPreguntas++;

            // Mural, encuesta y pregunta abierta no se corrigen: cuentan como
            // aportes, no como aciertos, y mezclarlos falsearía el porcentaje.
            if (!$actividad['puntuable']) {
                continue;
            }

            $preguntasPuntuables++;
            $respuestas = (int) $pregunta['respuestas'];
            $correctas  = (int) $pregunta['correctas'];
            $totalRespuestas += $respuestas;
            $totalCorrectas  += $correctas;

            if ($respuestas > 0) {
                $pct = (int) round($correctas / $respuestas * 100);
                if ($masDificil === null || $pct < $masDificil['pct']) {
                    $masDificil = ['pct' => $pct, 'texto' => $pregunta['resumen']];
                }
            }
        }
    }

    if ($totalRespuestas > 0) {
        $pctGlobal = (int) round($totalCorrectas / $totalRespuestas * 100);
    }
    $puntajeLider = $ranking ? max(array_column($ranking, 'puntaje')) : 0;
}

$page = [
    'title'       => $sesion ? 'Informe · ' . $sesion['nombre'] . ' — Ludia' : 'Informes — Ludia',
    'description' => 'Resultados de cada sesión: participantes, aciertos y aportes.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css', 'css/informes.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">

    <?php if (!$guardaInformes): ?>
      <div class="panel vacio">
        <b aria-hidden="true">📊</b>
        <h1>Los informes son de los planes de pago</h1>
        <p>
          En el plan gratis puedes jugar y enviar todo lo que quieras, pero no se guarda
          lo que hacen tus estudiantes. Con un plan de pago, cada sesión deja su informe:
          quién participó, cuántos aciertos hubo en cada pregunta y qué escribieron.
        </p>
        <a class="btn btn-primary" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver los planes</a>
      </div>

    <?php elseif ($sesion): ?>
      <div class="inf-cabecera">
        <div>
          <span class="eyebrow"><?= e($sesion['modo'] === 'enviar' ? '📨 Paquete enviado' : '📽️ Jugado en vivo') ?></span>
          <h1><?= e($sesion['nombre']) ?></h1>
          <p class="cuenta-sub">
            <?= e(date('d/m/Y · H:i', strtotime((string) ($sesion['terminada_at'] ?? $sesion['updated_at'])))) ?>
            · <?= count($informe) ?> <?= count($informe) === 1 ? 'actividad' : 'actividades' ?>
          </p>
        </div>
        <a class="btn btn-ghost" href="<?= e(base_url('app/informes.php')) ?>">← Todos los informes</a>
      </div>

      <!-- Cifras de un vistazo -->
      <section class="inf-cifras" aria-label="Resumen del informe">
        <article class="inf-cifra">
          <span>Participantes</span>
          <b><?= count($ranking) ?></b>
          <small><?= $totalRespuestas ?> <?= $totalRespuestas === 1 ? 'respuesta' : 'respuestas' ?></small>
        </article>

        <article class="inf-cifra <?= e($nivel($pctGlobal)) ?>">
          <span>Aciertos</span>
          <b><?= $pctGlobal === null ? '—' : $pctGlobal . '%' ?></b>
          <small><?= $totalCorrectas ?> de <?= $totalRespuestas ?> correctas</small>
        </article>

        <article class="inf-cifra">
          <span>Preguntas</span>
          <b><?= $totalPreguntas ?></b>
          <small><?= $preguntasPuntuables ?> se califican</small>
        </article>

        <?php /* Solo tiene sentido señalar "la más difícil" si hay con qué
                 compararla y si de verdad costó: con una sola pregunta, o con
                 todo al 100 %, la tarjeta dice una tontería. */ ?>
        <?php if ($masDificil !== null && $preguntasPuntuables > 1 && $masDificil['pct'] < 100): ?>
          <article class="inf-cifra <?= e($nivel($masDificil['pct'])) ?>">
            <span>La más difícil</span>
            <b><?= (int) $masDificil['pct'] ?>%</b>
            <small><?= e(mb_strimwidth($masDificil['texto'], 0, 46, '…')) ?></small>
          </article>
        <?php endif; ?>
      </section>

      <!-- Posiciones -->
      <?php if ($ranking): ?>
        <section class="panel">
          <h2 class="panel-title">Posiciones</h2>
          <ol class="inf-podio">
            <?php foreach ($ranking as $n => $r): ?>
              <?php
              $medalla = ['🥇', '🥈', '🥉'][$n] ?? ($n + 1) . '.';
              // La barra compara con quien va primero: así se ve de un vistazo
              // si la partida estuvo reñida o hubo una goleada.
              $ancho = $puntajeLider > 0 ? (int) round($r['puntaje'] / $puntajeLider * 100) : 0;
              $suPct = $preguntasPuntuables > 0
                  ? (int) round($r['aciertos'] / $preguntasPuntuables * 100)
                  : null;
              ?>
              <li class="inf-puesto<?= $n === 0 ? ' top' : '' ?>">
                <span class="inf-medalla" aria-hidden="true"><?= $medalla ?></span>
                <div>
                  <div class="inf-quien"><span class="inf-nombre"><?= e($r['nombre']) ?></span></div>
                  <span class="inf-barra <?= e($nivel($suPct)) ?>">
                    <span style="width:<?= $ancho ?>%"></span>
                  </span>
                  <span class="inf-detalle">
                    <?= (int) $r['aciertos'] ?> de <?= $preguntasPuntuables ?> aciertos<?php
                      if ($r['promedio'] > 0): ?> · <?= e(number_format((float) $r['promedio'], 1, ',', '.')) ?> s de media<?php endif; ?>
                  </span>
                </div>
                <span class="inf-puntos">
                  <?= number_format((int) $r['puntaje'], 0, ',', '.') ?>
                  <small>puntos</small>
                </span>
              </li>
            <?php endforeach; ?>
          </ol>
        </section>
      <?php endif; ?>

      <!-- Pregunta por pregunta -->
      <section class="panel">
        <h2 class="panel-title">Pregunta por pregunta</h2>

        <?php foreach ($informe as $actividad): ?>
          <div class="inf-actividad">
            <div class="inf-actividad-cab">
              <b><?= e($actividad['titulo'] ?: ucfirst(str_replace('_', ' ', $actividad['tipo']))) ?></b>
              <span class="inf-tipo"><?= e(str_replace('_', ' ', $actividad['tipo'])) ?><?= $actividad['puntuable'] ? '' : ' · no puntúa' ?></span>
            </div>

            <?php foreach ($actividad['preguntas'] as $pregunta): ?>
              <?php
              $respuestas = (int) $pregunta['respuestas'];
              $correctas  = (int) $pregunta['correctas'];
              $pct = ($actividad['puntuable'] && $respuestas > 0)
                  ? (int) round($correctas / $respuestas * 100)
                  : null;
              ?>
              <div class="inf-pregunta">
                <div class="inf-pregunta-cab">
                  <span class="inf-pregunta-texto"><?= (int) $pregunta['numero'] ?>. <?= e($pregunta['resumen']) ?></span>
                  <?php if ($pct !== null): ?>
                    <span class="inf-dato <?= e($nivel($pct)) ?>"><?= $pct ?>% · <?= $correctas ?>/<?= $respuestas ?></span>
                  <?php elseif ($actividad['puntuable']): ?>
                    <span class="inf-sin-datos">sin respuestas</span>
                  <?php else: ?>
                    <span class="inf-sin-datos"><?= count($pregunta['aportes']) ?> aportes</span>
                  <?php endif; ?>
                </div>

                <?php if ($pct !== null): ?>
                  <span class="inf-barra <?= e($nivel($pct)) ?>"><span style="width:<?= $pct ?>%"></span></span>
                <?php endif; ?>

                <?php if (!$actividad['puntuable'] && $pregunta['aportes']): ?>
                  <ul class="inf-aportes">
                    <?php foreach ($pregunta['aportes'] as $aporte): ?>
                      <li><b><?= e($aporte['nombre']) ?>:</b> <?= e($aporte['texto']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>

    <?php else: ?>
      <div class="inf-cabecera">
        <div>
          <span class="eyebrow">Resultados</span>
          <h1>Informes</h1>
          <p class="cuenta-sub">
            Cada vez que juegas o envías un paquete queda aquí lo que ocurrió.
            Editar el paquete después no cambia ningún informe ya guardado.
          </p>
        </div>
        <a class="btn btn-ghost" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
      </div>

      <?php if ($aviso): ?>
        <p class="mensaje <?= e($avisoTipo) ?>" role="status"><?= e($aviso) ?></p>
      <?php endif; ?>

      <?php if (!$lista): ?>
        <div class="panel vacio">
          <b aria-hidden="true">📊</b>
          <p>Todavía no hay informes: se crean al cerrar una sesión que hayas jugado o enviado.</p>
          <a class="btn btn-primary" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Ir a mis paquetes</a>
        </div>
      <?php else: ?>
        <ul class="lista-sesiones">
          <?php foreach ($lista as $i): ?>
            <li class="panel sesion-item">
              <div class="sesion-datos">
                <span class="sesion-codigo sesion-codigo-paquete" aria-hidden="true">📊</span>
                <div>
                  <b><?= e($i['nombre']) ?></b>
                  <span class="sesion-meta">
                    <span class="modo-tag"><?= $i['modo'] === 'enviar' ? '📨 Enviado' : '📽️ En vivo' ?></span> ·
                    <?= e(date('d/m/Y H:i', strtotime((string) $i['terminada_at']))) ?> ·
                    <?= (int) $i['participantes'] ?> participantes ·
                    <?= (int) $i['respuestas'] ?> respuestas
                  </span>
                  <?php if ($i['paquete_id'] === null): ?>
                    <span class="sesion-nota">El paquete original se eliminó; el informe se conserva.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="sesion-acciones">
                <a class="btn btn-primary btn-sm" href="<?= e(base_url('app/informes.php')) ?>?s=<?= e($i['codigo']) ?>">Ver informe</a>
                <form method="post" action="<?= e(base_url('app/informes.php')) ?>"
                      onsubmit="return confirm('¿Eliminar este informe? No se puede deshacer.');">
                  <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                  <input type="hidden" name="codigo" value="<?= e($i['codigo']) ?>">
                  <button class="link-btn danger" type="submit">Eliminar</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
