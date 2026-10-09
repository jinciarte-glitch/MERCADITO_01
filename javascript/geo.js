/* ============================================================
   geo.js — ubicación para el filtro por zona (estilo Marketplace).
   - Pide permiso con navigator.geolocation (el navegador muestra
     el cartel nativo "Permitir ubicación").
   - Guarda la última ubicación en localStorage para no pedirla
     en cada visita y para adjuntarla al publicar.
   - Reverse-geocode gratuito (BigDataCloud, sin API key) para
     mostrar "Barrio, Ciudad" en vez de números crudos.
   - Fallback por IP (ipapi.co, sin key): en PC sin GPS o con el
     permiso denegado igual se obtiene una zona aproximada.
   IMPORTANTE: request() resuelve APENAS tiene coordenadas (no
   espera al reverse-geocode) para que nunca se quede colgado en
   "Localizando…".
   ============================================================ */

const Geo = (() => {
  const K = {
    lat: 'mercadito-geo-lat',
    lng: 'mercadito-geo-lng',
    place: 'mercadito-geo-place',
    radius: 'mercadito-geo-radius',
    mode: 'mercadito-geo-mode', // 'near' | 'all'
    includeWithout: 'mercadito-geo-include-without', // '1' | '0'
  };

  function getCached() {
    try {
      const lat = parseFloat(localStorage.getItem(K.lat));
      const lng = parseFloat(localStorage.getItem(K.lng));
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
      if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
      return {
        lat,
        lng,
        place: localStorage.getItem(K.place) || null,
      };
    } catch (_) {
      return null;
    }
  }

  function getPrefs() {
    let radius = 25;
    let mode = 'near';
    let includeWithout = true;
    try {
      const r = parseFloat(localStorage.getItem(K.radius));
      if (Number.isFinite(r) && r >= 1 && r <= 500) radius = r;
      if (localStorage.getItem(K.mode) === 'all') mode = 'all';
      if (localStorage.getItem(K.includeWithout) === '0') includeWithout = false;
    } catch (_) { /* valores por defecto */ }
    return { radius, mode, includeWithout };
  }

  function savePrefs({ radius, mode, includeWithout }) {
    try {
      if (radius) localStorage.setItem(K.radius, String(radius));
      if (mode) localStorage.setItem(K.mode, mode);
      if (includeWithout !== undefined) localStorage.setItem(K.includeWithout, includeWithout ? '1' : '0');
    } catch (_) {}
  }

  function save(loc) {
    try {
      localStorage.setItem(K.lat, String(loc.lat));
      localStorage.setItem(K.lng, String(loc.lng));
      if (loc.place) localStorage.setItem(K.place, loc.place);
      else localStorage.removeItem(K.place);
    } catch (_) {}
  }

  function clear() {
    try {
      localStorage.removeItem(K.lat);
      localStorage.removeItem(K.lng);
      localStorage.removeItem(K.place);
    } catch (_) {}
  }

  function emitPlace(loc) {
    try {
      window.dispatchEvent(new CustomEvent('mercadito:geoplace', { detail: loc }));
    } catch (_) {}
  }

  // Pide la ubicación al navegador (muestra el permiso nativo).
  // Resuelve APENAS tiene coordenadas; el nombre del lugar llega
  // después en segundo plano (evento 'mercadito:geoplace').
  // Rechaza si no hay soporte, contexto inseguro, denegado o timeout.
  function request({ timeout = 15000 } = {}) {
    return new Promise((resolve, reject) => {
      if (!('geolocation' in navigator)) {
        reject(new Error('Tu navegador no soporta geolocalización.'));
        return;
      }
      if (window.isSecureContext === false) {
        reject(new Error('La ubicación necesita HTTPS o localhost. Abrí el sitio de forma segura para usarla.'));
        return;
      }
      let settled = false;
      const done = (fn, val) => { if (!settled) { settled = true; fn(val); } };
      const timer = setTimeout(() => {
        done(reject, new Error('La ubicación tardó demasiado. Probá de nuevo o revisá el permiso del navegador.'));
      }, timeout + 2000);
      try {
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            clearTimeout(timer);
            const loc = { lat: pos.coords.latitude, lng: pos.coords.longitude, place: null, approx: false };
            if (!Number.isFinite(loc.lat) || !Number.isFinite(loc.lng)) {
              done(reject, new Error('No se pudo obtener tu ubicación.'));
              return;
            }
            save(loc);
            done(resolve, loc);
            // Nombre del lugar en segundo plano, sin bloquear.
            reverse(loc.lat, loc.lng).then((place) => {
              if (place) { loc.place = place; save(loc); emitPlace(loc); }
            }).catch(() => {});
          },
          (err) => {
            clearTimeout(timer);
            if (err && err.code === 1) done(reject, new Error('Permiso de ubicación denegado.'));
            else if (err && err.code === 3) done(reject, new Error('La ubicación tardó demasiado. Probá de nuevo.'));
            else done(reject, new Error('No se pudo obtener tu ubicación.'));
          },
          // En PC (sin GPS) highAccuracy=true suele colgarse: usar false.
          { enableHighAccuracy: false, timeout, maximumAge: 10 * 60 * 1000 }
        );
      } catch (_) {
        clearTimeout(timer);
        done(reject, new Error('No se pudo obtener tu ubicación.'));
      }
    });
  }

  // Ubicación aproximada por IP (no pide permiso, ideal como
  // fallback en PC). Guarda en caché igual que el GPS.
  async function requestIP({ timeout = 8000 } = {}) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeout);
    try {
      const res = await fetch('https://ipapi.co/json/', { signal: ctrl.signal });
      if (!res.ok) throw new Error('IP fail');
      const d = await res.json();
      const lat = parseFloat(d.latitude);
      const lng = parseFloat(d.longitude);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) throw new Error('IP sin coordenadas');
      const place = [d.city, d.country_name].filter(Boolean).join(', ') || null;
      const loc = { lat, lng, place, approx: true };
      save(loc);
      emitPlace(loc);
      return loc;
    } catch (_) {
      throw new Error('Tampoco se pudo estimar tu zona por internet.');
    } finally {
      clearTimeout(t);
    }
  }

  // Intenta GPS y, si falla, cae a IP. Devuelve { loc, method }.
  // Si allowIP es false, propaga el error original del GPS.
  async function ensure({ timeout = 15000, allowIP = true } = {}) {
    try {
      const loc = await request({ timeout });
      return { loc, method: 'gps' };
    } catch (gpsErr) {
      if (!allowIP) throw gpsErr;
      try {
        const loc = await requestIP();
        return { loc, method: 'ip' };
      } catch (_) {
        throw gpsErr; // mostrar el error original (más accionable)
      }
    }
  }

  // Diagnóstico para dar mensajes accionables (permiso denegado,
  // contexto inseguro, sin soporte). Nunca rechaza.
  async function diagnose() {
    const out = {
      supported: ('geolocation' in navigator),
      secure: window.isSecureContext !== false,
      permission: 'unknown',
    };
    try {
      if (navigator.permissions && navigator.permissions.query) {
        const s = await navigator.permissions.query({ name: 'geolocation' });
        out.permission = s.state; // 'granted' | 'denied' | 'prompt'
      }
    } catch (_) {}
    return out;
  }

  // Convierte coords en "Barrio, Ciudad" con un servicio gratuito sin key.
  async function reverse(lat, lng) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 6000);
    try {
      const url = `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${encodeURIComponent(lat)}&longitude=${encodeURIComponent(lng)}&localityLanguage=es`;
      const res = await fetch(url, { signal: ctrl.signal });
      if (!res.ok) return null;
      const d = await res.json();
      const parts = [d.neighbourhood || d.locality || d.city, d.city && d.neighbourhood ? d.city : d.principalSubdivision].filter(Boolean);
      const uniq = [...new Set(parts)];
      return uniq.length ? uniq.join(', ') : null;
    } catch (_) {
      return null;
    } finally {
      clearTimeout(t);
    }
  }

  function formatDistance(km) {
    if (km === null || km === undefined || !Number.isFinite(Number(km))) return null;
    const v = Number(km);
    if (v < 1) return `${Math.max(100, Math.round(v * 1000))} m`;
    if (v < 10) return `${v.toFixed(1)} km`;
    return `${Math.round(v)} km`;
  }

  return { getCached, getPrefs, savePrefs, save, clear, request, requestIP, ensure, diagnose, reverse, formatDistance };
})();
