<?php
/**
 * POST /api/suscripcion-crear.php
 * Cuerpo: { plan: "estandar" | "pro", periodo: "mensual" | "anual" }
 *
 * Prepara el pago en Wompi y devuelve el enlace al checkout. No otorga nada:
 * el plan solo sube cuando Wompi confirma el cobro (ver
 * suscripcion_procesar_transaccion()).
 *
 * Sirve igual para el primer pago que para renovar: lo pagado se suma a lo que
 * quede.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Planes.php';
require_once __DIR__ . '/../includes/Suscripciones.php';
require_once __DIR__ . '/../includes/api.php';

api_iniciar();
api_metodo('POST');

$usuario = auth_usuario();
if (!$usuario) {
    api_error('Necesitas ingresar con tu cuenta', 401, ['ingresar' => base_url('app/ingresar.php')]);
}

if (!wompi_configurado()) {
    api_error('Los pagos todavía no están configurados en este servidor. Escríbenos y te activamos el plan a mano.', 503);
}

$datos = api_cuerpo();
$plan = api_opcion($datos, 'plan', ['estandar', 'pro'], '');
$periodo = api_opcion($datos, 'periodo', ['mensual', 'anual'], 'mensual');

if ($plan === '') {
    api_error('Elige un plan válido', 422);
}

// Bajar de Pro a Estándar con Pro aún pagado le quitaría el Pro al instante,
// porque lo pagado se suma y el plan pasa a ser el último comprado.
$actual = suscripcion_de_usuario((int) $usuario['id']);
if ($plan === 'estandar' && suscripcion_vigente($actual) && $actual['plan'] === 'pro') {
    api_error('Tienes Pro pagado hasta el ' . date('d/m/Y', strtotime((string) $actual['pagado_hasta']))
        . '. Podrás pasar a Estándar cuando venza.', 422);
}

// El monto sale de la tabla `planes`, nunca del navegador: el precio lo pone el
// administrador, no quien pulsa el botón.
$datosPlan = plan_datos($plan);
$monto = $periodo === 'anual' ? (int) $datosPlan['precio_anual'] : (int) $datosPlan['precio'];

if ($monto <= 0) {
    api_error($periodo === 'anual'
        ? 'Ese plan todavía no se vende por año.'
        : 'Ese plan no tiene precio configurado.', 422);
}

$nueva = suscripcion_crear((int) $usuario['id'], $plan, $monto, $periodo);

// Absoluta y con el esquema real: detrás del proxy del servidor la petición
// llega como HTTP aunque el visitante use HTTPS.
$enlace = wompi_enlace_pago(
    $nueva['referencia'],
    $monto,
    (string) $usuario['email'],
    url_absoluta('app/pago-listo.php')
);

api_ok([
    'enlace'  => $enlace,
    'plan'    => $plan,
    'periodo' => $periodo,
    'monto'   => $monto,
]);
