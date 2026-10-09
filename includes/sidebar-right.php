<?php
// includes/sidebar-right.php — columna derecha estilo Twitter: buscador +
// "A quién seguir". El contenido dinámico lo llena javascript/sidebar.js.
?>
<aside class="side-right">
  <div class="side-search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input type="text" id="sidebar-search" placeholder="Buscar personas…" autocomplete="off">
    <div class="side-search-results" id="sidebar-search-results" hidden></div>
  </div>

  <div class="side-card">
    <h2 class="side-card-title">A quién seguir</h2>
    <div id="who-to-follow" class="wtf-list"></div>
  </div>
</aside>
