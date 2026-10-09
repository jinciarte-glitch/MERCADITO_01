// login.js — pestañas Iniciar sesión / Registrarme + validación del formulario.
// El tema claro/oscuro vive en theme.js (se carga antes que este archivo).

const tabs = document.querySelectorAll('.tab, .switch-line button');
const forms = { login: document.getElementById('login-form'), register: document.getElementById('register-form') };
const tabLogin = document.getElementById('tab-login');
const tabRegister = document.getElementById('tab-register');

function showForm(target){
  Object.entries(forms).forEach(([key, form]) => form.classList.toggle('active', key === target));
  tabLogin.classList.toggle('active', target === 'login');
  tabRegister.classList.toggle('active', target === 'register');
  tabLogin.setAttribute('aria-selected', target === 'login');
  tabRegister.setAttribute('aria-selected', target === 'register');
}

tabs.forEach(btn => btn.addEventListener('click', () => showForm(btn.dataset.target)));

// Mostrar / ocultar contraseña (ojito dentro del campo).
document.querySelectorAll('.pw-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    if(!input) return;
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    btn.classList.toggle('showing', mostrar);
    btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });
});

// La verificación de correo es real y vive en el servidor: al registrarse se
// manda un mail con un link y un código, y la persona termina el trámite en
// /verificar.php. Acá sólo redirigimos a esa pantalla.
function irAVerificar(email){
  window.location.href = '/verificar.php?email=' + encodeURIComponent(email || '');
}

// Checklist de requisitos de la contraseña de registro (se marca en vivo).
const regPasswordInput = document.getElementById('reg-password');
const pwRequirementsEl = document.getElementById('pw-requirements');

function passwordRules(pw){
  return {
    length:  pw.length >= 8,
    upper:   /[A-Z]/.test(pw),
    number:  /[0-9]/.test(pw),
    special: /[^A-Za-z0-9]/.test(pw),
  };
}

function updatePasswordChecklist(){
  const rules = passwordRules(regPasswordInput.value);
  Object.entries(rules).forEach(([rule, met]) => {
    const li = pwRequirementsEl.querySelector(`[data-rule="${rule}"]`);
    if(li) li.classList.toggle('met', met);
  });
  return rules;
}

regPasswordInput.addEventListener('input', updatePasswordChecklist);


// Login
document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const user = document.getElementById('login-username').value.trim();
  const pass = document.getElementById('login-password').value;
  setInvalid('field-login-user', user === '');
  setInvalid('field-login-pass', pass === '');
  if(user === '' || pass === '') return;

  const submitBtn = e.target.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  try{
    await api.login({ username: user, password: pass });
        showToast('¡Listo! Iniciando sesión…');
    setTimeout(() => { window.location.href = '/feed.php'; }, 700);
  }catch(err){
    // Usuario y contraseña bien, pero la cuenta nunca se activó: lo llevamos
    // a la pantalla de activación en lugar de dejarlo trabado acá.
    if(err.data && err.data.requiereVerificacion){
      showToast('Falta activar tu cuenta. Te llevamos a hacerlo.');
      setTimeout(() => irAVerificar(err.data.email), 1200);
      return;
    }
    showToast(err.message || 'No se pudo iniciar sesión.');
    submitBtn.disabled = false;
  }
});

// Registro
document.getElementById('register-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fullname = document.getElementById('fullname').value.trim();
  const age = document.getElementById('age').value;
  const username = document.getElementById('reg-username').value.trim();
  const email = document.getElementById('email').value.trim();
  const pass = document.getElementById('reg-password').value;
  const confirm = document.getElementById('confirm-password').value;

  let ok = true;

  setInvalid('field-fullname', fullname === ''); if(fullname === '') ok = false;
  const ageOk = age !== '' && Number(age) >= 13;
  setInvalid('field-age', !ageOk); if(!ageOk) ok = false;
  setInvalid('field-reg-user', username === ''); if(username === '') ok = false;

  // Correo: debe tener formato válido (algo@algo.algo). Vacío o mal escrito
  // marca el campo en rojo, igual que los demás.
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  setInvalid('field-email', !emailOk); if(!emailOk) ok = false;

  const passOk = Object.values(updatePasswordChecklist()).every(Boolean);
  setInvalid('field-reg-pass', !passOk); if(!passOk) ok = false;

  const confirmOk = confirm === pass && pass !== '';
  setInvalid('field-confirm', !confirmOk); if(!confirmOk) ok = false;

  if(!ok) return;

  const submitBtn = e.target.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  try{
    const data = await api.register({ fullname, age: Number(age), username, email, password: pass });
    // La cuenta se crea inactiva: no se entra al feed hasta confirmar el correo.
    showToast(data.mensaje || 'Te mandamos un correo para activar la cuenta.');
    setTimeout(() => irAVerificar(email), 1200);
  }catch(err){
    // El correo ya tenía una cuenta sin activar: se reenvió el mensaje.
    if(err.data && err.data.requiereVerificacion){
      showToast(err.message);
      setTimeout(() => irAVerificar(err.data.email || email), 1600);
      return;
    }
    showToast(err.message || 'No se pudo crear la cuenta.');
    submitBtn.disabled = false;
  }
});
