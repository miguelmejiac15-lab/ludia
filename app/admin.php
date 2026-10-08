<?php
/**
 * Panel de administración.
 *
 * Solo para cuentas con rol 'admin'. Cada acción va por POST con CSRF y
 * responde con redirección (patrón PRG), para que recargar no repita el cambio.
 */

require_once __DIR__ . '/../includes/Admin.php';

$usuario = admin_requerir();
$esAdmin = admin_es_admin($usuario);

$aviso = null;
$avisoTipo = 'ok';

/**
 * El panel va por secciones, con barra lateral.
 *
 * No es solo estética: cada sección consulta SOLO lo suyo. Antes cada visita
 * ejecutaba las ocho consultas —cuentas, sesiones, métricas, dinero, catálogo—
 * aunque miraras una sola cosa, y eso se vuelve lento justo cuando la
 * plataforma crece, que es cuando más se usa el panel.
 */
$secciones = [
    'resumen'  => ['Resumen', '📊'],
    'cuentas'  => ['Cuentas', '👥'],
    'sesiones' => ['Sesiones abiertas', '📽️'],
    'metricas' => ['Métricas', '📈'],
    'planes'   => ['Planes y precios', '💳'],
    'colegios' => ['Colegios', '🏫'],
    'catalogo' => ['Actividades por plan', '🧩'],
];

// La sección viaja también en el `action` de los formularios, así que llega por
// GET incluso al guardar: sin eso, cambiar un precio te devolvía al Resumen.
$seccion = (string) ($_GET['seccion'] ?? 'resumen');
if (!array_key_exists($seccion, $secciones)) {
    $seccion = 'resumen';
}

if ($esAdmin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_csrf_valido($_POST['csrf'] ?? null)) {
        $resultado = ['ok' => false, 'mensaje' => 'El formulario caducó. Inténtalo otra vez.'];
    } else {
        $accion = (string) ($_POST['accion'] ?? '');
        $resultado = match ($accion) {
            'plan' => admin_cambiar_plan(
                (int) ($_POST['usuario_id'] ?? 0),
                (string) ($_POST['plan'] ?? '')
            ),
            'rol' => admin_cambiar_rol(
                (int) ($_POST['usuario_id'] ?? 0),
                (string) ($_POST['rol'] ?? ''),
                (int) $usuario['id']
            ),
            // Mientras no haya correo saliente, esta es la ÚNICA forma de que
            // alguien que olvidó su contraseña vuelva a entrar.
            'enlace_clave' => admin_enlace_clave((int) ($_POST['usuario_id'] ?? 0)),

            // Permisos sueltos de UNA cuenta, por encima de lo que da su plan.
            'guardar_permisos' => admin_guardar_permisos((int) ($_POST['usuario_id'] ?? 0), $_POST),

            // La licencia de institución se activa a mano desde aquí, como el
            // resto de planes mientras no haya pasarela de cobro.
            'crear_colegio' => admin_colegio_crear($_POST),
            'guardar_colegio' => admin_colegio_guardar((int) ($_POST['colegio_id'] ?? 0), $_POST),
            'eliminar_paquete' => admin_eliminar_paquete(
                preg_replace('/\D/', '', (string) ($_POST['codigo'] ?? ''))
            ),
            // Precios y topes de un plan: es lo que evita tener que tocar código.
            'guardar_plan' => admin_guardar_plan((string) ($_POST['clave'] ?? ''), $_POST),
            // A qué plan pertenece una actividad del catálogo.
            'mover_formato' => plan_asignar_formato(
                (string) ($_POST['formato'] ?? ''),
                (string) ($_POST['plan_minimo'] ?? '')
            ),
            // Material de muestra (includes/Demo.php): en la cuenta indicada
            // desde la lista de Cuentas, o en la propia desde el Resumen.
            'cargar_demo' => (static function () use ($usuario): array {
                require_once __DIR__ . '/../includes/Demo.php';
                $destino = (int) ($_POST['usuario_id'] ?? 0) ?: (int) $usuario['id'];
                $existe = db()->prepare('SELECT email FROM usuarios WHERE id = ?');
                $existe->execute([$destino]);
                $correo = $existe->fetchColumn();
                if (!$correo) {
                    return ['ok' => false, 'mensaje' => 'Esa cuenta no existe.'];
                }
                $r = demo_cargar($destino);
                if ($destino !== (int) $usuario['id']) {
                    $r['mensaje'] = str_replace('«Mis paquetes»', '«Mis paquetes» de ' . $correo, $r['mensaje']);
                }
                return $r;
            })(),
            default => ['ok' => false, 'mensaje' => 'Acción desconocida.'],
        };
    }

    $_SESSION['admin_aviso'] = $resultado;

    // Se vuelve a la MISMA sección: guardar un precio y aparecer en el Resumen
    // obliga a volver a navegar, y con varios cambios seguidos es exasperante.
    $volverA = ['seccion' => $seccion];
    if (($_POST['q'] ?? '') !== '') {
        $volverA['q'] = (string) $_POST['q'];
    }
    // Tras tocar permisos se vuelve a la ficha de esa cuenta: es donde estabas
    // y donde se ve el efecto del cambio.
    if ($accion === 'guardar_permisos' && (int) ($_POST['usuario_id'] ?? 0) > 0) {
        $volverA['permisos'] = (int) $_POST['usuario_id'];
    }
    header('Location: ' . base_url('app/admin.php') . '?' . http_build_query($volverA));
    exit;
}

