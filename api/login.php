<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/verificacion.php';

verificacion_migrar($pdo);

// ---- POST (JSON): autenticar usuario ----
// login.js hace fetch POST a /api/login.php con { username, password }.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $data     = json_input();
    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'Completá usuario y contraseña.']));
    }

    // Permite iniciar sesión con nombre de usuario O con el correo.
    $stmt = $pdo->prepare(
    'SELECT u.id, u.email, u.full_name, u.email_verified_at, u.password_hash, r.nombre AS profile_type FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.username = ? OR u.email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        exit(json_encode(['error' => 'Usuario o contraseña incorrectos.']));
    }

    // Contraseña correcta, pero el correo todavía no está confirmado: no entra.
    if (empty($user['email_verified_at'])) {
        $_SESSION['verificacion_email'] = $user['email'];
        http_response_code(403);
        exit(json_encode([
            'error'                => 'Todavía no activaste tu cuenta. Revisá el correo que te mandamos.',
            'requiereVerificacion' => true,
            'email'                => $user['email'],
        ]));
    }

    login_user((int)$user['id']);
    $_SESSION['profile_type'] = $user['profile_type'] ?? 'usuario';

    echo json_encode(['ok' => true]);
    exit;
}

// ---- GET: si ya está logueado, al feed ----
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
<title>Mercadito — Iniciá sesión</title>

<link rel="icon" href="logo.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/../base.css">
<link rel="stylesheet" href="/../login.css">
<link rel="stylesheet" href="/../verificar.css">
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

  <!-- BRAND PANEL -->
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
      <p class="tagline">Tu barrio, tu comunidad, tu mercado. Conectate con vecinos y descubrí emprendimientos cerca tuyo.</p>
      <div class="awning-strip"></div>
    </div>
  </aside>

  <!-- FORM PANEL -->
  <main class="form-panel">
    <div class="card auth-card">

      <div class="tabs" role="tablist">
        <button class="tab active" id="tab-login" role="tab" aria-selected="true" data-target="login">Iniciar sesión</button>
        <button class="tab" id="tab-register" role="tab" aria-selected="false" data-target="register">Registrarme</button>
      </div>

      <!-- LOGIN FORM -->
      <form class="form active" id="login-form" novalidate>
        <h2 class="form-title">Bienvenido de nuevo</h2>
        <p class="form-sub">Ingresá con tu usuario y contraseña.</p>

        <div class="field" id="field-login-user">
          <label for="login-username">Nombre de usuario</label>
          <input id="login-username" name="username" type="text" autocomplete="username" placeholder="tu_usuario" required>
          <span class="error-msg">Ingresá tu nombre de usuario.</span>
        </div>

        <div class="field" id="field-login-pass">
          <label for="login-password">Contraseña</label>
          <div class="pw-wrap">
            <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
            <button type="button" class="pw-toggle" data-target="login-password" aria-label="Mostrar contraseña">
              <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <span class="error-msg">Ingresá tu contraseña.</span>
        </div>

        <div class="checkbox-row">
          <label><input type="checkbox" id="remember"> Recordarme</label>
          <a href="/olvide-password.php">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn-primary">Iniciar sesión</button>

        <p class="switch-line">¿No tenés cuenta? <button type="button" data-target="register">Registrate</button></p>
      </form>

      <!-- REGISTER FORM -->
      <form class="form" id="register-form" novalidate>
        <h2 class="form-title">Creá tu cuenta</h2>
        <p class="form-sub">Sumate a la comunidad de Mercadito.</p>

        <div class="field" id="field-fullname">
          <label for="fullname">Nombre y apellido</label>
          <input id="fullname" name="fullname" type="text" autocomplete="name" placeholder="Ana Gómez" required>
          <span class="error-msg">Ingresá tu nombre y apellido.</span>
        </div>

        <div class="row-2">
          <div class="field" id="field-age">
            <label for="age">Edad</label>
            <input id="age" name="age" type="number" min="13" max="120" placeholder="18" required>
            <span class="error-msg">Ingresá una edad válida (mínimo 13).</span>
          </div>
          <div class="field" id="field-reg-user">
            <label for="reg-username">Nombre de usuario</label>
            <input id="reg-username" name="username" type="text" autocomplete="username" placeholder="tu_usuario" required>
            <span class="error-msg">Elegí un nombre de usuario.</span>
          </div>
        </div>

        <div class="email-row">
          <div class="field" id="field-email">
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" autocomplete="email" placeholder="vos@correo.com" required>
            <span class="error-msg">Ingresá un correo válido.</span>
          </div>
        </div>

        <p class="verif-nota">Te vamos a mandar un correo para activar la cuenta. Sin activarla, no vas a poder iniciar sesión.</p>

        <div class="field" id="field-reg-pass">
          <label for="reg-password">Contraseña</label>
          <div class="pw-wrap">
            <input id="reg-password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required>
            <button type="button" class="pw-toggle" data-target="reg-password" aria-label="Mostrar contraseña">
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

        <div class="field" id="field-confirm">
          <label for="confirm-password">Confirmar contraseña</label>
          <div class="pw-wrap">
            <input id="confirm-password" name="confirm" type="password" autocomplete="new-password" placeholder="Repetí tu contraseña" required>
            <button type="button" class="pw-toggle" data-target="confirm-password" aria-label="Mostrar contraseña">
              <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <span class="error-msg">Las contraseñas no coinciden.</span>
        </div>

        <button type="submit" class="btn-primary">Crear cuenta</button>

        <p class="switch-line">¿Ya tenés cuenta? <button type="button" data-target="login">Iniciá sesión</button></p>
      </form>

    </div>
  </main>

</div>

<div class="toast" id="toast"></div>

<script src="/../javascript/ui.js"></script>
<script src="/../javascript/theme.js"></script>
<script src="/../javascript/api.js"></script>
<script src="/../javascript/login.js"></script>

</body>
</html>
