// olvide-password.js — formulario para pedir el link de recuperación.

const olvideForm = document.getElementById('olvide-form');
const olvideListo = document.getElementById('olvide-listo');
const olvideListoTexto = document.getElementById('olvide-listo-texto');
const olvideDebug = document.getElementById('olvide-debug');

olvideForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('olvide-email').value.trim();
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  setInvalid('field-olvide-email', !emailOk);
  if (!emailOk) return;

  const submitBtn = olvideForm.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  try {
    const data = await api.forgotPassword(email);
    // Mostramos siempre la misma pantalla de "revisá tu correo", exista o no
    // esa cuenta: así nadie puede usar este formulario para averiguar qué
    // correos están registrados.
    if (data.mensaje) olvideListoTexto.textContent = data.mensaje;
    olvideForm.hidden = true;
    olvideListo.hidden = false;
  } catch (err) {
    showToast(err.message || 'No se pudo procesar el pedido.');
    // Modo desarrollo: si el correo existe pero el envío falló, el servidor
    // puede mandar un link para poder seguir probando.
    if (err.data && err.data.linkDesarrollo) {
      olvideDebug.hidden = false;
      olvideDebug.innerHTML =
        '<strong>Modo desarrollo (el correo no salió)</strong>' +
        '<a href="' + escapeAttr(err.data.linkDesarrollo) + '">Ir a elegir la contraseña nueva</a>';
      olvideForm.hidden = true;
      olvideListo.hidden = false;
    }
    submitBtn.disabled = false;
  }
});
