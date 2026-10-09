/* ============================================================
   api.js — capa de datos de Mercadito.
   Ahora habla con el backend real en PHP + MySQL (carpeta /api).
   La forma de cada función (getProfile, getPosts, createPost, ...)
   es la misma que antes, así que feed.js casi no cambió: sólo
   cambió lo que hay ADENTRO de estas funciones.
   ============================================================ */

// Avatares: API pública real de DiceBear (https://www.dicebear.com/),
// generados a partir del nombre. Los colores usan la paleta de
// Mercadito y cambian solos según el tema claro/oscuro.
function avatarUrl(seed){
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const bg = isDark ? '1e9a85,175f52' : '6b4226,9c6b43';
  const text = isDark ? '04120f' : 'fbf7f1';
  const params = new URLSearchParams({
    seed: seed || 'Mercadito',
    backgroundColor: bg,
    textColor: text,
    fontWeight: '700'
  });
  return `https://api.dicebear.com/9.x/initials/svg?${params.toString()}`;
}

// ---------- Tiempo relativo ("hace 5 min") ----------
function timeAgo(timestamp){
  const diffMs = Date.now() - timestamp;
  const minutes = Math.round(diffMs / 60000);
  if(minutes < 1) return 'ahora';
  if(minutes < 60) return `hace ${minutes} min`;
  const hours = Math.round(minutes / 60);
  if(hours < 24) return `hace ${hours} h`;
  const days = Math.round(hours / 24);
  if(days < 7) return `hace ${days} d`;
  const weeks = Math.round(days / 7);
  return `hace ${weeks} sem`;
}

// ---------- Helper para llamar a la API en PHP (JSON) ----------
async function apiRequest(url, options = {}){
  // Si la página trae el token CSRF (paneles de admin), lo mandamos en
  // todo pedido que modifica datos.
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  const method = (options.method || 'GET').toUpperCase();
  if(method !== 'GET' && method !== 'HEAD'){
    const meta = document.querySelector('meta[name="csrf-token"]');
    if(meta && meta.content) headers['X-CSRF-Token'] = meta.content;
  }

  let res;
  try{
    res = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers
    });
  }catch(networkErr){
    throw new Error('No se pudo conectar con el servidor. Revisá tu conexión.');
  }
  let data = {};
  try{ data = await res.json(); }catch(_){ /* respuesta vacía */ }
  if(!res.ok){
    const error = new Error(data.error || 'Ocurrió un error inesperado.');
    // Dejamos la respuesta completa colgada del error para que quien llama
    // pueda mirar banderas extra (por ej. requiereVerificacion en el login).
    error.data = data;
    error.status = res.status;
    throw error;
  }
  return data;
}

// ---------- Helper para subir un archivo (multipart, NO JSON) ----------
async function apiUpload(url, file, fieldName){
  const formData = new FormData();
  formData.append(fieldName, file);

  let res;
  try{
    // Importante: no seteamos "Content-Type" a mano. El navegador arma
    // el header multipart/form-data con el boundary correcto solo.
    res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
  }catch(networkErr){
    throw new Error('No se pudo conectar con el servidor. Revisá tu conexión.');
  }
  let data = {};
  try{ data = await res.json(); }catch(_){ /* respuesta vacía */ }
  if(!res.ok){
    throw new Error(data.error || 'Ocurrió un error inesperado.');
  }
  return data;
}

        // Pantalla Completa Nativa al hacer clic en cualquier imagen
document.addEventListener('click', (e) => {
  const target = e.target;

  // Verifica si se hizo clic en una imagen de publicación
  if (target.tagName === 'IMG' && (target.classList.contains('post-image') || target.id === 'post-image') || target.classList.contains('tw-avatar')) {
      //document.getElementById('tw-avatar').src = p.avatarUrl || avatarUrl(p.name); //pfp
    // Si la imagen ya está en pantalla completa, la cierra al hacer clic
    if (document.fullscreenElement || document.webkitFullscreenElement) {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    } else {
      // Abre la imagen en pantalla completa nativa
      if (target.requestFullscreen) {
        target.requestFullscreen();
      } else if (target.webkitRequestFullscreen) {
        target.webkitRequestFullscreen(); // Soporte para Safari
      }
    }
  }
});



