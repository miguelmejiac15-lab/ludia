<?php
/**
 * Paquetes de demostración: el catálogo completo con un tema sencillo
 * ("Las plantas"), para mostrar Ludia y grabar vídeos de ejemplo (08/10/2026).
 *
 * Son DOS paquetes porque el vídeo con preguntas solo se juega enviado
 * (SESION_SOLO_ENVIAR): metido en el paquete en vivo, ese paquete no se podría
 * proyectar.
 *
 * Los carga un administrador en SU cuenta desde el panel. No mira el plan a
 * propósito: es material de muestra y tiene que llevar los 23 formatos.
 */

declare(strict_types=1);

require_once __DIR__ . '/Paquetes.php';
require_once __DIR__ . '/Sesiones.php';

/** Las ilustraciones viven en assets/img/demo, dentro del propio sitio. */
function demo_imagen(string $nombre): string
{
    return base_url('assets/img/demo/' . $nombre . '.svg');
}

/** @return list<array{nombre:string, audiencia:string, actividades:list<array>}> */
function demo_paquetes(): array
{
    $vivo = [
        ['tipo' => 'pagina', 'tiempo' => 20, 'items' => [
            ['titulo' => '🌱 Las plantas', 'imagen' => demo_imagen('planta'), 'parrafos' => [
                'Las plantas son seres vivos: nacen, crecen, se reproducen y mueren.',
                'A diferencia de los animales, fabrican su propio alimento.',
                'Hoy vamos a descubrir cómo lo hacen.',
            ]],
            ['titulo' => '¿Qué necesita una planta?', 'imagen' => demo_imagen('sol'), 'parrafos' => [
                '☀️ Luz del sol, para tener energía.',
                '💧 Agua, que toma del suelo con sus raíces.',
                '🌬️ Aire, del que toma el dióxido de carbono.',
            ]],
        ]],
        ['tipo' => 'panel', 'tiempo' => 20, 'items' => [
            ['instruccion' => 'Repasa las partes de la planta', 'tarjetas' => [
                ['titulo' => 'Raíz', 'texto' => 'Sujeta la planta y absorbe agua del suelo.'],
                ['titulo' => 'Tallo', 'texto' => 'Sostiene la planta y lleva el agua a las hojas.'],
                ['titulo' => 'Hoja', 'texto' => 'Aquí se fabrica el alimento con la luz del sol.'],
                ['titulo' => 'Flor', 'texto' => 'De ella nacen el fruto y las semillas.'],
            ]],
        ]],
        ['tipo' => 'quiz', 'tiempo' => 20, 'items' => [
            ['enunciado' => '¿Qué parte de la planta absorbe el agua?', 'imagen' => demo_imagen('gota'),
             'opciones' => ['La raíz', 'La flor', 'El fruto', 'La hoja'], 'correcta' => 0],
            ['enunciado' => '¿Qué necesitan las plantas para hacer la fotosíntesis?', 'imagen' => demo_imagen('sol'),
             'opciones' => ['Luz del sol', 'Oscuridad', 'Sal', 'Arena'], 'correcta' => 0],
        ]],
        ['tipo' => 'verdadero_falso', 'tiempo' => 15, 'items' => [
            ['afirmacion' => 'Las plantas fabrican su propio alimento.', 'correcta' => true],
            ['afirmacion' => 'Las plantas pueden vivir sin agua.', 'correcta' => false, 'imagen' => demo_imagen('gota')],
        ]],
        ['tipo' => 'respuesta_multiple', 'tiempo' => 25, 'items' => [
            ['enunciado' => '¿Cuáles son partes de una planta?', 'imagen' => demo_imagen('flor'),
             'opciones' => ['Raíz', 'Tallo', 'Ala', 'Hoja'], 'correctas' => [0, 1, 3]],
        ]],
        ['tipo' => 'palabra_secreta', 'tiempo' => 40, 'items' => [
            ['palabra' => 'SEMILLA', 'pista' => 'De ella nace una planta nueva', 'imagen' => demo_imagen('semilla')],
        ]],
        ['tipo' => 'completar', 'tiempo' => 30, 'items' => [
            ['texto' => 'Las plantas necesitan [luz], [agua] y aire para vivir.', 'imagen' => demo_imagen('planta')],
        ]],
        ['tipo' => 'ordenar_palabras', 'tiempo' => 30, 'items' => [
            ['frase' => 'Las hojas fabrican el alimento'],
        ]],
        ['tipo' => 'sopa_letras', 'tiempo' => 60, 'items' => [
            ['instruccion' => 'Encuentra las partes de la planta', 'palabras' => ['RAIZ', 'TALLO', 'HOJA', 'FLOR', 'FRUTO']],
        ]],
        ['tipo' => 'ortografia', 'tiempo' => 25, 'items' => [
            ['instruccion' => 'Elige la forma correcta', 'pares' => [
                ['correcta' => 'hoja', 'incorrecta' => 'oja'],
                ['correcta' => 'semilla', 'incorrecta' => 'cemilla'],
                ['correcta' => 'vegetal', 'incorrecta' => 'bejetal'],
            ]],
        ]],
        ['tipo' => 'anagrama', 'tiempo' => 30, 'items' => [
            ['palabra' => 'TALLO', 'pista' => 'Sostiene la planta', 'imagen' => demo_imagen('planta')],
        ]],
        ['tipo' => 'numerica', 'tiempo' => 25, 'items' => [
            ['enunciado' => 'Una flor tiene 5 pétalos. ¿Cuántos pétalos hay en 4 flores?', 'imagen' => demo_imagen('flor'),
             'respuesta' => '20', 'tolerancia' => 0, 'unidad' => 'pétalos'],
        ]],
        ['tipo' => 'linea_tiempo', 'tiempo' => 40, 'items' => [
            ['instruccion' => 'Ordena los descubrimientos del más antiguo al más reciente', 'hechos' => [
                ['fecha' => '1648', 'texto' => 'Van Helmont: el árbol crece gracias al agua'],
                ['fecha' => '1771', 'texto' => 'Priestley: las plantas renuevan el aire'],
                ['fecha' => '1779', 'texto' => 'Ingenhousz: las plantas necesitan luz'],
                ['fecha' => '1961', 'texto' => 'Calvin gana el Nobel por explicar la fotosíntesis'],
            ]],
        ]],
        ['tipo' => 'crucigrama', 'tiempo' => 90, 'items' => [
            ['instruccion' => 'Resuelve el crucigrama de las plantas', 'entradas' => [
                ['palabra' => 'HOJA', 'pista' => 'Es verde y fabrica el alimento'],
                ['palabra' => 'RAIZ', 'pista' => 'Está bajo la tierra'],
                ['palabra' => 'AGUA', 'pista' => 'La planta la bebe del suelo'],
                ['palabra' => 'SOL', 'pista' => 'Da luz y energía'],
                ['palabra' => 'FLOR', 'pista' => 'Tiene pétalos de colores'],
            ]],
        ]],
        ['tipo' => 'relacionar', 'tiempo' => 40, 'items' => [
            ['instruccion' => 'Une cada parte con lo que hace', 'pares' => [
                ['izquierda' => 'Raíz', 'derecha' => 'Absorbe el agua'],
                ['izquierda' => 'Hoja', 'derecha' => 'Fabrica el alimento'],
                ['izquierda' => 'Flor', 'derecha' => 'Da origen al fruto'],
                ['izquierda' => 'Tallo', 'derecha' => 'Sostiene la planta'],
            ]],
        ]],
        ['tipo' => 'memoria', 'tiempo' => 60, 'items' => [
            ['instruccion' => 'Encuentra las parejas', 'pares' => [
                ['izquierda' => '☀️', 'derecha' => 'Sol'],
                ['izquierda' => '💧', 'derecha' => 'Agua'],
                ['izquierda' => '🌱', 'derecha' => 'Brote'],
                ['izquierda' => '🌸', 'derecha' => 'Flor'],
            ]],
        ]],
        ['tipo' => 'clasificar', 'tiempo' => 45, 'items' => [
            ['instruccion' => '¿Es una planta o un animal?', 'categorias' => [
                ['nombre' => 'Planta 🌿', 'elementos' => ['Girasol', 'Helecho', 'Cactus']],
                ['nombre' => 'Animal 🐾', 'elementos' => ['Conejo', 'Mariposa', 'Rana']],
            ]],
        ]],
        ['tipo' => 'ordenar', 'tiempo' => 40, 'items' => [
            ['instruccion' => 'Ordena el ciclo de vida de la planta', 'imagen' => demo_imagen('semilla'),
             'elementos' => ['Se siembra la semilla', 'Sale un brote', 'Crece la planta', 'Aparece la flor', 'Nace el fruto']],
        ]],
        ['tipo' => 'mural', 'tiempo' => 60, 'items' => [
            ['consigna' => '¿Qué planta tienes en casa o en tu barrio?', 'modo' => 'postit', 'anonimo' => false,
             'moderar' => false, 'maxCaracteres' => 80, 'ejemplos' => ['Una mata de sábila']],
        ]],
        ['tipo' => 'encuesta', 'tiempo' => 20, 'items' => [
            ['pregunta' => '¿Cuál es tu parte favorita de la planta?', 'imagen' => demo_imagen('flor'),
             'opciones' => ['La flor', 'El fruto', 'Las hojas', 'La raíz']],
        ]],
        ['tipo' => 'pregunta_abierta', 'tiempo' => 30, 'items' => [
            ['pregunta' => 'Di una palabra que te recuerde a las plantas', 'maxCaracteres' => 30],
        ]],
        ['tipo' => 'escala', 'tiempo' => 20, 'items' => [
            ['pregunta' => '¿Cuánto aprendiste hoy sobre las plantas?', 'etiquetaMin' => 'Muy poco', 'etiquetaMax' => '¡Muchísimo!'],
        ]],
    ];

    // Solo enviado: el vídeo se ve a su ritmo en el dispositivo de cada uno.
    // «LA FOTOSÍNTESIS - Video educativo para niñ@s» (Aprende con Tía Viviana,
    // 4:03), comprobado que permite insertarse el 08/10/2026.
    $video = [
        ['tipo' => 'pagina', 'tiempo' => 20, 'items' => [
            ['titulo' => '🎬 La fotosíntesis en vídeo', 'imagen' => demo_imagen('hoja'), 'parrafos' => [
                'Mira el vídeo con atención: se detendrá para hacerte preguntas.',
                'Responde y el vídeo seguirá solo.',
            ]],
        ]],
        ['tipo' => 'video_preguntas', 'tiempo' => 25, 'video' => 'https://www.youtube.com/watch?v=fLKBM5p1DQE', 'items' => [
            ['segundo' => 40, 'enunciado' => '¿De dónde sacan las plantas la energía?', 'opciones' => ['Del sol', 'De la luna', 'Del viento', 'De las piedras'], 'correcta' => 0],
            ['segundo' => 110, 'enunciado' => '¿Por dónde toma la planta el agua?', 'opciones' => ['Por la raíz', 'Por la flor', 'Por el fruto', 'Por la semilla'], 'correcta' => 0],
            ['segundo' => 190, 'enunciado' => '¿Qué gas sueltan las plantas al aire?', 'opciones' => ['Oxígeno', 'Humo', 'Vapor de sal', 'Ninguno'], 'correcta' => 0],
        ]],
        ['tipo' => 'verdadero_falso', 'tiempo' => 15, 'items' => [
            ['afirmacion' => 'Gracias a las plantas tenemos oxígeno para respirar.', 'correcta' => true, 'imagen' => demo_imagen('planta')],
        ]],
    ];

    return [
        ['nombre' => 'Demo · Las plantas (en vivo, todas las actividades)', 'audiencia' => 'estudiantes', 'actividades' => $vivo],
        ['nombre' => 'Demo · Las plantas (vídeo, para enviar)', 'audiencia' => 'estudiantes', 'actividades' => $video],
    ];
}

/**
 * Crea los paquetes de demostración en una cuenta. Si ya existe uno con el
 * mismo nombre, no lo duplica: pulsar el botón dos veces no llena la lista.
 *
 * @return array{ok:bool, mensaje:string}
 */
function demo_cargar(int $usuarioId): array
{
    $creados = 0;
    foreach (demo_paquetes() as $p) {
        $existe = db()->prepare('SELECT COUNT(*) FROM paquetes WHERE usuario_id = ? AND nombre = ?');
        $existe->execute([$usuarioId, $p['nombre']]);
        if ((int) $existe->fetchColumn() > 0) {
            continue;
        }
        paquete_crear($usuarioId, $p['nombre'], $p['audiencia'], sesion_normalizar_actividades($p['actividades']));
        $creados++;
    }

    return $creados
        ? ['ok' => true, 'mensaje' => 'Listo: ' . $creados . ($creados === 1 ? ' paquete de demostración creado' : ' paquetes de demostración creados') . ' en «Mis paquetes».']
        : ['ok' => true, 'mensaje' => 'Los paquetes de demostración ya estaban en «Mis paquetes».'];
}
