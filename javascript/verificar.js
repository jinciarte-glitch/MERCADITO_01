// verificar.js — pantalla de activación de cuenta (código + reenvío).

const verifForm = document.getElementById('verif-form');

// La pantalla de "cuenta activada" no tiene formulario: no hay nada que hacer.
if (verifForm) {
  const emailInput = document.getElementById('verif-email');
  const codigoInput = document.getElementById('verif-codigo');
  const botonReenviar = document.getElementById('verif-reenviar');
  const botonEnviar = verifForm.querySelector('button[type="submit"]');

  // Sólo dígitos, y se limpia el error apenas la persona corrige.
  codigoInput.addEventListener('input', () => {
    codigoInput.value = codigoInput.value.replace(/\D/g, '').slice(0, 6);
    setInvalid('field-verif-codigo', false);
  });

  // Si pega el código con espacios ("123 456"), igual entra bien.
  codigoInput.addEventListener('paste', (e) => {
    const texto = (e.clipboardData || window.clipboardData).getData('text');
    if (!texto) return;
    e.preventDefault();
    codigoInput.value = texto.replace(/\D/g, '').slice(0, 6);
    setInvalid('field-verif-codigo', false);
  });

  if (emailInput.value === '') emailInput.focus();
  else codigoInput.focus();

  // ---- Activar ----
  verifForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = emailInput.value.trim();
    const code = codigoInput.value.trim();

    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const codeOk = /^\d{6}$/.test(code);
    setInvalid('field-verif-email', !emailOk);
    setInvalid('field-verif-codigo', !codeOk);
    if (!emailOk || !codeOk) return;

    botonEnviar.disabled = true;
    try {
      const data = await api.verifyEmail({ email, code });
      showToast('¡Cuenta activada! Entrando…');
      setTimeout(() => { window.location.href = data.redirect || '/feed.php'; }, 800);
    } catch (err) {
      setInvalid('field-verif-codigo', true);
      showToast(err.message || 'No se pudo activar la cuenta.');
      botonEnviar.disabled = false;
    }
  });

  // ---- Reenviar el correo ----
  botonReenviar.addEventListener('click', async () => {
    const email = emailInput.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setInvalid('field-verif-email', true);
      showToast('Escribí tu correo para poder reenviarlo.');
      emailInput.focus();
      return;
    }

    botonReenviar.disabled = true;
    const textoOriginal = botonReenviar.textContent;

    try {
      const data = await api.resendVerification(email);
      showToast(data.mensaje || 'Te mandamos un correo nuevo.');
      cuentaRegresiva(60);
    } catch (err) {
      showToast(err.message || 'No se pudo reenviar el correo.');
      mostrarDatosDesarrollo(err.data);
      // Si el servidor pidió esperar, respetamos ese tiempo.
      const espera = Number(err.data && err.data.espera) || 0;
      if (espera > 0) cuentaRegresiva(espera);
      else botonReenviar.textContent = textoOriginal, botonReenviar.disabled = false;
    }

    function cuentaRegresiva(segundos) {
      let restantes = segundos;
      botonReenviar.textContent = `reenviar (${restantes}s)`;
      const reloj = setInterval(() => {
        restantes -= 1;
        if (restantes <= 0) {
          clearInterval(reloj);
          botonReenviar.textContent = textoOriginal;
          botonReenviar.disabled = false;
          return;
        }
        botonReenviar.textContent = `reenviar (${restantes}s)`;
      }, 1000);
    }
  });

  // Modo desarrollo: si el hosting no pudo mandar el mail y está activado
  // VERIF_MOSTRAR_CODIGO_SI_FALLA, el servidor devuelve el link y el código
  // para poder seguir probando sin correo.
  function mostrarDatosDesarrollo(data) {
    if (!data || !data.codigoDesarrollo) return;
    let caja = document.getElementById('verif-debug');
    if (!caja) {
      caja = document.createElement('div');
      caja.id = 'verif-debug';
      caja.className = 'verif-debug';
      verifForm.appendChild(caja);
    }
    caja.innerHTML =
      '<strong>Modo desarrollo (el correo no salió)</strong>' +
      'Código: ' + escapeHtml(data.codigoDesarrollo) + '<br>' +
      'Link: <a href="' + escapeAttr(data.linkDesarrollo || '#') + '">activar</a>';
    codigoInput.value = String(data.codigoDesarrollo).replace(/\D/g, '').slice(0, 6);
  }
}
