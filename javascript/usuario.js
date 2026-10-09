// usuario.js — perfil público estilo Twitter/X: banner, avatar grande,
// datos alineados a la izquierda y las publicaciones en lista vertical.

const profileId = document.currentScript.dataset.profileId;

const HEART   = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>`;
const COMMENT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
const SHARE   = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`;
const SHARE_EXTERNAL = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>`;
const state = { profile: null, currentTab: 'posts', cache: {} };

function safeImage(url){ return /^https?:\/\//i.test(url || '') ? url : null; }

const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
function joinedText(ms){
  if(!ms) return '';
  const d = new Date(ms);
  return 'Se unió en ' + MESES[d.getMonth()] + ' de ' + d.getFullYear();
}

// ---------- Cabecera ----------
function renderHeader(p){
  document.getElementById('tw-avatar').src = p.avatarUrl || avatarUrl(p.name); //pfp
  document.getElementById('tw-name').innerHTML = escapeHtml(p.name) +
    (p.type === 'emprendedor' ? ' <span class="badge">Emprendedor/a</span>' : '');
  document.getElementById('tw-username').textContent = p.username ? '@' + p.username : '';
  document.getElementById('tw-bio').textContent = p.bio || '';
  document.getElementById('tw-bio').style.display = p.bio ? 'block' : 'none';
  document.getElementById('tw-joined').textContent = joinedText(p.joinedAt);
  document.getElementById('tw-following').textContent = p.followingCount;
  document.getElementById('tw-followers').textContent = p.followerCount;
  document.getElementById('tw-likes').textContent = p.likesReceived;

  const actions = document.getElementById('tw-actions');
  actions.innerHTML = '';

  // Pestaña privada "Me gusta": solo aparece en tu propio perfil.
  const tabs = document.querySelector('.tw-tabs');
  let likedTab = document.getElementById('tw-tab-liked');
  if(p.isMe && !likedTab){
    likedTab = document.createElement('button');
    likedTab.type = 'button';
    likedTab.id = 'tw-tab-liked';
    likedTab.className = 'tw-tab';
    likedTab.dataset.tab = 'liked';
    likedTab.textContent = 'Me gusta';
    likedTab.addEventListener('click', () => loadTab('liked'));
    tabs.appendChild(likedTab);
  } else if(!p.isMe && likedTab){
    likedTab.remove();
  }
  if(p.isMe){
    const edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'btn-secondary tw-action-btn';
    edit.textContent = 'Editar perfil';
    edit.addEventListener('click', openEdit);
    actions.appendChild(edit);
  } else {
    const btn = document.createElement('button');
    btn.type = 'button';
    setFollowBtn(btn, p.isFollowing);
    btn.addEventListener('click', () => toggleFollow(btn));
    actions.appendChild(btn);
  }
}

function setFollowBtn(btn, following){
  btn.className = 'tw-action-btn ' + (following ? 'btn-secondary tw-following' : 'btn-primary');
  btn.textContent = following ? 'Siguiendo' : 'Seguir';
}

async function toggleFollow(btn){
  btn.disabled = true;
  try{
    const res = await api.toggleFollow(state.profile.id);
    setFollowBtn(btn, res.following);
    document.getElementById('tw-followers').textContent = res.followerCount;
    state.profile.isFollowing = res.following;
  }catch(e){
    showToast(e.message || 'No se pudo actualizar.');
  }finally{
    btn.disabled = false;
  }
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

// ---------- Publicaciones (lista vertical, como el feed) ----------
function postCard(post){
  const image = safeImage(post.imageUrl);
  const badge = post.authorType === 'emprendedor'
    ? '<span class="badge">Emprendedor/a</span>'
    : '<span class="badge badge-cliente">Cliente</span>';

  return `
    <article class="card post-card" data-id="${post.id}">
      <div class="post-header">
        <a href="usuario.php?id=${post.authorId}" class="post-author-link">
          <img class="avatar avatar-md" src="${escapeAttr(post.authorAvatar || avatarUrl(post.authorName))}" alt="">
        </a>
        <div class="post-header-meta">
          <a href="usuario.php?id=${post.authorId}" class="post-author-link">
            <span class="post-author">${escapeHtml(post.authorName)} ${badge}</span>
          </a>
          <span class="post-time">${timeAgo(post.createdAt)}</span>
        </div>
      </div>
      <p class="post-text">${escapeHtml(post.text)}</p>
      ${image ? `<img class="post-image" src="${escapeAttr(image)}" alt="" loading="lazy">` : ''}
     <div class="post-actions">
        <button type="button" class="btn-icon like-btn ${post.likedByMe ? 'active' : ''}" data-action="like" aria-pressed="${!!post.likedByMe}">${HEART}<span>${post.likeCount || 0}</span></button>
        <button type="button" class="btn-icon" data-action="toggle-comments">${COMMENT}<span>${(post.comments || []).length}</span></button>
        <button type="button" class="btn-icon share-btn ${post.sharedByMe ? 'active' : ''}" data-action="share" aria-pressed="${!!post.sharedByMe}">${SHARE}<span>${post.shareCount || 0}</span></button>
        <!-- NUEVO BOTÓN A CONTINUACIÓN -->
        <button type="button" class="btn-icon" data-action="share-external" title="Compartir esta publicación">${SHARE_EXTERNAL}<span>Compartir</span></button>
      </div>
      <div class="comments-section" hidden>
        <div class="comments-list">
          ${(post.comments || []).map(commentTemplate).join('')}
        </div>
        <form class="comment-form">
          <input type="text" placeholder="Escribí un comentario…" maxlength="240" required>
          <button type="submit" class="btn-secondary">Comentar</button>
        </form>
      </div>
    </article>`;
}

function renderList(posts){
  const list = document.getElementById('tw-list');
  const empty = document.getElementById('tw-empty');
  if(!posts.length){
    list.innerHTML = '';
    empty.style.display = 'block';
    empty.textContent = state.currentTab === 'posts'
      ? 'Todavía no publicó nada.'
      : state.currentTab === 'shared'
        ? 'Todavía no compartió nada.'
        : 'Todavía no le diste me gusta a nada.';
    return;
  }
  empty.style.display = 'none';
  list.innerHTML = posts.map(postCard).join('');

  list.querySelectorAll('.post-card').forEach(card => {
    const id = card.dataset.id;
    card.querySelector('[data-action="like"]').addEventListener('click', () => handleLike(id));
    card.querySelector('[data-action="toggle-comments"]').addEventListener('click', () => toggleComments(card));
    card.querySelector('[data-action="share"]').addEventListener('click', () => handleShare(id));
    card.querySelector('.comment-form').addEventListener('submit', (e) => handleComment(e, id));
    card.addEventListener('click', (e) => {
  if(e.target.closest('a, button, input, textarea, form')) return;
  window.location.href = `post.php?id=${id}`;
});
    
    // EVENTO PARA BOTÓN COMPARTIR
    const shareExtBtn = card.querySelector('[data-action="share-external"]');
    if (shareExtBtn) {
      shareExtBtn.addEventListener('click', () => {
        const post = posts.find(p => p.id == id);
        handleExternalShare(post);
      });
    }
      
  }); // <-- Cierra el forEach
} // <-- Cierra renderList
                                           
                                           
function toggleComments(card){
  const section = card.querySelector('.comments-section');
  section.hidden = !section.hidden;
  if(!section.hidden){
    section.querySelector('input').focus();
  }
}

// Vuelve a pedir los posts de la pestaña activa. Como dar like/compartir/comentar
// puede afectar otras pestañas (p. ej. "Me gusta" o "Compartidos"), invalidamos
// todo el cache y recargamos solo la pestaña visible.
async function refreshCurrentTab(){
  state.cache = {};
  let posts;
  if(state.currentTab === 'posts')       posts = await api.getUserPosts(state.profile.id);
  else if(state.currentTab === 'shared') posts = await api.getSharedPosts(state.profile.id);
  else                                    posts = await api.getLikedPosts(state.profile.id);
  state.cache[state.currentTab] = posts;
  return posts;
}

// Mantiene abierta la sección de comentarios del post tras recargar la lista
// (si ya estaba abierta), igual que en el feed.
async function reloadKeepingOpen(postId, wasOpen){
  renderList(await refreshCurrentTab());
  if(wasOpen){
    const newCard = document.querySelector(`.post-card[data-id="${postId}"]`);
    if(newCard) newCard.querySelector('.comments-section').hidden = false;
  }
}

function isCommentsOpen(postId){
  const card = document.querySelector(`.post-card[data-id="${postId}"]`);
  return card ? !card.querySelector('.comments-section').hidden : false;
}

async function handleLike(postId){
  const wasOpen = isCommentsOpen(postId);
  try{
    await api.toggleLike(postId);
    await reloadKeepingOpen(postId, wasOpen);
  }catch(err){
    showToast(err.message || 'No se pudo actualizar el like.');
  }
}

async function handleShare(postId){
  const wasOpen = isCommentsOpen(postId);
  try{
    const res = await api.toggleShare(postId);
    showToast(res.shared ? 'Compartido en tu perfil ✔' : 'Se quitó de tus compartidos.');
    await reloadKeepingOpen(postId, wasOpen);
  }catch(err){
    showToast(err.message || 'No se pudo compartir.');
  }
}

async function handleComment(e, postId){
  e.preventDefault();
  const input = e.target.querySelector('input');
  const text = input.value.trim();
  if(!text) return;

  try{
    await api.addComment(postId, text);
    await reloadKeepingOpen(postId, true);
  }catch(err){
    showToast(err.message || 'No se pudo publicar el comentario.');
  }
}

async function loadTab(tab){
  state.currentTab = tab;
  document.querySelectorAll('.tw-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));

  if(state.cache[tab]){ renderList(state.cache[tab]); return; }

  document.getElementById('tw-list').innerHTML = '';
  document.getElementById('tw-empty').style.display = 'none';
  try{
    let posts;
    if(tab === 'posts')       posts = await api.getUserPosts(state.profile.id);
    else if(tab === 'shared') posts = await api.getSharedPosts(state.profile.id);
    else                      posts = await api.getLikedPosts(state.profile.id);
    state.cache[tab] = posts;
    renderList(posts);
  }catch(e){
    showToast(e.message || 'No se pudieron cargar las publicaciones.');
  }
}

document.querySelectorAll('.tw-tab').forEach(t => {
  t.addEventListener('click', () => loadTab(t.dataset.tab));
});

// ---------- Modal de seguidores / seguidos ----------
async function openFollowList(type){
  const modal = document.getElementById('fl-modal');
  const list  = document.getElementById('fl-list');
  const empty = document.getElementById('fl-empty');
  document.getElementById('fl-title').textContent = type === 'following' ? 'Siguiendo' : 'Seguidores';
  list.innerHTML = '<p class="fl-empty" style="display:block">Cargando…</p>';
  empty.hidden = true;
  modal.hidden = false;

  try{
    const users = await api.getFollowList(state.profile.id, type);
    if(!users.length){
      list.innerHTML = '';
      empty.hidden = false;
      empty.textContent = type === 'following' ? 'Todavía no sigue a nadie.' : 'Todavía no tiene seguidores.';
      return;
    }
    list.innerHTML = users.map(u => `
      <a href="usuario.php?id=${u.id}" class="fl-item">
        <img class="avatar" src="${escapeAttr(u.avatarUrl || avatarUrl(u.name))}" alt="">
        <div class="wtf-meta">
          <span class="wtf-name">${escapeHtml(u.name)}${u.type === 'emprendedor' ? ' <span class="badge">Emprendedor/a</span>' : ''}</span>
          <span class="wtf-username">${u.username ? '@' + escapeHtml(u.username) : ''}</span>
        </div>
      </a>`).join('');
  }catch(e){
    list.innerHTML = '';
    empty.hidden = false;
    empty.textContent = e.message || 'No se pudo cargar la lista.';
  }
}
function closeFollowList(){ document.getElementById('fl-modal').hidden = true; }

document.getElementById('tw-following-btn').addEventListener('click', () => openFollowList('following'));
document.getElementById('tw-followers-btn').addEventListener('click', () => openFollowList('followers'));
document.getElementById('fl-close').addEventListener('click', closeFollowList);
document.getElementById('fl-backdrop').addEventListener('click', closeFollowList);
document.addEventListener('keydown', (e) => { if(e.key === 'Escape') closeFollowList(); });

// ---------- Editar mi perfil (desde el botón "Editar perfil") ----------
function openEdit(){
  const p = state.profile;
  document.querySelector('.tw-head').style.display = 'none';
  document.querySelector('.tw-tabs').style.display = 'none';
  document.getElementById('tw-list').style.display = 'none';
  document.getElementById('tw-empty').style.display = 'none';

  let box = document.getElementById('tw-edit');
  if(!box){
    box = document.createElement('div');
    box.id = 'tw-edit';
    box.className = 'tw-edit';
    document.getElementById('usuario-content').appendChild(box);
  }
  box.style.display = 'block';
  box.innerHTML = `
    <h2 class="tw-edit-title">Editar perfil</h2>
    <form id="tw-edit-form">
      <div class="field">
        <label for="e-name">Nombre</label>
        <input id="e-name" type="text" maxlength="60" required value="${escapeAttr(p.name)}">
      </div>
      <div class="field">
        <label for="e-bio">Bio</label>
        <textarea id="e-bio" rows="3" maxlength="160">${escapeHtml(p.bio || '')}</textarea>
      </div>
      <div class="field">
        <label>Foto de perfil (opcional)</label>
        <div style="display:flex; align-items:center; gap:10px;">
          <label for="e-avatar" class="icon-upload-btn" title="Subir foto">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <span>Elegir foto</span>
          </label>
          <input id="e-avatar" type="file" accept="image/*" class="file-hidden">
          <span class="file-chosen" id="e-avatar-name"></span>
        </div>
        <label style="display:flex; align-items:center; gap:6px; margin-top:6px; font-weight:400;">
          <input type="checkbox" id="e-avatar-remove"> Quitar foto (usar avatar generado)
        </label>
      </div>
      <div class="field">
        <label>Tipo de perfil</label>
        ${p.type === 'administrador' ? `
          <p style="margin:0;">Cuenta administrador.</p>
        ` : p.type === 'emprendedor' ? `
          <div class="profile-type-row">
            <label class="profile-type-option active" data-value="emprendedor">
              <input type="radio" name="e-type" value="emprendedor" checked> Emprendedor/a
            </label>
            <label class="profile-type-option" data-value="cliente">
              <input type="radio" name="e-type" value="cliente"> Cliente
            </label>
          </div>
        ` : `
          <input type="hidden" name="e-type" value="cliente">
          <p style="margin:0;">Cuenta cliente. <a href="solicitud-emprendedor.php">¿Querés vender? Pedí la certificación de emprendedor</a>.</p>
        `}
      </div>
      <div class="profile-edit-actions">
        <button type="button" class="btn-secondary" id="e-cancel">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>`;

  box.querySelectorAll('.profile-type-option').forEach(opt => {
    opt.addEventListener('click', () => {
      box.querySelectorAll('.profile-type-option').forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
    });
  });

  const fileInput = document.getElementById('e-avatar');
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    const lbl = document.getElementById('e-avatar-name');
    lbl.textContent = f ? f.name : '';
    lbl.classList.toggle('has-file', !!f);
  });

  document.getElementById('e-cancel').addEventListener('click', closeEdit);
  document.getElementById('tw-edit-form').addEventListener('submit', saveEdit);
}

function closeEdit(){
  const box = document.getElementById('tw-edit');
  if(box) box.style.display = 'none';
  document.querySelector('.tw-head').style.display = '';
  document.querySelector('.tw-tabs').style.display = '';
  document.getElementById('tw-list').style.display = '';
}

async function saveEdit(e){
  e.preventDefault();
  const name = document.getElementById('e-name').value.trim();
  if(!name) return;
  const bio  = document.getElementById('e-bio').value.trim();
  const file = document.getElementById('e-avatar').files[0];
  const remove = document.getElementById('e-avatar-remove').checked;
  const typeInput = document.querySelector('input[name="e-type"]:checked') || document.querySelector('input[name="e-type"]');
  const type = typeInput ? typeInput.value : 'cliente';
  const btn = e.target.querySelector('button[type="submit"]');
  const original = btn.textContent;
  btn.disabled = true;

  try{
    let avatarUrl = state.profile.avatarUrl;
    if(remove){ avatarUrl = null; }
    else if(file){ btn.textContent = 'Subiendo foto…'; avatarUrl = await api.uploadImage(file); }

    btn.textContent = 'Guardando…';
    await api.updateProfile({ name, bio, avatarUrl, type });

    // Recargar el perfil completo (con contadores) y volver a la vista.
    state.profile = await api.getUserProfile(state.profile.id);
    state.cache = {}; // los posts propios pueden haber cambiado de nombre/foto
    renderHeader(state.profile);
    closeEdit();
    loadTab(state.currentTab);
    showToast('Perfil actualizado.');
  }catch(err){
    showToast(err.message || 'No se pudo actualizar el perfil.');
    btn.disabled = false;
    btn.textContent = original;
  }
}
// -- BOTON COMPARTIR ---
    async function handleExternalShare(post) {
  if (!post) return;

  // Construimos la URL apuntando a post.php en la misma carpeta
  const urlBase = location.href.substring(0, location.href.lastIndexOf('/'));
  const url = `${urlBase}/post.php?id=${post.id}`;
  
  const shareData = {
    title: `Publicación de ${post.authorName || 'Usuario'}`,
   text: `¡MIRÁ ESTA PUBLICACIÓN DE ${post.authorName || 'NUESTRO CLIENTE'} EN Mercadito! \n"${post.text || ''}"\n`,
    url
  };

  if (navigator.share) {
    try {
      await navigator.share(shareData);
    } catch (err) {
      // El usuario canceló o cerró el diálogo nativo
    }
    return;
  }

  if (navigator.clipboard && navigator.clipboard.writeText) {
    try {
      await navigator.clipboard.writeText(url);
      showToast('Enlace copiado al portapapeles.');
    } catch (err) {
      showToast('No se pudo copiar el enlace.');
    }
    return;
  }

  showToast('Copiá esta URL para compartir: ' + url);
}
    
// ---------- Init ----------
async function init(){
  if(!profileId){
    document.getElementById('usuario-loading').textContent = 'No se indicó qué perfil mostrar.';
    return;
  }
  try{
    state.profile = await api.getUserProfile(profileId);
    renderHeader(state.profile);
    document.getElementById('usuario-loading').style.display = 'none';
    document.getElementById('usuario-content').style.display = 'block';
    loadTab('posts');
  }catch(e){
    document.getElementById('usuario-loading').textContent = e.message || 'No se pudo cargar el perfil.';
  }
}
    
init();