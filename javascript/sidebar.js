// sidebar.js — columna derecha: "A quién seguir" + búsqueda de personas.
(function(){
  if (typeof api === 'undefined') return;

  const wtf = document.getElementById('who-to-follow');
  const searchInput = document.getElementById('sidebar-search');
  const searchResults = document.getElementById('sidebar-search-results');

  // Guardamos los últimos datos para poder re-dibujar al cambiar de tema
  // (los avatares generados usan los colores del tema actual).
  let lastSuggestions = [];
  let lastSearch = [];

  // ---------- A quién seguir ----------
  function suggestionRow(u){
    const row = document.createElement('div');
    row.className = 'wtf-item';
    const avatar = u.avatarUrl || avatarUrl(u.name);
    row.innerHTML = `
      <a href="usuario.php?id=${u.id}" class="wtf-user">
        <img class="avatar" src="${escapeAttr(avatar)}" alt="">
        <div class="wtf-meta">
          <span class="wtf-name">${escapeHtml(u.name)}</span>
          <span class="wtf-username">${u.username ? '@' + escapeHtml(u.username) : ''}</span>
        </div>
      </a>
      <button type="button" class="btn-primary wtf-follow">Seguir</button>`;
    const btn = row.querySelector('.wtf-follow');
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try{
        const res = await api.toggleFollow(u.id);
        if(res.following){
          btn.textContent = 'Siguiendo';
          btn.classList.remove('btn-primary');
          btn.classList.add('btn-secondary');
          lastSuggestions = lastSuggestions.filter(x => x.id !== u.id);
          setTimeout(() => row.remove(), 600);
        }
      }catch(e){
        showToast(e.message || 'No se pudo seguir.');
        btn.disabled = false;
      }
    });
    return row;
  }

  function renderSuggestions(){
    if(!wtf) return;
    wtf.innerHTML = '';
    if(lastSuggestions.length === 0){
      wtf.innerHTML = '<p class="wtf-empty">No hay sugerencias por ahora.</p>';
      return;
    }
    lastSuggestions.forEach(u => wtf.appendChild(suggestionRow(u)));
  }

  async function loadSuggestions(){
    if(!wtf) return;
    try{
      const data = await apiRequest('/api/users.php?suggest=1');
      lastSuggestions = data.users || [];
      renderSuggestions();
    }catch(_){ /* silencioso */ }
  }

  // ---------- Búsqueda de personas ----------
  function renderResults(){
    if(!lastSearch.length){
      searchResults.innerHTML = '<p class="side-search-empty">Sin resultados.</p>';
      searchResults.hidden = false;
      return;
    }
    searchResults.innerHTML = lastSearch.map(u => `
      <a href="usuario.php?id=${u.id}" class="side-search-item">
        <img class="avatar" src="${escapeAttr(u.avatarUrl || avatarUrl(u.name))}" alt="">
        <div class="wtf-meta">
          <span class="wtf-name">${escapeHtml(u.name)}</span>
          <span class="wtf-username">${u.username ? '@' + escapeHtml(u.username) : ''}</span>
        </div>
      </a>`).join('');
    searchResults.hidden = false;
  }

  let searchTimer;
  if(searchInput){
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim();
      clearTimeout(searchTimer);
      if(q === ''){ lastSearch = []; searchResults.hidden = true; searchResults.innerHTML = ''; return; }
      searchTimer = setTimeout(async () => {
        try{
          const data = await apiRequest('/api/users.php?q=' + encodeURIComponent(q));
          lastSearch = data.users || [];
          renderResults();
        }catch(_){ /* silencioso */ }
      }, 300);
    });
    document.addEventListener('click', (e) => {
      if(!e.target.closest('.side-search')){ searchResults.hidden = true; }
    });
  }

  // ---------- Al cambiar de tema, re-dibujar avatares generados ----------
  document.addEventListener('mercadito:themechange', () => {
    renderSuggestions();
    if(searchResults && !searchResults.hidden) renderResults();
  });

  loadSuggestions();
})();