// ---------- API pública que usa el resto de la app ----------
const api = {
  // Sesión
  async register({ fullname, age, username, email, password }){
    return apiRequest('/api/register.php', {
      method: 'POST',
      body: JSON.stringify({ fullname, age, username, email, password })
    });
  },
  async login({ username, password }){
    return apiRequest('/api/login.php', {
      method: 'POST',
      body: JSON.stringify({ username, password })
    });
  },
  async logout(){
    return apiRequest('/api/logout.php', { method: 'POST' });
  },

  // Verificación de correo
  // Confirma el código de 6 dígitos que llegó por mail. Si sale bien, el
  // servidor ya deja la sesión iniciada y devuelve { redirect }.
  async verifyEmail({ email, code }){
    return apiRequest('/api/verify.php', {
      method: 'POST',
      body: JSON.stringify({ email, code })
    });
  },
  // Pide que manden de nuevo el correo de activación.
  async resendVerification(email){
    return apiRequest('/api/verify.php', {
      method: 'POST',
      body: JSON.stringify({ email, reenviar: true })
    });
  },

  // "Olvidé mi contraseña"
  // Pide el link de recuperación. La respuesta es la misma exista o no esa
  // cuenta, para no revelar qué correos están registrados.
  async forgotPassword(email){
    return apiRequest('/api/forgot-password.php', {
      method: 'POST',
      body: JSON.stringify({ email })
    });
  },
  // Guarda la contraseña nueva usando el token del link del correo.
  async resetPassword({ token, password }){
    return apiRequest('/api/reset-password.php', {
      method: 'POST',
      body: JSON.stringify({ token, password })
    });
  },

  // Perfil
  async getProfile(){
    const data = await apiRequest('/api/profile.php');
    return data.profile;
  },
  async updateProfile(payload){
    const data = await apiRequest('/api/profile.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    return data.profile;
  },

  // Feed
  // filtros opcionales de zona: { lat, lng, radius, zone, includeWithout,
  //   userId, sharedBy, likedBy } — si no hay lat/lng trae todo como antes.
  async getPosts(filters = {}){
    const q = new URLSearchParams();
    if (filters.lat !== undefined && filters.lng !== undefined) {
      q.set('lat', filters.lat);
      q.set('lng', filters.lng);
    }
    if (filters.radius) q.set('radius', filters.radius);
    if (filters.zone) q.set('zone', filters.zone);
    if (filters.includeWithout !== undefined) q.set('includeWithout', filters.includeWithout ? '1' : '0');
    if (filters.userId) q.set('userId', filters.userId);
    if (filters.sharedBy) q.set('sharedBy', filters.sharedBy);
    if (filters.likedBy) q.set('likedBy', filters.likedBy);
    const qs = q.toString();
    const data = await apiRequest('/api/posts.php' + (qs ? '?' + qs : ''));
    return data.posts;
  },
  async createPost({ text, imageUrl, lat, lng, place_name, placeName }){
    return apiRequest('/api/posts.php', {
      method: 'POST',
      body: JSON.stringify({
        text,
        imageUrl,
        lat: lat ?? null,
        lng: lng ?? null,
        place_name: place_name ?? placeName ?? null,
      })
    });
  },
  async toggleLike(postId){
    return apiRequest('/api/likes.php', {
      method: 'POST',
      body: JSON.stringify({ postId })
    });
  },
  async addComment(postId, text){
    const data = await apiRequest('/api/comments.php', {
      method: 'POST',
      body: JSON.stringify({ postId, text })
    });
    return data.comment;
  },
	//Publicaciones individuales
    async getPost(id, filters = {}){
    const q = new URLSearchParams();
    if (filters.lat !== undefined && filters.lng !== undefined) {
      q.set('lat', filters.lat);
      q.set('lng', filters.lng);
    }
    const qs = q.toString();
    const data = await apiRequest(
        '/api/posts.php?id=' + encodeURIComponent(id) + (qs ? '&' + qs : '')
    );

    return data.post;
},
  // Subida de imágenes (avatar o foto de publicación)
  // Devuelve la URL absoluta ya guardada en el servidor.
  async uploadImage(file){
    const data = await apiUpload('/api/upload.php', file, 'imagen');
    return data.imageUrl;
  },

  // ---------- Mensajería privada ----------
  // Lista de mis conversaciones (con último mensaje y no leídos).
  async getConversations(){
    const data = await apiRequest('/api/conversations.php');
    return data.conversations;
  },
  // Una conversación con todos sus mensajes + datos del otro usuario.
  async getConversation(id){
    return apiRequest('/api/conversations.php?id=' + encodeURIComponent(id));
  },
  // Inicia (o reusa) una conversación con un emprendedor. Devuelve el id.
  // Se le pasa { shopId } (desde una tienda) o { emprendedorId }.
  async startConversation({ shopId, emprendedorId } = {}){
    const data = await apiRequest('/api/conversations.php', {
      method: 'POST',
      body: JSON.stringify({ shopId, emprendedorId })
    });
    return data.id;
  },
  // Envía un mensaje. Devuelve el mensaje creado.
  async sendMessage(conversationId, text){
    const data = await apiRequest('/api/messages.php', {
      method: 'POST',
      body: JSON.stringify({ conversationId, text })
    });
    return data.message;
  },
  // Trae mensajes nuevos (id > afterId) + ids de eliminados para refrescar
  // burbujas viejas sin recargar. Devuelve { messages, deletedIds }.
  async pollMessages(conversationId, afterId){
    const params = new URLSearchParams({ conversationId, after: afterId || 0 });
    const data = await apiRequest('/api/messages.php?' + params.toString());
    return { messages: data.messages || [], deletedIds: data.deletedIds || [] };
  },
  // Elimina un mensaje mío.
  async deleteMessage(id){
    await apiRequest('/api/messages.php?id=' + encodeURIComponent(id), { method: 'DELETE' });
    return true;
  },
  // Bloquea o desbloquea (alterna) al otro usuario de una conversación. Devuelve true si quedó bloqueado.
  async toggleBlock(userId){
    const data = await apiRequest('/api/blocks.php', {
      method: 'POST',
      body: JSON.stringify({ userId })
    });
    return data.blocked;
  },

  // ---------- Reclamos ----------
  // Crea un reclamo a un emprendimiento. Sólo clientes.
  async createReclamo({ shopId, mensaje }){
    return apiRequest('/api/reclamos.php', {
      method: 'POST',
      body: JSON.stringify({ shopId, mensaje })
    });
  },
  // Lista los reclamos de mi propia tienda (emprendedor).
  async getReclamos(){
    const data = await apiRequest('/api/reclamos.php');
    return data.reclamos;
  },
  // Marca un reclamo mío como resuelto/pendiente.
  async setReclamoEstado(id, estado){
    return apiRequest('/api/reclamos.php?id=' + encodeURIComponent(id), {
      method: 'POST',
      body: JSON.stringify({ estado })
    });
  },

  // ---------- Social: compartir y seguir ----------
  // Comparte / descomparte una publicación. Devuelve { shared, shareCount }.
  async toggleShare(postId){
    return apiRequest('/api/shares.php', { method: 'POST', body: JSON.stringify({ postId }) });
  },
  // Sigue / deja de seguir a un usuario. Devuelve { following, followerCount }.
  async toggleFollow(userId){
    return apiRequest('/api/follows.php', { method: 'POST', body: JSON.stringify({ userId }) });
  },
  // Lista de seguidores o seguidos de un usuario. type: 'followers' | 'following'.
  async getFollowList(userId, type){
    const data = await apiRequest('/api/follows.php?userId=' + encodeURIComponent(userId) + '&list=' + type);
    return data.users;
  },
  // Perfil público de un usuario (con contadores y si lo sigo).
  async getUserProfile(id){
    const data = await apiRequest('/api/profile.php?id=' + encodeURIComponent(id));
    return data.profile;
  },
  // Publicaciones creadas por un usuario.
  async getUserPosts(id){
    const data = await apiRequest('/api/posts.php?userId=' + encodeURIComponent(id));
    return data.posts;
  },
  // Publicaciones que un usuario compartió.
  async getSharedPosts(id){
    const data = await apiRequest('/api/posts.php?sharedBy=' + encodeURIComponent(id));
    return data.posts;
  },
  // Publicaciones a las que el usuario logueado les dio me gusta (privado).
  async getLikedPosts(id){
    const data = await apiRequest('/api/posts.php?likedBy=' + encodeURIComponent(id));
    return data.posts;
  }
};