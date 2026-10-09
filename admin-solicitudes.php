<?php
// admin-solicitudes.php — Panel de administrador: filtrar y resolver solicitudes de emprendedor
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) { header('Location: /api/login.php'); exit; }
if (!is_administrador($pdo)) { header('Location: /feed.php'); exit; }

$navActive = 'admin-solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){try{var t=localStorage.getItem("mercadito-theme");if(!t){t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}if(t==="dark"){document.documentElement.setAttribute("data-theme","dark");}}catch(e){}})();</script>
    <link rel="shortcut icon" type="image/x-icon" href="icon.ico" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes de emprendedores — Mercadito</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="shop.css">
<style>
  .filtros{ display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
  .filtros button{
    font-family:'Work Sans', sans-serif; font-size:13.5px; font-weight:600;
    padding:8px 16px; border-radius:999px; border:1.5px solid var(--color-border-strong);
    background:none; color:var(--color-text-muted); cursor:pointer;
  }
  .filtros button.activo{ background:var(--color-accent); color:var(--color-on-accent); border-color:var(--color-accent); }
  .card-solicitud{ padding:18px; margin-bottom:14px; }
  .card-solicitud h3{ margin:0 0 4px; font-family:'Fraunces', serif; color:var(--color-text-strong); }
  .card-solicitud .acciones{ margin-top:12px; display:flex; gap:10px; }
  .badge-rechazada{ background:var(--color-error); color:#fff; }
  .badge-resuelto{ background:var(--color-accent); color:var(--color-on-accent); }
  .badge-aprobada{ background:var(--color-accent); color:var(--color-on-accent); }
</style>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
</head>
<body class="shop-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="shop-main">

  <div class="explore-intro">
    <h2 class="explore-title brand-font">Solicitudes, reclamos y denuncias</h2>
    <p class="explore-sub">Revisá los pedidos de certificación, los reclamos sobre la página y las denuncias de los usuarios.</p>
  </div>

  <div class="filtros" id="secciones">
    <button data-seccion="solicitudes" class="activo">Solicitudes de emprendedores</button>
    <button data-seccion="reclamos">Reclamos de la página <span id="c-reclamos"></span></button>
    <button data-seccion="denuncias">Denuncias <span id="c-denuncias"></span></button>
    <button data-seccion="moderacion">Moderación</button>
  </div>

  <div class="filtros" id="filtros"></div>

  <div id="lista">Cargando...</div>

</main>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/nav.js"></script>
<script>
let seccion = (location.hash === '#moderacion') ? 'moderacion' : 'solicitudes';
let estadoActual = 'pendiente';
let histQ = '';       // buscador del historial de moderación
let histFecha = '';
let histTimer = null;

// Botones de filtro de cada sección: [estado, texto, id del contador]
const FILTROS = {
    solicitudes: [
        ['pendiente', 'Pendientes', 'c-pendiente'],
        ['aprobada',  'Aprobadas',  'c-aprobada'],
        ['rechazada', 'Rechazadas', 'c-rechazada'],
        ['todas',     'Todas',      null],
    ],
    reclamos: [
        ['pendiente', 'Pendientes', 'c-pendiente'],
        ['resuelto',  'Resueltos',  'c-resuelto'],
        ['todos',     'Todos',      null],
    ],
    denuncias: [
        ['pendiente',  'Pendientes',  'c-pendiente'],
        ['resuelta',   'Resueltas',   'c-resuelta'],
        ['descartada', 'Descartadas', 'c-descartada'],
        ['todas',      'Todas',       null],
    ],
    moderacion: [],
};

const TIPOS_DENUNCIA = {
    publicacion: 'Publicación', comentario: 'Comentario',
    usuario: 'Usuario', emprendimiento: 'Emprendimiento',
};
const MOTIVOS_DENUNCIA = {
    spam: 'Spam o publicidad engañosa', contenido_inapropiado: 'Contenido inapropiado',
    acoso: 'Acoso o maltrato', estafa: 'Posible estafa', otro: 'Otro motivo',
};

function pintarFiltros() {
    document.getElementById('filtros').innerHTML = FILTROS[seccion].map(([estado, texto, idCont]) =>
        `<button data-estado="${estado}" class="${estado === estadoActual ? 'activo' : ''}">${texto}${idCont ? ` <span id="${idCont}"></span>` : ''}</button>`
    ).join('');
    document.querySelectorAll('#secciones button').forEach(b => {
        b.classList.toggle('activo', b.dataset.seccion === seccion);
    });
}

function cargar() {
    pintarFiltros();
    if (seccion === 'solicitudes') return cargarSolicitudes();
    if (seccion === 'reclamos')    return cargarReclamos();
    if (seccion === 'moderacion')  return cargarModeracion();
    return cargarDenuncias();
}

// ---------------- Solicitudes de emprendedores ----------------
async function cargarSolicitudes() {
    const lista = document.getElementById('lista');
    lista.innerHTML = '<p class="explore-sub">Cargando...</p>';

    let data;
    try {
        data = await apiRequest(`api/solicitudes.php?estado=${estadoActual}`);
    } catch (err) {
        lista.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
        return;
    }

    document.getElementById('c-pendiente').textContent = `(${data.counts.pendiente})`;
    document.getElementById('c-aprobada').textContent  = `(${data.counts.aprobada})`;
    document.getElementById('c-rechazada').textContent = `(${data.counts.rechazada})`;

    if (data.solicitudes.length === 0) {
        lista.innerHTML = '<div class="empty-state">No hay solicitudes en este estado.</div>';
        return;
    }

    lista.innerHTML = data.solicitudes.map(s => {
        const badgeClase = s.estado === 'rechazada' ? ' badge-rechazada' : (s.estado === 'aprobada' ? ' badge-aprobada' : '');
        const acciones = s.estado === 'pendiente'
            ? `<div class="acciones">
                   <button class="btn-primary" onclick="resolver(${s.id}, 'aprobar')">Aprobar</button>
                   <button class="btn-secondary" onclick="resolver(${s.id}, 'rechazar')">Rechazar</button>
               </div>`
            : (s.comentario_admin ? `<p class="explore-sub"><em>Comentario:</em> ${escapeHtml(s.comentario_admin)}</p>` : '');

        return `
        <div class="card card-solicitud" data-id="${s.id}">
            <h3>${escapeHtml(s.nombre_emprendimiento)}</h3>
            <p class="explore-sub">
                ${escapeHtml(s.nombre_usuario)} · ${escapeHtml(s.email_usuario)} · ${escapeHtml(s.categoria || 'sin rubro')} ·
                <span class="badge${badgeClase}">${s.estado}</span>
            </p>
            ${s.descripcion ? `<p>${escapeHtml(s.descripcion)}</p>` : ''}
            <p><strong>Motivo:</strong> ${escapeHtml(s.motivo)}</p>
            <p class="explore-sub">
                <strong>Documento:</strong> ${escapeHtml(s.tipo_documento || '—')} ${escapeHtml(s.numero_documento || '')}
                · <strong>Edad:</strong> ${s.edad != null ? s.edad + ' años' : '—'}
                · <strong>Declaración de licitud:</strong> ${s.declaracion_licitud ? 'Aceptada ✓' : 'No aceptada ✗'}
            </p>
            ${acciones}
        </div>`;
    }).join('');
}

async function resolver(id, accion) {
    let comentario = '';
    if (accion === 'rechazar') {
        comentario = prompt('Motivo del rechazo (opcional):') || '';
    }

    try {
        await apiRequest(`api/solicitudes.php?action=${accion}&id=${id}`, {
            method: 'POST',
            body: JSON.stringify({ comentario }),
        });
        showToast(accion === 'aprobar' ? 'Solicitud aprobada.' : 'Solicitud rechazada.');
        cargar();
    } catch (err) {
        showToast(err.message);
    }
}

// ---------------- Reclamos generales de la página ----------------
async function cargarReclamos() {
    const lista = document.getElementById('lista');
    lista.innerHTML = '<p class="explore-sub">Cargando...</p>';

    let data;
    try {
        data = await apiRequest(`api/reclamos-generales.php?estado=${estadoActual}`);
    } catch (err) {
        lista.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
        return;
    }

    document.getElementById('c-pendiente').textContent = `(${data.counts.pendiente})`;
    document.getElementById('c-resuelto').textContent  = `(${data.counts.resuelto})`;
    document.getElementById('c-reclamos').textContent  = `(${data.counts.pendiente})`;

    if (data.reclamos.length === 0) {
        lista.innerHTML = '<div class="empty-state">No hay reclamos en este estado.</div>';
        return;
    }

    lista.innerHTML = data.reclamos.map(r => {
        const resuelto = r.estado === 'resuelto';
        const fecha = new Date(r.createdAt).toLocaleString('es-AR');
        return `
        <div class="card card-solicitud" data-id="${r.id}">
            <h3>${escapeHtml(r.usuario.name)}</h3>
            <p class="explore-sub">
                ${escapeHtml(r.usuario.email)} · ${escapeHtml(fecha)} ·
                <span class="badge${resuelto ? ' badge-resuelto' : ''}">${r.estado}</span>
            </p>
            <p>${escapeHtml(r.mensaje)}</p>
            <div class="acciones">
                <button class="${resuelto ? 'btn-secondary' : 'btn-primary'}"
                        onclick="cambiarEstadoReclamo(${r.id}, '${resuelto ? 'pendiente' : 'resuelto'}')">
                    ${resuelto ? 'Reabrir' : 'Marcar como resuelto'}
                </button>
            </div>
        </div>`;
    }).join('');
}

async function cambiarEstadoReclamo(id, estado) {
    try {
        await apiRequest(`api/reclamos-generales.php?id=${id}`, {
            method: 'POST',
            body: JSON.stringify({ estado }),
        });
        showToast(estado === 'resuelto' ? 'Reclamo marcado como resuelto.' : 'Reclamo reabierto.');
        cargar();
    } catch (err) {
        showToast(err.message);
    }
}

// ---------------- Denuncias ----------------
async function cargarDenuncias() {
    const lista = document.getElementById('lista');
    lista.innerHTML = '<p class="explore-sub">Cargando...</p>';

    let data;
    try {
        data = await apiRequest(`api/denuncias.php?estado=${estadoActual}`);
    } catch (err) {
        lista.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
        return;
    }

    document.getElementById('c-pendiente').textContent  = `(${data.counts.pendiente})`;
    document.getElementById('c-resuelta').textContent   = `(${data.counts.resuelta})`;
    document.getElementById('c-descartada').textContent = `(${data.counts.descartada})`;
    document.getElementById('c-denuncias').textContent  = `(${data.counts.pendiente})`;

    if (data.denuncias.length === 0) {
        lista.innerHTML = '<div class="empty-state">No hay denuncias en este estado.</div>';
        return;
    }

    lista.innerHTML = data.denuncias.map(d => {
        const fecha = new Date(d.createdAt).toLocaleString('es-AR');
        const badgeClase = d.estado === 'resuelta' ? ' badge-resuelto' : (d.estado === 'descartada' ? ' badge-rechazada' : '');
        const acciones = d.estado === 'pendiente'
            ? `<button class="btn-primary" onclick="cambiarEstadoDenuncia(${d.id}, 'resuelta')">Marcar como resuelta</button>
               <button class="btn-secondary" onclick="cambiarEstadoDenuncia(${d.id}, 'descartada')">Descartar</button>`
            : `<button class="btn-secondary" onclick="cambiarEstadoDenuncia(${d.id}, 'pendiente')">Reabrir</button>`;
        const ver = d.url ? `<a href="${escapeAttr(d.url)}" target="_blank" rel="noopener">Ver</a>` : '';

        return `
        <div class="card card-solicitud" data-id="${d.id}">
            <h3>${escapeHtml(TIPOS_DENUNCIA[d.tipo] || d.tipo)}: ${escapeHtml(d.objetivo)} ${ver}</h3>
            <p class="explore-sub">
                <strong>Motivo:</strong> ${escapeHtml(MOTIVOS_DENUNCIA[d.motivo] || d.motivo)} ·
                <span class="badge${badgeClase}">${d.estado}</span>
            </p>
            ${d.detalle ? `<p>${escapeHtml(d.detalle)}</p>` : ''}
            <p class="explore-sub">
                Denunciado por ${escapeHtml(d.denunciante.name)} (${escapeHtml(d.denunciante.email)}) · ${escapeHtml(fecha)}
            </p>
            <div class="acciones">${acciones}${botonesModeracion(d)}</div>
        </div>`;
    }).join('');
}

async function cambiarEstadoDenuncia(id, estado) {
    const cuerpo = { estado };
    if (estado === 'resuelta' || estado === 'descartada') {
        const msg = prompt('Mensaje para quien denunció (opcional). Se le avisa el resultado por mail y en pantalla:');
        if (msg === null) return; // canceló
        cuerpo.mensaje = msg;
    }
    try {
        await apiRequest(`api/denuncias.php?id=${id}`, {
            method: 'POST',
            body: JSON.stringify(cuerpo),
        });
        showToast(estado === 'pendiente' ? 'Denuncia reabierta.' : (estado === 'resuelta' ? 'Denuncia marcada como resuelta.' : 'Denuncia descartada.'));
        cargar();
    } catch (err) {
        showToast(err.message);
    }
}

async function cargarContadorDenuncias() {
    try {
        const data = await apiRequest('api/denuncias.php?estado=pendiente');
        document.getElementById('c-denuncias').textContent = `(${data.counts.pendiente})`;
    } catch (_) { /* si falla, la pestaña queda sin número */ }
}

// ---------------- Moderación: suspender / eliminar ----------------
// Botones de moderación para una denuncia: actúan sobre lo denunciado
// (usuario o emprendimiento) o, si es una publicación/comentario, sobre su autor.
function botonesModeracion(d) {
    let obj = null;
    if ((d.tipo === 'usuario' || d.tipo === 'emprendimiento') && d.objetivo !== '(ya no existe)') {
        obj = { tipo: d.tipo, id: d.objetivoId, nombre: d.objetivo, etiqueta: d.tipo };
    } else if ((d.tipo === 'publicacion' || d.tipo === 'comentario') && d.autorId) {
        obj = { tipo: 'usuario', id: d.autorId, nombre: 'el autor del contenido denunciado', etiqueta: 'autor' };
    }
    if (!obj) return '';
    const attrs = `data-mod-tipo="${obj.tipo}" data-mod-id="${obj.id}" data-mod-nombre="${escapeAttr(obj.nombre)}"`;
    return `<button class="btn-secondary" data-mod-accion="suspender" ${attrs}>Suspender ${obj.etiqueta}</button>
            <button class="btn-secondary" data-mod-accion="eliminar" ${attrs}>Eliminar ${obj.etiqueta}</button>`;
}

async function moderar(accion, tipo, id, nombre) {
    const payload = { accion, tipo, id };

    if (accion === 'suspender') {
        const dias = parseInt(prompt(`¿Por cuántos días querés suspender a: ${nombre}?`, '3'), 10);
        if (!dias || dias < 1) return;
        payload.dias = dias;
        payload.motivo = prompt('Motivo (opcional):') || '';
    } else if (accion === 'eliminar') {
        const extra = tipo === 'usuario'
            ? ' También se elimina su emprendimiento, si tiene uno.'
            : ' A su dueño se le retira el rol de emprendedor de forma permanente.';
        if (!confirm(`¿Eliminar definitivamente: ${nombre}?${extra} Se le avisa por mail. No se puede deshacer.`)) return;
        payload.motivo = prompt('Motivo de la eliminación (se le envía por mail):') || '';
    }

    try {
        const r = await apiRequest('api/moderacion.php', { method: 'POST', body: JSON.stringify(payload) });
        showToast(r.mensaje || 'Listo.');
        cargar();
    } catch (err) {
        showToast(err.message);
    }
}

const VERBOS = { suspender: 'Suspendió', levantar: 'Levantó la suspensión de', eliminar: 'Eliminó' };

function renderHistorial(items) {
    if (items.length === 0) {
        return (histQ || histFecha)
            ? '<div class="empty-state">No se encontró ninguna acción con ese filtro.</div>'
            : '<div class="empty-state">Todavía no hay acciones registradas.</div>';
    }
    return items.map(h => {
        const quien = h.automatica ? 'Automático' : 'Administrador ' + (h.admin || '(cuenta eliminada)');
        const dias = h.dias ? ` por ${h.dias} día${h.dias === 1 ? '' : 's'}` : '';
        return `<p class="explore-sub">${escapeHtml(new Date(h.fecha).toLocaleString('es-AR'))} · <strong>${quien}</strong>: ${VERBOS[h.accion] || h.accion} ${h.tipo} «${escapeHtml(h.nombre || '—')}»${dias}${h.motivo ? ' — ' + escapeHtml(h.motivo) : ''}</p>`;
    }).join('');
}

// Busca en el historial sin volver a dibujar toda la pantalla (así el cursor no se pierde).
async function buscarHistorial() {
    const caja = document.getElementById('hist-lista');
    if (!caja) return;
    const params = new URLSearchParams({ solo_historial: '1' });
    if (histQ) params.set('q', histQ);
    if (histFecha) params.set('fecha', histFecha);
    try {
        const data = await apiRequest('api/moderacion.php?' + params.toString());
        caja.innerHTML = renderHistorial(data.historial);
    } catch (err) {
        caja.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
    }
}

async function cargarModeracion() {
    const lista = document.getElementById('lista');
    lista.innerHTML = '<p class="explore-sub">Cargando...</p>';

    let data;
    try {
        const ps = new URLSearchParams();
        if (histQ) ps.set('q', histQ);
        if (histFecha) ps.set('fecha', histFecha);
        data = await apiRequest('api/moderacion.php' + (ps.toString() ? '?' + ps.toString() : ''));
    } catch (err) {
        lista.innerHTML = `<p class="explore-sub">${escapeHtml(err.message)}</p>`;
        return;
    }

    const suspendidos = [
        ...data.usuarios.map(u => ({ tipo: 'usuario', etiqueta: 'Usuario', id: u.id, nombre: u.nombre, extra: u.email, hasta: u.hasta, motivo: u.motivo })),
        ...data.emprendimientos.map(s => ({ tipo: 'emprendimiento', etiqueta: 'Emprendimiento', id: s.id, nombre: s.nombre, extra: 'de ' + s.dueno, hasta: s.hasta, motivo: s.motivo })),
    ].sort((a, b) => a.hasta - b.hasta);

    const titulo = (t) => `<h3 class="explore-title brand-font" style="font-size:18px;margin:18px 0 8px">${t}</h3>`;

    let html = titulo(`Suspendidos ahora (${suspendidos.length})`);
    if (suspendidos.length === 0) {
        html += '<div class="empty-state">No hay nadie suspendido en este momento.</div>';
    } else {
        html += suspendidos.map(x => {
            const attrs = `data-mod-tipo="${x.tipo}" data-mod-id="${x.id}" data-mod-nombre="${escapeAttr(x.nombre)}"`;
            return `
            <div class="card card-solicitud">
                <h3>${x.etiqueta}: ${escapeHtml(x.nombre)}</h3>
                <p class="explore-sub">${escapeHtml(x.extra)} · Suspendido hasta ${escapeHtml(new Date(x.hasta).toLocaleString('es-AR'))}</p>
                ${x.motivo ? `<p>${escapeHtml(x.motivo)}</p>` : ''}
                <div class="acciones">
                    <button class="btn-primary" data-mod-accion="levantar" ${attrs}>Levantar suspensión</button>
                    <button class="btn-secondary" data-mod-accion="eliminar" ${attrs}>Eliminar</button>
                </div>
            </div>`;
        }).join('');
    }

    html += titulo('Historial');
    html += `
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
            <input type="search" id="hist-q" placeholder="Buscar por nombre o motivo..." value="${escapeAttr(histQ)}"
                   style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid var(--color-border-strong);border-radius:var(--radius-md);background:transparent;color:var(--color-text-strong);font:inherit">
            <input type="date" id="hist-fecha" value="${escapeAttr(histFecha)}"
                   style="padding:8px 12px;border:1.5px solid var(--color-border-strong);border-radius:var(--radius-md);background:transparent;color:var(--color-text-strong);font:inherit">
            <button type="button" class="btn-secondary" id="hist-limpiar">Limpiar</button>
        </div>
        <div id="hist-lista">${renderHistorial(data.historial)}</div>`;
    lista.innerHTML = html;
}

// Contador de reclamos pendientes en la pestaña, aunque se esté viendo otra sección.
async function cargarContadorReclamos() {
    try {
        const data = await apiRequest('api/reclamos-generales.php?estado=pendiente');
        document.getElementById('c-reclamos').textContent = `(${data.counts.pendiente})`;
    } catch (_) { /* si falla, la pestaña queda sin número */ }
}

// ---------------- Eventos ----------------
document.getElementById('secciones').addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (btn && btn.dataset.seccion && btn.dataset.seccion !== seccion) {
        seccion = btn.dataset.seccion;
        estadoActual = 'pendiente';
        cargar();
    }
});

