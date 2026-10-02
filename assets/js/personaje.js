/* =====================================================================
   Ludia — catálogo de personajes y dibujo del avatar.
   Lo comparten la pantalla de entrada (unirse.php) y la sala (sesion.php).
   ===================================================================== */
window.LudiaPersonaje = (function () {
  'use strict';

  var criaturas = [
    { clave: 'zorro',     emoji: '🦊', nombre: 'Zorro' },
    { clave: 'panda',     emoji: '🐼', nombre: 'Panda' },
    { clave: 'buho',      emoji: '🦉', nombre: 'Búho' },
    { clave: 'rana',      emoji: '🐸', nombre: 'Rana' },
    { clave: 'pulpo',     emoji: '🐙', nombre: 'Pulpo' },
    { clave: 'gato',      emoji: '🐱', nombre: 'Gato' },
    { clave: 'dragon',    emoji: '🐲', nombre: 'Dragón' },
    { clave: 'abeja',     emoji: '🐝', nombre: 'Abeja' },
    { clave: 'koala',     emoji: '🐨', nombre: 'Koala' },
    { clave: 'tigre',     emoji: '🐯', nombre: 'Tigre' },
    { clave: 'pinguino',  emoji: '🐧', nombre: 'Pingüino' },
    { clave: 'unicornio', emoji: '🦄', nombre: 'Unicornio' }
  ];

  var colores = [
    { clave: 'coral',   nombre: 'Coral',   valor: '#FF6A4D' },
    { clave: 'violeta', nombre: 'Violeta', valor: '#5B3DF5' },
    { clave: 'menta',   nombre: 'Menta',   valor: '#1FA98A' },
    { clave: 'sol',     nombre: 'Sol',     valor: '#FFB020' },
    { clave: 'cielo',   nombre: 'Cielo',   valor: '#3A87C9' },
    { clave: 'uva',     nombre: 'Uva',     valor: '#B04F9B' }
  ];

  var accesorios = [
    { clave: 'ninguno',   nombre: 'Sin accesorio', emoji: '' },
    { clave: 'gafas',     nombre: 'Gafas',         emoji: '🕶️' },
    { clave: 'sombrero',  nombre: 'Sombrero',      emoji: '🎩' },
    { clave: 'corona',    nombre: 'Corona',        emoji: '👑' },
    { clave: 'audifonos', nombre: 'Audífonos',     emoji: '🎧' },
    { clave: 'bufanda',   nombre: 'Bufanda',       emoji: '🧣' }
  ];

  var adjetivos = ['veloz', 'curioso', 'valiente', 'tranquilo', 'brillante', 'audaz', 'risueño', 'astuto'];

  function alAzar(lista) { return lista[Math.floor(Math.random() * lista.length)]; }

  function buscar(lista, clave) {
    for (var i = 0; i < lista.length; i++) if (lista[i].clave === clave) return lista[i];
    return lista[0];
  }

  function nuevo() {
    return {
      criatura: alAzar(criaturas).clave,
      color: alAzar(colores).clave,
      accesorio: 'ninguno'
    };
  }

  function nombreSugerido(personaje) {
    return buscar(criaturas, personaje.criatura).nombre + ' ' + alAzar(adjetivos);
  }

  /** Completa el personaje con su emoji y color reales (lo que se guarda en la BD). */
  function completo(personaje) {
    var criatura = buscar(criaturas, personaje.criatura);
    return {
      criatura: criatura.clave,
      emoji: criatura.emoji,
      color: buscar(colores, personaje.color).clave,
      accesorio: buscar(accesorios, personaje.accesorio).clave
    };
  }

  /** HTML del avatar. tamaño: 'sm' | 'md' | 'xl' */
  function avatar(personaje, tamano) {
    personaje = personaje || {};
    var criatura = buscar(criaturas, personaje.criatura);
    var color = buscar(colores, personaje.color);
    var accesorio = buscar(accesorios, personaje.accesorio);
    return '<span class="avatar avatar-' + (tamano || 'md') + '" style="--avatar-color:' + color.valor + '" aria-hidden="true">' +
      '<span class="avatar-cara">' + (personaje.emoji || criatura.emoji) + '</span>' +
      (accesorio.emoji ? '<span class="avatar-extra">' + accesorio.emoji + '</span>' : '') + '</span>';
  }

  return {
    criaturas: criaturas,
    colores: colores,
    accesorios: accesorios,
    nuevo: nuevo,
    completo: completo,
    nombreSugerido: nombreSugerido,
    avatar: avatar,
    buscarColor: function (clave) { return buscar(colores, clave); }
  };
})();
