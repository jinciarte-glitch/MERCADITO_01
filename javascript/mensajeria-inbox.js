// mensajeria-inbox.js — mensajería privada (lista de conversaciones + hilo abierto).
// Usa "polling": cada pocos segundos le pregunta al servidor si hay
// mensajes nuevos, así la conversación se actualiza sin recargar la página.

const state = {
  conversations: [],
  openId: null,   // id de la conversación abierta
  lastId: 0,      // id del último mensaje mostrado (para pedir sólo los nuevos)
  pollTimer: null,
};

const convListEl   = document.getElementById('conv-list');
const convEmptyEl   = document.getElementById('conv-empty');
const convSearchEmptyEl = document.getElementById('conv-search-empty');
const convSearchEl  = document.getElementById('conv-search');
const threadEmptyEl = document.getElementById('thread-empty');
const threadInnerEl = document.getElementById('thread-inner');
const messagesEl    = document.getElementById('msj-messages');
const nameEl        = document.getElementById('thread-name');
const avatarEl      = document.getElementById('thread-avatar');
const inputEl       = document.getElementById('msj-input');
const sendBtnEl     = document.getElementById('msj-send');
const layoutEl      = document.getElementById('msj-layout');
const blockBtnEl    = document.getElementById('msj-block-btn');
const blockLabelEl  = document.getElementById('msj-block-label');
const blockBannerEl = document.getElementById('msj-block-banner');

state.search = '';
state.openOtherId = null;
state.blockedByMe = false;
state.blockedMe = false;

// ---------- Lista de conversaciones ----------
function renderConversations() {
  convListEl.innerHTML = '';

  if (state.conversations.length === 0) {
    convEmptyEl.style.display = 'block';
    convSearchEmptyEl.style.display = 'none';
    return;
  }
  convEmptyEl.style.display = 'none';

  const q = state.search.trim().toLowerCase();
  const visibles = q
    ? state.conversations.filter(c => (c.otherName || '').toLowerCase().includes(q))
    : state.conversations;

  if (visibles.length === 0) {
    convSearchEmptyEl.style.display = 'block';
    return;
  }
  convSearchEmptyEl.style.display = 'none';

  visibles.forEach(c => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'conv-item' + (c.id === state.openId ? ' active' : '');
    const avatar = c.otherAvatar || avatarUrl(c.otherName);
    const last = c.lastDeleted
      ? '<span class="conv-nolast">🚫 Mensaje eliminado</span>'
      : (c.lastText ? escapeHtml(c.lastText) : '<span class="conv-nolast">Sin mensajes todavía</span>');
    const badge = c.unread > 0 ? `<span class="conv-badge">${c.unread}</span>` : '';
    item.innerHTML = `
      <img class="avatar" src="${escapeAttr(avatar)}" alt="">
      <div class="conv-item-body">
        <div class="conv-item-top">
          <span class="conv-name">${escapeHtml(c.otherName)}</span>
          ${c.lastAt ? `<span class="conv-time">${timeAgo(c.lastAt)}</span>` : ''}
        </div>
        <div class="conv-last">${last}</div>
      </div>
      ${badge}`;
    item.addEventListener('click', () => openConversation(c.id));
    convListEl.appendChild(item);
  });
}

async function loadConversations() {
  try {
    state.conversations = await api.getConversations();
    renderConversations();
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudieron cargar las conversaciones.');
  }
}

