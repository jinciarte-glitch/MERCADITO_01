<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }

// Solo emprendedores acceden a "Mi espacio". El resto vuelve al feed.
if (!is_emprendedor($pdo)) { header('Location: /feed.php'); exit; }
$navActive = 'mi-espacio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
        <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi espacio — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
    <style>
  .tablon-grid-3{ display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; }
.tablon-grid-2{ display:grid; grid-template-columns:repeat(2, minmax(0, 280px)); gap:16px; justify-content:start; }
  .tablon-lista{ display:flex; flex-direction:column; gap:12px; }
  .card-estilo-moderno{ border-radius:16px; border:1px solid rgba(128,128,128,0.18); box-shadow:var(--shadow-sm); }
  .card-estilo-clasico{ border-radius:0; border:2px solid rgba(128,128,128,0.3); }
  .card-estilo-minimal{ border-radius:8px; border:none; }
</style>
</head>
<body class="shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="shop-main">
  <div class="espacio-head">
    <div>
      <h2 class="brand-font" style="color:var(--color-text-strong); margin:0">Mi espacio</h2>
      <p class="explore-sub" style="margin:4px 0 0">Configurá tu página de venta. Así la van a ver tus clientes.</p>
    </div>
    <a href="#" id="ver-tienda" class="btn-secondary" style="text-decoration:none; display:none">Ver mi tienda ↗</a>
  </div>

  <div class="editor-layout" style="margin-top:20px">

    <nav class="editor-nav" id="editor-nav">
      <button class="active" data-panel="marca">Info de la marca</button>
      <button data-panel="catalogo">Catálogo</button>
      <button data-panel="reclamos">Reclamos</button>
      <button data-panel="diseno">Diseño</button>
    </nav>

    <div class="editor-content">

      <!-- Panel: Info de la marca -->
      <div class="editor-panel" data-panel="marca">
        <h3>Información de la marca</h3>

        <div class="field-group">
            <label>Foto de perfil</label>
            <input type="file" id="input-avatar" accept="image/*">
          <div class="img-preview" id="preview-avatar"></div>
        </div>

        <div class="field-group">
          <label>Banner (URL de imagen)</label>
            <input type="file" id="input-banner" accept="image/*">
            <div class="img-preview img-preview-wide" id="preview-banner"></div>
        </div>

        <div class="field-group">
          <label>Nombre del emprendimiento</label>
          <input type="text" id="input-name" maxlength="120" placeholder="Ej: Café Aroma">
        </div>
        <div class="field-group">
          <label>Rubro / categoría</label>
          <select id="input-category">
            <option value="">Elegí un rubro…</option>
            <option value="comida">Comida</option>
            <option value="ropa">Ropa</option>
            <option value="arte">Arte</option>
            <option value="hogar">Hogar</option>
            <option value="tecnologia">Tecnología</option>
          </select>
        </div>
        <div class="field-group">
          <label>Descripción</label>
          <textarea id="input-desc" rows="3" maxlength="500" placeholder="Contale a tus clientes qué ofrecés…"></textarea>
        </div>
        <button class="btn-primary" id="save-marca">Guardar cambios</button>
      </div>

      <!-- Panel: Catálogo -->
      <div class="editor-panel" data-panel="catalogo" style="display:none">
        <h3>Catálogo de productos</h3>
        <div class="field-group">
          <label>Nombre del producto</label>
          <input type="text" id="prod-name" maxlength="160" placeholder="Ej: Taza artesanal">
        </div>
        <div class="field-group">
          <label>Precio</label>
          <input type="number" id="prod-price" min="0" step="0.01" placeholder="Ej: 4500">
        </div>
        <div class="field-group">
          <label>Imagen (URL)</label>
        <input type="file" id="prod-image" accept="image/*">
            <div class="img-preview" id="preview-product"></div>
        </div>
        <button class="btn-primary" id="add-product">Agregar producto</button>

        <div id="product-list" style="margin-top:20px"><!-- items inyectados por espacio.js --></div>
        <div class="empty-state" id="product-empty" style="display:none">Todavía no cargaste ningún producto.</div>
      </div>

      <!-- Panel: Ventas (fuera de MVP) -->
      <div class="editor-panel" data-panel="ventas" style="display:none">
        <h3>Ventas</h3>
        <p style="color:var(--color-text-faint); font-size:14px">Esta sección se habilitará en una próxima versión.</p>
      </div>

      <!-- Panel: Reclamos -->
      <div class="editor-panel" data-panel="reclamos" style="display:none">
        <h3>Reclamos</h3>
        <p style="color:var(--color-text-faint); font-size:14px; margin-top:-8px">Reclamos que te dejaron tus clientes.</p>
        <div id="reclamo-list" style="margin-top:16px"><!-- items inyectados por espacio.js --></div>
        <div class="empty-state" id="reclamo-empty" style="display:none">Todavía no tenés reclamos.</div>
      </div>

      <!-- Panel: Diseño -->
      <div class="editor-panel" data-panel="diseno" style="display:none">
        <h3>Diseño del espacio</h3>

        <div class="field-group">
          <label>Ícono / emoji del logo</label>
          <input type="text" id="design-logo-icon" maxlength="4" placeholder="🛍️">
        </div>

        <div class="field-group">
          <label>Fuente</label>
          <select id="design-font">
            <option value="'Work Sans', sans-serif">Work Sans (Mercadito)</option>
            <option value="'Segoe UI', sans-serif">Segoe UI (Moderna)</option>
            <option value="'Roboto', sans-serif">Roboto (Limpia)</option>
            <option value="'Montserrat', sans-serif">Montserrat (Geométrica)</option>
            <option value="'Georgia', serif">Georgia (Elegante)</option>
            <option value="'Courier New', monospace">Courier (Técnica / Retro)</option>
          </select>
        </div>

        <div class="field-group">
          <label>Color de fondo</label>
          <input type="color" id="design-color-fondo">
        </div>
        <div class="field-group">
          <label>Color de tarjetas</label>
          <input type="color" id="design-color-tarjeta">
        </div>
        <div class="field-group">
          <label>Color de acento / botones</label>
          <input type="color" id="design-color-acento">
        </div>
        <div class="field-group">
          <label>Color de texto</label>
          <input type="color" id="design-color-texto">
        </div>

        <div class="field-group">
          <label>Altura del banner (px)</label>
          <input type="number" id="design-banner-height" min="100" max="400">
        </div>

        <div class="field-group">
          <label>Estructura del tablón</label>
          <select id="design-tablon-estructura">
            <option value="tablon-grid-3">Grilla de 4 columnas</option>
            <option value="tablon-grid-2">Grilla de 2 columnas</option>
            <option value="tablon-lista">Lista vertical</option>
          </select>
        </div>

        <div class="field-group">
          <label>Estilo de tarjetas</label>
          <select id="design-estilo-tarjeta">
            <option value="card-estilo-moderno">Redondeadas con sombra</option>
            <option value="card-estilo-clasico">Clásica con borde recto</option>
            <option value="card-estilo-minimal">Minimalista sin borde</option>
          </select>
        </div>

        <div class="field-group">
          <label><input type="checkbox" id="design-mostrar-buscador" checked> Mostrar barra de búsqueda</label>
        </div>
        <div class="field-group">
          <label>Vista previa</label>
          <div id="design-preview" style="border:2px dashed var(--color-border-strong); border-radius:var(--radius-lg); padding:16px;">
            <div id="design-preview-banner" style="background-size:cover; background-position:center; border-radius:var(--radius-md); margin-bottom:12px; background-color:var(--color-bg-hover);"></div>
            <strong id="design-preview-name" class="brand-font" style="display:block; margin-bottom:12px;">Mi Tienda</strong>
            <div id="design-preview-tablon">
              <div class="design-pv-card" style="padding:12px;">
                <h4 style="margin:0 0 4px">Producto de ejemplo</h4>
                <div class="design-pv-precio" style="font-weight:bold;">$4.500</div>
              </div>
              <div class="design-pv-card" style="padding:12px;">
                <h4 style="margin:0 0 4px">Otro producto</h4>
                <div class="design-pv-precio" style="font-weight:bold;">$7.200</div>
              </div>
            </div>
          </div>
        </div>
        <button class="btn-primary" id="save-diseno">Guardar diseño</button>
      </div>

    </div>
  </div>
</main>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/espacio.js"></script>
</body>
</html>