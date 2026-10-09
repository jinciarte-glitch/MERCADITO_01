<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
$navActive = 'emprendimientos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<title>Emprendimientos — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
</head>
<body class="shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="shop-main">
  <div class="explore-intro">
    <h1 class="brand-font explore-title">Descubrí emprendimientos</h1>
    <p class="explore-sub">Explorá los negocios de tu comunidad. Buscá por nombre, producto o categoría.</p>
  </div>

  <div class="explore-toolbar">
    <input type="text" class="explore-search" id="search-input" placeholder="Buscar emprendimientos o productos…">
  </div>
  <div class="category-chips" id="category-chips">
    <button class="chip active" data-cat="todos">Todos</button>
    <button class="chip" data-cat="comida">Comida</button>
    <button class="chip" data-cat="ropa">Ropa</button>
    <button class="chip" data-cat="arte">Arte</button>
    <button class="chip" data-cat="hogar">Hogar</button>
    <button class="chip" data-cat="tecnologia">Tecnología</button>
  </div>

  <div class="shop-grid" id="shop-grid"><!-- tarjetas inyectadas por shops.js --></div>
  <div class="empty-state" id="empty-state" style="display:none">
    Todavía no hay emprendimientos que coincidan con tu búsqueda.
  </div>
  <div class="loader-sentinel" id="loader-sentinel">Cargando más emprendimientos…</div>
</main>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/shops.js"></script>
</body>
</html>
