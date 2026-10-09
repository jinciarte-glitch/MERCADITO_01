// denuncia.js — modal reutilizable para denunciar publicaciones, comentarios,
// usuarios y emprendimientos. Requiere ui.js (showToast) y api.js (apiRequest).
//
// Uso 1 (automático): cualquier elemento con estos atributos abre el modal al tocarlo:
//   <button data-denuncia-tipo="usuario" data-denuncia-id="12">Denunciar</button>
//   tipos: publicacion | comentario | usuario | emprendimiento
// Uso 2 (desde JS):   abrirDenuncia('publicacion', 34);
(function () {
  const MOTIVOS = [
    ['spam', 'Spam o publicidad engañosa'],
    ['contenido_inapropiado', 'Contenido inapropiado'],
    ['acoso', 'Acoso o maltrato'],
    ['estafa', 'Posible estafa'],
    ['otro', 'Otro motivo'],
  ];
  const TITULOS = {
    publicacion: 'Denunciar publicación',
    comentario: 'Denunciar comentario',
    usuario: 'Denunciar usuario',
    emprendimiento: 'Denunciar emprendimiento',
  };

  let modal = null;
  let actual = null; // { tipo, objetivoId }

  function inyectarEstilos() {
    if (document.getElementById('denuncia-estilos')) return;
    const st = document.createElement('style');
    st.id = 'denuncia-estilos';
    st.textContent = `
      .dn-overlay{ position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; }
      .dn-overlay[hidden]{ display:none; }
      .dn-backdrop{ position:absolute; inset:0; background:var(--color-shadow, rgba(0,0,0,.45)); }
      .dn-box{ position:relative; width:100%; max-width:440px; padding:24px; background:var(--color-bg-card, #fff);
               border-radius:var(--radius-lg, 14px); box-shadow:0 10px 40px rgba(0,0,0,.25); }
      .dn-box h3{ margin:0 0 4px; font-family:'Fraunces', serif; color:var(--color-text-strong); }
      .dn-box p{ margin:0 0 14px; font-size:13.5px; color:var(--color-text-muted); }
      .dn-box label{ display:block; margin:12px 0 6px; font-size:13px; font-weight:600; color:var(--color-text-strong); }
      .dn-box select, .dn-box textarea{ width:100%; box-sizing:border-box; padding:10px 12px; font:inherit; font-size:14px;
               color:var(--color-text-strong); background:var(--color-bg-input); border:1.5px solid var(--color-border-strong); border-radius:10px; }
      .dn-box textarea{ resize:vertical; }
      .dn-acciones{ display:flex; gap:10px; justify-content:flex-end; margin-top:18px; }
    `;
    document.head.appendChild(st);
  }

  function construir() {
    if (modal) return;
    inyectarEstilos();
    modal = document.createElement('div');
    modal.className = 'dn-overlay';
    modal.hidden = true;
    modal.innerHTML = `
      <div class="dn-backdrop" data-dn-cerrar></div>
      <div class="dn-box" role="dialog" aria-modal="true" aria-labelledby="dn-titulo">
        <h3 id="dn-titulo">Denunciar</h3>
        <p>Tu denuncia le llega al equipo de Mercadito, que la va a revisar.</p>
        <label for="dn-motivo">Motivo</label>
        <select id="dn-motivo">
          <option value="">Elegí un motivo…</option>
          ${MOTIVOS.map(([v, t]) => `<option value="${v}">${t}</option>`).join('')}
        </select>
        <label for="dn-detalle">Detalle (opcional)</label>
        <textarea id="dn-detalle" rows="4" maxlength="500" placeholder="Contanos qué pasó…"></textarea>
        <div class="dn-acciones">
          <button type="button" class="btn-secondary" data-dn-cerrar>Cancelar</button>
          <button type="button" class="btn-primary" id="dn-enviar">Enviar denuncia</button>
        </div>
      </div>`;
    document.body.appendChild(modal);

    modal.addEventListener('click', (e) => {
      if (e.target.closest('[data-dn-cerrar]')) cerrar();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !modal.hidden) cerrar();
    });
    modal.querySelector('#dn-enviar').addEventListener('click', enviar);
  }

  function cerrar() {
    if (modal) modal.hidden = true;
    actual = null;
  }

  async function enviar() {
    if (!actual) return;
    const motivo = modal.querySelector('#dn-motivo').value;
    const detalle = modal.querySelector('#dn-detalle').value.trim();
    if (!motivo) { showToast('Elegí un motivo para la denuncia.'); return; }

    const boton = modal.querySelector('#dn-enviar');
    boton.disabled = true;
    try {
      await apiRequest('/api/denuncias.php', {
        method: 'POST',
        body: JSON.stringify({ tipo: actual.tipo, objetivoId: Number(actual.objetivoId), motivo, detalle }),
      });
      showToast('Denuncia enviada. Gracias por avisarnos.');
      cerrar();
    } catch (err) {
      showToast(err.message || 'No se pudo enviar la denuncia.');
    }
    boton.disabled = false;
  }

  window.abrirDenuncia = function (tipo, objetivoId) {
    if (!TITULOS[tipo] || !objetivoId) return;
    construir();
    actual = { tipo, objetivoId };
    modal.querySelector('#dn-titulo').textContent = TITULOS[tipo];
    modal.querySelector('#dn-motivo').value = '';
    modal.querySelector('#dn-detalle').value = '';
    modal.hidden = false;
    modal.querySelector('#dn-motivo').focus();
  };

  // Botones con data-denuncia-tipo / data-denuncia-id (también los creados después).
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-denuncia-tipo]');
    if (b) {
      e.preventDefault();
      window.abrirDenuncia(b.dataset.denunciaTipo, b.dataset.denunciaId);
    }
  });
})();