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
}));

// --- Vista previa de imágenes por URL ---
function bindPreview(inputId, previewId) {
  const input = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!input || !preview) return;
  function refresh() {
    const url = input.value.trim();
    if (url && /^https?:\/\//i.test(url)) {
      preview.style.backgroundImage = `url('${url}')`;
      preview.classList.add('has-img');
    } else {
      preview.style.backgroundImage = '';
      preview.classList.remove('has-img');
    }
  }
  input.addEventListener('input', refresh);
  refresh();
}
bindPreview('input-avatar', 'preview-avatar');
bindPreview('input-banner', 'preview-banner');
bindPreview('prod-image', 'preview-product');

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
    setPreviewUrl('preview-avatar', shop.avatar_url);
    setPreviewUrl('preview-banner', shop.banner_url);
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
  const body = {
    name: document.getElementById('input-name').value.trim(),
    category: document.getElementById('input-category').value,
    description: document.getElementById('input-desc').value.trim(),
    avatarUrl: document.getElementById('input-avatar').value.trim(),
    bannerUrl: document.getElementById('input-banner').value.trim(),
  };
  if (!body.name) { showToast('Poné un nombre para tu emprendimiento.'); return; }

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
    showVerTienda();
    showToast('Cambios guardados ✔');
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo guardar. Intentá de nuevo.');
  } finally {
    btn.disabled = false;
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
  const body = {
    name: document.getElementById('prod-name').value.trim(),
    price: document.getElementById('prod-price').value,
    imageUrl: document.getElementById('prod-image').value.trim(),
  };
  if (!body.name) { showToast('El producto necesita un nombre.'); return; }

  btn.disabled = true;
  try {
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
    bindPreview('prod-image', 'preview-product');
    document.getElementById('preview-product').style.backgroundImage = '';
    document.getElementById('preview-product').classList.remove('has-img');
    showToast('Producto agregado ✔');
    if (!myShopId) loadMyShop(); // el backend crea la tienda si no existía
  } catch (e) {
    console.error(e);
    showToast(e.message || 'No se pudo agregar el producto.');
  } finally {
    btn.disabled = false;
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

loadMyShop();
loadProducts();