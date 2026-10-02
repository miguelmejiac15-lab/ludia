<?php
/**
 * Un curso por dentro: la lista del salón, lo que se le ha enviado y el
 * progreso de cada estudiante.
 *
 * El permiso se comprueba en el servidor con `curso_puede()`, que exige además
 * que el curso sea del MISMO colegio: sin eso, un coordinador podría asomarse
 * a los cursos de otra institución cambiando el número de la dirección.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Colegios.php';
require_once __DIR__ . '/../includes/Cursos.php';
require_once __DIR__ . '/../includes/Paquetes.php';

$usuario = auth_requerir();
$cursoId = (int) ($_GET['id'] ?? $_POST['curso_id'] ?? 0);
$curso   = $cursoId > 0 ? curso_de($cursoId) : null;

if (!curso_puede($usuario, $curso)) {
    http_response_code(404);
    $page = ['title' => 'Curso no encontrado — Ludia', 'home' => false];
    $extraStyles = ['css/cuenta.css'];
    require LUDIA_ROOT . '/includes/partials/header.php';
    echo '<main id="contenido" class="cuenta"><div class="wrap"><section class="panel cuenta-card">'
       . '<h1>Ese curso no existe</h1><p class="cuenta-sub">O no es de tu colegio.</p>'
       . '<p style="margin-top:18px"><a class="btn btn-primary" href="' . e(base_url('app/colegio.php')) . '">Volver a mi colegio</a></p>'
       . '</section></div></main>';
    require LUDIA_ROOT . '/includes/partials/footer.php';
    exit;
}

$aviso = null;
$avisoTipo = 'ok';

if (!empty($_SESSION['curso_aviso'])) {
    $aviso = (string) $_SESSION['curso_aviso']['mensaje'];
    $avisoTipo = $_SESSION['curso_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['curso_aviso']);
}

/* ---------- Acciones ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $resultado = ['ok' => false, 'mensaje' => 'El formulario caducó. Inténtalo otra vez.'];
    } else {
        $resultado = match ((string) ($_POST['accion'] ?? '')) {
            'agregar_estudiantes' => curso_agregar_estudiantes($cursoId, (string) ($_POST['lista'] ?? '')),
            'quitar_estudiante'   => curso_quitar_estudiante($cursoId, (int) ($_POST['estudiante_id'] ?? 0)),

            // Enviar al curso es el MISMO flujo de siempre —una sesión nueva con
            // copia del contenido—, solo que la sesión sabe a qué curso va y
            // queda constancia en el historial del curso.
            'enviar_al_curso'     => curso_enviar_paquete($cursoId, (int) ($_POST['paquete_id'] ?? 0), $usuario),

            default => ['ok' => false, 'mensaje' => 'Acción desconocida.'],
        };
    }

    $_SESSION['curso_aviso'] = $resultado;
    header('Location: ' . base_url('app/curso.php') . '?id=' . $cursoId);
    exit;
}

/**
 * Manda un paquete propio al curso.
 *
 * Vive aquí y no en Cursos.php porque necesita el flujo de sesiones y el plan
 * del profesor; Cursos.php se queda con lo que es del curso en sí.
 */
function curso_enviar_paquete(int $cursoId, int $paqueteId, array $usuario): array
{
    require_once LUDIA_ROOT . '/includes/Planes.php';

    $paquete = paquete_de($paqueteId, (int) $usuario['id']);
    if (!$paquete) {
        return ['ok' => false, 'mensaje' => 'Ese paquete no es tuyo.'];
    }

    $resultado = sesion_lanzar($paquete, (int) $usuario['id'], 'enviar', plan_max_participantes($usuario));
    if (!$resultado['ok']) {
        return $resultado;
    }

    $sesion = sesion_por_codigo((string) $resultado['codigo']);
    if ($sesion) {
        db()->prepare('UPDATE sesiones SET curso_id = ? WHERE id = ?')->execute([$cursoId, (int) $sesion['id']]);
        curso_registrar_envio($cursoId, $sesion, (int) $usuario['id']);
    }

    return [
        'ok' => true,
        'mensaje' => 'Enviado al curso. El código es ' . $resultado['codigo'] .
            ': los estudiantes entran con él y escriben su nombre.',
    ];
}

$estudiantes = curso_estudiantes($cursoId);
$envios      = curso_envios($cursoId);
$progreso    = curso_progreso($cursoId);
$totalEnvios = curso_total_envios($cursoId);
$misPaquetes = paquetes_del_usuario((int) $usuario['id']);