if (!empty($_SESSION['admin_aviso'])) {
    $aviso = $_SESSION['admin_aviso']['mensaje'];
    $avisoTipo = $_SESSION['admin_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['admin_aviso']);
}

$busqueda = trim((string) ($_GET['q'] ?? ''));

// Cada sección pide lo suyo y nada más.
if ($esAdmin) {
    if ($seccion === 'resumen') {
        $resumen = admin_resumen();
    }
    if ($seccion === 'cuentas') {
        $usuarios = admin_usuarios($busqueda);
        // Ficha de permisos de una cuenta concreta, si se pidió.
        $permisosDe = ((int) ($_GET['permisos'] ?? 0) > 0)
            ? admin_usuario_con_permisos((int) $_GET['permisos'])
            : null;
    }
    if ($seccion === 'sesiones') {
        $sesiones = admin_sesiones();
    }
    if ($seccion === 'colegios') {
        require_once LUDIA_ROOT . '/includes/Colegios.php';
        $colegios = colegios_todos();
    }
    if ($seccion === 'metricas') {
        $serie     = admin_metricas_diarias(14);
        $dinero    = admin_metricas_dinero();
        $masUsados = admin_formatos_usados();
    }
    if ($seccion === 'catalogo') {
        $formatos = admin_formatos();
        $catalogo = admin_catalogo();
    }
    // 'planes' lee de planes_todos(), que ya trae su propia caché por petición.
}

$page = [
    'title'       => 'Panel de administración — Ludia',
    'description' => 'Cuentas, planes y paquetes activos.',
    'home'        => false,
];
$extraStyles = ['css/cuenta.css', 'css/admin.css'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta admin">
  <div class="wrap">

<?php if (!$esAdmin): ?>
    <div class="panel vacio">
      <b aria-hidden="true">🔒</b>
      <h1>Esta zona es solo para administradores</h1>
      <p>Tu cuenta no tiene ese permiso. Si crees que es un error, pídele a un administrador que te lo asigne.</p>
      <a class="btn btn-primary" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Ir a mis paquetes</a>
    </div>
<?php else: ?>

    <div class="mis-head">
      <div>
        <span class="eyebrow">Administración</span>
        <h1>Panel</h1>
        <p class="cuenta-sub">Elige una sección en la barra lateral. Los cambios se aplican al instante.</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(base_url('app/mis-sesiones.php')) ?>">Mis paquetes</a>
    </div>

    <?php if ($aviso): ?>
      <?php /* `mensaje-largo` para el enlace de restablecimiento: es una URL de
               casi cien caracteres que hay que poder seleccionar entera, y en
               un párrafo normal se desborda o se parte donde no debe. */ ?>
      <p class="mensaje <?= e($avisoTipo) ?><?= str_contains($aviso, 'http') ? ' mensaje-largo' : '' ?>" role="status"><?= e($aviso) ?></p>
    <?php endif; ?>

    <div class="admin-layout">
      <nav class="admin-nav" aria-label="Secciones del panel">
        <?php foreach ($secciones as $clave => [$titulo, $icono]): ?>
          <a class="admin-nav-item<?= $seccion === $clave ? ' activo' : '' ?>"
             href="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($clave) ?>"
             <?= $seccion === $clave ? 'aria-current="page"' : '' ?>>
            <span class="admin-nav-icono" aria-hidden="true"><?= $icono ?></span>
            <?= e($titulo) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="admin-contenido">

    <?php if ($seccion === 'resumen'): ?>
    <!-- Cifras -->
    <section class="admin-cifras" aria-label="Resumen">
      <?php
      $cuentas  = $resumen['cuentas'];
      $paqs     = $resumen['paquetes'];
      $ses      = $resumen['sesiones'];
      $tarjetas = [
          ['Cuentas',        (int) $cuentas['total'],        (int) $cuentas['nuevas_semana'] . ' esta semana'],
          ['Plan gratis',    (int) $cuentas['gratis'],       'de ' . (int) $cuentas['total'] . ' cuentas'],
          ['De pago',        (int) $cuentas['estandar'] + (int) $cuentas['pro'], (int) $cuentas['estandar'] . ' Estándar · ' . (int) $cuentas['pro'] . ' Pro'],
          // Contenido guardado y partidas abiertas, separados: juntarlos daba una
          // cifra que no quería decir nada.
          ['Paquetes guardados', (int) $paqs['guardados'],   (int) $paqs['nuevos_semana'] . ' esta semana · ' . (int) $paqs['actividades'] . ' actividades'],
          ['Sesiones abiertas',  (int) $ses['vivas'],        (int) $ses['en_vivo'] . ' en vivo · ' . (int) $ses['enviadas'] . ' enviadas'],
          ['Informes guardados', (int) $resumen['informes']['guardados'], 'solo de los planes de pago'],
          ['Participantes',  (int) $resumen['juego']['participantes'], (int) $resumen['juego']['respuestas'] . ' respuestas'],
          ['Imágenes',       (int) $resumen['imagenes']['archivos'],   admin_peso((int) $resumen['imagenes']['bytes'])],
      ];
      foreach ($tarjetas as [$titulo, $valor, $pie]): ?>
        <article class="cifra">
          <span class="cifra-label"><?= e($titulo) ?></span>
          <b class="cifra-valor"><?= $valor ?></b>
          <span class="cifra-pie"><?= e($pie) ?></span>
        </article>
      <?php endforeach; ?>
    </section>

    <?php /* Material de muestra: los 23 formatos con el tema «Las plantas»,
             para enseñar Ludia o grabar vídeos. Va a la cuenta de quien pulsa. */ ?>
    <section class="panel" style="margin-top:22px">
      <h2 class="panel-title">Paquetes de demostración</h2>
      <p class="cuenta-sub" style="margin-bottom:14px">
        Crea en <b>tu</b> cuenta dos paquetes con el tema «Las plantas»: uno en vivo con las 22 actividades
        que se proyectan, y otro con el vídeo con preguntas (ese solo se juega enviado). Si ya están, no los duplica.
      </p>
      <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=resumen">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="accion" value="cargar_demo">
        <button class="btn btn-primary" type="submit">Cargar paquetes de demostración</button>
      </form>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'cuentas' && $permisosDe): ?>
    <?php /* Ficha de permisos de UNA cuenta.
             Cada permiso tiene tres estados, no dos: «según el plan», «sí» y
             «no». Una casilla simple no distingue «que NO pueda» de «que siga a
             su plan», y esa diferencia importa: una cuenta sin excepciones debe
             seguir al plan sola cuando el plan cambie. */ ?>
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Permisos de <?= e($permisosDe['nombre']) ?></h2>
        <a class="link-btn" href="<?= e(base_url('app/admin.php')) ?>?seccion=cuentas">← Volver a la lista</a>
      </div>

      <p class="cuenta-sub" style="margin-bottom:14px">
        <?= e($permisosDe['email']) ?> · plan <b><?= e(plan_nombre(plan_de($permisosDe))) ?></b>.
        Lo que dejes en «según el plan» seguirá al plan si este cambia; lo demás queda fijado para esta cuenta.
      </p>

      <?php /* Lo que la cuenta puede AHORA, ya con sus excepciones aplicadas:
               es la respuesta a «¿qué puede este usuario exactamente?», que es
               justo la pregunta difícil de contestar sin esto. */ ?>
      <ul class="plan-datos" style="margin-bottom:18px">
        <li><span>Actividades</span><b><?= (int) $permisosDe['formatos'] ?> de <?= (int) $permisosDe['formatos_pro'] ?></b></li>
        <li><span>Exportar</span><b><?= $permisosDe['efectivo']['exporta'] ? 'Sí' : 'No' ?></b></li>
        <li><span>Paquetes</span><b><?= (int) $permisosDe['efectivo']['max_paquetes'] === 0 ? 'Sin tope' : (int) $permisosDe['efectivo']['max_paquetes'] ?></b></li>
        <li><span>Actividades / paquete</span><b><?= (int) $permisosDe['efectivo']['max_actividades'] === 0 ? 'Sin tope' : (int) $permisosDe['efectivo']['max_actividades'] ?></b></li>
        <li><span>Participantes</span><b><?= (int) $permisosDe['efectivo']['max_participantes'] ?></b></li>
      </ul>

      <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=cuentas">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="accion" value="guardar_permisos">
        <input type="hidden" name="usuario_id" value="<?= (int) $permisosDe['id'] ?>">
        <?php if ($busqueda !== ''): ?><input type="hidden" name="q" value="<?= e($busqueda) ?>"><?php endif; ?>

        <div class="campos-2">
          <?php foreach (ADMIN_PERMISOS as $clave => $etiqueta): ?>
            <?php
            $suelto  = $permisosDe['sueltos'][$clave] ?? null;
            $estado  = $suelto === null ? 'plan' : ($suelto ? 'si' : 'no');
            $delPlan = $permisosDe['del_plan'][$clave] ? 'sí' : 'no';
            ?>
            <div class="field">
              <label for="perm-<?= e($clave) ?>"><?= e($etiqueta) ?></label>
              <select class="select" id="perm-<?= e($clave) ?>" name="<?= e($clave) ?>">
                <option value="plan"<?= $estado === 'plan' ? ' selected' : '' ?>>Según el plan (<?= $delPlan ?>)</option>
                <option value="si"<?= $estado === 'si' ? ' selected' : '' ?>>Sí, siempre</option>
                <option value="no"<?= $estado === 'no' ? ' selected' : '' ?>>No, nunca</option>
              </select>
            </div>
          <?php endforeach; ?>

          <div class="field">
            <label for="perm-formatos">Catálogo de actividades</label>
            <select class="select" id="perm-formatos" name="formatos">
              <option value="plan"<?= ($permisosDe['sueltos']['formatos'] ?? '') !== 'todos' ? ' selected' : '' ?>>
                Según el plan (<?= count(plan_formatos(plan_de($permisosDe))) ?> actividades)
              </option>
              <option value="todos"<?= ($permisosDe['sueltos']['formatos'] ?? '') === 'todos' ? ' selected' : '' ?>>
                Todas las <?= (int) $permisosDe['formatos_pro'] ?>, sin candados
              </option>
            </select>
          </div>
        </div>

        <h3 class="panel-title" style="margin:20px 0 10px;font-size:1rem">Topes</h3>
        <div class="campos-2">
          <?php foreach (ADMIN_TOPES as $clave => $etiqueta): ?>
            <?php $delPlan = (int) $permisosDe['del_plan'][$clave]; ?>
            <div class="field">
              <label for="tope-<?= e($clave) ?>"><?= e($etiqueta) ?></label>
              <input class="input" id="tope-<?= e($clave) ?>" name="<?= e($clave) ?>" type="number" min="0" max="9999"
                     value="<?= isset($permisosDe['sueltos'][$clave]) ? (int) $permisosDe['sueltos'][$clave] : '' ?>"
                     placeholder="<?= $delPlan === 0 ? 'sin tope' : (string) $delPlan ?>">
              <span class="hint">Vacío = según el plan (<?= $delPlan === 0 ? 'sin tope' : $delPlan ?>). 0 = sin tope.</span>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-primary" type="submit" style="margin-top:16px">Guardar permisos</button>
      </form>

      <?php if ($permisosDe['sueltos']): ?>
        <?php /* Volver a "todo según el plan" en un gesto: dejar cada campo en
                 su sitio a mano es tedioso y fácil de hacer a medias. */ ?>
        <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=cuentas" style="margin-top:12px"
              onsubmit="return confirm('¿Quitar todas las excepciones? Esta cuenta pasará a comportarse exactamente como su plan.')">
          <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
          <input type="hidden" name="accion" value="guardar_permisos">
          <input type="hidden" name="usuario_id" value="<?= (int) $permisosDe['id'] ?>">
          <button class="link-btn danger" type="submit">Quitar todas las excepciones</button>
        </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($seccion === 'cuentas'): ?>
    <!-- Cuentas -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Cuentas</h2>
        <form method="get" action="<?= e(base_url('app/admin.php')) ?>" class="admin-buscar">
          <?php /* Un formulario GET DESCARTA la cadena de consulta del action,
                   así que la sección tiene que viajar como campo: si no,
                   buscar una cuenta te saca de "Cuentas". */ ?>
          <input type="hidden" name="seccion" value="<?= e($seccion) ?>">
          <label class="sr-only" for="q">Buscar por nombre o correo</label>
          <input class="input" id="q" name="q" type="search" value="<?= e($busqueda) ?>" placeholder="Buscar por nombre o correo">
          <button class="btn btn-ghost btn-sm" type="submit">Buscar</button>
          <?php if ($busqueda !== ''): ?>
            <a class="link-btn" href="<?= e(base_url('app/admin.php')) ?>">Limpiar</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="tabla-scroll">
        <table class="tabla" data-tabla="cuentas">
          <thead>
            <tr>
              <th scope="col">Cuenta</th>
              <th scope="col">Plan</th>
              <th scope="col">Rol</th>
              <th scope="col">Paquetes</th>
              <th scope="col">Imágenes</th>
              <th scope="col">Último acceso</th>
              <th scope="col">Contraseña</th>
              <th scope="col">Permisos</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
              <tr<?= (int) $u['id'] === (int) $usuario['id'] ? ' class="es-tu-cuenta"' : '' ?>>
                <td>
                  <b><?= e($u['nombre']) ?></b>
                  <?php if ((int) $u['id'] === (int) $usuario['id']): ?><span class="tu-chip">tú</span><?php endif; ?>
                  <span class="celda-sub"><?= e($u['email']) ?></span>
                </td>
                <td>
                  <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>" class="fila-form">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <input type="hidden" name="accion" value="plan">
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($busqueda) ?>">
                    <select class="select select-sm" name="plan" onchange="this.form.submit()" aria-label="Plan de <?= e($u['email']) ?>">
                      <?php foreach (planes_todos() as $clave => $datos): ?>
                        <option value="<?= e($clave) ?>"<?= $u['plan'] === $clave ? ' selected' : '' ?>><?= e($datos['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Guardar</button></noscript>
                  </form>
                  <?php
                  // De dónde viene el plan: pagado con Wompi o puesto a mano aquí.
                  $estadoSus = $u['suscripcion_estado'] ?? null;
                  if ($u['plan'] !== 'gratis' && !$estadoSus) {
                      echo '<span class="sus-chip sus-manual">dado a mano</span>';
                  } elseif ($estadoSus === 'activa') {
                      echo '<span class="sus-chip sus-activa">pagada hasta ' .
                          e(date('d/m/y', strtotime((string) $u['suscripcion_hasta']))) . '</span>';
                  } elseif ($estadoSus === 'pendiente') {
                      echo '<span class="sus-chip sus-pendiente">esperando pago</span>';
                  } elseif ($estadoSus) {
                      echo '<span class="sus-chip sus-fin">' . e($estadoSus) . '</span>';
                  }
                  ?>
                </td>
                <td>
                  <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>" class="fila-form">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <input type="hidden" name="accion" value="rol">
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($busqueda) ?>">
                    <select class="select select-sm" name="rol" onchange="this.form.submit()" aria-label="Rol de <?= e($u['email']) ?>">
                      <option value="creador"<?= $u['rol'] === 'creador' ? ' selected' : '' ?>>Creador</option>
                      <option value="admin"<?= $u['rol'] === 'admin' ? ' selected' : '' ?>>Administrador</option>
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Guardar</button></noscript>
                  </form>
                </td>
                <td class="num">
                  <?= (int) $u['paquetes'] ?>
                  <?php /* Carga los paquetes de demostración («Las plantas»,
                           los 23 formatos) en ESTA cuenta, sin entrar con ella. */ ?>
                  <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <input type="hidden" name="accion" value="cargar_demo">
                    <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($busqueda) ?>">
                    <button class="link-btn" type="submit" title="Crear en esta cuenta los paquetes de demostración">🌱 Demo</button>
                  </form>
                </td>
                <td class="num"><?= (int) $u['imagenes'] ?></td>
                <td><span class="celda-sub"><?= e(admin_cuando($u['ultimo_acceso'])) ?></span></td>
                <td>
                  <?php /* Mientras no haya correo saliente, este botón es la
                           única salida para quien olvidó su contraseña: genera
                           el enlace y el administrador se lo hace llegar. Una
                           cuenta de Google no tiene contraseña que restablecer,
                           así que ahí no se ofrece. */ ?>
                  <?php if ($u['google_id'] !== null && !$u['tiene_clave']): ?>
                    <span class="celda-sub">Entra con Google</span>
                  <?php else: ?>
                    <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>" class="fila-form">
                      <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                      <input type="hidden" name="accion" value="enlace_clave">
                      <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                      <input type="hidden" name="q" value="<?= e($busqueda) ?>">
                      <button class="link-btn" type="submit">Generar enlace</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  // Se marca cuántas excepciones tiene: sin esto habría que
                  // abrir cuenta por cuenta para saber cuáles están tocadas.
                  $cuantosSueltos = count(json_decode((string) ($u['permisos'] ?? ''), true) ?: []);
                  ?>
                  <a class="link-btn" href="<?= e(base_url('app/admin.php')) ?>?seccion=cuentas&amp;permisos=<?= (int) $u['id'] ?><?= $busqueda !== '' ? '&amp;q=' . e(urlencode($busqueda)) : '' ?>">
                    <?= $cuantosSueltos > 0 ? 'Ajustar' : 'Según el plan' ?>
                  </a>
                  <?php if ($cuantosSueltos > 0): ?>
                    <span class="sus-chip sus-manual"><?= $cuantosSueltos ?> excepción<?= $cuantosSueltos === 1 ? '' : 'es' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?>
              <tr><td colspan="8" class="celda-vacia">No hay cuentas que coincidan con «<?= e($busqueda) ?>».</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'sesiones'): ?>
    <!-- Sesiones abiertas: partidas en curso, no contenido -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Sesiones abiertas</h2>
        <span class="hint">Cada una nació al jugar o enviar un paquete. Caducan solas a las <?= SESION_HORAS_VIDA ?> h, salvo las que se guardaron como informe.</span>
      </div>

      <div class="tabla-scroll">
        <table class="tabla" data-tabla="sesiones">
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Tema</th>
              <th scope="col">Dueño</th>
              <th scope="col">Estado</th>
              <th scope="col">Gente</th>
              <th scope="col">Caduca</th>
              <th scope="col"><span class="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sesiones as $p): ?>
              <tr>
                <td><span class="codigo-chip"><?= e(substr($p['codigo'], 0, 3) . ' ' . substr($p['codigo'], 3)) ?></span></td>
                <td>
                  <b><?= e($p['nombre']) ?></b>
                  <span class="celda-sub"><?= $p['modo'] === 'enviar' ? '📨 Para enviar' : '📽️ En vivo' ?> · <?= (int) $p['total_actividades'] ?> actividades</span>
                </td>
                <td><span class="celda-sub"><?= e($p['dueno'] ?? 'sin cuenta') ?></span></td>
                <td><span class="estado <?= $p['estado'] === 'en_curso' ? 'curso' : ($p['estado'] === 'terminada' ? 'fin' : 'espera') ?>"><?= e($p['estado']) ?></span></td>
                <td class="num"><?= (int) $p['participantes'] ?><span class="celda-sub"><?= (int) $p['respuestas'] ?> resp.</span></td>
                <td><span class="celda-sub"><?= e(date('d/m H:i', strtotime((string) $p['expira_at']))) ?></span></td>
                <td>
                  <div class="acciones-fila">
                    <a class="link-btn" href="<?= e(base_url('app/sesion.php')) ?>?c=<?= e($p['codigo']) ?>">Ver</a>
                    <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>"
                          onsubmit="return confirm('¿Eliminar la sesión <?= e($p['codigo']) ?>? Se borran sus participantes y respuestas. El paquete no se toca.');">
                      <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                      <input type="hidden" name="accion" value="eliminar_paquete">
                      <input type="hidden" name="codigo" value="<?= e($p['codigo']) ?>">
                      <button class="link-btn danger" type="submit">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$sesiones): ?>
              <tr><td colspan="7" class="celda-vacia">No hay ninguna sesión abierta en este momento.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'metricas'): ?>
    <!-- Métricas -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Métricas</h2>
        <span class="hint">Últimos 14 días</span>
      </div>

      <?php
      // La barra más alta manda: así la gráfica se lee aunque los números sean
      // pequeños al principio.
      $tope = 1;
      foreach ($serie as $d) {
          $tope = max($tope, (int) $d['cuentas'], (int) $d['paquetes']);
      }
      ?>
      <div class="grafica" role="img" aria-label="Altas de cuentas y paquetes creados por día">
        <?php foreach ($serie as $d): ?>
          <div class="grafica-dia">
            <div class="grafica-barras">
              <span class="grafica-barra barra-cuentas"
                    style="height:<?= (int) round(((int) $d['cuentas'] / $tope) * 100) ?>%"
                    title="<?= e($d['etiqueta']) ?>: <?= (int) $d['cuentas'] ?> cuentas"></span>
              <span class="grafica-barra barra-paquetes"
                    style="height:<?= (int) round(((int) $d['paquetes'] / $tope) * 100) ?>%"
                    title="<?= e($d['etiqueta']) ?>: <?= (int) $d['paquetes'] ?> paquetes"></span>
            </div>
            <span class="grafica-fecha"><?= e($d['etiqueta']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="grafica-leyenda">
        <span><i class="grafica-punto barra-cuentas"></i> Cuentas nuevas</span>
        <span><i class="grafica-punto barra-paquetes"></i> Paquetes creados</span>
      </div>

      <div class="admin-cifras" style="margin-top:18px">
        <article class="cifra">
          <span class="cifra-label">Ingreso recurrente</span>
          <b class="cifra-valor">$<?= number_format((int) $dinero['recurrente'], 0, ',', '.') ?></b>
          <span class="cifra-pie">al mes, si nadie cancela</span>
        </article>
        <article class="cifra">
          <span class="cifra-label">Cobrado (30 días)</span>
          <b class="cifra-valor">$<?= number_format((int) $dinero['pagos']['cobrado_mes'], 0, ',', '.') ?></b>
          <span class="cifra-pie">$<?= number_format((int) $dinero['pagos']['cobrado'], 0, ',', '.') ?> histórico</span>
        </article>
        <article class="cifra">
          <span class="cifra-label">Suscripciones</span>
          <b class="cifra-valor"><?= (int) $dinero['suscripciones']['activas'] ?></b>
          <span class="cifra-pie"><?= (int) $dinero['suscripciones']['pendientes'] ?> pendientes · <?= (int) $dinero['suscripciones']['canceladas'] ?> canceladas</span>
        </article>
        <article class="cifra">
          <span class="cifra-label">Pagos aprobados</span>
          <b class="cifra-valor"><?= (int) $dinero['pagos']['aprobados'] ?></b>
          <span class="cifra-pie">de <?= (int) $dinero['pagos']['intentos'] ?> intentos</span>
        </article>
      </div>

      <?php if ($masUsados): ?>
        <h3 class="panel-title" style="margin:22px 0 12px">Actividades más usadas</h3>
        <div class="uso-lista">
          <?php foreach ($masUsados as $u2): ?>
            <div class="uso-fila">
              <span><?= e(ucfirst(str_replace('_', ' ', (string) $u2['tipo']))) ?></span>
              <span class="uso-barra"><span style="width:<?= (int) $u2['porcentaje'] ?>%"></span></span>
              <span class="uso-dato"><?= (int) $u2['veces'] ?> · <?= (int) $u2['porcentaje'] ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="hint" style="margin-top:18px">Todavía no hay paquetes creados: cuando los haya, aquí se verá qué actividades se usan más.</p>
      <?php endif; ?>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'planes'): ?>
    <!-- Precios y topes -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Planes, precios y topes</h2>
        <span class="hint">Se aplica al instante, sin tocar código. 0 = sin límite.</span>
      </div>

      <div class="planes-editor">
        <?php foreach (planes_todos() as $clave => $p): ?>
          <form class="plan-editable" method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <input type="hidden" name="accion" value="guardar_plan">
            <input type="hidden" name="clave" value="<?= e($clave) ?>">

            <h3><?= e($p['nombre']) ?>
              <span class="plan-chip plan-<?= e($clave) ?>"><?= count(plan_formatos($clave)) ?> actividades</span>
            </h3>

            <div class="campos-2">
              <div class="field">
                <label for="precio-<?= e($clave) ?>">
                  <?= $clave === 'escuela' ? 'Precio mensual (no aplica)' : 'Precio (COP / mes)' ?>
                </label>
