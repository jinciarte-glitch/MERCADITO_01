<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
$shopId = $_GET['id'] ?? null;
$navActive = 'emprendimientos';
// Solo los clientes inician conversaciones (emprendedor y administrador no ven el botón).
$esCliente = current_profile_type($pdo) === 'cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<title>Tienda — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
<?php if ($shopId): ?>
<link rel="preload" as="fetch" href="api/shops.php?id=<?= urlencode($shopId) ?>">
<?php endif; ?>
<style>
  /* Diseño personalizado por tienda: variables sobreescritas por shop-detail.js */
  .shop-main{
    --scolor-fondo: #fbf7f1;
    --scolor-tarjeta: #ffffff;
    --scolor-acento: #6b4226;
    --scolor-texto: #3b2418;
    background: var(--scolor-fondo);
    color: var(--scolor-texto);
    transition: background-color .2s ease, color .2s ease;
    /* Oculto hasta que shop-detail.js cargue los datos reales de la tienda;
       mientras tanto se muestra #shop-loading. */
    display: none;
  }
  .shop-main.is-ready{ display: block; }
  #shop-loading{
    text-align: center;
    padding: 60px 20px;
    opacity: .7;
  }
  .shop-main .product-card{ background: var(--scolor-tarjeta); color: var(--scolor-texto); }
  .shop-main .product-price{ color: var(--scolor-acento); }
  .shop-main .shop-tab.active{ background: var(--scolor-acento); }
    .shop-main .shop-title{ color: var(--scolor-texto); }
  .shop-main .shop-desc{ color: var(--scolor-texto); opacity: .75; }
  .shop-main .shop-info-row{ color: var(--scolor-texto); opacity: .75; }

  .shop-main .shop-tab{ color: var(--scolor-texto); opacity: .6; }
  .shop-main .shop-tab.active{ color: var(--scolor-fondo); opacity: 1; border-color: var(--scolor-acento); }

  .shop-main .shop-back{
    color: var(--scolor-acento);
    border-color: var(--scolor-acento);
  }
  .shop-main .shop-back:hover{
    background: var(--scolor-acento);
    color: var(--scolor-fondo);
  }

  .shop-main .product-name{ color: var(--scolor-texto); }

  /* Botones de acción (Enviar mensaje, Hacer un reclamo, Denunciar, Volver):
     usan los colores y la tipografía que eligió el emprendedor. */
  .shop-main .shop-actions .btn-primary,
  .shop-main .shop-actions .btn-secondary{
    font-family: inherit;
    border: 1.5px solid var(--scolor-acento);
    transition: background-color .2s ease, color .2s ease, filter .2s ease;
  }
  .shop-main .shop-actions .btn-primary{
    background: var(--scolor-acento);
    color: var(--scolor-fondo);
  }
  .shop-main .shop-actions .btn-primary:hover{
    background: var(--scolor-acento);
    color: var(--scolor-fondo);
    filter: brightness(.9);
  }
  .shop-main .shop-actions .btn-secondary{
    background: transparent;
    color: var(--scolor-acento);
  }
  .shop-main .shop-actions .btn-secondary:hover{
    background: var(--scolor-acento);
    color: var(--scolor-fondo);
  }

  .tablon-grid-3{ display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:20px; }
