/* ============================================================
   feed.js — lógica de la página de red social.
   Usa api.js (capa de datos) y ui.js (toast / escapeHtml).
   ============================================================ */

const ICON_HEART = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>`;
const ICON_COMMENT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
const ICON_SHARE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`;
const ICON_SHARE_EXTERNAL = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>`;

const state = { profile: null, posts: [], geo: null, prefs: { radius: 25, mode: 'near', includeWithout: true } };

/* ---------------- ZONA (filtro por cercanía estilo Marketplace) ---------------- */
function zoneFilters(){
  if (state.geo && state.prefs.mode === 'near') {
    return {
      lat: state.geo.lat,
      lng: state.geo.lng,
      radius: state.prefs.radius,
      zone: 'near',
      includeWithout: state.prefs.includeWithout,
    };
  }
  return {};
}

async function loadPosts(){
  state.posts = await api.getPosts(zoneFilters());
  renderPosts();
}

function renderZoneUI(){
  const status = document.getElementById('zone-status');
  const btn = document.getElementById('zone-btn');
  const toggle = document.getElementById('zone-toggle');
  const radiusSel = document.getElementById('zone-radius');
  const includeChk = document.getElementById('zone-include-without');
  const composerLoc = document.getElementById('composer-loc');
  if (!status) return;

  if (radiusSel) radiusSel.value = String(state.prefs.radius);
  if (includeChk) includeChk.checked = state.prefs.includeWithout;

  if (state.geo) {
    const where = state.geo.place ? ` · ${state.geo.place}` : '';
    if (state.prefs.mode === 'near') {
      status.textContent = `Mostrando lo cercano (radio ${state.prefs.radius} km)${where}.`;
      if (toggle) { toggle.hidden = false; toggle.textContent = 'Ver todo'; }
    } else {
      status.textContent = `Ubicación activa${where}. Estás viendo todo.`;
      if (toggle) { toggle.hidden = false; toggle.textContent = 'Solo cercanos'; }
    }
    if (btn) btn.textContent = 'Actualizar ubicación';
  } else {
    status.textContent = 'Activá tu ubicación para ver lo de tu zona.';
    if (toggle) toggle.hidden = true;
    if (btn) btn.textContent = 'Usar mi ubicación';
  }

  if (composerLoc) {
    composerLoc.textContent = state.geo
      ? `Tu zona actual${state.geo.place ? ` (${state.geo.place})` : ''} se adjuntará al publicar.`
      : 'Si activás tu ubicación, tus publicaciones saldrán con zona.';
  }
}

function bindZoneUI(){
  const btn = document.getElementById('zone-btn');
  const toggle = document.getElementById('zone-toggle');
  const radiusSel = document.getElementById('zone-radius');
  const includeChk = document.getElementById('zone-include-without');

  if (radiusSel) radiusSel.addEventListener('change', async () => {
    state.prefs.radius = Math.min(500, Math.max(1, parseFloat(radiusSel.value) || 25));
    Geo.savePrefs(state.prefs);
    renderZoneUI();
    try { await loadPosts(); } catch (err) { showToast(err.message || 'No se pudo filtrar por zona.'); }
  });

  if (includeChk) includeChk.addEventListener('change', async () => {
    state.prefs.includeWithout = includeChk.checked;
    Geo.savePrefs(state.prefs);
    try { await loadPosts(); } catch (err) { showToast(err.message || 'No se pudo filtrar por zona.'); }
  });

  if (toggle) toggle.addEventListener('click', async () => {
    if (!state.geo) return;
    state.prefs.mode = state.prefs.mode === 'near' ? 'all' : 'near';
    Geo.savePrefs(state.prefs);
    renderZoneUI();
    try { await loadPosts(); } catch (err) { showToast(err.message || 'No se pudo cambiar el filtro.'); }
  });

  if (btn) btn.addEventListener('click', async () => {
    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = 'Localizando…';
    try {
      const diag = await Geo.diagnose().catch(() => null);
      if (diag && !diag.supported) {
        showToast('Tu navegador no soporta geolocalización.');
        return;
      }
      if (diag && diag.secure === false) {
        showToast('Abrí el sitio por HTTPS o localhost para usar la ubicación.');
        return;
      }
      // GPS primero; en PC sin GPS o con permiso denegado cae a IP.
      const { loc, method } = await Geo.ensure({ timeout: 15000, allowIP: true });
      state.geo = loc;
      state.prefs.mode = 'near';
      Geo.savePrefs(state.prefs);
      renderZoneUI();
      await loadPosts();
      if (method === 'ip') {
        showToast(loc.place ? `Zona aproximada: ${loc.place}. Activá el GPS para más precisión.` : 'Zona aproximada activada (por internet).');
      } else {
        showToast(loc.place ? `Ubicación lista: ${loc.place}` : 'Ubicación activada.');
      }
    } catch (err) {
      const diag = await Geo.diagnose().catch(() => null);
      if (diag && diag.permission === 'denied') {
        showToast('Bloqueaste la ubicación. Tocá el candado/ícono de la barra de dirección → Permisos → Ubicación → Permitir, y reintentá.');
        const st = document.getElementById('zone-status');
        if (st) st.textContent = 'Ubicación bloqueada en el navegador. Permití el acceso desde el candado de la barra de dirección.';
      } else {
        showToast(err.message || 'No se pudo obtener tu ubicación.');
      }
    } finally {
      btn.disabled = false;
      btn.textContent = original;
      renderZoneUI();
    }
  });
}

// Pide el permiso automáticamente al entrar (estilo Marketplace),
// sin bloquear el feed: si falla, queda el botón como alternativa.
// Solo lo intenta una vez por sesión para no insistir.
let zoneAutoTried = false;
async function autoRequestZone(){
  try {
    if (zoneAutoTried || state.geo) return;
    zoneAutoTried = true;
    let skip = false;
    try { skip = sessionStorage.getItem('mercadito-geo-asked') === '1'; } catch (_) {}
    if (skip) return;
    try { sessionStorage.setItem('mercadito-geo-asked', '1'); } catch (_) {}
    const diag = await Geo.diagnose().catch(() => null);
    if (!diag || !diag.supported || diag.secure === false || diag.permission === 'denied') return;
    state.geo = await Geo.request({ timeout: 12000 });
    state.prefs.mode = 'near';
    Geo.savePrefs(state.prefs);
    renderZoneUI();
    await loadPosts();
  } catch (_) {
    // Silencioso: el usuario puede activar con el botón cuando quiera.
  }
}

async function init(){
  try{
    state.profile = await api.getProfile();
    state.prefs = Geo.getPrefs();
    state.geo = Geo.getCached();
    bindZoneUI();
    renderZoneUI();
    renderProfile();
    renderComposerAvatar();
    await loadPosts();
    // Pedir permiso automáticamente (no bloquea si falla).
    autoRequestZone();
  }catch(err){
    showToast(err.message || 'Tu sesión expiró. Iniciá sesión de nuevo.');
    setTimeout(() => { window.location.href = '/api/login.php'; }, 1200);
  }
}

// Si el nombre del lugar llega después (2do plano), refrescar etiquetas.
window.addEventListener('mercadito:geoplace', (e) => {
  if (e && e.detail && typeof e.detail.lat === 'number') {
    state.geo = e.detail;
    renderZoneUI();
  }
});

function myPostsCount(){
  return state.posts.filter(p => p.authorId === state.profile.id).length;
}

/* ---------------- PERFIL ---------------- */
function renderProfile(){
  const card = document.getElementById('profile-card');
  if(!card) return; // el feed ya no muestra la tarjeta de perfil (se edita desde el perfil)
  const src = state.profile.avatarUrl || avatarUrl(state.profile.name);
  const isEmprendedor = state.profile.type === 'emprendedor';

  card.innerHTML = `
    <div class="profile-avatar-wrap">
      <img class="avatar avatar-lg" src="${escapeAttr(src)}" alt="Foto de ${escapeAttr(state.profile.name)}">
    </div>
    <p class="profile-name">${escapeHtml(state.profile.name)} ${isEmprendedor ? '<span class="badge">Emprendedor/a</span>' : ''}</p>
    <p class="profile-bio">${escapeHtml(state.profile.bio || 'Todavía no escribiste tu bio.')}</p>
    <div class="profile-stats">
      <div class="profile-stat"><strong>${myPostsCount()}</strong><span>Publicaciones</span></div>
    </div>
    <button type="button" class="btn-secondary" id="edit-profile-btn" style="width:100%;">Editar perfil</button>
    <a href="usuario.php?id=${state.profile.id}" class="btn-secondary" style="width:100%; display:block; text-align:center; text-decoration:none; margin-top:8px;">Ver mi perfil</a>
  `;

  document.getElementById('edit-profile-btn').addEventListener('click', showProfileEditForm);
}

function showProfileEditForm(){
  const card = document.getElementById('profile-card');
  const p = state.profile;

  card.innerHTML = `
    <form class="profile-edit-form" id="profile-edit-form">
      <div class="field">
        <label for="edit-name">Nombre</label>
        <input id="edit-name" type="text" maxlength="60" required value="${escapeAttr(p.name)}">
      </div>
      <div class="field">
        <label for="edit-bio">Bio</label>
        <textarea id="edit-bio" rows="3" maxlength="160">${escapeHtml(p.bio || '')}</textarea>
      </div>
      <div class="field">
        <label for="edit-avatar">Foto de perfil (opcional)</label>
        <div style="display:flex; align-items:center; gap:10px;">
          <label for="edit-avatar" class="icon-upload-btn" title="Subir foto" aria-label="Subir foto">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <path d="M21 15l-5-5L5 21"/>
            </svg>
            <span>Elegir foto</span>
          </label>
          <input id="edit-avatar" type="file" name="imagen" accept="image/*" class="file-hidden">
          <span class="file-chosen" id="edit-avatar-name"></span>
        </div>
        <label class="profile-avatar-remove" style="display:flex; align-items:center; gap:6px; margin-top:6px; font-weight:400;">
          <input type="checkbox" id="edit-avatar-remove"> Quitar foto (usar avatar generado)
        </label>
      </div>
      <div class="field">
        <label>Tipo de perfil</label>
        <div class="profile-type-row">
          <label class="profile-type-option ${p.type === 'cliente' ? 'active' : ''}" data-value="cliente">
            <input type="radio" name="profile-type" value="cliente" ${p.type === 'cliente' ? 'checked' : ''}> Cliente
          </label>
          <label class="profile-type-option ${p.type === 'emprendedor' ? 'active' : ''}" data-value="emprendedor">
            <input type="radio" name="profile-type" value="emprendedor" ${p.type === 'emprendedor' ? 'checked' : ''}> Emprendedor/a
          </label>
        </div>
      </div>
      <div class="profile-edit-actions">
        <button type="button" class="btn-secondary" id="cancel-edit-btn">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  `;

  card.querySelectorAll('.profile-type-option').forEach(opt => {
    opt.addEventListener('click', () => {
      card.querySelectorAll('.profile-type-option').forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
    });
  });

  document.getElementById('cancel-edit-btn').addEventListener('click', renderProfile);

  // Muestra el nombre del archivo elegido al lado del botón de foto.
  bindFileName('edit-avatar', 'edit-avatar-name');

  document.getElementById('profile-edit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('edit-name').value.trim();
    if(!name) return;
    const bio = document.getElementById('edit-bio').value.trim();
    const avatarFile = document.getElementById('edit-avatar').files[0];
    const removeAvatar = document.getElementById('edit-avatar-remove').checked;
    const type = card.querySelector('input[name="profile-type"]:checked').value;

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalLabel = submitBtn.textContent;
    submitBtn.disabled = true;

    try{
      // Por defecto, no tocamos la foto actual.
      let avatarUrl = state.profile.avatarUrl;

      if(removeAvatar){
        avatarUrl = null; // vuelve al avatar generado automáticamente
      }else if(avatarFile){
        submitBtn.textContent = 'Subiendo foto…';
        avatarUrl = await api.uploadImage(avatarFile);
      }

      submitBtn.textContent = 'Guardando…';
      state.profile = await api.updateProfile({ name, bio, avatarUrl, type });
      showToast('Perfil actualizado.');
      renderProfile();
      renderComposerAvatar();
    }catch(err){
      showToast(err.message || 'No se pudo actualizar el perfil.');
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
}

function renderComposerAvatar(){
  const img = document.getElementById('composer-avatar');
  img.src = state.profile.avatarUrl || avatarUrl(state.profile.name);
  img.alt = `Foto de ${state.profile.name}`;
}

/* ---------------- FEED ---------------- */
function postAuthorAvatar(post){
  return post.authorAvatar || avatarUrl(post.authorName);
}

function safeImage(url){
  return /^https?:\/\//i.test(url || '') ? url : null;
}

function postTemplate(post){
  const liked = post.likedByMe;
  const badge = post.authorType === 'emprendedor' ? '<span class="badge">Emprendedor/a</span>':'<span class="badge badge-cliente">Cliente</span>';;
  const image = safeImage(post.imageUrl);
  const dist = Geo.formatDistance(post.distanceKm);
  const locLine = (post.placeName || dist)
    ? `<span class="post-loc">📍 ${escapeHtml(post.placeName || 'Cerca tuyo')}${dist ? ` · ${escapeHtml(dist)}` : ''}</span>`
    : '';

  return `
    <article class="card post-card" data-id="${post.id}">
      <div class="post-header">
        <a href="usuario.php?id=${post.authorId}" class="post-author-link">
          <img class="avatar avatar-md" src="${escapeAttr(postAuthorAvatar(post))}" alt="Foto de ${escapeAttr(post.authorName)}">
        </a>
        <div class="post-header-meta">
          <a href="usuario.php?id=${post.authorId}" class="post-author-link">
            <span class="post-author">${escapeHtml(post.authorName)} ${badge}</span>
          </a>
          <span class="post-time">${timeAgo(post.createdAt)}${locLine ? ` · ${locLine}` : ''}</span>
        </div>
      </div>
      <p class="post-text">${escapeHtml(post.text)}</p>
      ${image ? `<img class="post-image" src="${escapeAttr(image)}" alt="" loading="lazy">` : ''}
      <div class="post-actions">
        <button type="button" class="btn-icon like-btn ${liked ? 'active' : ''}" data-action="like" aria-pressed="${liked}">
          ${ICON_HEART}<span>${post.likeCount}</span>
        </button>
        <button type="button" class="btn-icon" data-action="toggle-comments">
          ${ICON_COMMENT}<span>${post.comments.length}</span>
        </button>
        <button type="button" class="btn-icon share-btn ${post.sharedByMe ? 'active' : ''}" data-action="share" aria-pressed="${post.sharedByMe}">
          ${ICON_SHARE}<span>${post.shareCount || 0}</span>
        </button>
        <button type="button" class="btn-icon" data-action="share-external" title="Compartir esta publicación (redes, WhatsApp, copiar enlace)">
          ${ICON_SHARE_EXTERNAL}<span>Compartir</span>
        </button>
      </div>
      <div class="comments-section" hidden>
        <div class="comments-list">
          ${post.comments.map(commentTemplate).join('')}
        </div>
        <form class="comment-form">
          <input type="text" placeholder="Escribí un comentario…" maxlength="240" required>
          <button type="submit" class="btn-secondary">Comentar</button>
        </form>
      </div>
    </article>
  `;
}

function commentTemplate(comment){
  return `
    <div class="comment">
      <img class="avatar avatar-sm" src="${escapeAttr(avatarUrl(comment.authorName))}" alt="">
      <div class="comment-bubble">
        <span class="comment-author">${escapeHtml(comment.authorName)}</span><span class="comment-time">${timeAgo(comment.createdAt)}</span>
        <p class="comment-text">${escapeHtml(comment.text)}</p>
      </div>
    </div>
  `;
}

function renderPosts(){
  const list = document.getElementById('posts-list');

  if(state.posts.length === 0){
    list.innerHTML = state.geo && state.prefs.mode === 'near'
      ? `<div class="empty-state">No hay publicaciones en tu zona (radio ${escapeHtml(String(state.prefs.radius))} km). Probá ampliar el radio o ser el primero en publicar cerca tuyo.</div>`
      : `<div class="empty-state">Todavía no hay publicaciones. ¡Sé el primero en compartir algo!</div>`;
    return;
  }

  list.innerHTML = state.posts.map(postTemplate).join('');

  list.querySelectorAll('.post-card').forEach(card => {
    const id = card.dataset.id;
    card.querySelector('[data-action="like"]').addEventListener('click', () => handleLike(id));
    card.querySelector('[data-action="toggle-comments"]').addEventListener('click', () => toggleComments(card));
    card.querySelector('[data-action="share"]').addEventListener('click', () => handleShare(id));
    card.querySelector('[data-action="share-external"]').addEventListener('click', (e) => { e.stopPropagation(); handleExternalShare(id); });
    card.querySelector('.comment-form').addEventListener('submit', (e) => handleComment(e, id));

    // Ir a la publicación individual (post.php) al clickear la tarjeta,
    // salvo que el click haya sido sobre un botón, link o el formulario
    // de comentarios (esos ya tienen su propia acción).
    card.addEventListener('click', (e) => {
      if(e.target.closest('a, button, input, textarea, form')) return;
      window.location.href = `post.php?id=${id}`;
    });
  });
}

async function handleShare(postId){
  const card = document.querySelector(`.post-card[data-id="${postId}"]`);
  const wasOpen = card ? !card.querySelector('.comments-section').hidden : false;
  try{
    const res = await api.toggleShare(postId);
    showToast(res.shared ? 'Compartido en tu perfil ✔' : 'Se quitó de tus compartidos.');
    await loadPosts();
    if(wasOpen){
      const newCard = document.querySelector(`.post-card[data-id="${postId}"]`);
      if(newCard) newCard.querySelector('.comments-section').hidden = false;
    }
  }catch(err){
    showToast(err.message || 'No se pudo compartir.');
  }
}

async function handleLike(postId){
  const card = document.querySelector(`.post-card[data-id="${postId}"]`);
  const wasOpen = card ? !card.querySelector('.comments-section').hidden : false;
  try{
    await api.toggleLike(postId);
    await loadPosts();
    if(wasOpen){
      const newCard = document.querySelector(`.post-card[data-id="${postId}"]`);
      if(newCard) newCard.querySelector('.comments-section').hidden = false;
    }
  }catch(err){
    showToast(err.message || 'No se pudo actualizar el like.');
  }
}

function toggleComments(card){
  const section = card.querySelector('.comments-section');
  section.hidden = !section.hidden;
  if(!section.hidden){
    section.querySelector('input').focus();
  }
}

async function handleComment(e, postId){
  e.preventDefault();
  const input = e.target.querySelector('input');
  const text = input.value.trim();
  if(!text) return;

  try{
    await api.addComment(postId, text);
    await loadPosts();
    const card = document.querySelector(`.post-card[data-id="${postId}"]`);
    if(card) card.querySelector('.comments-section').hidden = false;
  }catch(err){
    showToast(err.message || 'No se pudo publicar el comentario.');
  }
}

/* ---------------- NUEVO: compartir la publicación afuera de la app ----------------
   Distinto del botón "share" de arriba (que es un repost dentro del feed).
   Este usa el share sheet nativo del dispositivo (WhatsApp, Instagram, etc.)
   cuando está disponible, y si no, copia el enlace al portapapeles.
*/
async function handleExternalShare(postId){
  const post = state.posts.find(p => String(p.id) === String(postId));
  if(!post) return;

  const basePath = location.pathname.substring(0, location.pathname.lastIndexOf('/') + 1);
  const url = `${location.origin}${basePath}post.php?id=${postId}`;
  const shareData = {
          title: `Compartiendo publicación de ${post.authorName || 'Mercadito'}`,
          text: `¡MIRÁ ESTA PUBLICACIÓN DE ${post.authorName || 'NUESTRO CLIENTE'} EN Mercadito! \n"${post.text || ''}"\n`,
          url
  };

  if(navigator.share){
    try{
      await navigator.share(shareData);
    }catch(err){
      // el usuario cerró el diálogo nativo sin elegir nada: no es un error real
    }
    return;
  }

  if(navigator.clipboard && navigator.clipboard.writeText){
    try{
      await navigator.clipboard.writeText(url);
      showToast('Enlace copiado al portapapeles.');
    }catch(err){
      showToast('No se pudo copiar el enlace.');
    }
    return;
  }

  showToast('Copiá esta URL para compartir: ' + url);
}

/* ---------------- COMPOSER ---------------- */
// Muestra el nombre del archivo elegido junto a un botón-ícono de subida.
function bindFileName(inputId, labelId){
  const input = document.getElementById(inputId);
  const label = document.getElementById(labelId);
  if(!input || !label) return;
  input.addEventListener('change', () => {
    const f = input.files[0];
    label.textContent = f ? f.name : '';
    label.classList.toggle('has-file', !!f);
  });
}

// Ícono de imagen del compositor.
bindFileName('composer-image', 'composer-file-name');

document.getElementById('composer-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const textInput = document.getElementById('composer-text');
  const imageInput = document.getElementById('composer-image');
  const text = textInput.value.trim();
  if(!text) return;

  const file = imageInput.files[0];
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalLabel = submitBtn.textContent;
  submitBtn.disabled = true;

  try{
    let imageUrl = null;
    if(file){
      submitBtn.textContent = 'Subiendo imagen…';
      imageUrl = await api.uploadImage(file);
    }

    // La ubicación se adjunta sola: se usa la guardada y, si no hay,
    // se intenta obtenerla en el momento (el click en Publicar vale
    // como gesto para el permiso). Si falla, SE PUBLICA IGUAL sin zona.
    let g = Geo.getCached();
    if (!g) {
      try {
        submitBtn.textContent = 'Obteniendo tu zona…';
        g = await Geo.request({ timeout: 8000 });
        state.geo = g;
        state.prefs.mode = 'near';
        Geo.savePrefs(state.prefs);
        renderZoneUI();
      } catch (_) { g = null; }
      submitBtn.textContent = 'Publicando…';
    }
    const res = await api.createPost({
      text,
      imageUrl,
      lat: g ? g.lat : null,
      lng: g ? g.lng : null,
      place_name: g ? g.place : null,
    });
    await loadPosts();
    textInput.value = '';
    imageInput.value = '';
    const nameLabel = document.getElementById('composer-file-name');
    if(nameLabel){ nameLabel.textContent = ''; nameLabel.classList.remove('has-file'); }
    renderProfile();
    // Si el filtro estricto de zona oculta el post recién creado
    // (no tiene ubicación), avisar en vez de parecer que falló.
    const visible = !res || !res.id || state.posts.some(p => String(p.id) === String(res.id));
    if (!visible) {
      showToast('¡Publicada! Quedó fuera de tu filtro estricto de zona (no tiene ubicación).');
    } else if (res && res.withGeo) {
      showToast('¡Publicación creada con tu zona! 📍');
    } else {
      showToast('¡Publicación creada! Activá tu ubicación para que salga con zona.');
    }
  }catch(err){
    showToast(err.message || 'No se pudo crear la publicación.');
  }finally{
    submitBtn.disabled = false;
    submitBtn.textContent = originalLabel;
  }
});

/* ---------------- SESIÓN Y TEMA ---------------- */
const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) logoutBtn.addEventListener('click', async () => {
  try{ await api.logout(); }catch(_){ /* igual redirigimos */ }
  window.location.href = '/api/login.php';
});

document.addEventListener('mercadito:themechange', () => {
  if(!state.profile) return; // todavía no cargó nada
  renderComposerAvatar();
  if(!document.getElementById('profile-edit-form')) renderProfile();
  renderPosts();
});

init();