<?php
/**
 * Mini tutoriales animados: cómo se crea un paquete y cómo se comparte.
 *
 * Son animaciones hechas con CSS, no vídeos: pesan unos pocos KB, se ven igual
 * en el celular y no dependen de ningún reproductor externo. Se muestran solos
 * la primera vez que alguien entra a su cuenta y se pueden volver a abrir desde
 * el menú.
 *
 * Respetan `prefers-reduced-motion`: a quien pide menos movimiento se le
 * muestran los pasos quietos, sin animación.
 */
?>
<div class="tuto" id="tuto" data-tutoriales hidden>
  <div class="tuto-caja" role="dialog" aria-modal="true" aria-labelledby="tuto-titulo">

    <button class="tuto-cerrar" type="button" data-tuto-cerrar aria-label="Cerrar los tutoriales">✕</button>

    <div class="tuto-cabecera">
      <span class="eyebrow">En un minuto</span>
      <h2 id="tuto-titulo">Así funciona Ludia</h2>
      <p id="tuto-sub">Dos pasos: armas el paquete y lo compartes con tu grupo.</p>
    </div>

    <!-- Pantalla de la animación -->
    <div class="tuto-pantalla">

      <!-- 1. Crear el paquete -->
      <div class="tuto-video" data-tuto-video="crear">
        <div class="tuto-escena esc-1">
          <span class="tuto-paso">1 · Ponle tema</span>
          <div class="tuto-campo"><span class="tuto-escribe">Las sumas</span><i class="tuto-cursor"></i></div>
        </div>

        <div class="tuto-escena esc-2">
          <span class="tuto-paso">2 · Agrega actividades</span>
          <div class="tuto-lista">
            <span class="tuto-item i1">🎯 Quiz</span>
            <span class="tuto-item i2">⚖️ Verdadero o falso</span>
            <span class="tuto-item i3">🔤 Sopa de letras</span>
          </div>
        </div>

        <div class="tuto-escena esc-3">
          <span class="tuto-paso">3 · Revisa y crea</span>
          <div class="tuto-ok">✓</div>
          <span class="tuto-pie">Tu paquete está creado</span>
        </div>

        <div class="tuto-escena esc-4">
          <span class="tuto-paso">4 · Ahora eliges cómo usarlo</span>
          <div class="tuto-modos">
            <span class="tuto-modo elegido">📽️ Jugar en vivo</span>
            <span class="tuto-modo">📨 Enviar</span>
          </div>
          <span class="tuto-pie">Desde «Mis paquetes», cuando quieras</span>
        </div>
      </div>

      <!-- 2. Compartirlo -->
      <div class="tuto-video" data-tuto-video="compartir" hidden>
        <div class="tuto-escena esc-1">
          <span class="tuto-paso">1 · Comparte el acceso</span>
          <div class="tuto-codigo">
            <span class="tuto-codigo-label">Código</span>
            <b>482 913</b>
            <span class="tuto-qr" aria-hidden="true"></span>
          </div>
        </div>

        <div class="tuto-escena esc-2">
          <span class="tuto-paso">2 · Entra tu grupo</span>
          <div class="tuto-gente">
            <span class="tuto-persona p1">🦊</span>
            <span class="tuto-persona p2">🐼</span>
            <span class="tuto-persona p3">🦉</span>
            <span class="tuto-persona p4">🐢</span>
          </div>
          <span class="tuto-pie">Sin crear cuenta</span>
        </div>

        <div class="tuto-escena esc-3">
          <span class="tuto-paso">3 · Juegan a la vez</span>
          <div class="tuto-pregunta">¿Cuánto es 24 + 18?</div>
          <div class="tuto-opciones">
            <span class="tuto-opcion">32</span>
            <span class="tuto-opcion buena">42</span>
            <span class="tuto-opcion">46</span>
            <span class="tuto-opcion">38</span>
          </div>
        </div>

        <div class="tuto-escena esc-4">
          <span class="tuto-paso">4 · Resultados</span>
          <div class="tuto-podio">
            <span class="podio-barra b2">2</span>
            <span class="podio-barra b1">1</span>
            <span class="podio-barra b3">3</span>
          </div>
          <span class="tuto-pie">Posiciones e informe al terminar</span>
        </div>
      </div>
    </div>

    <!-- Selector de tutorial -->
    <div class="tuto-tabs" role="tablist">
      <button class="tuto-tab activo" type="button" role="tab" data-tuto-tab="crear" aria-selected="true">
        <b>🧩 Crear un paquete</b><span>Tipo, tema y actividades</span>
      </button>
      <button class="tuto-tab" type="button" role="tab" data-tuto-tab="compartir" aria-selected="false">
        <b>📣 Publicarlo</b><span>Código, QR y resultados</span>
      </button>
    </div>

    <div class="tuto-acciones">
      <label class="tuto-nover">
        <input type="checkbox" data-tuto-nover> No volver a mostrarlo
      </label>
      <a class="btn btn-primary" href="<?= e(base_url('app/crear.php')) ?>">Crear mi paquete</a>
    </div>
  </div>
</div>
