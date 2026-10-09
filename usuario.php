<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
$profileId = $_GET['id'] ?? null;
$navActive = 'perfil';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<title>Perfil — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="feed.css">
<link rel="stylesheet" href="usuario.css">
</head>
<body class="usuario-body app-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="app-layout">

  <?php require __DIR__ . '/includes/sidebar-left.php'; ?>

  <main class="app-center usuario-main">
    <div id="usuario-loading" class="usuario-loading">Cargando perfil…</div>

    <div id="usuario-content" style="display:none; ">
      <div class="tw-head">
        <div class="tw-head-top">
          <img id="tw-avatar" class="tw-avatar" src="" alt=""> <!-- pfp -->
          <div class="tw-actions" id="tw-actions"><!-- seguir / editar perfil --></div>
        </div>

        <h1 id="tw-name" class="tw-name"></h1>
        <p id="tw-username" class="tw-username">@usuario</p>
        <p id="tw-bio" class="tw-bio"></p>
        <p id="tw-joined" class="tw-joined"></p>

        <div class="tw-stats">
          <button type="button" class="tw-stat-btn" id="tw-following-btn"><strong id="tw-following">0</strong> Siguiendo</button>
          <button type="button" class="tw-stat-btn" id="tw-followers-btn"><strong id="tw-followers">0</strong> Seguidores</button>
          <span><strong id="tw-likes">0</strong> Me gusta</span>
        </div>
      </div>

      <nav class="tw-tabs">
        <button type="button" class="tw-tab active" data-tab="posts">Publicaciones</button>
        <button type="button" class="tw-tab" data-tab="shared">Reposts</button>
      </nav>

      <div id="tw-list" class="tw-list"></div>
      <div id="tw-empty" class="empty-state" style="display:none; min-height: 60vh;"></div>
    </div>
  </main>

  <?php require __DIR__ . '/includes/sidebar-right.php'; ?>

</div>

<div class="toast" id="toast"></div>

<!-- Modal de seguidores / seguidos -->
<div class="fl-modal" id="fl-modal" hidden>
  <div class="fl-backdrop" id="fl-backdrop"></div>
  <div class="fl-panel">
    <header class="fl-head">
      <span id="fl-title">Seguidores</span>
      <button type="button" id="fl-close" aria-label="Cerrar">✕</button>
    </header>
    <div class="fl-list" id="fl-list"></div>
    <div class="fl-empty" id="fl-empty" hidden></div>
  </div>
</div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/sidebar.js"></script>
<script src="javascript/usuario.js" data-profile-id="<?= htmlspecialchars($profileId ?? '') ?>"></script>
</body>
</html>