$page = [
    'title'       => $curso['nombre'] . ' — Ludia',
    'description' => 'Lista, envíos y progreso del curso.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css', 'css/colegio.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">

    <div class="mis-head">
      <div>
        <span class="eyebrow">Curso</span>
        <h1><?= e($curso['nombre']) ?></h1>
        <p class="cuenta-sub">
          <?php if ($curso['grado']): ?>Grado <?= e($curso['grado']) ?> · <?php endif; ?>
          <?php if ($curso['jornada']): ?><?= e($curso['jornada']) ?> · <?php endif; ?>
          <?= e((string) $curso['profesor_nombre']) ?>
        </p>
      </div>
      <div class="mis-head-acciones">
        <a class="btn btn-ghost" href="<?= e(base_url('app/colegio.php')) ?>">← Mi colegio</a>
      </div>
    </div>

    <?php if ($aviso): ?>
      <p class="mensaje <?= e($avisoTipo) ?>" role="status"><?= e($aviso) ?></p>
    <?php endif; ?>

    <!-- ---------- Progreso ---------- -->
    <section class="panel bloque-colegio">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Progreso</h2>
        <span class="hint">
          <?= $totalEnvios ?> <?= $totalEnvios === 1 ? 'envío' : 'envíos' ?> ·
          <?= count($estudiantes) ?> en la lista
        </span>
      </div>

      <?php if (!$estudiantes): ?>
        <div class="vacio">
          <b>📋</b>
          <p>Pega abajo la lista de tu salón y aquí verás quién va al día y quién no.</p>
        </div>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla" data-tabla="progreso">
            <thead>
              <tr>
                <th scope="col">Estudiante</th>
                <th scope="col">Hizo</th>
                <th scope="col">Terminó</th>
                <th scope="col">Aciertos</th>
                <th scope="col">Última vez</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($progreso as $e): ?>
                <?php
                // Semáforo de la casa: el color acompaña a la cifra, nunca la
                // sustituye. Sin porcentaje no hay color: no es lo mismo
                // "lo hizo todo mal" que "no ha entrado".
                $pct = $e['porcentaje'];
                $clase = $pct === null ? '' : ($pct >= 70 ? 'nota-bien' : ($pct >= 40 ? 'nota-medio' : 'nota-mal'));
                ?>
                <tr>
                  <td><b><?= e($e['nombre']) ?></b></td>
                  <td>
                    <?= (int) $e['envios_tocados'] ?><?= $totalEnvios > 0 ? ' de ' . $totalEnvios : '' ?>
                  </td>
                  <td><?= (int) $e['terminados'] ?></td>
                  <td>
                    <?php if ($pct === null): ?>
                      <span class="celda-sub">sin datos</span>
                    <?php else: ?>
                      <span class="nota <?= $clase ?>"><?= (int) $pct ?> %</span>
                      <span class="celda-sub"><?= (int) $e['aciertos'] ?> de <?= (int) $e['preguntas'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="celda-sub"><?= $e['ultima_vez'] ? e(date('d/m/y', strtotime((string) $e['ultima_vez']))) : 'nunca' ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- ---------- Enviar un paquete ---------- -->
    <section class="panel bloque-colegio">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Enviar un paquete al curso</h2>
        <span class="hint">Cada envío abre una sesión nueva con su código.</span>
      </div>

      <?php if ($misPaquetes): ?>
        <form method="post" action="<?= e(base_url('app/curso.php')) ?>" class="alta-linea">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <input type="hidden" name="accion" value="enviar_al_curso">
          <input type="hidden" name="curso_id" value="<?= (int) $cursoId ?>">
          <div class="field" style="flex:1 1 260px">
            <label for="paquete">Paquete</label>
            <select class="select" id="paquete" name="paquete_id" required>
              <?php foreach ($misPaquetes as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> (<?= (int) $p['total_actividades'] ?> actividades)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary" type="submit">Enviar al curso</button>
        </form>
      <?php else: ?>
        <p class="hint">Todavía no tienes paquetes.
          <a href="<?= e(base_url('app/crear.php')) ?>">Crea el primero</a>.
        </p>
      <?php endif; ?>

      <?php if ($envios): ?>
        <h3 class="panel-title" style="margin:22px 0 10px;font-size:1rem">Lo enviado</h3>
        <ul class="lista-envios">
          <?php foreach ($envios as $v): ?>
            <li class="envio-item">
              <div>
                <b><?= e($v['titulo']) ?></b>
                <span class="sesion-meta">
                  <?= e(date('d/m/y', strtotime((string) $v['created_at']))) ?>
                  <?php if ($v['codigo']): ?> · código <?= e($v['codigo']) ?><?php endif; ?>
                  · <?= (int) $v['terminaron'] ?> de <?= (int) $v['participantes'] ?> terminaron
                </span>
              </div>
              <?php if ($v['estado'] && $v['estado'] !== 'terminada' && strtotime((string) $v['expira_at']) > time()): ?>
                <span class="chip-abierto">abierto</span>
              <?php else: ?>
                <span class="celda-sub">cerrado</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- ---------- La lista del salón ---------- -->
    <section class="panel bloque-colegio">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Lista del salón</h2>
        <span class="hint">Quitar a alguien conserva su historial.</span>
      </div>

      <?php if ($estudiantes): ?>
        <ul class="lista-estudiantes">
          <?php foreach ($estudiantes as $e): ?>
            <li>
              <span><?= e($e['nombre']) ?></span>
              <form method="post" action="<?= e(base_url('app/curso.php')) ?>" class="fila-form">
                <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                <input type="hidden" name="accion" value="quitar_estudiante">
                <input type="hidden" name="curso_id" value="<?= (int) $cursoId ?>">
                <input type="hidden" name="estudiante_id" value="<?= (int) $e['id'] ?>">
                <button class="link-btn danger" type="submit">Quitar</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('app/curso.php')) ?>" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="accion" value="agregar_estudiantes">
        <input type="hidden" name="curso_id" value="<?= (int) $cursoId ?>">
        <div class="field">
          <label for="lista">Pega la lista, un nombre por línea</label>
          <textarea class="input" id="lista" name="lista" rows="5" required
                    placeholder="José Pérez&#10;Ana María Gómez&#10;Luis Ñuño"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Añadir a la lista</button>
        <p class="hint" style="margin-top:8px">
          No hace falta que escriban tildes al entrar: «jose perez» encuentra a «José Pérez».
        </p>
      </form>
    </section>

  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
