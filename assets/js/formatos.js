/* =====================================================================
   Ludia — catálogo de formatos y reglas de uso.
   Solo datos y validación: nada de DOM aquí (eso vive en editor.js y crear.js).
   La forma de cada ítem es el contrato que luego se guardará en actividades.preguntas.

   Qué formatos puede usar cada plan NO se decide aquí: lo manda el servidor
   (includes/Planes.php) y llega al navegador en window.LudiaPlan, que crear.js
   usa para pintar los candados del catálogo.
   ===================================================================== */
window.LudiaFormatos = (function () {
  'use strict';

  // Topes técnicos, iguales para todos los planes. Los que dependen del plan
  // (paquetes, participantes, formatos) viajan en window.LudiaPlan.
  var limites = {
    actividadesPorSesion: 0, // 0 = sin tope
    itemsPorActividad: 30
  };

  var audiencias = [
    { clave: 'estudiantes',  nombre: 'Estudiantes' },
    { clave: 'auditorio',    nombre: 'Auditorio o evento' },
    { clave: 'capacitacion', nombre: 'Capacitación de empleados' }
  ];

  var tiempos = [10, 20, 30, 45, 60, 90, 120, 300];

  var grupos = [
    { clave: 'preguntas',   nombre: 'Preguntas y evaluación', descripcion: 'Lo clásico: puntúan por acierto y rapidez.' },
    { clave: 'texto',       nombre: 'Texto y lengua',         descripcion: 'Para trabajar palabras, frases y ortografía.' },
    { clave: 'relacionar',  nombre: 'Relacionar, clasificar y ordenar', descripcion: 'Unir, agrupar y poner en orden.' },
    { clave: 'aportes',     nombre: 'Aportes y participación', descripcion: 'Mural colaborativo, encuestas y opiniones. No puntúan: van al informe.' },
    { clave: 'apoyo',       nombre: 'Apoyo y repaso',          descripcion: 'Material para mostrar antes o después de jugar.' }
  ];

  function vacio(texto) { return !texto || !String(texto).trim(); }
  function llenas(lista) { return (lista || []).filter(function (t) { return !vacio(t); }); }
  function paresCompletos(lista, a, b) {
    return (lista || []).filter(function (p) { return !vacio(p[a]) && !vacio(p[b]); });
  }
  // Valida listas de parejas: mínimo N completas y ninguna a medio llenar.
  function validarPares(lista, a, b, min, etiqueta) {
    var completos = paresCompletos(lista, a, b);
    var e = [];
    if (completos.length < min) e.push('Completa al menos ' + min + ' ' + etiqueta);
    else if (completos.length !== lista.length) e.push('Hay ' + etiqueta + ' a medio llenar');
    return e;
  }

  var tipos = {
    /* ---------- Preguntas y evaluación ---------- */
    quiz: {
      nombre: 'Quiz', icono: '🎯', grupo: 'preguntas', puntua: true,
      descripcion: 'Una pregunta, varias opciones y una sola correcta.',
      nuevoItem: function () { return { enunciado: '', opciones: ['', '', '', ''], correcta: 0 }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.enunciado)) e.push('Escribe la pregunta');
        if (llenas(it.opciones).length < 2) e.push('Agrega al menos 2 opciones');
        if (vacio(it.opciones[it.correcta])) e.push('Marca una opción correcta que tenga texto');
        return e;
      }
    },

    video_preguntas: {
      nombre: 'Vídeo con preguntas', icono: '🎬', grupo: 'preguntas', puntua: true,
      soloEnviar: true,
      descripcion: 'Un vídeo de YouTube que se detiene en los minutos que marques para preguntar.',
      nuevoItem: function () { return { segundo: 30, enunciado: '', opciones: ['', '', '', ''], correcta: 0 }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.enunciado)) e.push('Escribe la pregunta');
        if (llenas(it.opciones).length < 2) e.push('Agrega al menos 2 opciones');
        if (vacio(it.opciones[it.correcta])) e.push('Marca una opción correcta que tenga texto');
        if (!(Number(it.segundo) > 0)) e.push('Di en qué segundo del vídeo aparece');
        return e;
      }
    },

    verdadero_falso: {
      nombre: 'Verdadero o falso', icono: '⚖️', grupo: 'preguntas', puntua: true,
      descripcion: 'Una afirmación que el público marca como cierta o falsa.',
      nuevoItem: function () { return { afirmacion: '', correcta: true }; },
      validar: function (it) { return vacio(it.afirmacion) ? ['Escribe la afirmación'] : []; }
    },

    respuesta_multiple: {
      nombre: 'Respuesta múltiple', icono: '☑️', grupo: 'preguntas', puntua: true,
      descripcion: 'Una pregunta con más de una respuesta correcta.',
      nuevoItem: function () { return { enunciado: '', opciones: ['', '', '', ''], correctas: [] }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.enunciado)) e.push('Escribe la pregunta');
        if (llenas(it.opciones).length < 3) e.push('Agrega al menos 3 opciones');
        var marcadas = (it.correctas || []).filter(function (i) { return !vacio(it.opciones[i]); });
        if (marcadas.length < 2) e.push('Marca al menos 2 opciones correctas');
        return e;
      }
    },

    palabra_secreta: {
      nombre: 'Palabra secreta', icono: '🔡', grupo: 'preguntas', puntua: true,
      descripcion: 'Adivinar la palabra letra por letra a partir de una pista.',
      nuevoItem: function () { return { palabra: '', pista: '' }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.palabra)) e.push('Escribe la palabra secreta');
        else if (it.palabra.trim().length < 3) e.push('La palabra debe tener al menos 3 letras');
        if (vacio(it.pista)) e.push('Escribe una pista');
        return e;
      }
    },

    /* ---------- Texto y lengua ---------- */
    completar: {
      nombre: 'Completar la frase', icono: '✏️', grupo: 'texto', puntua: true,
      descripcion: 'Escribe la frase y encierra entre [corchetes] lo que deben completar.',
      nuevoItem: function () { return { texto: '' }; },
      validar: function (it) {
        if (vacio(it.texto)) return ['Escribe la frase'];
        return /\[[^\]]+\]/.test(it.texto) ? [] : ['Marca al menos una palabra entre [corchetes]'];
      }
    },

    ordenar_palabras: {
      nombre: 'Ordenar palabras', icono: '🔀', grupo: 'texto', puntua: true,
      descripcion: 'Una frase que se desordena para que el público la reconstruya.',
      nuevoItem: function () { return { frase: '' }; },
      validar: function (it) {
        if (vacio(it.frase)) return ['Escribe la frase'];
        return it.frase.trim().split(/\s+/).length < 4 ? ['La frase debe tener al menos 4 palabras'] : [];
      }
    },

    sopa_letras: {
      nombre: 'Sopa de letras', icono: '🔤', grupo: 'texto', puntua: true,
      descripcion: 'Palabras clave escondidas en una cuadrícula.',
      nuevoItem: function () { return { instruccion: 'Encuentra las palabras', palabras: ['', '', ''] }; },
      validar: function (it) {
        var e = [];
        var palabras = llenas(it.palabras);
        if (palabras.length < 3) e.push('Agrega al menos 3 palabras');
        if (palabras.some(function (p) { return p.trim().length > 12; })) e.push('Usa palabras de máximo 12 letras');
        if (palabras.some(function (p) { return /\s/.test(p.trim()); })) e.push('Cada palabra debe ir sin espacios');
        return e;
      }
    },

    ortografia: {
      nombre: 'Ortografía', icono: '📝', grupo: 'texto', puntua: true,
      descripcion: 'Elegir cómo se escribe bien entre dos formas parecidas.',
      nuevoItem: function () {
        return {
          instruccion: 'Elige la forma correcta',
          pares: [{ correcta: '', incorrecta: '' }, { correcta: '', incorrecta: '' }, { correcta: '', incorrecta: '' }]
        };
      },
      validar: function (it) {
        var e = validarPares(it.pares, 'correcta', 'incorrecta', 3, 'parejas de palabras');
        it.pares.forEach(function (p, i) {
          if (!vacio(p.correcta) && !vacio(p.incorrecta) &&
              p.correcta.trim().toLowerCase() === p.incorrecta.trim().toLowerCase()) {
            e.push('La pareja ' + (i + 1) + ' tiene la misma palabra dos veces');
          }
        });
        return e;
      }
    },

    anagrama: {
      nombre: 'Anagrama', icono: '🔠', grupo: 'texto', puntua: true,
      descripcion: 'Las letras salen revueltas y hay que armar la palabra.',
      nuevoItem: function () { return { palabra: '', pista: '' }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.palabra)) e.push('Escribe la palabra');
        else if (it.palabra.trim().replace(/\s+/g, '').length < 4) e.push('La palabra necesita al menos 4 letras');
        else if (/\d/.test(it.palabra)) e.push('Usa solo letras, sin números');
        return e;
      }
    },

    numerica: {
      nombre: 'Respuesta numérica', icono: '🔢', grupo: 'preguntas', puntua: true,
      descripcion: 'La respuesta es un número, con margen de error si lo necesitas.',
      nuevoItem: function () { return { enunciado: '', respuesta: '', tolerancia: 0, unidad: '' }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.enunciado)) e.push('Escribe la pregunta');
        if (vacio(String(it.respuesta))) e.push('Escribe la respuesta correcta');
        else if (isNaN(Number(String(it.respuesta).replace(',', '.')))) e.push('La respuesta debe ser un número');
        return e;
      }
    },

    linea_tiempo: {
      nombre: 'Línea de tiempo', icono: '📅', grupo: 'relacionar', puntua: true,
      descripcion: 'Ordenar hechos por fecha: primero el más antiguo.',
      nuevoItem: function () {
        return {
          instruccion: 'Ordena del más antiguo al más reciente',
          hechos: [{ fecha: '', texto: '' }, { fecha: '', texto: '' }, { fecha: '', texto: '' }]
        };
      },
      validar: function (it) {
        return validarPares(it.hechos, 'fecha', 'texto', 3, 'hechos con su fecha');
      }
    },

    crucigrama: {
      nombre: 'Crucigrama', icono: '🧩', grupo: 'texto', puntua: true,
      descripcion: 'Definiciones que se resuelven en una cuadrícula cruzada.',
      nuevoItem: function () {
        return { instruccion: 'Resuelve el crucigrama', entradas: [{ palabra: '', pista: '' }, { palabra: '', pista: '' }, { palabra: '', pista: '' }] };
      },
      validar: function (it) {
        var e = validarPares(it.entradas, 'palabra', 'pista', 3, 'definiciones');
        if (it.entradas.some(function (x) { return !vacio(x.palabra) && /\s/.test(x.palabra.trim()); })) e.push('Cada palabra debe ir sin espacios');
        return e;
      }
    },

    /* ---------- Relacionar, clasificar y ordenar ---------- */
    relacionar: {
      nombre: 'Relacionar', icono: '🔗', grupo: 'relacionar', puntua: true,
      descripcion: 'Parejas que el público une: concepto con definición, fecha con evento…',
      nuevoItem: function () {
        return { instruccion: 'Relaciona cada elemento con su pareja', pares: [{ izquierda: '', derecha: '' }, { izquierda: '', derecha: '' }, { izquierda: '', derecha: '' }] };
      },
      validar: function (it) { return validarPares(it.pares, 'izquierda', 'derecha', 3, 'parejas'); }
    },

    memoria: {
      nombre: 'Memoria', icono: '🃏', grupo: 'relacionar', puntua: true,
      descripcion: 'Voltear tarjetas para encontrar las parejas.',
      nuevoItem: function () {
        return { instruccion: 'Encuentra las parejas', pares: [{ izquierda: '', derecha: '' }, { izquierda: '', derecha: '' }, { izquierda: '', derecha: '' }] };
      },
      validar: function (it) { return validarPares(it.pares, 'izquierda', 'derecha', 3, 'parejas'); }
    },

    clasificar: {
      nombre: 'Clasificar', icono: '🗂️', grupo: 'relacionar', puntua: true,
      descripcion: 'Llevar cada elemento a la categoría que le corresponde.',
      nuevoItem: function () {
        return {
          instruccion: 'Clasifica cada elemento',
          categorias: [{ nombre: '', elementos: ['', ''] }, { nombre: '', elementos: ['', ''] }]
        };
      },
      validar: function (it) {
        var e = [];
        var conNombre = it.categorias.filter(function (c) { return !vacio(c.nombre); });
        if (conNombre.length < 2) e.push('Ponle nombre a al menos 2 categorías');
        it.categorias.forEach(function (c, i) {
          if (!vacio(c.nombre) && llenas(c.elementos).length < 2) e.push('La categoría ' + (i + 1) + ' necesita 2 elementos');
        });
        return e;
      }
    },

    ordenar: {
      nombre: 'Ordenar secuencia', icono: '🔢', grupo: 'relacionar', puntua: true,
      descripcion: 'Pasos, eventos o elementos que deben quedar en el orden correcto.',
      nuevoItem: function () { return { instruccion: 'Ordena de primero a último', elementos: ['', '', ''] }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.instruccion)) e.push('Escribe la instrucción');
        if (llenas(it.elementos).length < 3) e.push('Agrega al menos 3 elementos');
        return e;
      }
    },

    /* ---------- Aportes y participación ---------- */
    mural: {
      nombre: 'Mural colaborativo', icono: '🧷', grupo: 'aportes', puntua: false,
      descripcion: 'Un tablero donde el público pega notas con sus aportes.',
      modos: [
        { clave: 'postit',   nombre: 'Notas adhesivas (post-it)', ayuda: 'Cada aporte es una nota de color en un tablero.' },
        { clave: 'fijo',     nombre: 'Mensajes fijos (lista)',    ayuda: 'Los aportes se acomodan en una lista ordenada.' },
        { clave: 'flotante', nombre: 'Mensajes flotantes',        ayuda: 'Los aportes cruzan la pantalla al proyectarse.' }
      ],
      nuevoItem: function () {
        return { consigna: '', modo: 'postit', anonimo: true, moderar: false, maxCaracteres: 120, ejemplos: [''] };
      },
      validar: function (it) { return vacio(it.consigna) ? ['Escribe la consigna del mural'] : []; }
    },

    encuesta: {
      nombre: 'Encuesta', icono: '📊', grupo: 'aportes', puntua: false,
      descripcion: 'Una pregunta de opinión con resultados en vivo.',
      nuevoItem: function () { return { pregunta: '', opciones: ['', ''] }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.pregunta)) e.push('Escribe la pregunta');
        if (llenas(it.opciones).length < 2) e.push('Agrega al menos 2 opciones');
        return e;
      }
    },

    pregunta_abierta: {
      nombre: 'Pregunta abierta', icono: '💬', grupo: 'aportes', puntua: false,
      descripcion: 'Respuestas cortas que se agrupan en una nube de palabras.',
      nuevoItem: function () { return { pregunta: '', maxCaracteres: 60 }; },
      validar: function (it) { return vacio(it.pregunta) ? ['Escribe la pregunta'] : []; }
    },

    escala: {
      nombre: 'Escala de opinión', icono: '🌡️', grupo: 'aportes', puntua: false,
      descripcion: 'Del 1 al 5: satisfacción, acuerdo o nivel de confianza.',
      nuevoItem: function () { return { pregunta: '', etiquetaMin: 'Nada de acuerdo', etiquetaMax: 'Totalmente de acuerdo' }; },
      validar: function (it) { return vacio(it.pregunta) ? ['Escribe la pregunta'] : []; }
    },

    /* ---------- Apoyo y repaso ---------- */
    panel: {
      nombre: 'Panel de repaso', icono: '📋', grupo: 'apoyo', puntua: false,
      descripcion: 'Tarjetas con los conceptos clave para mostrar antes de jugar.',
      nuevoItem: function () {
        return { instruccion: 'Repasa antes de jugar', tarjetas: [{ titulo: '', texto: '' }, { titulo: '', texto: '' }] };
      },
      validar: function (it) { return validarPares(it.tarjetas, 'titulo', 'texto', 2, 'tarjetas'); }
    },

    pagina: {
      nombre: 'Página informativa', icono: '📄', grupo: 'apoyo', puntua: false,
      descripcion: 'Portada o diapositiva con título, imagen y texto para explicar el tema.',
      nuevoItem: function () { return { titulo: '', parrafos: [''] }; },
      validar: function (it) {
        var e = [];
        if (vacio(it.titulo)) e.push('Escribe el título de la página');
        if (llenas(it.parrafos).length < 1) e.push('Escribe al menos un párrafo');
        return e;
      }
    }
  };

  return {
    limites: limites,
    audiencias: audiencias,
    tiempos: tiempos,
    grupos: grupos,
    /**
     * Saca el identificador de un vídeo de YouTube de casi cualquier forma de
     * pegarlo: la URL larga, la corta, la de incrustar, o el id a secas.
     *
     * Se acepta pegar el <iframe> entero porque es lo que ofrece YouTube al
     * pulsar «Compartir → Insertar», y obligar a limpiarlo a mano sería pedirle
     * al profesor que haga de programador.
     */
    idYouTube: function (texto) {
      var t = String(texto || '').trim();
      if (!t) return '';

      // Del <iframe …src="…"> se queda con la dirección.
      var src = t.match(/src\s*=\s*["']([^"']+)["']/i);
      if (src) t = src[1];

      var patrones = [
        /[?&]v=([A-Za-z0-9_-]{11})/,        // watch?v=ID
        /youtu\.be\/([A-Za-z0-9_-]{11})/,   // youtu.be/ID
        /\/embed\/([A-Za-z0-9_-]{11})/,     // /embed/ID
        /\/shorts\/([A-Za-z0-9_-]{11})/     // /shorts/ID
      ];
      for (var i = 0; i < patrones.length; i++) {
        var m = t.match(patrones[i]);
        if (m) return m[1];
      }

      return /^[A-Za-z0-9_-]{11}$/.test(t) ? t : '';
    },

    /** Segundos a «m:ss», para que el profesor lea el minuto de un vistazo. */
    minuto: function (segundos) {
      var s = Math.max(0, Math.floor(Number(segundos) || 0));
      return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    },

    tipos: tipos,
    vacio: vacio
  };
})();