<?php /* Sin `step`: un paso de 100 parecía inofensivo, pero el navegador
         rechaza en SILENCIO cualquier valor que no sea múltiplo —y el precio
         anual lo calculamos como descuento, así que casi nunca lo es—. El
         botón de guardar dejaba de responder sin decir por qué. */ ?>
                <input class="input" id="precio-<?= e($clave) ?>" name="precio" type="number" min="0"
                       value="<?= (int) $p['precio'] ?>" <?= $clave === 'gratis' ? 'readonly' : '' ?>>
              </div>
              <div class="field">
                <label for="anual-<?= e($clave) ?>">Precio (COP / año)</label>
                <input class="input" id="anual-<?= e($clave) ?>" name="precio_anual" type="number" min="0"
                       value="<?= (int) $p['precio_anual'] ?>" <?= $clave === 'gratis' ? 'readonly' : '' ?>>
                <span class="hint">
                  <?php if ($clave === 'escuela'): ?>
                    <?php /* Escuela no tiene precio mensual con el que comparar,
                             así que anunciar un «0 % de descuento» sería una
                             división contra cero disfrazada de oferta. */ ?>
                    Licencia anual de institución · se factura, sin tarjeta
                  <?php elseif ((int) $p['precio'] > 0 && (int) $p['precio_anual'] > 0): ?>
                    <?= round((1 - $p['precio_anual'] / ($p['precio'] * 12)) * 100) ?> % de descuento ·
                    equivale a $<?= number_format((int) round($p['precio_anual'] / 12), 0, ',', '.') ?>/mes
                  <?php else: ?>
                    0 = no se vende por año
                  <?php endif; ?>
                </span>
              </div>
            </div>

            <div class="campos-2">
              <div class="field">
                <label for="paq-<?= e($clave) ?>">Paquetes guardados</label>
                <input class="input" id="paq-<?= e($clave) ?>" name="max_paquetes" type="number" min="0" max="9999" value="<?= (int) $p['max_paquetes'] ?>">
              </div>
              <div class="field">
                <label for="act-<?= e($clave) ?>">Actividades / paquete</label>
                <input class="input" id="act-<?= e($clave) ?>" name="max_actividades" type="number" min="0" max="999" value="<?= (int) $p['max_actividades'] ?>">
              </div>
            </div>

            <div class="field">
              <label for="part-<?= e($clave) ?>">Participantes por sesión</label>
              <input class="input" id="part-<?= e($clave) ?>" name="max_participantes" type="number" min="1" max="9999" value="<?= (int) $p['max_participantes'] ?>">
            </div>

            <div class="interruptores">
              <label><input type="checkbox" name="imagenes" value="1" <?= $p['imagenes'] ? 'checked' : '' ?>> Imágenes en las preguntas</label>
              <label><input type="checkbox" name="informe" value="1" <?= $p['informe'] ? 'checked' : '' ?>> Informe con aciertos y gráficos</label>
              <label><input type="checkbox" name="guarda" value="1" <?= $p['guarda'] ? 'checked' : '' ?>> Sus paquetes se guardan</label>
              <?php /* Exportar estaba escrito en el codigo como «solo pro», lo que
                       dejaba fuera a Escuela. Ahora es una capacidad mas, editable. */ ?>
              <label><input type="checkbox" name="exporta" value="1" <?= $p['exporta'] ? 'checked' : '' ?>> Exportar a SCORM y HTML</label>
            </div>

            <button class="btn btn-primary btn-block btn-sm" type="submit">Guardar <?= e($p['nombre']) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'colegios'): ?>
    <!-- Licencias de institución -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Colegios</h2>
        <span class="hint">La licencia reparte plan Pro entre las cuentas del colegio. Vencer no borra nada.</span>
      </div>

      <?php if ($colegios): ?>
        <div class="colegios-editor">
          <?php foreach ($colegios as $c): ?>
            <?php
            $vigente = colegio_vigente($c);
            $dias = colegio_dias_restantes($c);
            ?>
            <form class="colegio-editable" method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>">
              <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
              <input type="hidden" name="accion" value="guardar_colegio">
              <input type="hidden" name="colegio_id" value="<?= (int) $c['id'] ?>">

              <h3>
                <?= e($c['nombre']) ?>
                <?php /* El estado va con la cifra al lado, nunca solo con el
                         color: "vence en 12 días" dice algo; un punto rojo no. */ ?>
                <span class="sus-chip <?= $vigente ? 'sus-activa' : 'sus-fin' ?>">
                  <?php if (!$c['pagado_hasta']): ?>sin activar
                  <?php elseif ($vigente): ?><?= (int) $dias ?> días
                  <?php else: ?>vencida hace <?= abs((int) $dias) ?> días<?php endif; ?>
                </span>
              </h3>

              <div class="campos-2">
                <div class="field">
                  <label for="col-nombre-<?= (int) $c['id'] ?>">Nombre</label>
                  <input class="input" id="col-nombre-<?= (int) $c['id'] ?>" name="nombre" maxlength="140" value="<?= e($c['nombre']) ?>">
                </div>
                <div class="field">
                  <label for="col-ciudad-<?= (int) $c['id'] ?>">Ciudad</label>
                  <input class="input" id="col-ciudad-<?= (int) $c['id'] ?>" name="ciudad" maxlength="80" value="<?= e((string) $c['ciudad']) ?>">
                </div>
              </div>

              <div class="campos-2">
                <div class="field">
                  <label for="col-hasta-<?= (int) $c['id'] ?>">Licencia pagada hasta</label>
                  <input class="input" id="col-hasta-<?= (int) $c['id'] ?>" name="pagado_hasta" type="date" value="<?= e((string) $c['pagado_hasta']) ?>">
                </div>
                <div class="field">
                  <label for="col-cupo-<?= (int) $c['id'] ?>">Cuentas</label>
                  <input class="input" id="col-cupo-<?= (int) $c['id'] ?>" name="cupo_profesores" type="number" min="0" max="9999" value="<?= (int) $c['cupo_profesores'] ?>">
                  <span class="hint"><?= (int) $c['cuentas'] ?> ocupadas · <?= (int) $c['cursos'] ?> cursos</span>
                </div>
              </div>

              <div class="campos-2">
                <div class="field">
                  <label for="col-precio-<?= (int) $c['id'] ?>">Precio al año (COP)</label>
                  <input class="input" id="col-precio-<?= (int) $c['id'] ?>" name="precio_anual" type="number" min="0" value="<?= (int) $c['precio_anual'] ?>">
                </div>
                <div class="field">
                  <label for="col-mail-<?= (int) $c['id'] ?>">Contacto</label>
                  <input class="input" id="col-mail-<?= (int) $c['id'] ?>" name="contacto_email" type="email" value="<?= e((string) $c['contacto_email']) ?>">
                </div>
              </div>

              <div class="interruptores">
                <label><input type="checkbox" name="activo" value="1" <?= $c['activo'] ? 'checked' : '' ?>> Licencia activa</label>
              </div>

              <button class="btn btn-primary btn-block btn-sm" type="submit">Guardar <?= e($c['nombre']) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="vacio">
          <b>🏫</b>
          <p>Todavía no hay colegios. Crea el primero abajo y activa su licencia.</p>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Nuevo colegio</h2>
        <span class="hint">Después, su coordinador da de alta a los profesores desde «Mi colegio».</span>
      </div>

      <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="accion" value="crear_colegio">

        <div class="campos-2">
          <div class="field">
            <label for="nuevo-col-nombre">Nombre del colegio</label>
            <input class="input" id="nuevo-col-nombre" name="nombre" required maxlength="140" placeholder="Institución Educativa San José">
          </div>
          <div class="field">
            <label for="nuevo-col-ciudad">Ciudad</label>
            <input class="input" id="nuevo-col-ciudad" name="ciudad" maxlength="80" placeholder="Medellín">
          </div>
        </div>

        <div class="campos-2">
          <div class="field">
            <label for="nuevo-col-hasta">Licencia pagada hasta</label>
            <input class="input" id="nuevo-col-hasta" name="pagado_hasta" type="date" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>">
          </div>
          <div class="field">
            <label for="nuevo-col-cupo">Cuentas de profesor</label>
            <input class="input" id="nuevo-col-cupo" name="cupo_profesores" type="number" min="0" max="9999" value="30">
          </div>
        </div>

        <div class="field">
          <label for="nuevo-col-precio">Precio al año (COP)</label>
          <input class="input" id="nuevo-col-precio" name="precio_anual" type="number" min="0" value="6000000">
        </div>

        <button class="btn btn-primary" type="submit">Crear colegio</button>
      </form>
    </section>

    <?php endif; ?>

    <?php if ($seccion === 'catalogo'): ?>
    <!-- Catálogo: a qué plan pertenece cada actividad -->
    <section class="panel admin-bloque">
      <div class="admin-bloque-head">
        <h2 class="panel-title">Actividades por plan</h2>
        <span class="hint">Una actividad nueva se asigna aquí: los planes de pago la reciben al instante.</span>
      </div>

      <ul class="admin-formatos" style="margin-bottom:16px">
        <?php foreach ($formatos as $f): ?>
          <li>
            <span class="plan-chip plan-<?= e($f['plan_minimo']) ?>"><?= e(plan_nombre((string) $f['plan_minimo'])) ?></span>
            <b><?= (int) $f['cuantos'] ?></b>
            <span class="celda-sub"><?= $f['estado'] === 'activo' ? 'construidas' : 'en lista de espera' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="tabla-scroll">
        <table class="tabla" data-tabla="catalogo">
          <thead>
            <tr>
              <th scope="col">Actividad</th>
              <th scope="col">Grupo</th>
              <th scope="col">Plan mínimo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($catalogo as $f): ?>
              <tr>
                <td>
                  <b><?= e($f['nombre']) ?></b>
                  <span class="celda-sub"><?= e($f['clave']) ?><?= $f['evaluable'] ? '' : ' · no puntúa' ?></span>
                </td>
                <td><span class="celda-sub"><?= e(str_replace('_', ' ', (string) $f['grupo'])) ?></span></td>
                <td>
                  <form method="post" action="<?= e(base_url('app/admin.php')) ?>?seccion=<?= e($seccion) ?>" class="fila-form">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <input type="hidden" name="accion" value="mover_formato">
                    <input type="hidden" name="formato" value="<?= e($f['clave']) ?>">
                    <select class="select select-sm" name="plan_minimo" onchange="this.form.submit()"
                            aria-label="Plan mínimo de <?= e($f['nombre']) ?>">
                      <?php foreach (planes_todos() as $clave => $p): ?>
                        <option value="<?= e($clave) ?>"<?= $f['plan_minimo'] === $clave ? ' selected' : '' ?>><?= e($p['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Mover</button></noscript>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

      </div><!-- /.admin-contenido -->
    </div><!-- /.admin-layout -->

<?php endif; ?>
  </div>
</main>

<?php require LUDIA_ROOT . '/includes/partials/footer.php'; ?>
