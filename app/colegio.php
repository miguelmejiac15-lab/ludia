<?php
/**
 * El colegio: lo que ve el coordinador.
 *
 * Profesores (con su cupo), cursos y el estado de la licencia. Un profesor que
 * entre aquí ve solo sus cursos y no la gestión de cuentas: la comprobación es
 * del servidor, que los botones no salgan es solo cosmética.
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Colegios.php';
require_once __DIR__ . '/../includes/Cursos.php';

$usuario = auth_requerir();
$colegio = colegio_de_usuario($usuario);

$aviso = null;
$avisoTipo = 'ok';

if (!empty($_SESSION['colegio_aviso'])) {
    $aviso = (string) $_SESSION['colegio_aviso']['mensaje'];
    $avisoTipo = $_SESSION['colegio_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['colegio_aviso']);
}

/* ---------- Acciones ---------- */

if ($colegio && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $resultado = ['ok' => false, 'mensaje' => 'El formulario caducó. Inténtalo otra vez.'];
    } else {
        $accion = (string) ($_POST['accion'] ?? '');
        $colegioId = (int) $colegio['id'];

        // Gestionar cuentas es SOLO del coordinador. Crear cursos lo puede
        // hacer cualquier profesor del colegio: son suyos.
        $resultado = match ($accion) {
            'agregar_profesor' => es_coordinador($usuario)
                ? colegio_agregar_profesor($colegioId, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''))
                : ['ok' => false, 'mensaje' => 'Solo el coordinador da de alta profesores.'],

            'quitar_profesor' => es_coordinador($usuario)
                ? colegio_quitar_profesor($colegioId, (int) ($_POST['usuario_id'] ?? 0))
                : ['ok' => false, 'mensaje' => 'Solo el coordinador puede hacer eso.'],

            'rol_colegio' => es_coordinador($usuario)
                ? colegio_cambiar_rol($colegioId, (int) ($_POST['usuario_id'] ?? 0), (string) ($_POST['rol'] ?? ''))
                : ['ok' => false, 'mensaje' => 'Solo el coordinador puede hacer eso.'],

            // Mover un curso de profesor: solo el coordinador. Un profesor
            // no puede regalarse el curso de otro ni soltar el suyo.
            'mover_curso' => es_coordinador($usuario)
                ? curso_reasignar(
                    (int) ($_POST['curso_id'] ?? 0),
                    (int) ($_POST['profesor_id'] ?? 0),
                    $colegioId
                )
                : ['ok' => false, 'mensaje' => 'Solo el coordinador mueve cursos de profesor.'],

            'crear_curso' => curso_crear(
                $colegioId,
                (int) $usuario['id'],
                (string) ($_POST['nombre'] ?? ''),
                ['grado' => $_POST['grado'] ?? '', 'jornada' => $_POST['jornada'] ?? '']
            ),

            default => ['ok' => false, 'mensaje' => 'Acción desconocida.'],
        };
    }

    // El alta de un profesor devuelve un enlace para que ponga su contraseña:
    // mientras no haya correo saliente, es la única forma de que entre.
    if (!empty($resultado['enlace'])) {
        $resultado['mensaje'] .= ' ' . $resultado['enlace'];
    }

    $_SESSION['colegio_aviso'] = $resultado;
    header('Location: ' . base_url('app/colegio.php'));
    exit;
}

$profesores = $colegio ? colegio_profesores((int) $colegio['id']) : [];
$cursos     = $colegio ? cursos_visibles($usuario) : [];
$dias       = colegio_dias_restantes($colegio);
$vigente    = colegio_vigente($colegio);

