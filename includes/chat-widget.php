<?php
// includes/chat-widget.php — mensajería flotante (tipo Messenger).
// Se incluye en el navbar (para las páginas con barra superior) y en las
// páginas con layout de 3 columnas (feed, usuario). No se incluye en
// mensajeria.php (esa es la versión de pantalla completa).
?>
<div id="mc-dock" class="mc-dock">
  <!-- Ventana de mensaje abierta -->
  <div class="mc-window" id="mc-window" hidden>
    <header class="mc-window-head">
      <img class="mc-avatar" id="mc-window-avatar" src="" alt="">
      <span class="mc-window-name" id="mc-window-name">…</span>
      <button type="button" class="mc-icon-btn" id="mc-window-close" aria-label="Cerrar mensaje">✕</button>
    </header>
    <div class="mc-messages" id="mc-messages"></div>
    <form class="mc-composer" id="mc-composer">
      <input type="text" id="mc-input" placeholder="Escribí un mensaje…" autocomplete="off" maxlength="1000">
      <button type="submit" class="mc-send" aria-label="Enviar">➤</button>
    </form>
  </div>

  <!-- Panel con la lista de conversaciones -->
  <div class="mc-list-panel" id="mc-list-panel" hidden>
    <header class="mc-list-head">
      <span>Mensajes</span>
      <div class="mc-list-head-actions">
        <a href="mensajeria.php" class="mc-icon-btn" id="mc-list-expand" aria-label="Abrir mensajes en pantalla completa" title="Abrir en pantalla completa">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/>
          </svg>
        </a>
        <button type="button" class="mc-icon-btn" id="mc-list-close" aria-label="Cerrar">✕</button>
      </div>
    </header>
    <div class="mc-list" id="mc-list"></div>
    <div class="mc-empty" id="mc-list-empty" hidden>Todavía no tenés conversaciones.</div>
  </div>

  <!-- Botón flotante -->
  <button type="button" class="mc-launcher" id="mc-launcher" aria-label="Abrir mensajes">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
    </svg>
    <span class="mc-launcher-badge" id="mc-launcher-badge" hidden>0</span>
  </button>
</div>
<script src="javascript/mensajeria.js" defer></script>
