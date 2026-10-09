<?php
// solicitud-emprendedor.php — Formulario para pedir la certificación de emprendedor
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/moderacion-lib.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }

// Chequeo ESTRICTO (no cuenta a los administradores como ya-emprendedores),
// para que un admin pueda ver y probar este formulario tal cual lo ve un
// usuario cliente común.
$yaEsEmprendedor = is_emprendedor_real($pdo);
$esAdminProbando = is_administrador($pdo) && !$yaEsEmprendedor;

$stmt = $pdo->prepare('SELECT * FROM solicitudes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([current_user_id()]);
$solicitud = $stmt->fetch();

// Si se le retiró el rol de emprendedor de forma permanente, no puede volver a pedirlo.
$rolRetirado = mod_emprendedor_bloqueado($pdo, (int)current_user_id());

$navActive = 'solicitud-emprendedor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
    <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ser emprendedor — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
</head>
<body class="shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="shop-main" style="max-width:640px">

  <div class="explore-intro">
    <h2 class="explore-title brand-font">Convertite en emprendedor</h2>
    <p class="explore-sub">Certificá tu emprendimiento para tener tu propio espacio de venta dentro de Mercadito.</p>
  </div>

  <?php if ($esAdminProbando): ?>
    <div class="card" style="padding:14px 20px; margin-bottom:16px; border:1.5px dashed var(--color-accent)">
      <p class="explore-sub" style="margin:0"><strong>Modo prueba (admin):</strong> estás viendo este formulario igual que lo vería un cliente, para poder probar el envío de la solicitud. Si la aprobás desde el panel de admin, tu cuenta <strong>no</strong> pierde el rol de administrador (se resuelve la solicitud sin cambiarte el rol).</p>
    </div>
  <?php endif; ?>

  <?php if ($rolRetirado): ?>
    <div class="card" style="padding:24px">
      <p><strong>Se te retiró de forma permanente el rol de emprendedor.</strong></p>
      <p class="explore-sub" style="margin-top:8px">Tu emprendimiento fue eliminado por el equipo de Mercadito, por eso ya no podés crear otro ni volver a pedir la certificación. Tu cuenta sigue activa como cliente.</p>
    </div>

  <?php elseif ($yaEsEmprendedor): ?>
    <div class="card" style="padding:24px">
      <p>Tu cuenta ya está certificada como emprendedor. Podés ir a <a href="mi-espacio.php">Mi espacio</a> para armar tu página de venta.</p>
    </div>

  <?php elseif ($solicitud && $solicitud['estado'] === 'pendiente'): ?>
    <div class="card" style="padding:24px">
      <p><span class="badge">Pendiente de revisión</span></p>
      <p class="explore-sub" style="margin-top:8px">Ya enviaste una solicitud. Te avisaremos apenas un administrador la revise.</p>
    </div>

  <?php else: ?>

    <?php if ($solicitud && $solicitud['estado'] === 'rechazada'): ?>
      <div class="card" style="padding:20px; margin-bottom:16px">
        <p><span class="badge" style="background:var(--color-error); color:#fff">Rechazada</span></p>
        <?php if (!empty($solicitud['comentario_admin'])): ?>
          <p class="explore-sub" style="margin-top:8px"><strong>Motivo:</strong> <?= htmlspecialchars($solicitud['comentario_admin']) ?></p>
        <?php endif; ?>
        <p class="explore-sub" style="margin-top:8px">Podés corregir lo necesario y volver a enviarla.</p>
      </div>
    <?php endif; ?>

    <form id="form-solicitud" class="card" style="padding:24px">
      <div class="field-group">
        <label for="nombreEmprendimiento">Nombre del emprendimiento</label>
        <input type="text" id="nombreEmprendimiento" required>
      </div>
      <div class="field-group">
        <label for="categoria">Rubro</label>
        <input type="text" id="categoria" placeholder="Ej: indumentaria, comida, artesanías...">
      </div>
      <div class="field-group">
        <label for="descripcion">Descripción breve</label>
        <textarea id="descripcion" rows="3" placeholder="Contanos de qué se trata tu emprendimiento"></textarea>
      </div>
      <div class="field-group">
        <label for="motivo">¿Por qué querés certificarte como emprendedor?</label>
        <textarea id="motivo" rows="3" required placeholder="Contanos cómo comprobamos que tu emprendimiento es real"></textarea>
      </div>

      <hr style="border:none; border-top:1px solid var(--color-border-strong); margin:20px 0">
      <h4 style="margin:0 0 4px">Verificación de identidad</h4>
      <p class="explore-sub" style="margin:0 0 12px">Necesitamos estos datos para confirmar que sos una persona real y mayor de edad. No se muestran públicamente, solo los ve el equipo que revisa las solicitudes.</p>

      <div class="field-group">
        <label for="tipoDocumento">Tipo de documento</label>
        <select id="tipoDocumento" required>
          <option value="">Elegí una opción…</option>
          <option value="DNI">DNI</option>
          <option value="CUIT/CUIL">CUIT/CUIL</option>
        </select>
      </div>
      <div class="field-group">
        <label for="numeroDocumento">Número de documento</label>
        <input type="text" id="numeroDocumento" required maxlength="11" inputmode="numeric" placeholder="Elegí primero el tipo de documento" disabled>
        <small class="explore-sub" id="ayuda-documento">Elegí el tipo de documento arriba.</small>
      </div>
      <div class="field-group">
        <label for="fechaNacimiento">Fecha de nacimiento</label>
        <input type="date" id="fechaNacimiento" required>
        <small class="explore-sub">Tenés que ser mayor de 18 años para certificarte como emprendedor.</small>
      </div>
      <div class="field-group">
        <label style="display:flex; gap:8px; align-items:flex-start; font-weight:normal">
          <input type="checkbox" id="declaracionLicitud" required style="margin-top:3px">
          <span>Declaro que soy mayor de 18 años, que los datos ingresados son verdaderos, y que la actividad que voy a ofrecer en mi espacio de venta es lícita y cumple con las leyes vigentes (no incluye productos o servicios prohibidos, falsificados, robados ni de venta ilegal).</span>
        </label>
      </div>

      <button type="submit" class="btn-primary">Enviar solicitud</button>
    </form>

  <?php endif; ?>

</main>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script>
// Ajusta el campo de número de documento según el tipo elegido (DNI u
// CUIT/CUIL): largo máximo y texto de ayuda. La validación real (que no se
// puede saltear desde la consola) se hace en el servidor.
const selectTipoDoc = document.getElementById('tipoDocumento');
const inputNumeroDoc = document.getElementById('numeroDocumento');
const ayudaDocumento = document.getElementById('ayuda-documento');

if (selectTipoDoc && inputNumeroDoc) {
    selectTipoDoc.addEventListener('change', () => {
        inputNumeroDoc.value = '';
        if (selectTipoDoc.value === 'DNI') {
            inputNumeroDoc.disabled = false;
            inputNumeroDoc.maxLength = 8;
            inputNumeroDoc.placeholder = 'Ej: 30123456 (sin puntos)';
            ayudaDocumento.textContent = 'DNI: 7 u 8 números, sin puntos.';
        } else if (selectTipoDoc.value === 'CUIT/CUIL') {
            inputNumeroDoc.disabled = false;
            inputNumeroDoc.maxLength = 11;
            inputNumeroDoc.placeholder = 'Ej: 20301234567 (sin guiones)';
            ayudaDocumento.textContent = 'CUIT/CUIL: 11 números, sin guiones ni espacios.';
        } else {
            inputNumeroDoc.disabled = true;
            inputNumeroDoc.placeholder = 'Elegí primero el tipo de documento';
            ayudaDocumento.textContent = 'Elegí el tipo de documento arriba.';
        }
    });
    // Solo permite dígitos mientras se escribe.
    inputNumeroDoc.addEventListener('input', () => {
        inputNumeroDoc.value = inputNumeroDoc.value.replace(/\D/g, '');
    });
}

// Dígito verificador de CUIT/CUIL (algoritmo módulo 11 de AFIP). Solo valida
// el FORMATO matemático, no confirma que el CUIT exista de verdad.
function cuitFormatoValido(numero) {
    if (!/^\d{11}$/.test(numero)) return false;
    const mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    let suma = 0;
    for (let i = 0; i < 10; i++) suma += Number(numero[i]) * mult[i];
    let verificador = 11 - (suma % 11);
    if (verificador === 11) verificador = 0;
    if (verificador === 10) return false;
    return verificador === Number(numero[10]);
}

function documentoValido(tipo, numero) {
    if (tipo === 'DNI') return /^\d{7,8}$/.test(numero);
    if (tipo === 'CUIT/CUIL') return cuitFormatoValido(numero);
    return false;
}

const form = document.getElementById('form-solicitud');
if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const boton = form.querySelector('button');
        const tipoDoc = document.getElementById('tipoDocumento').value;
        const numeroDoc = document.getElementById('numeroDocumento').value;

        if (!documentoValido(tipoDoc, numeroDoc)) {
            showToast(tipoDoc === 'CUIT/CUIL'
                ? 'Ese CUIT/CUIL no es válido. Revisá los 11 números, sin guiones.'
                : 'Ese DNI no es válido. Tiene que tener 7 u 8 números, sin puntos.');
            return;
        }

        if (!document.getElementById('declaracionLicitud').checked) {
            showToast('Tenés que aceptar la declaración para poder enviar la solicitud.');
            return;
        }

        boton.disabled = true;
        try {
            await apiRequest('api/solicitudes.php', {
                method: 'POST',
                body: JSON.stringify({
                    nombreEmprendimiento: document.getElementById('nombreEmprendimiento').value,
                    categoria: document.getElementById('categoria').value,
                    descripcion: document.getElementById('descripcion').value,
                    motivo: document.getElementById('motivo').value,
                    tipoDocumento: tipoDoc,
                    numeroDocumento: numeroDoc,
                    fechaNacimiento: document.getElementById('fechaNacimiento').value,
                    declaracionLicitud: document.getElementById('declaracionLicitud').checked,
                }),
            });
            showToast('Solicitud enviada. Te avisaremos cuando sea revisada.');
            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            showToast(err.message);
            boton.disabled = false;
        }
    });
}
</script>
</body>
</html>