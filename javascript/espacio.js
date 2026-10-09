// espacio.js — Panel del emprendedor: edita marca, catálogo y diseño
const navButtons = document.querySelectorAll('#editor-nav button');
const panels = document.querySelectorAll('.editor-panel');

const money = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 2 });
function formatPrice(p) {
  return (p === null || p === undefined || p === '') ? '' : money.format(Number(p));
}

const THEME_DEFAULTS = {
  light: { fondo: '#fbf7f1', tarjeta: '#ffffff', acento: '#6b4226', texto: '#3b2418' },
  dark:  { fondo: '#0a0d0c', tarjeta: '#0f1917', acento: '#1e9a85', texto: '#e7f5f1' },
};
function currentThemeDefaults() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return isDark ? THEME_DEFAULTS.dark : THEME_DEFAULTS.light;
}

navButtons.forEach(btn => btn.addEventListener('click', () => {
  navButtons.forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  panels.forEach(p => p.style.display = p.dataset.panel === btn.dataset.panel ? 'block' : 'none');
  if (btn.dataset.panel === 'reclamos') loadReclamos();
}));

// --- Vista previa de imágenes ---
// setPreviewUrl: muestra una URL ya guardada en el servidor (al cargar la tienda).
function setPreviewUrl(previewId, url) {
  const preview = document.getElementById(previewId);
  if (!preview) return;
  if (url) {
    preview.style.backgroundImage = `url('${url}')`;
    preview.classList.add('has-img');
  } else {
    preview.style.backgroundImage = '';
    preview.classList.remove('has-img');
  }
}

// bindFilePreview: cuando el usuario elige un archivo nuevo, lo muestra al toque
// usando una URL local (todavía no se subió a ningún lado).
function bindFilePreview(inputId, previewId) {
  const input = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!input || !preview) return;
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (file) {
      preview.style.backgroundImage = `url('${URL.createObjectURL(file)}')`;
      preview.classList.add('has-img');
    }
  });
}
bindFilePreview('input-avatar', 'preview-avatar');
bindFilePreview('input-banner', 'preview-banner');
bindFilePreview('prod-image', 'preview-product');

// --- Prefill: cargar mi tienda si ya existe ---
let myShopId = null;
let myShop = null;
async function loadMyShop() {
  try {
    const res = await fetch('api/shops.php?mine=1', { credentials: 'same-origin' });
    const data = await res.json();
    const shop = data.shop;
    if (!shop) return;
    myShop = shop;
    myShopId = shop.id;
    document.getElementById('input-name').value = shop.name || '';
    document.getElementById('input-category').value = shop.category || '';
    document.getElementById('input-desc').value = shop.description || '';
    // Los inputs de tipo "file" no se pueden precargar con una URL:
    // mostramos la imagen actual solo en el preview.
    setPreviewUrl('preview-avatar', shop.avatar_url);
    setPreviewUrl('preview-banner', shop.banner_url);

    const def = currentThemeDefaults();
    document.getElementById('design-logo-icon').value = shop.logo_icon || '';
    document.getElementById('design-font').value = shop.font || "'Work Sans', sans-serif";
    document.getElementById('design-color-fondo').value = shop.color_fondo || def.fondo;
    document.getElementById('design-color-tarjeta').value = shop.color_tarjeta || def.tarjeta;
    document.getElementById('design-color-acento').value = shop.color_acento || def.acento;
    document.getElementById('design-color-texto').value = shop.color_texto || def.texto;
    document.getElementById('design-banner-height').value = shop.banner_height || 180;
    document.getElementById('design-tablon-estructura').value = shop.tablon_estructura || 'tablon-grid-3';
    document.getElementById('design-estilo-tarjeta').value = shop.estilo_tarjeta || 'card-estilo-moderno';
    document.getElementById('design-mostrar-buscador').checked = shop.mostrar_buscador !== false;

    showVerTienda();
    renderDesignPreview();
  } catch (e) { console.error(e); }
}

function showVerTienda() {
  if (!myShopId) return;
  const link = document.getElementById('ver-tienda');
  if (link) {
    link.href = `tienda.php?id=${myShopId}`;
    link.style.display = 'inline-block';
  }
}

