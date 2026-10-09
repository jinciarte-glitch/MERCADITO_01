<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) {
   header('Location: /api/login.php');
   exit;
}
$navActive = 'feed';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<title>Mercadito — Tu red social</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="feed.css">
</head>
<body class="feed-body app-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="app-layout">

  <!-- IZQUIERDA: menú -->
  <?php require __DIR__ . '/includes/sidebar-left.php'; ?>

  <!-- CENTRO: filtro zona + compositor + publicaciones -->
  <main class="app-center feed-main">
    <div class="card zone-card" id="zone-card">
      <div class="zone-row">
        <div class="zone-info">
          <span class="zone-pin" aria-hidden="true">📍</span>
          <div class="zone-text">
            <strong>Cerca tuyo</strong>
            <span class="zone-status" id="zone-status">Activá tu ubicación para ver lo de tu zona.</span>
          </div>
        </div>
        <div class="zone-controls">
          <label class="zone-radius-label" for="zone-radius">Radio
            <select id="zone-radius">
              <option value="2">2 km</option>
              <option value="5">5 km</option>
              <option value="10">10 km</option>
              <option value="25" selected>25 km</option>
              <option value="50">50 km</option>
              <option value="100">100 km</option>
            </select>
          </label>
          <button type="button" class="btn-primary zone-btn" id="zone-btn">Usar mi ubicación</button>
          <button type="button" class="btn-secondary zone-btn" id="zone-toggle" hidden>Ver todo</button>
        </div>
      </div>
      <label class="zone-check">
        <input type="checkbox" id="zone-include-without" checked>
        Incluir publicaciones sin ubicación (anteriores)
      </label>
      <p class="zone-hint">Al publicar se guarda tu zona automáticamente para que tus vecinos te encuentren. <span id="composer-loc"></span></p>
    </div>

    <div class="card composer-card">
      <form id="composer-form">
        <div class="composer-top">
          <img class="avatar avatar-md" id="composer-avatar" src="" alt="Tu foto de perfil">
          <div class="field" style="margin:0; flex:1;">
            <textarea id="composer-text" placeholder="¿Qué querés compartir con tu barrio?" required maxlength="500" rows="2"></textarea>
          </div>
        </div>
        <div class="composer-bottom">
          <label for="composer-image" class="icon-upload-btn" title="Agregar imagen" aria-label="Agregar imagen">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <path d="M21 15l-5-5L5 21"/>
            </svg>
            <span>Imagen</span>
          </label>
          <input type="file" id="composer-image" name="imagen" accept="image/*" class="file-hidden">
          <span class="file-chosen" id="composer-file-name"></span>
          <button type="submit" class="btn-primary">Publicar</button>
        </div>
      </form>
    </div>

    <div class="posts-list" id="posts-list"><!-- Renderizado por feed.js --></div>
  </main>

  <!-- DERECHA: buscador + a quién seguir -->
  <?php require __DIR__ . '/includes/sidebar-right.php'; ?>

</div>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/geo.js"></script>
<script src="javascript/sidebar.js"></script>
<script src="javascript/feed.js"></script>
</body>
</html>
