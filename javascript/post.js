// post.js

// Íconos (mismos que feed.js, para que los botones se vean igual)
const ICON_HEART = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>`;
const ICON_COMMENT = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
const ICON_SHARE = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`;

// NUEVO: ícono de "compartir hacia afuera" (redes / enlace), distinto del ícono
// de "compartir a mi perfil" (ICON_SHARE) que ya existía en el feed.
const ICON_SHARE_EXTERNAL = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>`;

const postId = document.currentScript?.dataset.postId;

const loading = document.getElementById('post-loading');
const content = document.getElementById('post-content');
const error = document.getElementById('post-error');

let currentPost = null;

async function init() {

    console.log('post.js cargado');
    console.log('ID de publicación:', postId);

    if (!postId || postId === '0') {

        loading.style.display = 'none';

        error.textContent = 'No se indicó una publicación.';
        error.style.display = 'block';

        return;
    }

    try {

        const geo = (typeof Geo !== 'undefined' && Geo.getCached) ? Geo.getCached() : null;
        const post = await api.getPost(postId, geo ? { lat: geo.lat, lng: geo.lng } : {});

        console.log('Publicación recibida:', post);

        currentPost = post;

        renderAuthor(post);
        renderText(post);
        renderImage(post);
        renderDate(post);
        renderLoc(post);
        renderActions(post);
        renderComments(post);

        // Mostrar
        loading.style.display = 'none';
        content.style.display = 'block';

        // Distancia en 2do plano si el visitante no dio ubicación.
        enrichDistance();

    } catch (err) {

        console.error(
            'Error cargando publicación:',
            err
        );

        loading.style.display = 'none';

        error.textContent =
            err.message ||
            'No se pudo cargar la publicación.';

        error.style.display = 'block';
    }
}

function renderAuthor(post) {

    const avatar = document.getElementById('post-avatar');
    const avatarLink = document.getElementById('post-avatar-link');
    const nameLink = document.getElementById('post-name-link');
    const name = document.getElementById('post-name');
    const username = document.getElementById('post-username');

    avatar.src =
        post.authorAvatar ||
        avatarUrl(post.authorName);

    avatar.alt =
        post.authorName ||
        'Usuario';

    name.textContent =
        post.authorName ||
        'Usuario';

    const badge = document.getElementById('post-badge');
    if (badge) {
        badge.innerHTML = post.authorType === 'emprendedor'
            ? '<span class="badge">Emprendedor/a</span>'
            : '<span class="badge badge-cliente">Cliente</span>';
    }

    if (username) {
        username.textContent = '';
    }

    const profileUrl = `usuario.php?id=${post.authorId}`;
    if (avatarLink) avatarLink.href = profileUrl;
    if (nameLink) nameLink.href = profileUrl;
}

function renderText(post) {

    const text = document.getElementById('post-text');

    text.textContent =
        post.text ||
        '';
}

function renderImage(post) {

    const image = document.getElementById('post-image');

    if (post.imageUrl) {

        image.src = post.imageUrl;
        image.style.display = 'block';

    } else {

        image.style.display = 'none';

    }
}

function renderDate(post) {

    const date = document.getElementById('post-date');

    if (post.createdAt) {

        date.textContent =
            timeAgo(post.createdAt);

    }
}

function renderLoc(post) {
    const el = document.getElementById('post-loc');
    if (!el) return;
    const dist = (typeof Geo !== 'undefined' && Geo.formatDistance)
        ? Geo.formatDistance(post.distanceKm) : null;
    // Mostrar siempre que el post tenga zona guardada, aunque el
    // visitante no haya dado su ubicación (sin distancia en ese caso).
    const label = post.placeName
        || ((post.lat !== null && post.lat !== undefined && post.lng !== null && post.lng !== undefined)
            ? 'Ubicación guardada' : null);
    if (label || dist) {
        el.textContent = `📍 ${label || 'Cerca tuyo'}${dist ? ` · ${dist}` : ''}`;
        el.style.display = '';
    } else {
        el.style.display = 'none';
    }
}

// Si el post tiene zona pero el visitante no dio su ubicación,
// estimarla por IP (sin permiso) para mostrar la distancia.
async function enrichDistance() {
    try {
        if (!currentPost || currentPost.distanceKm !== null && currentPost.distanceKm !== undefined) return;
        if (currentPost.lat === null || currentPost.lat === undefined) return;
        if (currentPost.lng === null || currentPost.lng === undefined) return;
        if (typeof Geo === 'undefined' || !Geo.getCached || !Geo.requestIP) return;
        if (Geo.getCached()) return; // ya se pidió con coords: no hay más que calcular
        const loc = await Geo.requestIP();
        const post = await api.getPost(postId, { lat: loc.lat, lng: loc.lng });
        currentPost = post;
        renderLoc(post);
    } catch (_) {
        // Silencioso: el nombre del lugar ya se muestra igual.
    }
}

/* ---------------- ACCIONES (like / comentarios / compartir) ----------------
   Misma lógica que feed.js, adaptada a una sola publicación en vez de una lista.
*/

function renderActions(post) {

    const container = document.getElementById('post-actions');
    const liked = post.likedByMe;

    container.innerHTML = `
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
    `;

    container.querySelector('[data-action="like"]').addEventListener('click', handleLike);
    container.querySelector('[data-action="toggle-comments"]').addEventListener('click', toggleComments);
    container.querySelector('[data-action="share"]').addEventListener('click', handleShare);
    container.querySelector('[data-action="share-external"]').addEventListener('click', handleExternalShare);
}

function commentTemplate(comment) {
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

function renderComments(post) {

    const list = document.getElementById('post-comments-list');
    list.innerHTML = post.comments.map(commentTemplate).join('');

    const form = document.getElementById('post-comment-form');
    form.onsubmit = handleComment;
}

// Vuelve a pedir la publicación al servidor y refresca contadores/comentarios
// (like, comentarios y compartidos cambian del lado del servidor).
async function refreshPost(forceOpenComments) {

    const section = document.getElementById('post-comments-section');
    const wasOpen = !section.hidden;

    const geo = (typeof Geo !== 'undefined' && Geo.getCached) ? Geo.getCached() : null;
    currentPost = await api.getPost(postId, geo ? { lat: geo.lat, lng: geo.lng } : {});

    renderDate(currentPost);
    renderLoc(currentPost);
    renderActions(currentPost);
    renderComments(currentPost);

    if (wasOpen || forceOpenComments) {
        section.hidden = false;
    }
}

async function handleLike() {
    try {
        await api.toggleLike(postId);
        await refreshPost();
    } catch (err) {
        showToast(err.message || 'No se pudo actualizar el like.');
    }
}

async function handleShare() {
    try {
        const res = await api.toggleShare(postId);
        showToast(res.shared ? 'Compartido en tu perfil ✔' : 'Se quitó de tus compartidos.');
        await refreshPost();
    } catch (err) {
        showToast(err.message || 'No se pudo compartir.');
    }
}

function toggleComments() {
    const section = document.getElementById('post-comments-section');
    section.hidden = !section.hidden;
    if (!section.hidden) {
        section.querySelector('input').focus();
    }
}

async function handleComment(e) {
    e.preventDefault();
    const input = e.target.querySelector('input');
    const text = input.value.trim();
    if (!text) return;

    try {
        await api.addComment(postId, text);
        input.value = '';
        await refreshPost(true);
    } catch (err) {
        showToast(err.message || 'No se pudo publicar el comentario.');
    }
}

/* ---------------- NUEVO: compartir la publicación afuera de la app ----------------
   Distinto del botón "share" de arriba (que es un repost dentro del feed).
   Este usa el share sheet nativo del dispositivo (WhatsApp, Instagram, etc.)
   cuando está disponible, y si no, copia el enlace al portapapeles.
*/
async function handleExternalShare() {

    if (!currentPost) return;

    const url = `${location.origin}${location.pathname}?id=${postId}`;
    const shareData = {
        title: `Compartiendo publicación de ${currentPost.authorName || 'Mercadito'}`,
        text: `¡MIRÁ ESTA PUBLICACIÓN DE ${currentPost.authorName || 'NUESTRO CLIENTE'} EN Mercadito! \n"${currentPost.text || ''}"\n`,
        url
    };

    if (navigator.share) {
        try {
            await navigator.share(shareData);
        } catch (err) {
            // el usuario cerró el diálogo nativo sin elegir nada: no es un error real
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

init();