document.getElementById('filtros').addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (btn && btn.dataset.estado) {
        estadoActual = btn.dataset.estado;
        cargar();
    }
});

// Botones de moderación (suspender / levantar / eliminar), también los que se dibujan después.
document.getElementById('lista').addEventListener('click', (e) => {
    const b = e.target.closest('[data-mod-accion]');
    if (b) moderar(b.dataset.modAccion, b.dataset.modTipo, Number(b.dataset.modId), b.dataset.modNombre);
});

// Buscador del historial de moderación (nombre / motivo y fecha).
document.getElementById('lista').addEventListener('input', (e) => {
    if (e.target.id === 'hist-q' || e.target.id === 'hist-fecha') {
        histQ = document.getElementById('hist-q').value.trim();
        histFecha = document.getElementById('hist-fecha').value;
        clearTimeout(histTimer);
        histTimer = setTimeout(buscarHistorial, 300);
    }
});
document.getElementById('lista').addEventListener('click', (e) => {
    if (e.target.id === 'hist-limpiar') {
        histQ = ''; histFecha = '';
        document.getElementById('hist-q').value = '';
        document.getElementById('hist-fecha').value = '';
        buscarHistorial();
    }
});

cargar();
cargarContadorReclamos();
cargarContadorDenuncias();
</script>
</body>
</html>