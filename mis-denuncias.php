<?php
// mis-denuncias.php — Estado de las denuncias que hizo la persona
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }

$navActive = 'mis-denuncias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
    <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis denuncias — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
<style>
  .den-estado{ display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; }
  .den-estado--pendiente{  background:#fff3cd; color:#664d03; }
  .den-estado--resuelta{   background:#d1e7dd; color:#0a3622; }
  .den-estado--descartada{ background:#e2e3e5; color:#2b2f32; }
  :root[data-theme="dark"] .den-estado--pendiente{  background:#332701; color:#ffda6a; }
  :root[data-theme="dark"] .den-estado--resuelta{   background:#051b11; color:#75b798; }
  :root[data-theme="dark"] .den-estado--descartada{ background:#2b2f32; color:#ced4da; }
  .den-card{ padding:18px 20px; margin-bottom:12px; }
  .den-card h3{ margin:0 0 6px; font-size:16px; color:var(--color-text-strong); }
  .den-card p{ margin:6px 0 0; }
  .den-resultado{ margin-top:10px; padding:10px 12px; border-radius:10px; border:1px solid var(--color-border); background:var(--color-bg-input); color:var(--color-text-strong); font-size:14px; }
</style>
</head>
<body class="app-body shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="app-layout">

  <!-- IZQUIERDA: menú -->
  <?php require __DIR__ . '/includes/sidebar-left.php'; ?>

  <main class="app-center">

    <div class="explore-intro">
      <h2 class="explore-title brand-font">Mis denuncias</h2>
      <p class="explore-sub">Acá ves cómo va cada denuncia que hiciste. Cuando el equipo la resuelve, también te avisamos arriba y por mail.</p>
    </div>

    <div id="lista"><p class="explore-sub">Cargando...</p></div>

  </main>

  <!-- DERECHA: buscador + a quién seguir -->
  <?php require __DIR__ . '/includes/sidebar-right.php'; ?>

</div>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/sidebar.js"></script>
<script>
const MOTIVOS = {
    spam: 'Spam o publicidad engañosa', contenido_inapropiado: 'Contenido inapropiado',
    acoso: 'Acoso o maltrato', estafa: 'Posible estafa', otro: 'Otro motivo',
};
const ESTADOS = { pendiente: 'En revisión', resuelta: 'Resuelta', descartada: 'Descartada' };

function fmt(ms) { return new Date(ms).toLocaleString('es-AR'); }

async function cargar() {
    const lista = document.getElementById('lista');
    let data;
    try {
        data = await apiRequest('api/mis-denuncias.php');
    } catch (err) {
        lista.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
        return;
    }

    if (data.denuncias.length === 0) {
        lista.innerHTML = '<div class="empty-state">Todavía no hiciste ninguna denuncia.</div>';
        return;
    }

    lista.innerHTML = data.denuncias.map(d => {
        let extra = '';
        if (d.estado !== 'pendiente') {
            if (d.resolucion) {
                extra += `<div class="den-resultado"><strong>Resultado:</strong> ${escapeHtml(d.resolucion)}</div>`;
            }
            if (d.puedeDenunciarDesde) {
                extra += d.puedeDenunciarDesde > Date.now()
                    ? `<p class="explore-sub">Vas a poder volver a denunciar esto a partir del ${escapeHtml(fmt(d.puedeDenunciarDesde))}.</p>`
                    : `<p class="explore-sub">Si el problema continúa, ya podés volver a denunciar esto.</p>`;
            }
        } else {
            extra = '<p class="explore-sub">El equipo todavía la está revisando. Te avisamos apenas tenga resultado.</p>';
        }

        return `
        <div class="card den-card">
            <h3>${escapeHtml(d.objetivo)}</h3>
            <p class="explore-sub">
                <span class="den-estado den-estado--${d.estado}">${ESTADOS[d.estado] || d.estado}</span>
                · ${escapeHtml(MOTIVOS[d.motivo] || d.motivo)} · ${escapeHtml(fmt(d.createdAt))}
            </p>
            ${d.detalle ? `<p>${escapeHtml(d.detalle)}</p>` : ''}
            ${extra}
        </div>`;
    }).join('');
}

cargar();
</script>
</body>
</html>
