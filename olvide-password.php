<?php
// olvide-password.php — pantalla para pedir el correo de recuperación.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';

// Si ya está logueado no tiene sentido estar acá.
if (current_user_id()) {
    header('Location: /feed.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercadito — Recuperar contraseña</title>

<link rel="icon" href="/icon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/base.css">
<link rel="stylesheet" href="/login.css">
<link rel="stylesheet" href="/verificar.css">
</head>
<body>

<button type="button" class="theme-toggle" id="theme-toggle" aria-label="Cambiar a modo oscuro">
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
  </svg>
  <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="4.5"/>
    <path d="M12 2.5v2.5M12 19v2.5M4.2 4.2l1.8 1.8M18 18l1.8 1.8M2.5 12H5M19 12h2.5M4.2 19.8L6 18M18 6l1.8-1.8"/>
  </svg>
</button>

<div class="page">

  <aside class="brand-panel">
    <div class="brand-content">
      <svg class="logo-svg" viewBox="0 0 120 130" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Logo de Mercadito">
        <path class="logo-cream logo-line" stroke-width="2" d="M10,55 L60,15 L110,55 L110,115 L10,115 Z"/>
        <path class="logo-awning-a" d="M40,80 L80,80 L80,93 L74,87 L68,93 L62,87 L56,93 L50,87 L44,93 L40,87 Z"/>
        <rect class="logo-door" x="50" y="93" width="20" height="22" rx="2"/>
        <circle class="logo-cream logo-line" stroke-width="2" cx="60" cy="55" r="17"/>
        <text class="logo-text" x="60" y="61" text-anchor="middle" font-size="15">MD</text>
      </svg>
      <h1 class="logo-word">Mercadito</h1>
      <p class="tagline">Te ayudamos a volver a entrar. Escribí tu correo y te mandamos un link.</p>
      <div class="awning-strip"></div>
    </div>
  </aside>

  <main class="form-panel">
    <div class="card auth-card">

      <form class="form active" id="olvide-form" novalidate>
        <h2 class="form-title">Recuperar contraseña</h2>
        <p class="form-sub">Escribí el correo de tu cuenta. Si existe, te mandamos un link para elegir una contraseña nueva.</p>

        <div class="field" id="field-olvide-email">
          <label for="olvide-email">Correo electrónico</label>
          <input id="olvide-email" name="email" type="email" autocomplete="email" placeholder="vos@correo.com" required>
          <span class="error-msg">Ingresá un correo válido.</span>
        </div>

        <button type="submit" class="btn-primary">Mandar link de recuperación</button>

        <p class="switch-line">¿Te acordaste? <a href="/api/login.php">Iniciá sesión</a></p>
      </form>

      <div class="verif-resultado" id="olvide-listo" hidden>
        <div class="verif-icono verif-icono-ok" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2 class="form-title">Revisá tu correo</h2>
        <p class="form-sub" id="olvide-listo-texto">Si ese correo tiene una cuenta, te llegó un link para recuperar la contraseña.</p>
        <a class="btn-primary verif-boton-link" href="/api/login.php">Volver a iniciar sesión</a>
        <div class="verif-debug" id="olvide-debug" hidden></div>
      </div>

    </div>
  </main>

</div>

<div class="toast" id="toast"></div>

<script src="/javascript/ui.js"></script>
<script src="/javascript/theme.js"></script>
<script src="/javascript/api.js"></script>
<script src="/javascript/olvide-password.js"></script>

</body>
</html>
