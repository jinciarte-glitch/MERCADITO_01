// nav.js — comportamiento compartido de la barra de navegación
// (botón "Cerrar sesión"). El tema claro/oscuro lo maneja theme.js.
(function () {
  const logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try { await api.logout(); } catch (_) { /* igual redirigimos */ }
      window.location.href = '/api/login.php';
    });
  }
})();
