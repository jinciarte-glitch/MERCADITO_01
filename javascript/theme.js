// theme.js — modo claro/oscuro compartido por todas las páginas.
// Guarda la preferencia en localStorage y respeta el modo del sistema
// operativo si el usuario todavía no eligió nada.
(function(){
  const root = document.documentElement;
  const toggle = document.getElementById('theme-toggle');
  const STORAGE_KEY = 'mercadito-theme';

  function applyTheme(theme){
    if(theme === 'dark'){
      root.setAttribute('data-theme', 'dark');
      if(toggle) toggle.setAttribute('aria-label', 'Cambiar a modo claro');
    } else {
      root.removeAttribute('data-theme');
      if(toggle) toggle.setAttribute('aria-label', 'Cambiar a modo oscuro');
    }
    // Avisa al resto de la página (por ej. feed.js redibuja los avatares
    // porque su color de fondo depende del tema).
    document.dispatchEvent(new CustomEvent('mercadito:themechange', { detail:{ theme } }));
  }

  const saved = localStorage.getItem(STORAGE_KEY);
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  applyTheme(saved || (prefersDark ? 'dark' : 'light'));

  if(toggle){
    toggle.addEventListener('click', () => {
      const isDark = root.getAttribute('data-theme') === 'dark';
      const next = isDark ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem(STORAGE_KEY, next);
    });
  }

  window.mercaditoTheme = {
    current: () => root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
  };
})();
