<?php
// reclamo-general.php — Formulario para que un cliente o emprendedor envíe un reclamo sobre la página
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
if (!in_array(current_profile_type($pdo), ['cliente', 'emprendedor'], true)) { header('Location: /feed.php'); exit; }

$navActive = 'reclamo-general';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
    <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hacer un reclamo — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
</head>
<body class="app-body shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="app-layout">

  <!-- IZQUIERDA: menú -->
  <?php require __DIR__ . '/includes/sidebar-left.php'; ?>

  <main class="app-center">

  <div class="explore-intro">
    <h2 class="explore-title brand-font">Hacer un reclamo</h2>
    <p class="explore-sub">¿Algo no funciona bien en la página o tuviste un problema? Contanoslo y lo revisa el equipo de Mercadito. Si el problema es con un emprendimiento en particular, usá el botón "Hacer un reclamo" dentro de su tienda.</p>
  </div>

  <form id="form-reclamo-general" class="card" style="padding:24px">
    <div class="field-group">
      <label for="mensaje">¿Qué pasó?</label>
      <textarea id="mensaje" rows="6" maxlength="1000" required placeholder="Contanos con el mayor detalle posible…"></textarea>
      <small class="explore-sub"><span id="contador">0</span>/1000 caracteres</small>
    </div>
    <button type="submit" class="btn-primary">Enviar reclamo</button>
  </form>

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
const form = document.getElementById('form-reclamo-general');
const mensaje = document.getElementById('mensaje');
const contador = document.getElementById('contador');

mensaje.addEventListener('input', () => {
    contador.textContent = mensaje.value.length;
});

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const boton = form.querySelector('button');
    const texto = mensaje.value.trim();

    if (!texto) {
        showToast('Contanos qué pasó antes de enviar el reclamo.');
        return;
    }

    boton.disabled = true;
    try {
        await apiRequest('api/reclamos-generales.php', {
            method: 'POST',
            body: JSON.stringify({ mensaje: texto }),
        });
        showToast('Reclamo enviado. Gracias por avisarnos.');
        mensaje.value = '';
        contador.textContent = '0';
    } catch (err) {
        showToast(err.message);
    }
    boton.disabled = false;
});
</script>
</body>
</html>