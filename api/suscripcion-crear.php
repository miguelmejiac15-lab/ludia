<?php
/**
 * POST /api/suscripcion-crear.php
 * Cuerpo: { plan: "estandar" | "pro" }
 *
 * Prepara la suscripción en Mercado Pago y devuelve el enlace de pago.
 * No otorga nada: el plan solo sube cuando Mercado Pago confirma el cobro
 * (ver api/mercadopago-webhook.php).
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

if (!mp_configurado()) {
    api_error('Los pagos todavía no están configurados en este servidor. Escríbenos y te activamos el plan a mano.', 503);
}

$datos = api_cuerpo();
$plan = api_opcion($datos, 'plan', ['estandar', 'pro'], '');
$periodo = api_opcion($datos, 'periodo', ['mensual', 'anual'], 'mensual');

if ($plan === '') {
    api_error('Elige un plan válido', 422);
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

$suscripcionId = suscripcion_crear((int) $usuario['id'], $plan, $monto, $periodo);

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$volverA = $esquema . '://' . $host . base_url('app/pago-listo.php');

$respuesta = mp_crear_suscripcion(
    $suscripcionId,
    (string) $usuario['email'],
    plan_nombre($plan),
    $monto,
    $volverA,
    $periodo
);

if (!$respuesta['ok'] || $respuesta['enlace'] === '') {
    api_error('No pudimos abrir el pago: ' . $respuesta['error'], 502);
}

suscripcion_guardar_preapproval($suscripcionId, $respuesta['preapproval_id']);

api_ok([
    'enlace'  => $respuesta['enlace'],
    'plan'    => $plan,
    'periodo' => $periodo,
    'monto'   => $monto,
]);
