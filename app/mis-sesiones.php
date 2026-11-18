<?php
/**
 * Mis paquetes: el contenido guardado de la cuenta.
 *
 * Un paquete es permanente y se puede EDITAR SIEMPRE. Cada vez que se juega en
 * vivo o se envía nace una sesión aparte, con su propio código y sus propios
 * participantes; los resultados de cada una viven en "Informes" (planes de
 * pago). Antes esta página listaba sesiones, y por eso el contenido caducaba a
 * las 24 h y se bloqueaba en cuanto alguien respondía.
 *
 * Todas las acciones van por POST con CSRF y responden con redirección (PRG).
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Paquetes.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Juegos.php';

$usuario   = auth_requerir();
$usuarioId = (int) $usuario['id'];
$aviso     = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (auth_csrf_valido($_POST['csrf'] ?? null)) {
        $id     = (int) ($_POST['id'] ?? 0);
        $accion = (string) ($_POST['accion'] ?? '');

        if ($id > 0) {
            $paquete = paquete_de($id, $usuarioId);

            if (!$paquete) {
                $_SESSION['paquete_aviso'] = ['ok' => false, 'mensaje' => 'No encontramos ese paquete.'];
            } else {
                switch ($accion) {
                    // Jugar y enviar son lo mismo con distinto modo: abren una
                    // sesión NUEVA con una copia del contenido de ahora.
                    case 'jugar':
                    case 'enviar':
                        $resultado = sesion_lanzar(
                            $paquete,
                            $usuarioId,
                            $accion === 'enviar' ? 'enviar' : 'vivo',
                            plan_max_participantes($usuario)
                        );
                        $_SESSION['paquete_aviso'] = $resultado;

                        if ($resultado['ok']) {
                            header('Location: ' . base_url('app/sesion.php') . '?c=' . $resultado['codigo']);
                            exit;
                        }
                        break;

                    case 'duplicar':
                        if (juego_es($paquete)) {
                            $resultado = juego_duplicar($usuario, $paquete);
                            $_SESSION['paquete_aviso'] = ['ok' => $resultado['ok'], 'mensaje' => $resultado['mensaje']];
                            if ($resultado['ok']) {
                                header('Location: ' . base_url('app/juego.php') . '?id=' . $resultado['id']);
                                exit;
                            }
                            break;
                        }
                        $resultado = paquete_duplicar($id, $usuarioId, $usuario);
                        $_SESSION['paquete_aviso'] = $resultado;

                        // Se duplica para cambiar algo, así que lo útil es
                        // entrar directo a la copia. Ojo con la clave:
                        // paquete_duplicar devuelve el ID del paquete nuevo, no
                        // un código de sesión —un paquete no tiene código—.
                        if ($resultado['ok']) {
                            header('Location: ' . base_url('app/crear.php') . '?editar=' . $resultado['id']);
                            exit;
                        }
                        break;

                    case 'eliminar':
                        $_SESSION['paquete_aviso'] = paquete_eliminar($id, $usuarioId)
                            ? ['ok' => true, 'mensaje' => (juego_es($paquete) ? 'Juego eliminado.' : 'Paquete eliminado.') . ' Sus informes siguen en "Informes".']
                            : ['ok' => false, 'mensaje' => 'No encontramos ese paquete.'];
                        break;

                    default:
                        $_SESSION['paquete_aviso'] = ['ok' => false, 'mensaje' => 'Acción desconocida.'];
                }
            }
        }
    }

    header('Location: ' . base_url('app/mis-sesiones.php'));
    exit;
}

$avisoTipo = 'ok';
if (!empty($_SESSION['paquete_aviso'])) {
    $aviso     = $_SESSION['paquete_aviso']['mensaje'];
    $avisoTipo = $_SESSION['paquete_aviso']['ok'] ? 'ok' : 'error';
    unset($_SESSION['paquete_aviso']);
}

// Paquetes y juegos son la misma tabla (clase = 'paquete' | 'juego') y cada
// uno tiene su propio cupo del plan.
$todos    = paquetes_del_usuario($usuarioId);
$paquetes = array_values(array_filter($todos, static fn (array $p): bool => ($p['clase'] ?? 'paquete') !== 'juego'));
$juegos   = array_values(array_filter($todos, static fn (array $p): bool => ($p['clase'] ?? 'paquete') === 'juego'));
$plan     = plan_capacidades($usuario);
$cupo     = plan_max_paquetes($usuario);   // 0 = sin tope
$usados   = count($paquetes);

$misFormatos   = count(plan_formatos(plan_de($usuario)));
$todosFormatos = count(plan_formatos('pro'));

// Los informes son de los planes de pago; el gratis juega igual, pero no queda
// registro de lo que hicieron los estudiantes.
$guardaInformes = plan_permite($usuario, 'informe');
// Capacidad, no un plan escrito a mano: así Escuela también exporta y se
// puede conceder a una cuenta suelta desde el panel.
$puedeExportar  = puede_exportar($usuario);

$page = [
    'title'       => 'Mis paquetes — Ludia',
    'description' => 'Tus paquetes guardados: edítalos, juégalos en vivo o envíalos.',
    'home'        => false,
];
$extraStyles  = ['css/cuenta.css', 'css/tutoriales.css'];
$extraScripts = ['js/tutoriales.js'];

require LUDIA_ROOT . '/includes/partials/header.php';
?>

<main id="contenido" class="cuenta">
  <div class="wrap">
    <div class="mis-head">
      <div>
        <span class="eyebrow">Plan <?= e($plan['nombre']) ?></span>
        <h1>Mis paquetes</h1>
        <p class="cuenta-sub">
          <?php if ($cupo > 0): ?>
            <b><?= $usados ?> de <?= $cupo ?></b> paquetes guardados · se quedan hasta que tú los borres.
          <?php else: ?>
            <b><?= $usados ?></b> <?= $usados === 1 ? 'paquete guardado' : 'paquetes guardados' ?> · sin límite en tu plan.
          <?php endif; ?>
        </p>
      </div>
      <div class="mis-head-acciones">
        <?php if ($guardaInformes): ?>
          <a class="btn btn-ghost" href="<?= e(base_url('app/informes.php')) ?>">📊 Informes</a>
        <?php endif; ?>
        <?php if ($cupo === 0 || count($juegos) < $cupo): ?>
          <a class="btn btn-ghost" href="<?= e(base_url('app/juego.php')) ?>" data-crear-juego>🎲 Crear juego</a>
        <?php endif; ?>
        <?php if ($cupo === 0 || $usados < $cupo): ?>
          <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>?nuevo=paquete">Crear paquete</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($cupo > 0): ?>
      <div class="meter" aria-hidden="true"><span style="width:<?= (int) round((min($usados, $cupo) / $cupo) * 100) ?>%"></span></div>
    <?php endif; ?>

    <?php if ($aviso): ?>
      <p class="mensaje <?= e($avisoTipo) ?>" role="status"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if ($juegos): ?>
      <h2 class="mis-seccion">🎲 Mis juegos <small><?= count($juegos) ?><?= $cupo > 0 ? ' de ' . $cupo : '' ?></small></h2>
      <ul class="lista-sesiones" data-lista="juegos">
        <?php foreach ($juegos as $p): ?>
          <?php require LUDIA_ROOT . '/includes/partials/tarjeta-paquete.php'; ?>
        <?php endforeach; ?>
      </ul>
      <h2 class="mis-seccion">📦 Mis paquetes</h2>
    <?php endif; ?>

    <?php if (!$paquetes): ?>
      <div class="panel vacio">
        <b aria-hidden="true">📭</b>
        <p>Todavía no tienes paquetes guardados.</p>
        <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>?nuevo=paquete">Crear el primero</a>
      </div>
    <?php else: ?>
      <ul class="lista-sesiones">
        <?php foreach ($paquetes as $p): ?>
          <?php require LUDIA_ROOT . '/includes/partials/tarjeta-paquete.php'; ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (plan_de($usuario) === 'gratis'): ?>
      <div class="panel plan-aviso">
        <div>
          <b>Tu plan gratis guarda <?= $cupo ?> paquetes e incluye <?= $misFormatos ?> de las <?= $todosFormatos ?> actividades.</b>
          <p class="cuenta-sub">Con un plan de pago guardas muchos más paquetes, usas imágenes en las preguntas y —sobre todo— cada sesión que juegues o envíes deja su informe con aciertos y gráficos. Pro no tiene ningún tope.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('app/suscripcion.php')) ?>">Ver los planes</a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php
// Mini tutoriales animados: salen solos la primera vez y se vuelven a abrir
// desde "¿Cómo funciona?" en el menú.
require LUDIA_ROOT . '/includes/partials/tutoriales.php';
require LUDIA_ROOT . '/includes/partials/footer.php';
?>
