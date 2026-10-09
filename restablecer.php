<?php
// restablecer.php — pantalla para elegir una contraseña nueva.
// Se llega acá desde el link del correo: /restablecer.php?token=...

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/recuperacion.php';

$token = trim($_GET['token'] ?? '');
$validacion = $token !== '' ? recuperacion_validar_token($pdo, $token) : ['ok' => false, 'error' => 'Falta el link de recuperación.'];

function e($texto) { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercadito — Elegir contraseña nueva</title>

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
      <p class="tagline">Un último paso y volvés a entrar con tu contraseña nueva.</p>
      <div class="awning-strip"></div>
    </div>
  </aside>

  <main class="form-panel">
    <div class="card auth-card">

<?php if (!$validacion['ok']): ?>

      <div class="verif-resultado">
        <div class="verif-icono verif-icono-error" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </div>
        <h2 class="form-title">Ese link no sirve</h2>
        <p class="form-sub"><?= e($validacion['error']) ?></p>
        <a class="btn-primary verif-boton-link" href="/olvide-password.php">Pedir un link nuevo</a>
      </div>

<?php else: ?>

      <form class="form active" id="restablecer-form" novalidate>
        <h2 class="form-title">Elegí tu contraseña nueva</h2>
        <p class="form-sub">Va a reemplazar la anterior. Después de guardarla, iniciá sesión con la nueva.</p>

        <input type="hidden" id="restablecer-token" value="<?= e($token) ?>">

        <div class="field" id="field-restablecer-pass">
          <label for="restablecer-password">Contraseña nueva</label>
          <div class="pw-wrap">
            <input id="restablecer-password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>
            <button type="button" class="pw-toggle" data-target="restablecer-password" aria-label="Mostrar contraseña">
              <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <ul class="pw-requirements" id="pw-requirements">
            <li data-rule="length"><span class="pw-req-icon"></span>Al menos 8 caracteres</li>
            <li data-rule="upper"><span class="pw-req-icon"></span>Al menos una mayúscula</li>
            <li data-rule="number"><span class="pw-req-icon"></span>Al menos un número</li>
            <li data-rule="special"><span class="pw-req-icon"></span>Al menos un carácter especial (!@#$%…)</li>
          </ul>
          <span class="error-msg">Tu contraseña no cumple los requisitos de arriba.</span>
        </div>

        <div class="field" id="field-restablecer-confirm">
          <label for="restablecer-confirm">Confirmar contraseña</label>
          <div class="pw-wrap">
            <input id="restablecer-confirm" name="confirm" type="password" autocomplete="new-password" placeholder="Repetí tu contraseña" required>
            <button type="button" class="pw-toggle" data-target="restablecer-confirm" aria-label="Mostrar contraseña">
              <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <span class="error-msg">Las contraseñas no coinciden.</span>
        </div>

        <button type="submit" class="btn-primary">Guardar contraseña</button>
      </form>

      <div class="verif-resultado" id="restablecer-listo" hidden>
        <div class="verif-icono verif-icono-ok" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2 class="form-title">Contraseña actualizada</h2>
        <p class="form-sub">Ya podés iniciar sesión con tu contraseña nueva.</p>
        <a class="btn-primary verif-boton-link" href="/api/login.php">Iniciar sesión</a>
      </div>

<?php endif; ?>

    </div>
  </main>

</div>

<div class="toast" id="toast"></div>

<script src="/javascript/ui.js"></script>
<script src="/javascript/theme.js"></script>
<script src="/javascript/api.js"></script>
<script src="/javascript/restablecer.js"></script>

</body>
</html>
