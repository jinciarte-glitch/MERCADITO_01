<?php
// includes/navbar.php — barra de navegación compartida.
// Se incluye en feed.php, emprendimientos.php, tienda.php y mi-espacio.php.
// Requiere que $pdo (config.php) y las funciones de session.php ya estén
// cargadas antes del include. $navActive marca el link activo (opcional).
$navActive       = $navActive ?? '';

// Si a esta persona se le retiró el rol de emprendedor, se corrige la sesión ya mismo
// (la sesión guarda el rol y si no seguiría mostrándose como emprendedor hasta cerrar sesión).
require_once __DIR__ . '/moderacion-lib.php';
if (current_user_id() && ($_SESSION['profile_type'] ?? '') === 'emprendedor'
    && mod_emprendedor_bloqueado($pdo, (int)current_user_id())) {
    $_SESSION['profile_type'] = 'cliente';
}

$esEmprendedor   = is_emprendedor($pdo);
$esAdministrador = is_administrador($pdo);

// Aviso para quien está suspendido (cuenta o emprendimiento).
require_once __DIR__ . '/moderacion-lib.php';
$avisoCuenta = current_user_id() ? mod_suspension_usuario($pdo, (int)current_user_id()) : null;
$avisoTienda = current_user_id() ? mod_suspension_tienda($pdo, (int)current_user_id()) : null;
$avisosPantalla = current_user_id() ? mod_notificaciones_pendientes($pdo, (int)current_user_id()) : [];
?>
<header class="navbar">
  <a href="feed.php" class="navbar-brand" aria-label="Ir al inicio de Mercadito">
    <svg class="navbar-logo" viewBox="0 0 120 130" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
      <path class="logo-cream logo-line" stroke-width="4" d="M10,55 L60,15 L110,55 L110,115 L10,115 Z"/>
      <path class="logo-awning-a" d="M40,80 L80,80 L80,93 L74,87 L68,93 L62,87 L56,93 L50,87 L44,93 L40,87 Z"/>
      <rect class="logo-door" x="50" y="93" width="20" height="22" rx="2"/>
      <circle class="logo-cream logo-line" stroke-width="4" cx="60" cy="55" r="17"/>
      <text class="logo-text" x="60" y="61" text-anchor="middle" font-size="15">MD</text>
    </svg>
    <span class="navbar-word brand-font">Mercadito</span>
  </a>
  <div class="navbar-actions">
    <a href="feed.php" class="btn-secondary<?= $navActive === 'feed' ? ' nav-current' : '' ?>" style="text-decoration:none">Inicio</a>
    <a href="emprendimientos.php" class="btn-secondary<?= $navActive === 'emprendimientos' ? ' nav-current' : '' ?>" style="text-decoration:none">Emprendimientos</a>
   
    <?php if ($esEmprendedor && !$esAdministrador): ?>
      <a href="mi-espacio.php" class="btn-secondary<?= $navActive === 'mi-espacio' ? ' nav-current' : '' ?>" style="text-decoration:none">Mi espacio</a>
    <?php endif; ?>
    <?php if (!$esEmprendedor || $esAdministrador): ?>
      <a href="solicitud-emprendedor.php" class="btn-secondary<?= $navActive === 'solicitud-emprendedor' ? ' nav-current' : '' ?>" style="text-decoration:none">Ser emprendedor</a>
    <?php endif; ?>
    <?php if ($esAdministrador): ?>
      <a href="admin-solicitudes.php" class="btn-secondary<?= $navActive === 'admin-solicitudes' ? ' nav-current' : '' ?>" style="text-decoration:none">Solicitudes</a>
    <?php endif; ?>
    <button type="button" class="btn-secondary" id="logout-btn">Cerrar sesión</button>
    <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Cambiar a modo oscuro">
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4.5"/>
        <path d="M12 2.5v2.5M12 19v2.5M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2.5 12H5M19 12h2.5M4.2 19.8L6 18M18 6l1.8-1.8"/>
      </svg>
    </button>
  </div>
</header>
<?php if ($avisoCuenta || $avisoTienda || $avisosPantalla): ?>
<style>
  .aviso-banner{ display:flex; gap:16px; align-items:flex-start; justify-content:space-between; padding:12px 24px; font-size:14px; line-height:1.45; border-bottom:1px solid; }
  .aviso-banner a{ color:inherit; font-weight:600; }
  .aviso-banner button{ flex:none; padding:6px 14px; border:1px solid currentColor; border-radius:8px; background:transparent; color:inherit; font:inherit; cursor:pointer; }
  /* Modo claro */
  .aviso-banner--alerta{ background:#fff3cd; color:#664d03; border-color:#ffe69c; }
  .aviso-banner--info{   background:#e7f1ff; color:#052c65; border-color:#b6d4fe; }
  /* Modo oscuro */
  :root[data-theme="dark"] .aviso-banner--alerta{ background:#332701; color:#ffda6a; border-color:#664d03; }
  :root[data-theme="dark"] .aviso-banner--info{   background:#031633; color:#6ea8fe; border-color:#084298; }
</style>
<?php endif; ?>
<?php if ($avisoCuenta): ?>
<div class="aviso-banner aviso-banner--alerta" role="alert">
  <div>
    <strong>Tu cuenta está suspendida</strong> hasta el <?= date('d/m/Y H:i', (int)$avisoCuenta['hasta']) ?>.
    Podés mirar la página, pero no publicar, comentar, escribir mensajes ni modificar tu perfil o tu emprendimiento.
    <?php if (!empty($avisoCuenta['motivo'])): ?>Motivo: <?= htmlspecialchars($avisoCuenta['motivo']) ?><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php if ($avisoTienda): ?>
<div class="aviso-banner aviso-banner--alerta" role="alert">
  <div>
    <strong>Tu emprendimiento «<?= htmlspecialchars($avisoTienda['name']) ?>» está suspendido</strong> hasta el <?= date('d/m/Y H:i', (int)$avisoTienda['hasta']) ?>.
    No aparece en el listado y no podés modificarlo ni cargar productos hasta entonces.
    <?php if (!empty($avisoTienda['motivo'])): ?>Motivo: <?= htmlspecialchars($avisoTienda['motivo']) ?><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php foreach ($avisosPantalla as $av): ?>
<?php $claseAviso = ($av['tipo'] ?? '') === 'denuncia_resultado' ? 'info' : 'alerta'; ?>
<div class="aviso-banner aviso-banner--<?= $claseAviso ?> aviso-pantalla" role="status">
  <div>
    <strong><?= htmlspecialchars($av['titulo']) ?>:</strong>
    <?= nl2br(htmlspecialchars($av['mensaje'])) ?>
    <?php if (!empty($av['url'])): ?> <a href="<?= htmlspecialchars($av['url']) ?>">Ver</a><?php endif; ?>
  </div>
  <button type="button" data-aviso-ok="<?= (int)$av['id'] ?>">Entendido</button>
</div>
<?php endforeach; ?>
<?php if ($avisosPantalla): ?>
<script>
document.querySelectorAll('[data-aviso-ok]').forEach(function (b) {
  b.addEventListener('click', function () {
    fetch('api/notificaciones.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: Number(b.getAttribute('data-aviso-ok')) })
    }).catch(function () {});
    var caja = b.closest('.aviso-pantalla');
    if (caja) caja.remove();
  });
});
</script>
<?php endif; ?>
<?php if ($navActive !== 'mensajeria') require __DIR__ . '/chat-widget.php'; ?>