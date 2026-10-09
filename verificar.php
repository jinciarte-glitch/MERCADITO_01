<?php
// verificar.php — pantalla de activación de la cuenta.
//
// Dos caminos llegan acá:
//   1. El botón del correo:  /verificar.php?token=...  → activa sola y entra.
//   2. Después de registrarse: /verificar.php          → pide el código.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/verificacion.php';

verificacion_migrar($pdo);

$estado  = 'pendiente'; // 'pendiente' | 'listo' | 'error'
$mensaje = '';

// --- Camino 1: link del correo ---
if (isset($_GET['token'])) {
    $resultado = verificacion_confirmar_token($pdo, $_GET['token']);

    if ($resultado['ok']) {
        $stmt = $pdo->prepare(
            'SELECT r.nombre AS profile_type FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$resultado['userId']]);
        $rol = $stmt->fetchColumn();

        login_user((int)$resultado['userId']);
        $_SESSION['profile_type'] = $rol !== false ? $rol : 'cliente';
        unset($_SESSION['verificacion_email']);

        $estado  = 'listo';
        $mensaje = 'Tu cuenta quedó activada. Te llevamos al feed…';
    } else {
        $estado  = 'error';
        $mensaje = $resultado['error'];
    }
}

// Correo a mostrar en el formulario: el de la URL, el de la sesión, o nada.
$emailPrevio = trim($_GET['email'] ?? ($_SESSION['verificacion_email'] ?? ''));
if (!filter_var($emailPrevio, FILTER_VALIDATE_EMAIL)) {
    $emailPrevio = '';
}

// Sólo tiene contenido si VERIF_MOSTRAR_CODIGO_SI_FALLA está en true y el
// envío del correo falló. En producción queda siempre vacío.
$debug = VERIF_MOSTRAR_CODIGO_SI_FALLA ? ($_SESSION['verificacion_debug'] ?? null) : null;

function e($texto) { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercadito — Activá tu cuenta</title>

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
      <p class="tagline">Un último paso y ya estás adentro: confirmá que el correo es tuyo.</p>
      <div class="awning-strip"></div>
    </div>
  </aside>

  <main class="form-panel">
    <div class="card auth-card">

<?php if ($estado === 'listo'): ?>

      <div class="verif-resultado">
        <div class="verif-icono verif-icono-ok" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2 class="form-title">Cuenta activada</h2>
        <p class="form-sub"><?= e($mensaje) ?></p>
        <a class="btn-primary verif-boton-link" href="/feed.php">Ir al feed</a>
      </div>
      <script>setTimeout(function(){ window.location.href = '/feed.php'; }, 2500);</script>

<?php else: ?>

      <form class="form active" id="verif-form" novalidate>
        <h2 class="form-title">Activá tu cuenta</h2>

<?php if ($estado === 'error'): ?>
        <p class="verif-aviso verif-aviso-error"><?= e($mensaje) ?> Pedí uno nuevo con el botón de abajo.</p>
<?php else: ?>
        <p class="form-sub">Te mandamos un correo con un botón de activación y un código. Tocá el botón del correo o escribí el código acá.</p>
<?php endif; ?>

        <div class="field" id="field-verif-email">
          <label for="verif-email">Correo electrónico</label>
          <input id="verif-email" name="email" type="email" autocomplete="email" placeholder="vos@correo.com" value="<?= e($emailPrevio) ?>" required>
          <span class="error-msg">Ingresá el correo con el que te registraste.</span>
        </div>

        <div class="field" id="field-verif-codigo">
          <label for="verif-codigo">Código de 6 dígitos</label>
          <input id="verif-codigo" name="code" class="verif-codigo" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
          <span class="error-msg">El código tiene 6 dígitos.</span>
        </div>

        <button type="submit" class="btn-primary">Activar cuenta</button>

        <p class="verif-reenviar">
          ¿No te llegó? Mirá en el correo no deseado o
          <button type="button" id="verif-reenviar">reenviá el mensaje</button>.
        </p>

        <p class="switch-line">¿Ya la activaste? <a href="/api/login.php">Iniciá sesión</a></p>

<?php if ($debug): ?>
        <div class="verif-debug">
          <strong>Modo desarrollo (el correo no salió)</strong>
          Código: <?= e($debug['codigo']) ?><br>
          <a href="<?= e($debug['link']) ?>">Activar con el link</a>
        </div>
<?php endif; ?>
      </form>

<?php endif; ?>

    </div>
  </main>

</div>

<div class="toast" id="toast"></div>

<script src="/javascript/ui.js"></script>
<script src="/javascript/theme.js"></script>
<script src="/javascript/api.js"></script>
<script src="/javascript/verificar.js"></script>

</body>
</html>
