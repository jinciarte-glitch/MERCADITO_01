// ui.js — helpers de interfaz compartidos (toast y estados de error).
function showToast(msg){
  const toast = document.getElementById('toast');
  if(!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove('show'), 3200);
}

function setInvalid(fieldId, invalid){
  const field = document.getElementById(fieldId);
  if(field) field.classList.toggle('invalid', invalid);
}

function escapeHtml(str){
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

// Igual que escapeHtml pero además escapa comillas, para usar dentro
// de atributos HTML (src="...", href="...").
function escapeAttr(str){
  return escapeHtml(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
