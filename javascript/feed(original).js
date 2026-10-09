/* ============================================================
   feed.js — lógica de la página de red social.
   Usa api.js (capa de datos) y ui.js (toast / escapeHtml).
   ============================================================ */

const ICON_HEART = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>`;
const ICON_COMMENT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;

const state = { profile: null, posts: [] };

async function init(){
  try{
    state.profile = await api.getProfile();
    state.posts = await api.getPosts();
    renderProfile();
    renderComposerAvatar();
    renderPosts();
  }catch(err){
    showToast(err.message || 'Tu sesión expiró. Iniciá sesión de nuevo.');
    setTimeout(() => { window.location.href = '/api/login.php'; }, 1200);
  }
}

function myPostsCount(){
  return state.posts.filter(p => p.authorId === state.profile.id).length;
}

/* ---------------- PERFIL ---------------- */
function renderProfile(){
  const card = document.getElementById('profile-card');
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
        <label for="edit-avatar">URL de foto (opcional)</label>
        <input id="edit-avatar" type="url" placeholder="Vacío = se genera automáticamente" value="${p.avatarUrl ? escapeAttr(p.avatarUrl) : ''}">
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

  document.getElementById('profile-edit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('edit-name').value.trim();
    if(!name) return;
    const bio = document.getElementById('edit-bio').value.trim();
    const avatarInput = document.getElementById('edit-avatar').value.trim();
    const type = card.querySelector('input[name="profile-type"]:checked').value;

    try{
      state.profile = await api.updateProfile({ name, bio, avatarUrl: avatarInput || null, type });
	  window.location.reload(); //Recarga pagina para mostrar todo con cambios nuevos
      showToast('Perfil actualizado.');
    }catch(err){
      showToast(err.message || 'No se pudo actualizar el perfil.');
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

  return `
    <article class="card post-card" data-id="${post.id}">
      <div class="post-header">
        <img class="avatar avatar-md" src="${escapeAttr(postAuthorAvatar(post))}" alt="Foto de ${escapeAttr(post.authorName)}">
        <div class="post-header-meta">
          <span class="post-author">${escapeHtml(post.authorName)} ${badge}</span>
          <span class="post-time">${timeAgo(post.createdAt)}</span>
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
    list.innerHTML = `<div class="empty-state">Todavía no hay publicaciones. ¡Sé el primero en compartir algo!</div>`;
    return;
  }

  list.innerHTML = state.posts.map(postTemplate).join('');

  list.querySelectorAll('.post-card').forEach(card => {
    const id = card.dataset.id;
    card.querySelector('[data-action="like"]').addEventListener('click', () => handleLike(id));
    card.querySelector('[data-action="toggle-comments"]').addEventListener('click', () => toggleComments(card));
    card.querySelector('.comment-form').addEventListener('submit', (e) => handleComment(e, id));
  });
}

async function handleLike(postId){
  const card = document.querySelector(`.post-card[data-id="${postId}"]`);
  const wasOpen = card ? !card.querySelector('.comments-section').hidden : false;
  try{
    await api.toggleLike(postId);
    state.posts = await api.getPosts();
    renderPosts();
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
    state.posts = await api.getPosts();
    renderPosts();
    const card = document.querySelector(`.post-card[data-id="${postId}"]`);
    if(card) card.querySelector('.comments-section').hidden = false;
  }catch(err){
    showToast(err.message || 'No se pudo publicar el comentario.');
  }
}

/* ---------------- COMPOSER ---------------- */
document.getElementById('composer-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const textInput = document.getElementById('composer-text');
  const imageInput = document.getElementById('composer-image');
  const text = textInput.value.trim();
  if(!text) return;

  const imageUrl = imageInput.value.trim();
  if(imageUrl && !safeImage(imageUrl)){
    showToast('La URL de la imagen debe empezar con http:// o https://');
    return;
  }

  try{
    await api.createPost({ text, imageUrl });
    state.posts = await api.getPosts();
    textInput.value = '';
    imageInput.value = '';
    renderProfile();
    renderPosts();
    showToast('¡Publicación creada!');
  }catch(err){
    showToast(err.message || 'No se pudo crear la publicación.');
  }
});

/* ---------------- SESIÓN Y TEMA ---------------- */
document.getElementById('logout-btn').addEventListener('click', async () => {
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
