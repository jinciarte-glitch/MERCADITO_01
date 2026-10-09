<?php
// includes/sidebar-left.php — menú lateral (columna izquierda).
// El navbar de arriba sigue teniendo el logo, cambiar tema y cerrar sesión;
// acá van sólo los accesos de navegación. Requiere $pdo y session.php.
$navActive     = $navActive ?? '';
$esEmprendedor = is_emprendedor($pdo);
$esAdmin       = is_administrador($pdo);
$miId          = current_user_id();
$puedeReclamar  = in_array(current_profile_type($pdo), ['cliente', 'emprendedor'], true);
?>
<aside class="side-nav">
  <nav class="side-links">
    <a href="feed.php" class="side-link<?= $navActive === 'feed' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"/></svg>
      <span>Inicio</span>
    </a>
    <a href="emprendimientos.php" class="side-link<?= $navActive === 'emprendimientos' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18l-1.5 11.5a1 1 0 0 1-1 .5H5.5a1 1 0 0 1-1-.5zM8 9V6a4 4 0 0 1 8 0v3"/></svg>
      <span>Emprendimientos</span>
    </a>
    <a href="mensajeria.php" class="side-link<?= $navActive === 'mensajeria' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      <span>Mensajes</span>
    </a>
    <?php if ($esEmprendedor && !$esAdmin): ?>
    <a href="mi-espacio.php" class="side-link<?= $navActive === 'mi-espacio' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V8l9-5 9 5v13h-6v-6H9v6z"/></svg>
      <span>Mi espacio</span>
    </a>
    <?php endif; ?>
    <a href="usuario.php?id=<?= (int)$miId ?>" class="side-link<?= $navActive === 'perfil' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
      <span>Perfil</span>
    </a>
    <?php if ($puedeReclamar): ?>
    <a href="reclamo-general.php" class="side-link<?= $navActive === 'reclamo-general' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22V4"/><path d="M4 4h13l-2 4 2 4H4"/></svg>
      <span>Hacer un reclamo</span>
    </a>
    <a href="mis-denuncias.php" class="side-link<?= $navActive === 'mis-denuncias' ? ' active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Mis denuncias</span>
    </a>
    <?php endif; ?>
  </nav>
</aside>