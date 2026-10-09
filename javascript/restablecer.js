// restablecer.js — formulario para elegir la contraseña nueva.
// Si el token del link no era válido, restablecer.php ni siquiera pinta este
// formulario (ver el bloque "Ese link no sirve" en el PHP), así que acá
// siempre asumimos que hay un formulario real en la página.

const restablecerForm = document.getElementById('restablecer-form');

if (restablecerForm) {
  const tokenInput = document.getElementById('restablecer-token');
  const passwordInput = document.getElementById('restablecer-password');
  const confirmInput = document.getElementById('restablecer-confirm');
  const requirementsEl = document.getElementById('pw-requirements');
  const listo = document.getElementById('restablecer-listo');

  // Mostrar / ocultar contraseña (mismo comportamiento que login.js).
  document.querySelectorAll('.pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const mostrar = input.type === 'password';
      input.type = mostrar ? 'text' : 'password';
      btn.classList.toggle('showing', mostrar);
      btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
  });

  function passwordRules(pw) {
    return {
      length: pw.length >= 8,
      upper: /[A-Z]/.test(pw),
      number: /[0-9]/.test(pw),
      special: /[^A-Za-z0-9]/.test(pw),
    };
  }

  function actualizarChecklist() {
    const rules = passwordRules(passwordInput.value);
    Object.entries(rules).forEach(([rule, met]) => {
      const li = requirementsEl.querySelector(`[data-rule="${rule}"]`);
      if (li) li.classList.toggle('met', met);
    });
    return rules;
  }
  passwordInput.addEventListener('input', actualizarChecklist);

  restablecerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const password = passwordInput.value;
    const confirm = confirmInput.value;

    const passOk = Object.values(actualizarChecklist()).every(Boolean);
    setInvalid('field-restablecer-pass', !passOk);

    const confirmOk = confirm === password && password !== '';
    setInvalid('field-restablecer-confirm', !confirmOk);

    if (!passOk || !confirmOk) return;

    const submitBtn = restablecerForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      await api.resetPassword({ token: tokenInput.value, password });
      restablecerForm.hidden = true;
      listo.hidden = false;
    } catch (err) {
      showToast(err.message || 'No se pudo cambiar la contraseña.');
      submitBtn.disabled = false;
    }
  });
}
