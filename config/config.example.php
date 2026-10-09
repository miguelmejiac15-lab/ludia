<?php
/**
 * Plantilla de configuración de Ludia.
 *
 * Copia este archivo como config/config.php y pon ahí las credenciales reales.
 * config.php NO se versiona (ver .gitignore); este archivo sí.
 */

return [
    'app' => [
        'name'     => 'Ludia',
        'env'      => 'local',          // local | produccion
        'debug'    => true,             // false en producción
        'base_url' => null,             // null = autodetectar (ej. /LUDIRA en XAMPP, '' en la raíz del dominio)
        'timezone' => 'America/Bogota',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'ludia',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // No hay credenciales de IA: la plataforma no genera contenido automáticamente
    // (decisión del 2026-09-15, ver masterplan sección 14).

    /*
     * Google Analytics 4 (G-XXXXXXXXXX). Vacío = no se carga. En local se deja
     * vacío para no mezclar tus pruebas con las visitas reales.
     */
    'analytics' => [
        'id' => '',
    ],

    /**
     * Wompi — cobro de los planes (pago por adelantado, sin cobro automático).
     *
     * Se sacan del panel de Wompi → Desarrollo → Programadores. Con las de
     * Sandbox (pub_test_...) se paga con datos de prueba y no se mueve dinero;
     * la propia llave decide a qué ambiente se habla.
     *
     * `events_secret` comprueba que un aviso viene de verdad de Wompi; sin él,
     * los avisos se rechazan. `integrity_secret` firma el monto del checkout
     * para que nadie lo cambie en la URL. La llave privada NO hace falta.
     *
     * En el panel de Wompi, la "URL de eventos" es https://<dominio>/api/wompi-webhook.php
     */
    'wompi' => [
        'public_key'       => '',   // pub_test_... o pub_prod_...
        'events_secret'    => '',   // test_events_... o prod_events_...
        'integrity_secret' => '',   // test_integrity_... o prod_integrity_...
    ],

    /*
     * Entrar con Google (OAuth 2.0). Sin estas claves, el botón no aparece y
     * todo lo demás sigue funcionando igual: es opcional, como los pagos.
     *
     * Se sacan de console.cloud.google.com → APIs y servicios → Credenciales →
     * "ID de cliente de OAuth" (tipo: aplicación web). Ahí mismo hay que
     * registrar la URI de redirección EXACTA: si no coincide carácter por
     * carácter con la de abajo, Google rechaza el acceso.
     *
     * En producción Google exige HTTPS; localhost es la única excepción.
     */
    'google' => [
        'client_id'     => '',
        'client_secret' => '',
        'redirect_uri'  => 'http://localhost/LUDIRA/app/google-volver.php',
    ],

    /**
     * Quién responde por los datos, para la Política de Privacidad.
     *
     * Va aquí y no escrito dentro de la página porque son datos que cambian
     * (una dirección, un correo de contacto) y porque la política los repite
     * en varios sitios: en un solo lugar no pueden quedar descuadrados.
     *
     * Mientras estén vacíos, la página lo dice en voz alta en vez de fingir
     * que están. Una política sin responsable identificable no sirve de nada,
     * y disimularlo sería peor que la falta.
     */
    'legal' => [
        'responsable' => '',   // nombre o razón social de quien responde
        'documento'   => '',   // NIT o cédula
        'ciudad'      => '',   // ciudad y país
        'correo'      => '',   // correo para ejercer derechos (habeas data)
    ],
];
