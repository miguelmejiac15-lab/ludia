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

    /**
     * Mercado Pago — cobro de las suscripciones.
     *
     * Las credenciales se sacan del panel de Mercado Pago (Tus integraciones →
     * Credenciales). Con las de PRUEBA se cobra con tarjetas de test y no se
     * mueve dinero real; cambia a las de producción solo cuando vayas a vender.
     *
     * `webhook_secret` es la clave de firma que Mercado Pago muestra al
     * configurar las notificaciones: sirve para comprobar que un aviso viene
     * de verdad de ellos y no de alguien que descubrió la URL.
     */
    'mercadopago' => [
        'access_token'   => '',       // APP_USR-... (o TEST-... en pruebas)
        'public_key'     => '',
        'webhook_secret' => '',
        'moneda'         => 'COP',
        'pruebas'        => true,     // false cuando uses credenciales de producción
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