.tablon-grid-2{ display:grid; grid-template-columns:repeat(2, minmax(0, 280px)); gap:20px; justify-content:start; }
  .tablon-lista{ display:flex; flex-direction:column; gap:15px; }

  .card-estilo-moderno{ border-radius:16px; border:1px solid rgba(128,128,128,0.18); box-shadow:var(--shadow-sm); }
  .card-estilo-clasico{ border-radius:0; border:2px solid rgba(128,128,128,0.3); }
  .card-estilo-minimal{ border-radius:8px; border:none; }

  @media (max-width:900px){
    .tablon-grid-3{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .tablon-grid-2{ grid-template-columns:1fr; }
  }
  @media (max-width:560px){
    .tablon-grid-3{ grid-template-columns:1fr; }
  }
</style>
<?php if (is_administrador($pdo)): ?>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
<?php endif; ?>
</head>
<body class="shop-body">
<?php require __DIR__ . '/includes/navbar.php'; ?>
<div id="shop-loading">
    Cargando emprendimiento…
    </div>
<main class="shop-main">
  <div class="shop-banner" id="shop-banner"></div>
  <div class="shop-header">
    <img src="" alt="" class="shop-avatar" id="shop-avatar">
    <div class="shop-header-info">
      <div class="shop-title">
        <span id="shop-logo-icon"></span>
        <span id="shop-name">Nombre del emprendimiento</span>
        <svg class="shop-verified" viewBox="0 0 24 24" fill="currentColor" aria-label="Emprendimiento verificado"><path d="M12 2l2.4 2.4 3.3-.6.6 3.3 2.4 2.4-2.4 2.4-.6 3.3-3.3-.6L12 15l-2.4-2.4-3.3.6-.6-3.3L3.3 7.5 5.7 5.1l.6-3.3 3.3.6z"/></svg>
      </div>
      <p class="shop-desc" id="shop-desc">Breve descripción del emprendimiento…</p>
    </div>
    <div class="shop-actions">
      <a href="emprendimientos.php" class="btn-secondary shop-back" style="text-decoration:none">← Volver</a>
      <?php if ($esCliente): ?>
        <button type="button" class="btn-primary shop-message-btn" id="btn-message"
                data-shop-id="<?= htmlspecialchars($shopId ?? '') ?>">Enviar mensaje</button>
        <button type="button" class="btn-primary shop-message-btn" id="btn-reclamo"
                data-shop-id="<?= htmlspecialchars($shopId ?? '') ?>">Hacer un reclamo</button>
      <?php endif; ?>
      <?php if (current_profile_type($pdo) !== 'administrador'): ?>
        <button type="button" class="btn-secondary" data-denuncia-tipo="emprendimiento"
                data-denuncia-id="<?= htmlspecialchars($shopId ?? '') ?>">Denunciar</button>
      <?php else: ?>
        <button type="button" class="btn-secondary" id="btn-mod-suspender">Suspender</button>
        <button type="button" class="btn-secondary" id="btn-mod-eliminar">Eliminar</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="shop-tabs">
    <div class="shop-tab active" data-tab="catalogo">Catálogo</div>
    <div class="shop-tab" data-tab="info">Info de la marca</div>
  </div>
  <div class="field" id="shop-search-container" style="margin-bottom:20px; display:none">
    <input type="text" id="shop-search-input" placeholder="Buscar productos en este espacio…">
  </div>
  <section id="tab-catalogo" class="catalog-grid"><!-- productos inyectados por shop-detail.js --></section>
  <div class="empty-state" id="catalog-empty" style="display:none">Este emprendimiento todavía no cargó productos.</div>
  <section id="tab-info" style="display:none">
    <p class="shop-info-row"><strong>Rubro:</strong> <span id="shop-info-cat">—</span></p>
    <p class="shop-desc" id="shop-info-full">Información completa de la marca…</p>
  </section>
</main>

<?php if ($esCliente): ?>
<!-- Cartel: hacer un reclamo -->
<div class="fl-modal" id="reclamo-modal" hidden>
  <div class="fl-backdrop" id="reclamo-backdrop"></div>
  <div class="reclamo-cartel">
    <button type="button" id="reclamo-close" class="reclamo-cartel-close" aria-label="Cerrar">✕</button>
    <div class="reclamo-cartel-icon">📢</div>
    <h3 class="reclamo-cartel-title">Hacer un reclamo</h3>
    <p class="reclamo-cartel-sub">Contanos qué pasó y se lo hacemos llegar al emprendimiento.</p>
    <div class="field-group" style="text-align:left; margin:18px 0">
      <textarea id="reclamo-mensaje" rows="5" maxlength="1000" placeholder="Ej: hice un pedido y todavía no me llegó…"></textarea>
    </div>
    <button type="button" class="btn-primary" id="reclamo-enviar" style="width:100%">Enviar reclamo</button>
  </div>
</div>
<?php endif; ?>

<div class="toast" id="toast"></div>
<script src="javascript/ui.js"></script>
<script src="javascript/api.js" ></script>
<script src="javascript/shop-detail.js" data-shop-id="<?= htmlspecialchars($shopId ?? '') ?>"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/denuncia.js"></script>
<?php if (is_administrador($pdo) && $shopId): ?>
<script>
// Moderación (solo administrador): suspender o eliminar este emprendimiento.
(function () {
  const shopId = <?= (int)$shopId ?>;

  async function moderar(accion, extra) {
    try {
      const r = await apiRequest('/api/moderacion.php', {
        method: 'POST',
        body: JSON.stringify(Object.assign({ accion: accion, tipo: 'emprendimiento', id: shopId }, extra)),
      });
      showToast(r.mensaje || 'Listo.');
      return true;
    } catch (err) {
      showToast(err.message);
      return false;
    }
  }

  document.getElementById('btn-mod-suspender')?.addEventListener('click', async () => {
    const dias = parseInt(prompt('¿Por cuántos días querés suspender este emprendimiento?', '3'), 10);
    if (!dias || dias < 1) return;
    const motivo = prompt('Motivo (opcional):') || '';
    await moderar('suspender', { dias: dias, motivo: motivo });
  });

  document.getElementById('btn-mod-eliminar')?.addEventListener('click', async () => {
    if (!confirm('¿Eliminar este emprendimiento definitivamente? A su dueño se le retira el rol de emprendedor de forma permanente (no podrá crear otro ni volver a pedirlo) y se le avisa por mail. No se puede deshacer.')) return;
    const motivo = prompt('Motivo de la eliminación (se le envía por mail):') || '';
    if (await moderar('eliminar', { motivo: motivo })) {
      setTimeout(() => { window.location.href = 'emprendimientos.php'; }, 900);
    }
  });
})();
</script>
<?php endif; ?>
<script>
  // "Enviar mensaje": abre la mensajería flotante con este emprendedor, sin salir
  // de la página. Sólo se renderiza para clientes.
  (function(){
    const btn = document.getElementById('btn-message');
    if(!btn) return;
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try{
        if (window.mercaditoMensajes) {
          await window.mercaditoMensajes.startWithShop(btn.dataset.shopId);
        } else {
          // Respaldo por si el widget no cargó: ir a la página de mensajería.
          const id = await api.startConversation({ shopId: btn.dataset.shopId });
          window.location.href = 'mensajeria.php?c=' + id;
        }
      }catch(e){
        showToast(e.message || 'No se pudo abrir la conversación.');
      }finally{
        btn.disabled = false;
      }
    });
  })();
