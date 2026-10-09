<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
$navActive = 'mensajeria';

// Cache-busting: agrega ?v=<fecha de modificación> a css/js propios para que
// el navegador siempre traiga la versión nueva apenas se sube un cambio,
// sin depender de que el usuario limpie el caché a mano.
function asset_v($path) {
    $full = __DIR__ . '/' . $path;
    return $path . '?v=' . (file_exists($full) ? filemtime($full) : time());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
        <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<title>Mensajes — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_v('base.css') ?>">
<link rel="stylesheet" href="<?= asset_v('mensajeria.css') ?>">
</head>
<body class="msj-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="msj-main">
  <div class="msj-layout" id="msj-layout">

    <aside class="msj-sidebar" id="msj-sidebar">
      <h2 class="brand-font msj-title">Mensajes</h2>
      <div class="conv-search-wrap">
        <svg class="conv-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/>
          <path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" id="conv-search" class="conv-search-input" placeholder="Buscar conversación…" autocomplete="off">
      </div>
      <div id="conv-list" class="conv-list"><!-- conversaciones inyectadas por mensajeria-inbox.js --></div>
      <div class="empty-state" id="conv-empty" style="display:none">Todavía no tenés conversaciones.</div>
      <div class="empty-state" id="conv-search-empty" style="display:none">No encontramos conversaciones con ese nombre.</div>
    </aside>

    <section class="msj-thread" id="msj-thread">
      <div class="msj-thread-empty" id="thread-empty">
        Elegí una conversación de la izquierda para empezar a escribir.
      </div>

      <div class="msj-thread-inner" id="thread-inner" style="display:none">
        <header class="msj-thread-head">
          <button type="button" class="msj-back-btn" id="msj-back" aria-label="Volver a la lista">←</button>
          <img id="thread-avatar" class="avatar" src="" alt="">
          <span id="thread-name" class="thread-name">…</span>
          <button type="button" class="btn-icon msj-block-btn" id="msj-block-btn" title="Bloquear usuario">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/>
              <path d="m5.5 5.5 13 13"/>
            </svg>
            <span id="msj-block-label">Bloquear</span>
          </button>
        </header>

        <div class="msj-block-banner" id="msj-block-banner" style="display:none"></div>

        <div class="msj-messages" id="msj-messages"></div>

        <form class="msj-composer" id="msj-composer">
          <input type="text" id="msj-input" placeholder="Escribí un mensaje…" autocomplete="off" maxlength="1000">
          <button type="submit" class="btn-primary" id="msj-send">Enviar</button>
        </form>
      </div>
    </section>

  </div>
</main>

<div class="toast" id="toast"></div>

<script src="<?= asset_v('javascript/ui.js') ?>"></script>
<script src="<?= asset_v('javascript/theme.js') ?>"></script>
<script src="<?= asset_v('javascript/api.js') ?>"></script>
<script src="<?= asset_v('javascript/nav.js') ?>"></script>
<script src="<?= asset_v('javascript/mensajeria-inbox.js') ?>"></script>
</body>
</html>
