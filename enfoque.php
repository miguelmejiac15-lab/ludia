<?php
/**
 * Por qué Ludia está hecha así.
 *
 * No habla de personas ni de trayectorias: habla de DECISIONES DE PRODUCTO, y
 * cada una de ellas se puede comprobar usando la plataforma. Es la diferencia
 * entre demostrar criterio y presumir de él.
 *
 * Si alguna de estas decisiones se revierte en el código, este texto deja de
 * ser cierto y hay que cambiarlo. Todas están documentadas en docs/masterplan.md.
 */

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/Planes.php';

$page = [
    'title'       => 'Cómo está pensada Ludia — Enfoque',
    'description' => 'Por qué el participante no crea cuenta, por qué no hay ranking entre estudiantes y por qué cada informe señala la pregunta más difícil.',
    'canonica'    => '/enfoque.php',
];

$cuantasActividades = count(plan_formatos('pro'));

require __DIR__ . '/includes/partials/header.php';
?>

<main id="contenido" class="legal-doc">
  <div class="wrap wrap-estrecho">

    <p class="eyebrow">Enfoque</p>
    <h1>Cómo está pensada Ludia</h1>
    <p class="entradilla">
      Ludia no es un generador de trivias con colores. Cada decisión de esta lista
      se tomó pensando en lo que ocurre de verdad en un salón, y todas se pueden
      comprobar usando la plataforma.
    </p>

    <h2>Se evalúa para enseñar, no para calificar</h2>
    <p>
      Al terminar una actividad, el informe no se queda en la nota. Muestra
      <strong>qué pregunta falló más gente</strong> y en qué porcentaje, que es la
      información que de verdad cambia la clase siguiente: ahí es donde hay que
      volver a explicar. Es evaluación formativa: sirve mientras se aprende, no
      después.
    </p>
    <p>
      Por eso también el informe <strong>esconde la tarjeta de «la más difícil»
      cuando mentiría</strong>: si solo hubo una pregunta calificable, o si todo el
      mundo acertó todo, señalar una «más difícil» sería inventar un problema.
    </p>

    <h2>El participante no crea cuenta</h2>
    <p>
      Entra con un código de seis dígitos, escribe su nombre y juega. Sin correo,
      sin contraseña, sin esperar un enlace de confirmación. Esto resuelve dos cosas
      a la vez: <strong>los primeros diez minutos de clase no se van en registrar
      gente</strong>, y no se acumulan datos personales de menores que después haya
      que custodiar.
    </p>

    <h2>No hay ranking entre estudiantes</h2>
    <p>
      En una partida en vivo sí hay marcador y podio: ahí la competencia es el juego,
      dura veinte minutos y todo el mundo sabe que es un juego. Pero en el seguimiento
      del curso, <strong>los números de cada estudiante son suyos y solo se comparan
      consigo mismo</strong>. No hay tabla de mejores ni de peores.
    </p>
    <p>
      Es una decisión, no un descuido: una lista ordenada de estudiantes por
      rendimiento deja siempre a alguien al final, y ese alguien suele ser el que más
      necesita que le acompañen. La herramienta está para acompañar, no para vigilar.
    </p>

    <h2>«No entró» y «lo hizo mal» no se ven igual</h2>
    <p>
      Cuando un estudiante no ha participado, su casilla aparece <strong>vacía, no en
      cero</strong>. Un cero diría «lo hizo todo mal», que es una cosa muy distinta de
      «no entró» y lleva a la conversación equivocada con esa familia.
    </p>

    <h2>Nunca un color sin su número</h2>
    <p>
      Todas las barras y semáforos de los informes llevan la cifra escrita al lado
      —«50 % · 1 de 2»—, porque un color solo no se lee impreso en blanco y negro ni
      lo distingue quien tiene daltonismo. Y un profesor acaba imprimiendo el informe:
      las hojas de estilo incluyen una versión pensada para eso.
    </p>

    <h2>Lo que se aporta no se califica</h2>
    <p>
      Un mural colaborativo, una encuesta o una pregunta abierta <strong>no entran en
      el porcentaje de aciertos</strong>: se listan aparte, con su autor. No tienen
      respuesta correcta, y mezclarlos falsearía el dato de toda la clase.
    </p>

    <h2>El contenido es tuyo y se puede editar siempre</h2>
    <p>
      Cada vez que juegas o envías un paquete, esa partida <strong>guarda su propia
      copia</strong> del contenido. Por eso puedes corregir una pregunta mal escrita
      cuando quieras sin estropear ni un informe ya emitido: el histórico no se entera
      del cambio.
    </p>

    <h2><?= (int) $cuantasActividades ?> formatos, porque no todo se evalúa igual</h2>
    <p>
      Un quiz sirve para comprobar datos; una sopa de letras, para fijar vocabulario;
      una línea de tiempo, para ordenar procesos; un mural, para recoger lo que piensa
      el grupo antes de empezar. Tener <?= (int) $cuantasActividades ?> formatos no es
      una cifra de catálogo: es poder elegir la forma que corresponde a lo que se está
      enseñando.
    </p>

    <h2>Pensada para Latinoamérica</h2>
    <p>
      Los precios están en pesos colombianos y se fijaron pensando en lo que gana un
      docente de la región, no convertidos desde dólares. Todo funciona en un
      navegador corriente y en el celular que ya tiene cada estudiante, sin instalar
      nada.
    </p>

    <p class="legal-volver"><a href="<?= e(base_url('/')) ?>">← Volver al inicio</a></p>
  </div>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>