// ---------- Un mensaje ----------
const ICON_BAN = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg>`;

function messageEl(m) {
  const div = document.createElement('div');
  div.className = 'msg ' + (m.mine ? 'msg-mine' : 'msg-theirs');
  div.dataset.mid = m.id;
  div.dataset.createdAt = m.createdAt;
  if (m.deleted) {
    div.innerHTML = `
    <div class="msg-row">
      <div class="msg-bubble msg-deleted"><span class="msg-deleted-icon">${ICON_BAN}</span><em>Mensaje eliminado</em></div>
    </div>
    <div class="msg-time">${timeAgo(m.createdAt)}</div>`;
    return div;
  }
  const deleteBtn = m.mine ? `
    <button type="button" class="msg-delete-btn" title="Eliminar mensaje" aria-label="Eliminar mensaje">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6h18"/>
        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
        <path d="M10 11v6"/>
        <path d="M14 11v6"/>
      </svg>
    </button>` : '';
  div.innerHTML = `
    <div class="msg-row">
      ${deleteBtn}
      <div class="msg-bubble">${escapeHtml(m.text)}</div>
    </div>
    <div class="msg-time">${timeAgo(m.createdAt)}</div>`;
  if (m.mine) {
    div.querySelector('.msg-delete-btn').addEventListener('click', () => deleteMessage(m.id, div));
  }
  return div;
}

// ---------- Eliminar un mensaje (queda "Mensaje eliminado", estilo WhatsApp) ----------
async function deleteMessage(id, el) {
  try {
    await api.deleteMessage(id);
    el.replaceWith(messageEl({
      id,
      mine: true,
      text: '',
      deleted: true,
      createdAt: Number(el.dataset.createdAt) || Date.now(),
    }));
    loadConversations(); // por si era el último mensaje: actualiza el texto en la lista
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo eliminar el mensaje.');
  }
}

// Marca como eliminadas las burbujas viejas borradas mientras miraba el chat.
function applyDeleted(ids) {
  let changed = false;
  (ids || []).forEach(id => {
    const el = messagesEl.querySelector(`[data-mid="${id}"]`);
    if (el && !el.querySelector('.msg-deleted')) {
      el.replaceWith(messageEl({
        id,
        mine: el.classList.contains('msg-mine'),
        text: '',
        deleted: true,
        createdAt: Number(el.dataset.createdAt) || Date.now(),
      }));
      changed = true;
    }
  });
  if (changed) loadConversations();
}

function appendMessages(list) {
  list.forEach(m => {
    if (m.id > state.lastId) state.lastId = m.id;
    messagesEl.appendChild(messageEl(m));
  });
  // Auto-scroll al fondo.
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

// ---------- Abrir una conversación ----------
async function openConversation(id) {
  stopPolling();
  state.openId = id;
  state.lastId = 0;
  messagesEl.innerHTML = '';
  threadEmptyEl.style.display = 'none';
  threadInnerEl.style.display = 'flex';
  layoutEl.classList.add('thread-open'); // para el modo mobile
  nameEl.textContent = '…';

  try {
    const conv = await api.getConversation(id);
    const other = conv.other || {};
    state.openOtherId = other.id;
    state.blockedByMe = !!conv.blockedByMe;
    state.blockedMe = !!conv.blockedMe;
    renderThreadName(other);
    avatarEl.src = other.avatarUrl || avatarUrl(other.name);
    updateBlockUI();
    appendMessages(conv.messages || []);
    inputEl.focus();

    renderConversations();   // marca activa y refresca badges
    loadConversations();     // el no-leído ya se limpió en el server
    startPolling();
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo abrir la conversación.');
  }
}

// Nombre del hilo: si es un emprendimiento, linkea a su tienda.
function renderThreadName(other) {
  nameEl.innerHTML = '';
  if (other.shopId) {
    const a = document.createElement('a');
    a.href = `tienda.php?id=${other.shopId}`;
    a.textContent = other.name || 'Emprendimiento';
    a.className = 'thread-shop-link';
    nameEl.appendChild(a);
  } else {
    nameEl.textContent = other.name || 'Usuario';
  }
}

// ---------- Bloquear / desbloquear ----------
async function toggleBlockAndSync() {
  const blocked = await api.toggleBlock(state.openOtherId);
  if (typeof blocked !== 'boolean') {
    // El servidor respondió algo raro (p.ej. no está la tabla "blocks" todavía).
    throw new Error('El servidor no confirmó el bloqueo. Probá de nuevo en unos segundos.');
  }
  return blocked;
}

function updateBlockUI() {
  if (state.blockedByMe) {
    blockBtnEl.classList.add('is-blocked');
    blockLabelEl.textContent = 'Desbloquear';
    blockBtnEl.title = 'Desbloquear usuario';
  } else {
    blockBtnEl.classList.remove('is-blocked');
    blockLabelEl.textContent = 'Bloquear';
    blockBtnEl.title = 'Bloquear usuario';
  }

  const disabled = state.blockedByMe || state.blockedMe;
  inputEl.disabled = disabled;
  sendBtnEl.disabled = disabled;

  if (state.blockedByMe) {
    blockBannerEl.textContent = 'Bloqueaste a este usuario: no pueden enviarse mensajes hasta que lo desbloquees.';
    blockBannerEl.style.display = 'block';
  } else if (state.blockedMe) {
    blockBannerEl.textContent = 'No podés enviarle mensajes a este usuario en este momento.';
    blockBannerEl.style.display = 'block';
  } else {
    blockBannerEl.style.display = 'none';
  }
}

blockBtnEl.addEventListener('click', async () => {
  if (!state.openOtherId) return;
  blockBtnEl.disabled = true;
  try {
    const blocked = await toggleBlockAndSync();
    state.blockedByMe = blocked;
    updateBlockUI();
    showToast(blocked ? 'Usuario bloqueado.' : 'Usuario desbloqueado.');
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo actualizar el bloqueo.');
  } finally {
    blockBtnEl.disabled = false;
  }
});

// ---------- Enviar ----------
document.getElementById('msj-composer').addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = inputEl.value.trim();
  if (!text || !state.openId) return;
  if (state.blockedByMe || state.blockedMe) return;

  inputEl.value = '';
  const sendBtn = document.getElementById('msj-send');
  sendBtn.disabled = true;
  try {
    const msg = await api.sendMessage(state.openId, text);
    appendMessages([msg]);
    loadConversations(); // sube esta conversación arriba con el nuevo texto
  } catch (err) {
    console.error(err);
    showToast(err.message || 'No se pudo enviar el mensaje.');
    inputEl.value = text; // no perder lo escrito
  } finally {
    sendBtn.disabled = false;
    inputEl.focus();
  }
});

// ---------- Polling ----------
async function pollOnce() {
  if (!state.openId) return;
  try {
    const res = await api.pollMessages(state.openId, state.lastId);
    if (res.messages.length) {
      appendMessages(res.messages);
      loadConversations();
    }
    applyDeleted(res.deletedIds);
  } catch (_) { /* silencioso: reintenta en el próximo tick */ }
}

function startPolling() {
  stopPolling();
  state.pollTimer = setInterval(pollOnce, 3000);
}
function stopPolling() {
  if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
}

// ---------- Botón "volver" (mobile) ----------
document.getElementById('msj-back').addEventListener('click', () => {
  stopPolling();
  state.openId = null;
  state.openOtherId = null;
  state.blockedByMe = false;
  state.blockedMe = false;
  layoutEl.classList.remove('thread-open');
  threadInnerEl.style.display = 'none';
  threadEmptyEl.style.display = 'flex';
  renderConversations();
});

// ---------- Buscador de conversaciones ----------
convSearchEl.addEventListener('input', () => {
  state.search = convSearchEl.value;
  renderConversations();
});

// Refresca temas al cambiar claro/oscuro (los avatares por defecto cambian de color).
document.addEventListener('mercadito:themechange', () => {
  renderConversations();
});

// ---------- Init ----------
async function init() {
  await loadConversations();
  // Si venimos de "Enviar mensaje" en una tienda: mensajeria.php?c=ID
  const params = new URLSearchParams(window.location.search);
  const c = parseInt(params.get('c'), 10);
  if (c) openConversation(c);
  // Refresco periódico de la lista (por si llegan mensajes en otra conversación).
  setInterval(loadConversations, 8000);
}
init();