// --- Guardar info de la marca ---
document.getElementById('save-marca').addEventListener('click', async () => {
  const btn = document.getElementById('save-marca');
  const originalLabel = btn.textContent;

  const name = document.getElementById('input-name').value.trim();
  if (!name) { showToast('Poné un nombre para tu emprendimiento.'); return; }

  const avatarFile = document.getElementById('input-avatar').files[0];
  const bannerFile = document.getElementById('input-banner').files[0];

  btn.disabled = true;
  try {
    // Por defecto, si no elegiste un archivo nuevo, se mantiene la imagen que ya tenías guardada.
    let avatarUrl = myShop?.avatar_url || null;
    let bannerUrl = myShop?.banner_url || null;

    if (avatarFile) {
      btn.textContent = 'Subiendo foto de perfil…';
      avatarUrl = await api.uploadImage(avatarFile);
    }
    if (bannerFile) {
      btn.textContent = 'Subiendo banner…';
      bannerUrl = await api.uploadImage(bannerFile);
    }

    const body = {
      name,
      category: document.getElementById('input-category').value,
      description: document.getElementById('input-desc').value.trim(),
      avatarUrl,
      bannerUrl,
    };

    btn.textContent = 'Guardando…';
    const res = await fetch('api/shops.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error al guardar');
    myShop = data.shop;
    myShopId = data.shop.id;
    setPreviewUrl('preview-avatar', myShop.avatar_url);
    setPreviewUrl('preview-banner', myShop.banner_url);
    showVerTienda();
    showToast('Cambios guardados ✔');
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo guardar. Intentá de nuevo.');
  } finally {
    btn.disabled = false;
    btn.textContent = originalLabel;
  }
});

// --- Guardar diseño ---
document.getElementById('save-diseno').addEventListener('click', async () => {
  const btn = document.getElementById('save-diseno');
  if (!myShop) { showToast('Primero completá la información de la marca.'); return; }

  const body = {
    name: myShop.name,
    category: myShop.category,
    description: myShop.description,
    avatarUrl: myShop.avatar_url,
    bannerUrl: myShop.banner_url,
    logoIcon: document.getElementById('design-logo-icon').value.trim(),
    font: document.getElementById('design-font').value,
    colorFondo: document.getElementById('design-color-fondo').value,
    colorTarjeta: document.getElementById('design-color-tarjeta').value,
    colorAcento: document.getElementById('design-color-acento').value,
    colorTexto: document.getElementById('design-color-texto').value,
    bannerHeight: document.getElementById('design-banner-height').value,
    tablonEstructura: document.getElementById('design-tablon-estructura').value,
    estiloTarjeta: document.getElementById('design-estilo-tarjeta').value,
    mostrarBuscador: document.getElementById('design-mostrar-buscador').checked,
  };

  btn.disabled = true;
  try {
    const res = await fetch('api/shops.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error al guardar');
    myShop = data.shop;
    myShopId = data.shop.id;
    showToast('Diseño guardado ✔');
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo guardar el diseño.');
  } finally {
    btn.disabled = false;
  }
});

// --- Vista previa en vivo del diseño ---
const designInputIds = [
  'design-logo-icon', 'design-font', 'design-color-fondo', 'design-color-tarjeta',
  'design-color-acento', 'design-color-texto', 'design-banner-height',
  'design-tablon-estructura', 'design-estilo-tarjeta',
];

function renderDesignPreview() {
  const icon = document.getElementById('design-logo-icon').value.trim();
  const name = myShop?.name || 'Mi Tienda';
  const font = document.getElementById('design-font').value;
  const colorFondo = document.getElementById('design-color-fondo').value;
  const colorTarjeta = document.getElementById('design-color-tarjeta').value;
  const colorAcento = document.getElementById('design-color-acento').value;
  const colorTexto = document.getElementById('design-color-texto').value;
  const bannerHeight = document.getElementById('design-banner-height').value || 180;
  const tablonEstructura = document.getElementById('design-tablon-estructura').value;
  const estiloTarjeta = document.getElementById('design-estilo-tarjeta').value;

  const preview = document.getElementById('design-preview');
  preview.style.backgroundColor = colorFondo;
  preview.style.color = colorTexto;
  preview.style.fontFamily = font;

  document.getElementById('design-preview-banner').style.height = bannerHeight + 'px';
  document.getElementById('design-preview-name').textContent = icon ? `${icon} ${name}` : name;

  const tablon = document.getElementById('design-preview-tablon');
  tablon.className = tablonEstructura;

  document.querySelectorAll('.design-pv-card').forEach(card => {
    card.className = `design-pv-card ${estiloTarjeta}`;
    card.style.backgroundColor = colorTarjeta;
    card.style.color = colorTexto;
  });
  document.querySelectorAll('.design-pv-precio').forEach(p => p.style.color = colorAcento);
}

designInputIds.forEach(id => {
  document.getElementById(id).addEventListener('input', renderDesignPreview);
  document.getElementById(id).addEventListener('change', renderDesignPreview);
});
document.getElementById('design-mostrar-buscador').addEventListener('change', renderDesignPreview);

// --- Catálogo ---
const productList = document.getElementById('product-list');
const productEmpty = document.getElementById('product-empty');

function refreshEmpty() {
  if (productEmpty) productEmpty.style.display = productList.children.length === 0 ? 'block' : 'none';
}

