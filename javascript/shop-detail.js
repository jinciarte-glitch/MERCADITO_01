// shop-detail.js — Carga los datos de una tienda + su catálogo
const shopId = document.currentScript.dataset.shopId;
const tabs = document.querySelectorAll('.shop-tab');
const money = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 2 });
function formatPrice(p) {
  return (p === null || p === undefined || p === '') ? '' : money.format(Number(p));
}
tabs.forEach(tab => tab.addEventListener('click', () => {
  tabs.forEach(t => t.classList.remove('active'));
  tab.classList.add('active');
  document.getElementById('tab-catalogo').style.display = tab.dataset.tab === 'catalogo' ? 'grid' : 'none';
  document.getElementById('tab-info').style.display = tab.dataset.tab === 'info' ? 'block' : 'none';
  const empty = document.getElementById('catalog-empty');
  if (empty) empty.style.display = 'none';
}));

let estiloTarjetaActual = 'card-estilo-moderno';

function productCard(p) {
  const div = document.createElement('div');
  div.className = `product-card ${estiloTarjetaActual}`;
  const img = p.image_url
    ? `<img class="product-img" src="${escapeAttr(p.image_url)}" alt="${escapeAttr(p.name)}">`
    : `<div class="product-img product-img-empty"></div>`;
  div.innerHTML = `
    ${img}
    <div class="product-info">
      <div class="product-name">${escapeHtml(p.name)}</div>
      <div class="product-price">${escapeHtml(formatPrice(p.price))}</div>
    </div>`;
  return div;
}

const THEME_DEFAULTS = {
  light: { fondo: '#FBF7F1', tarjeta: '#FFFFFF', acento: '#6B4226', texto: '#3B2418' },
  dark:  { fondo: '#0A0D0C', tarjeta: '#0F1917', acento: '#1E9A85', texto: '#E7F5F1' },
};
function currentThemeDefaults() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return isDark ? THEME_DEFAULTS.dark : THEME_DEFAULTS.light;
}

// --- Aplica el diseño guardado por el emprendedor ---
function applyDesign(shop) {
  const main = document.querySelector('.shop-main');
  const def = currentThemeDefaults();
  main.style.setProperty('--scolor-fondo', shop.color_fondo || def.fondo);
  main.style.setProperty('--scolor-tarjeta', shop.color_tarjeta || def.tarjeta);
  main.style.setProperty('--scolor-acento', shop.color_acento || def.acento);
  main.style.setProperty('--scolor-texto', shop.color_texto || def.texto);
  main.style.fontFamily = shop.font || "'Work Sans', sans-serif";

  document.getElementById('shop-banner').style.height = (shop.banner_height || 180) + 'px';
  document.getElementById('shop-logo-icon').textContent = shop.logo_icon ? shop.logo_icon + ' ' : '';

  const catalogEl = document.getElementById('tab-catalogo');
  catalogEl.className = shop.tablon_estructura || 'tablon-grid-3';

  estiloTarjetaActual = shop.estilo_tarjeta || 'card-estilo-moderno';

  const searchContainer = document.getElementById('shop-search-container');
  if (searchContainer) searchContainer.style.display = shop.mostrar_buscador ? 'block' : 'none';
}

document.getElementById('shop-search-input')?.addEventListener('input', (e) => {
  const q = e.target.value.trim().toLowerCase();
  document.querySelectorAll('#tab-catalogo .product-card').forEach(card => {
    const name = card.querySelector('.product-name')?.textContent.toLowerCase() || '';
    card.style.display = name.includes(q) ? '' : 'none';
  });
});

let currentShop = null;

async function loadShop() {
  if (!shopId) {
    showToast('No se indicó qué emprendimiento mostrar.');
    document.getElementById('shop-loading')?.remove();
    document.querySelector('.shop-main')?.classList.add('is-ready');
    return;
  }
  try {
    const res = await fetch(`api/shops.php?id=${encodeURIComponent(shopId)}`, { credentials: 'same-origin' });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new Error(errBody.error || 'No se encontró el emprendimiento.');
    }
    const shop = await res.json();
    currentShop = shop;

    applyDesign(shop);

    document.getElementById('shop-name').textContent = shop.name || 'Emprendimiento';
    document.getElementById('shop-desc').textContent = shop.description || 'Sin descripción todavía.';
    document.getElementById('shop-info-full').textContent = shop.description || 'Sin descripción todavía.';
    document.getElementById('shop-info-cat').textContent = shop.category || '—';
    const avatarEl = document.getElementById('shop-avatar');
    avatarEl.src = shop.avatar_url || avatarUrl(shop.name);
    if (shop.banner_url) {
      const banner = document.getElementById('shop-banner');
      banner.style.backgroundImage = `url('${shop.banner_url}')`;
      banner.style.backgroundSize = 'cover';
      banner.style.backgroundPosition = 'center';
    }
    const catalogEl = document.getElementById('tab-catalogo');
    catalogEl.innerHTML = '';
    const products = shop.products || [];
    products.forEach(p => catalogEl.appendChild(productCard(p)));
    const empty = document.getElementById('catalog-empty');
    if (empty) empty.style.display = products.length === 0 ? 'block' : 'none';
  } catch (e) {
    console.error('Error cargando la tienda', e);
    showToast(e.message || 'Error cargando la tienda.');
  } finally {
    document.getElementById('shop-loading')?.remove();
    document.querySelector('.shop-main')?.classList.add('is-ready');
  }
}
loadShop();

document.addEventListener('mercadito:themechange', () => {
  if (currentShop) applyDesign(currentShop);
});