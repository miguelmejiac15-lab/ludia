<?php
/**
 * POST /api/paquete-guardar.php
 * Cuerpo: { id?, nombre, audiencia, actividades: [...] }
 *
 * Crea o actualiza un PAQUETE, que es solo el contenido. Aquí no se genera
 * ningún código ni se abre ninguna sala: jugarlo o enviarlo es otra acción
 * (ver "Mis paquetes"), y cada vez que se hace nace una sesión aparte.
 *
 * Editar está SIEMPRE permitido, incluso si el paquete ya se jugó mil veces:
 * cada sesión conserva su propia copia del contenido, así que los informes ya
 * emitidos siguen cuadrando con lo que respondieron los estudiantes. Esto
 * sustituye a sesion-crear.php y sesion-actualizar.php, donde editar se
 * bloqueaba en cuanto alguien respondía.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Paquetes.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';

api_iniciar();
api_metodo('POST');

// Crear contenido exige cuenta; jugar no.
$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta para guardar un paquete', 401, [
        'ingresar' => base_url('app/ingresar.php'),
    ]);
}

$datos       = api_cuerpo();
$id          = (int) ($datos['id'] ?? 0);
$nombre      = api_texto($datos, 'nombre', 120);
$audiencia   = api_opcion($datos, 'audiencia', ['estudiantes', 'auditorio', 'capacitacion'], 'estudiantes');
$actividades = sesion_normalizar_actividades($datos['actividades'] ?? null);

// El catálogo y el tope de actividades se recortan por plan aquí, no solo en
// el navegador.
sesion_exigir_formatos_del_plan($actividades, $usuario);

$usuarioId = (int) $usuario['id'];

if ($id > 0) {
    if (!paquete_de($id, $usuarioId)) {
        api_error('Ese paquete no es tuyo', 403);
    }
    paquete_actualizar($id, $usuarioId, $nombre, $audiencia, $actividades);
    $creado = false;
} else {
    // Cuántos paquetes puede GUARDAR el plan. Ya no caducan solos: el profesor
    // borra el que no quiera.
    $tope      = plan_max_paquetes($usuario);
    $guardados = paquetes_contar($usuarioId);

    if ($tope > 0 && $guardados >= $tope) {
        api_error(
            'Tu plan ' . plan_nombre(plan_de($usuario)) . ' guarda ' . $tope . ' paquetes. ' .
            'Borra uno en "Mis paquetes" o pásate a un plan superior.',
            409,
            [
                'mis_sesiones' => base_url('app/mis-sesiones.php'),
                'guardados'    => $guardados,
                'planes'       => base_url('/') . '#planes',
            ]
        );
    }

    $id = paquete_crear($usuarioId, $nombre, $audiencia, $actividades);
    $creado = true;
}

api_ok([
    'id'                => $id,
    'creado'            => $creado,
    'total_actividades' => count($actividades),
    'url_paquetes'      => base_url('app/mis-sesiones.php'),
]);