</script>
<script>
  // "Hacer un reclamo": abre un modal simple y lo manda con api.createReclamo.
  // Sólo se renderiza para clientes.
  (function(){
    const btn      = document.getElementById('btn-reclamo');
    if(!btn) return;
    const modal    = document.getElementById('reclamo-modal');
    const backdrop = document.getElementById('reclamo-backdrop');
    const closeBtn = document.getElementById('reclamo-close');
    const textarea = document.getElementById('reclamo-mensaje');
    const sendBtn  = document.getElementById('reclamo-enviar');

    function openModal(){ modal.hidden = false; textarea.value = ''; textarea.focus(); }
    function closeModal(){ modal.hidden = true; }

    btn.addEventListener('click', openModal);
    backdrop.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    sendBtn.addEventListener('click', async () => {
      const mensaje = textarea.value.trim();
      if(!mensaje){ showToast('Contanos qué pasó antes de enviar.'); return; }
      sendBtn.disabled = true;
      try{
        await api.createReclamo({ shopId: btn.dataset.shopId, mensaje });
        showToast('Reclamo enviado. El emprendimiento lo va a ver en su panel.');
        closeModal();
      }catch(e){
        showToast(e.message || 'No se pudo enviar el reclamo.');
      }finally{
        sendBtn.disabled = false;
      }
    });
  })();
</script>
            <script src="javascript/theme.js"></script>
</body>
</html>