function renderProduct(p) {
  const div = document.createElement('div');
  div.className = 'product-list-item';
  const img = p.image_url
    ? `<img src="${escapeAttr(p.image_url)}" alt="">`
    : `<div class="product-thumb-empty"></div>`;
  div.innerHTML = `
    ${img}
    <div class="grow">${escapeHtml(p.name)}</div>
    <div class="price">${escapeHtml(formatPrice(p.price))}</div>
    <button class="btn-danger-ghost" data-id="${p.id}">Eliminar</button>`;
  div.querySelector('button').addEventListener('click', () => deleteProduct(p.id, div));
  productList.appendChild(div);
  refreshEmpty();
}

async function loadProducts() {
  try {
    const res = await fetch('api/products.php', { credentials: 'same-origin' });
    const data = await res.json();
    productList.innerHTML = '';
    (data.products || []).forEach(renderProduct);
    refreshEmpty();
  } catch (e) { console.error(e); }
}

document.getElementById('add-product').addEventListener('click', async () => {
  const btn = document.getElementById('add-product');
  const originalLabel = btn.textContent;

  const name = document.getElementById('prod-name').value.trim();
  if (!name) { showToast('El producto necesita un nombre.'); return; }

  const imageFile = document.getElementById('prod-image').files[0];

  btn.disabled = true;
  try {
    let imageUrl = null;
    if (imageFile) {
      btn.textContent = 'Subiendo imagen…';
      imageUrl = await api.uploadImage(imageFile);
    }

    const body = {
      name,
      price: document.getElementById('prod-price').value,
      imageUrl,
    };

    btn.textContent = 'Agregando…';
    const res = await fetch('api/products.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const created = await res.json();
    if (!res.ok) throw new Error(created.error || 'No se pudo agregar el producto.');
    renderProduct(created);
    document.getElementById('prod-name').value = '';
    document.getElementById('prod-price').value = '';
    document.getElementById('prod-image').value = '';
    setPreviewUrl('preview-product', null);
    showToast('Producto agregado ✔');
    if (!myShopId) loadMyShop(); // el backend crea la tienda si no existía
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo agregar el producto.');
  } finally {
    btn.disabled = false;
    btn.textContent = originalLabel;
  }
});

async function deleteProduct(id, el) {
  try {
    const res = await fetch(`api/products.php?id=${id}`, { method: 'DELETE', credentials: 'same-origin' });
    if (!res.ok) throw new Error('No se pudo eliminar.');
    el.remove();
    refreshEmpty();
  } catch (e) {
    console.error(e);
    showToast('No se pudo eliminar el producto.');
  }
}

// --- Reclamos ---
const reclamoList = document.getElementById('reclamo-list');
const reclamoEmpty = document.getElementById('reclamo-empty');
let reclamosCargados = false;

function refreshReclamoEmpty() {
  if (reclamoEmpty) reclamoEmpty.style.display = reclamoList.children.length === 0 ? 'block' : 'none';
}

function renderReclamo(r) {
  const div = document.createElement('div');
  div.className = 'product-list-item';
  div.style.alignItems = 'flex-start';
  const fecha = new Date(r.createdAt).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  div.innerHTML = `
    <div class="grow">
      <strong>${escapeHtml(r.cliente.name || 'Cliente')}</strong>
      <span style="color:var(--color-text-faint); font-size:12px; margin-left:6px">${fecha}</span>
      <p style="margin:6px 0 0; white-space:pre-wrap">${escapeHtml(r.mensaje)}</p>
    </div>
    <button class="btn-secondary" data-id="${r.id}" data-estado="${r.estado}" style="white-space:nowrap">
      ${r.estado === 'resuelto' ? 'Resuelto ✔' : 'Marcar resuelto'}
    </button>`;
  const toggleBtn = div.querySelector('button');
  toggleBtn.addEventListener('click', async () => {
    const nuevoEstado = toggleBtn.dataset.estado === 'resuelto' ? 'pendiente' : 'resuelto';
    toggleBtn.disabled = true;
    try {
      await api.setReclamoEstado(r.id, nuevoEstado);
      toggleBtn.dataset.estado = nuevoEstado;
      toggleBtn.textContent = nuevoEstado === 'resuelto' ? 'Resuelto ✔' : 'Marcar resuelto';
    } catch (e) {
      showToast(e.message || 'No se pudo actualizar el reclamo.');
    } finally {
      toggleBtn.disabled = false;
    }
  });
  reclamoList.appendChild(div);
  refreshReclamoEmpty();
}

async function loadReclamos() {
  if (reclamosCargados) return;
  reclamosCargados = true;
  try {
    const reclamos = await api.getReclamos();
    reclamoList.innerHTML = '';
    reclamos.forEach(renderReclamo);
    refreshReclamoEmpty();
  } catch (e) {
    console.error(e);
    reclamosCargados = false; // permite reintentar si falló
    showToast(e.message || 'No se pudieron cargar los reclamos.');
  }
}

loadMyShop();
loadProducts();