$page = [
    'title'       => 'Mi colegio — Ludia',
    'description' => 'Profesores, cursos y licencia de tu institución.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css', 'css/colegio.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">

    <?php if (!$colegio): ?>
      <section class="panel cuenta-card">
        <span class="eyebrow">Institución</span>
        <h1>Tu cuenta no pertenece a ningún colegio</h1>
        <p class="cuenta-sub">
          La licencia de institución permite que un coordinador dé de alta a sus profesores,
          que cada profesor arme sus cursos y que todos vean el progreso de sus estudiantes.
        </p>
        <p style="margin-top:18px">
          <a class="btn btn-primary" href="<?= e(base_url('/')) ?>#planes">Ver la licencia de institución</a>
        </p>
      </section>

    <?php else: ?>

      <div class="mis-head">
        <div>
          <span class="eyebrow">Institución</span>
          <h1><?= e($colegio['nombre']) ?></h1>
          <p class="cuenta-sub">
            <?= es_coordinador($usuario) ? 'Coordinas este colegio.' : 'Eres profesor de este colegio.' ?>
            <?php if ($colegio['ciudad']): ?> · <?= e($colegio['ciudad']) ?><?php endif; ?>
          </p>
        </div>
        <div class="mis-head-acciones">
          <a class="btn btn-ghost" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
        </div>
      </div>

      <?php if ($aviso): ?>
        <p class="mensaje <?= e($avisoTipo) ?><?= str_contains($aviso, 'http') ? ' mensaje-largo' : '' ?>" role="status"><?= e($aviso) ?></p>
      <?php endif; ?>

      <?php /* El estado de la licencia va arriba y con la cifra, no solo con
               color: si está por vencer, es lo primero que hay que saber. */ ?>
      <section class="panel licencia <?= $vigente ? 'licencia-ok' : 'licencia-fin' ?>">
        <div class="licencia-dato">
          <span class="licencia-etiqueta">Licencia</span>
          <b><?= $vigente ? 'Al día' : ($colegio['pagado_hasta'] ? 'Vencida' : 'Sin activar') ?></b>
        </div>
        <div class="licencia-dato">
          <span class="licencia-etiqueta">Hasta</span>
          <b><?= $colegio['pagado_hasta'] ? e(date('d/m/Y', strtotime((string) $colegio['pagado_hasta']))) : '—' ?></b>
        </div>
        <div class="licencia-dato">
          <span class="licencia-etiqueta">Días restantes</span>
          <b><?= $dias === null ? '—' : (int) $dias ?></b>
        </div>
        <div class="licencia-dato">
          <span class="licencia-etiqueta">Cuentas</span>
          <b><?= count($profesores) ?><?= (int) $colegio['cupo_profesores'] > 0 ? ' de ' . (int) $colegio['cupo_profesores'] : '' ?></b>
        </div>
      </section>

      <?php if (!$vigente): ?>
        <p class="mensaje error" role="status">
          La licencia no está al día, así que las cuentas del colegio funcionan en plan Gratis.
          <b>No se ha borrado nada</b>: al renovar, todos los paquetes vuelven a estar disponibles.
        </p>
      <?php endif; ?>

      <!-- ---------- Cursos ---------- -->
      <section class="panel bloque-colegio">
        <div class="admin-bloque-head">
          <h2 class="panel-title">Cursos</h2>
          <span class="hint"><?= es_coordinador($usuario) ? 'Todos los del colegio.' : 'Los tuyos.' ?></span>
        </div>

        <?php if ($cursos): ?>
          <ul class="lista-cursos">
            <?php foreach ($cursos as $c): ?>
              <li class="curso-item">
                <div class="curso-datos">
                  <b><?= e($c['nombre']) ?></b>
                  <span class="sesion-meta">
                    <?php if ($c['grado']): ?>Grado <?= e($c['grado']) ?> · <?php endif; ?>
                    <?= (int) $c['estudiantes'] ?> <?= (int) $c['estudiantes'] === 1 ? 'estudiante' : 'estudiantes' ?>
                    · <?= (int) $c['envios'] ?> <?= (int) $c['envios'] === 1 ? 'envío' : 'envíos' ?>
                  </span>
                </div>

                <?php if (es_coordinador($usuario)): ?>
                  <?php /* Quién lleva el curso, cambiable en el sitio donde se
                           lee: un profesor se va, entra otro, o se reparten los
                           cursos al empezar el año. La lista del salón y el
                           historial de envíos NO se mueven con el cambio. */ ?>
                  <form method="post" action="<?= e(base_url('app/colegio.php')) ?>" class="fila-form">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <input type="hidden" name="accion" value="mover_curso">
                    <input type="hidden" name="curso_id" value="<?= (int) $c['id'] ?>">
                    <select class="select select-sm" name="profesor_id" onchange="this.form.submit()"
                            aria-label="Profesor de <?= e($c['nombre']) ?>">
                      <?php foreach ($profesores as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) $c['profesor_id'] ? ' selected' : '' ?>>
                          <?= e($p['nombre']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Mover</button></noscript>
                  </form>
                <?php endif; ?>

                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/curso.php')) ?>?id=<?= (int) $c['id'] ?>">Abrir</a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="vacio">
            <b>🎒</b>
            <p>Todavía no hay cursos. Crea el primero y pega la lista de tu salón.</p>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('app/colegio.php')) ?>" class="alta-linea">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <input type="hidden" name="accion" value="crear_curso">
          <div class="field">
            <label for="curso-nombre">Nombre del curso</label>
            <input class="input" id="curso-nombre" name="nombre" required maxlength="80" placeholder="Once B">
          </div>
          <div class="field">
            <label for="curso-grado">Grado</label>
            <input class="input" id="curso-grado" name="grado" maxlength="30" placeholder="11">
          </div>
          <div class="field">
            <label for="curso-jornada">Jornada</label>
            <input class="input" id="curso-jornada" name="jornada" maxlength="30" placeholder="Mañana">
          </div>
          <button class="btn btn-primary" type="submit">Crear curso</button>
        </form>
      </section>

      <!-- ---------- Profesores ---------- -->
      <?php if (es_coordinador($usuario)): ?>
      <section class="panel bloque-colegio">
        <div class="admin-bloque-head">
          <h2 class="panel-title">Profesores</h2>
          <span class="hint">Quitar a alguien no borra su cuenta ni sus paquetes.</span>
        </div>

        <div class="tabla-scroll">
          <table class="tabla" data-tabla="profesores">
            <thead>
              <tr>
                <th scope="col">Profesor</th>
                <th scope="col">Rol</th>
                <th scope="col">Paquetes</th>
                <th scope="col">Cursos</th>
                <th scope="col">Último acceso</th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($profesores as $p): ?>
                <tr<?= (int) $p['id'] === (int) $usuario['id'] ? ' class="es-tu-cuenta"' : '' ?>>
                  <td>
                    <b><?= e($p['nombre']) ?></b>
                    <?php if ((int) $p['id'] === (int) $usuario['id']): ?><span class="tu-chip">tú</span><?php endif; ?>
                    <span class="celda-sub"><?= e($p['email']) ?></span>
                  </td>
                  <td>
                    <form method="post" action="<?= e(base_url('app/colegio.php')) ?>" class="fila-form">
                      <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                      <input type="hidden" name="accion" value="rol_colegio">
                      <input type="hidden" name="usuario_id" value="<?= (int) $p['id'] ?>">
                      <select class="select select-sm" name="rol" onchange="this.form.submit()" aria-label="Rol de <?= e($p['email']) ?>">
                        <option value="profesor"<?= $p['rol_colegio'] === 'profesor' ? ' selected' : '' ?>>Profesor</option>
                        <option value="coordinador"<?= $p['rol_colegio'] === 'coordinador' ? ' selected' : '' ?>>Coordinador</option>
                      </select>
                    </form>
                  </td>
                  <td><?= (int) $p['paquetes'] ?></td>
                  <td><?= (int) $p['cursos'] ?></td>
                  <td><span class="celda-sub"><?= $p['ultimo_acceso'] ? e(date('d/m/y', strtotime((string) $p['ultimo_acceso']))) : 'nunca' ?></span></td>
                  <td>
                    <?php if ($p['rol_colegio'] !== 'coordinador'): ?>
                      <form method="post" action="<?= e(base_url('app/colegio.php')) ?>" class="fila-form"
                            onsubmit="return confirm('¿Sacar a <?= e($p['nombre']) ?> del colegio? Su cuenta y sus paquetes se conservan.')">
                        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                        <input type="hidden" name="accion" value="quitar_profesor">
                        <input type="hidden" name="usuario_id" value="<?= (int) $p['id'] ?>">
                        <button class="link-btn danger" type="submit">Quitar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php
        $cupo = (int) $colegio['cupo_profesores'];
        $lleno = $cupo > 0 && count($profesores) >= $cupo;
        ?>
        <?php if ($lleno): ?>
          <p class="mensaje error" role="status">
            La licencia cubre <?= $cupo ?> cuentas y ya están todas ocupadas.
          </p>
        <?php else: ?>
          <form method="post" action="<?= e(base_url('app/colegio.php')) ?>" class="alta-linea">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <input type="hidden" name="accion" value="agregar_profesor">
            <div class="field">
              <label for="prof-nombre">Nombre</label>
              <input class="input" id="prof-nombre" name="nombre" required maxlength="120" placeholder="María Restrepo">
            </div>
            <div class="field">
              <label for="prof-email">Correo</label>
              <input class="input" id="prof-email" name="email" type="email" required placeholder="maria@colegio.edu.co">
            </div>
            <button class="btn btn-primary" type="submit">Agregar profesor</button>
          </form>
          <p class="hint" style="margin-top:8px">
            Se crea la cuenta sin contraseña y recibes un enlace para que la ponga el profesor.
            Así nadie más conoce su clave.
          </p>
        <?php endif; ?>
      </section>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
