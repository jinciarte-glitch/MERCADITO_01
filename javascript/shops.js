// shops.js — Marketplace: búsqueda + filtros + scroll infinito
const grid = document.getElementById('shop-grid');
const sentinel = document.getElementById('loader-sentinel');
const emptyState = document.getElementById('empty-state');
const searchInput = document.getElementById('search-input');
const chips = document.querySelectorAll('.chip');

let page = 1, loading = false, done = false, category = 'todos', query = '';

function shopCard(shop) {
  const a = document.createElement('a');
  a.href = `tienda.php?id=${shop.id}`;
  a.className = 'shop-card';
  const bannerStyle = shop.banner_url
    ? `background-image:url('${escapeAttr(shop.banner_url)}');background-size:cover;background-position:center`
    : '';
  const avatar = shop.avatar_url
    ? `<img class="shop-card-avatar" src="${escapeAttr(shop.avatar_url)}" alt="">`
    : `<img class="shop-card-avatar" src="${avatarUrl(shop.name)}" alt="">`;
  a.innerHTML = `
    <div class="shop-card-banner" style="${bannerStyle}"></div>
    <div class="shop-card-body">
      ${avatar}
      <div class="shop-card-name">${escapeHtml(shop.name)}
        <svg class="shop-verified" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 2.4 3.3-.6.6 3.3 2.4 2.4-2.4 2.4-.6 3.3-3.3-.6L12 15l-2.4-2.4-3.3.6-.6-3.3L3.3 7.5 5.7 5.1l.6-3.3 3.3.6z"/></svg>
      </div>
      ${shop.category ? `<div class="shop-card-cat">${escapeHtml(shop.category)}</div>` : ''}
      <div class="shop-card-desc">${escapeHtml(shop.description || '')}</div>
    </div>`;
  return a;
}

async function loadMore() {
  if (loading || done) return;
  loading = true;
  sentinel.style.display = 'block';
  try {
    const params = new URLSearchParams({ page, category, q: query });
    const res = await fetch(`api/shops.php?${params}`, { credentials: 'same-origin' });
    const data = await res.json();
    const list = data.shops || [];
    list.forEach(s => grid.appendChild(shopCard(s)));
    if (list.length === 0) done = true;
    page++;
  } catch (e) {
    console.error('Error cargando emprendimientos', e);
    showToast('No se pudieron cargar los emprendimientos.');
    done = true;
  } finally {
    loading = false;
    sentinel.style.display = done ? 'none' : 'block';
    if (emptyState) emptyState.style.display = (done && grid.children.length === 0) ? 'block' : 'none';
  }
}

function resetGrid() {
  grid.innerHTML = '';
  page = 1; done = false;
  if (emptyState) emptyState.style.display = 'none';
  loadMore();
}

new IntersectionObserver((entries) => {
  if (entries[0].isIntersecting) loadMore();
}).observe(sentinel);

chips.forEach(chip => chip.addEventListener('click', () => {
  chips.forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  category = chip.dataset.cat;
  resetGrid();
}));

let debounce;
searchInput.addEventListener('input', () => {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    query = searchInput.value.trim();
    resetGrid();
  }, 350);
});

loadMore();
