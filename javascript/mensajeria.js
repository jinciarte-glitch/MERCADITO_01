// mensajeria.js — mensajería flotante estilo Messenger, disponible en todas
// las páginas (se incluye desde includes/navbar.php). Reusa los endpoints y
// las funciones de api.js. No pisa a mensajeria-inbox.js: el widget no se
// carga en mensajeria.php.
(function () {
  // Si por algún motivo falta el markup o la API, no hacemos nada.
  const dock = document.getElementById('mc-dock');
  if (!dock || typeof api === 'undefined') return;

  const launcher    = document.getElementById('mc-launcher');
  const launchBadge = document.getElementById('mc-launcher-badge');
  const listPanel   = document.getElementById('mc-list-panel');
  const listEl      = document.getElementById('mc-list');
  const listEmpty   = document.getElementById('mc-list-empty');
  const listClose   = document.getElementById('mc-list-close');
  const windowEl    = document.getElementById('mc-window');
  const winAvatar   = document.getElementById('mc-window-avatar');
  const winName     = document.getElementById('mc-window-name');
  const winClose    = document.getElementById('mc-window-close');
  const messagesEl  = document.getElementById('mc-messages');
  const inputEl     = document.getElementById('mc-input');
  const composer    = document.getElementById('mc-composer');

  const state = { openId: null, lastId: 0, pollTimer: null, conversations: [] };

  // Íconos: tacho (eliminar) y prohibido (mensaje eliminado, estilo WhatsApp).
  const ICON_TRASH = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>`;
  const ICON_BAN = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m5.5 5.5 13 13"/></svg>`;

  // ---------- Utilidades de UI ----------
  function show(el){ el.hidden = false; }
  function hide(el){ el.hidden = true; }

  function toggleList() {
    if (listPanel.hidden) { openList(); }
    else { hide(listPanel); }
  }
  async function openList() {
    hide(windowEl);
    show(listPanel);
    await refreshList();
  }

  // ---------- Lista de conversaciones ----------
  function totalUnread() {
    return state.conversations.reduce((sum, c) => sum + (c.unread || 0), 0);
  }
  function paintBadge() {
    const n = totalUnread();
    if (n > 0) { launchBadge.textContent = n > 99 ? '99+' : n; show(launchBadge); }
    else { hide(launchBadge); }
  }
  function renderList() {
    listEl.innerHTML = '';
    if (state.conversations.length === 0) { show(listEmpty); return; }
    hide(listEmpty);
    state.conversations.forEach(c => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'mc-list-item' + (c.unread > 0 ? ' mc-unread' : '');
      const avatar = c.otherAvatar || avatarUrl(c.otherName);
      const last = c.lastDeleted
        ? '<span class="mc-muted"><em>🚫 Mensaje eliminado</em></span>'
        : (c.lastText ? escapeHtml(c.lastText) : '<span class="mc-muted">Sin mensajes</span>');
      const badge = c.unread > 0 ? `<span class="mc-badge">${c.unread}</span>` : '';
      item.innerHTML = `
        <img class="mc-avatar" src="${escapeAttr(avatar)}" alt="">
        <div class="mc-list-body">
          <div class="mc-list-top">
            <span class="mc-list-name">${escapeHtml(c.otherName)}</span>
            ${c.lastAt ? `<span class="mc-list-time">${timeAgo(c.lastAt)}</span>` : ''}
          </div>
          <div class="mc-list-last">${last}</div>
        </div>
        ${badge}`;
      item.addEventListener('click', () => openConversation(c.id));
      listEl.appendChild(item);
    });
  }
  async function refreshList() {
    try {
      state.conversations = await api.getConversations();
      renderList();
      paintBadge();
    } catch (_) { /* silencioso */ }
  }

  // ---------- Ventana de mensaje ----------
  function deletedBubbleHTML() {
    return `<div class="mc-bubble mc-deleted"><span class="mc-deleted-icon">${ICON_BAN}</span><em>Mensaje eliminado</em></div>`;
  }
  function messageEl(m) {
    const div = document.createElement('div');
    div.className = 'mc-msg ' + (m.mine ? 'mc-msg-mine' : 'mc-msg-theirs');
    div.dataset.mid = m.id;
    if (m.deleted) {
      div.innerHTML = deletedBubbleHTML();
      return div;
    }
    const delBtn = m.mine
      ? `<button type="button" class="mc-delete-btn" title="Eliminar mensaje" aria-label="Eliminar mensaje">${ICON_TRASH}</button>`
      : '';
    div.innerHTML = `${delBtn}<div class="mc-bubble">${escapeHtml(m.text)}</div>`;
    if (m.mine) {
      div.querySelector('.mc-delete-btn').addEventListener('click', () => handleDelete(m.id));
    }
    return div;
  }
  async function handleDelete(id) {
    try {
      await api.deleteMessage(id);
      const el = messagesEl.querySelector(`[data-mid="${id}"]`);
      if (el) el.replaceWith(messageEl({ id, mine: true, text: '', deleted: true }));
      refreshList(); // actualiza la vista previa de la lista
    } catch (e) {
      showToast(e.message || 'No se pudo eliminar el mensaje.');
    }
  }
  // Marca como eliminadas las burbujas viejas que el otro (o yo desde
  // otro dispositivo) borró mientras miraba el chat.
  function applyDeleted(ids) {
    let changed = false;
    (ids || []).forEach(id => {
      const el = messagesEl.querySelector(`[data-mid="${id}"]`);
      if (el && !el.querySelector('.mc-deleted')) {
        el.replaceWith(messageEl({
          id,
          mine: el.classList.contains('mc-msg-mine'),
          text: '',
          deleted: true,
        }));
        changed = true;
      }
    });
    if (changed) refreshList();
  }
  function appendMessages(list) {
    list.forEach(m => {
      if (m.id > state.lastId) state.lastId = m.id;
      messagesEl.appendChild(messageEl(m));
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  async function openConversation(id) {
    stopPolling();
    state.openId = id;
    state.lastId = 0;
    messagesEl.innerHTML = '';
    hide(listPanel);
    show(windowEl);
    winName.textContent = '…';
    show(dock); // por si el dock estaba minimizado

    try {
      const conv = await api.getConversation(id);
      const other = conv.other || {};
      renderWinName(other);
      winAvatar.src = other.avatarUrl || avatarUrl(other.name);
      appendMessages(conv.messages || []);
      inputEl.focus();
      refreshList(); // el no-leído ya se limpió en el server
      startPolling();
    } catch (e) {
      showToast(e.message || 'No se pudo abrir la conversación.');
    }
  }

  // Nombre del header: si es un emprendimiento, linkea a su tienda.
  function renderWinName(other) {
    if (other.shopId) {
      winName.innerHTML = '';
      const a = document.createElement('a');
      a.href = `tienda.php?id=${other.shopId}`;
      a.textContent = other.name || 'Emprendimiento';
      winName.appendChild(a);
    } else {
      winName.textContent = other.name || 'Usuario';
    }
  }

  composer.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = inputEl.value.trim();
    if (!text || !state.openId) return;
    inputEl.value = '';
    try {
      const msg = await api.sendMessage(state.openId, text);
      appendMessages([msg]);
      refreshList();
    } catch (err) {
      showToast(err.message || 'No se pudo enviar el mensaje.');
      inputEl.value = text;
    } finally {
      inputEl.focus();
    }
  });

  // ---------- Polling ----------
  async function pollOnce() {
    if (!state.openId) return;
    try {
      const res = await api.pollMessages(state.openId, state.lastId);
      if (res.messages.length) { appendMessages(res.messages); refreshList(); }
      applyDeleted(res.deletedIds);
    } catch (_) { /* reintenta */ }
  }
  function startPolling() { stopPolling(); state.pollTimer = setInterval(pollOnce, 3000); }
  function stopPolling() { if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; } }

  // ---------- Eventos de la interfaz ----------
  launcher.addEventListener('click', () => {
    // Si hay una ventana abierta, el botón la muestra/oculta; si no, abre la lista.
    if (!windowEl.hidden) { hide(windowEl); return; }
    toggleList();
  });
  listClose.addEventListener('click', () => hide(listPanel));
  winClose.addEventListener('click', () => { stopPolling(); state.openId = null; hide(windowEl); });

  // Link "Mensajes" de la barra: en vez de ir a mensajeria.php, abre el widget.
  const navLink = document.getElementById('nav-mensajes');
  if (navLink) {
    navLink.addEventListener('click', (e) => { e.preventDefault(); openList(); });
  }

  // Refresca avatares por defecto al cambiar de tema.
  document.addEventListener('mercadito:themechange', renderList);

  // ---------- API pública para otras páginas (ej. botón de la tienda) ----------
  window.mercaditoMensajes = {
    open: openList,
    openConversation,
    async startWithShop(shopId) {
      try {
        const id = await api.startConversation({ shopId });
        await openConversation(id);
      } catch (e) {
        showToast(e.message || 'No se pudo abrir la conversación.');
      }
    }
  };

  // ---------- Init ----------
  refreshList();                       // pinta el badge al cargar
  setInterval(refreshList, 8000);      // mantiene el badge al día
})();
