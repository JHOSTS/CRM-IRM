/* ===================================================
   Utils — funções globais reutilizáveis
   =================================================== */

// ---------- API helper ----------
async function api(url, opts = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', ...opts.headers },
      ...opts,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || `Erro ${res.status}`);
    return data;
  } catch (err) {
    throw err;
  }
}

// ---------- Toast ----------
function toast(msg, type = 'success', duration = 3500) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type] || '•'}</span><span>${msg}</span>`;
  container.appendChild(el);
  setTimeout(() => el.remove(), duration);
}

// ---------- Modal ----------
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

function createModal({ id, title, body, size = '', onConfirm, confirmLabel = 'Salvar', confirmClass = 'btn-primary' }) {
  document.getElementById(id)?.remove();
  const div = document.createElement('div');
  div.id = id;
  div.className = 'modal-overlay';
  div.innerHTML = `
    <div class="modal ${size}">
      <div class="modal-header">
        <span class="modal-title">${title}</span>
        <button class="modal-close" onclick="document.getElementById('${id}').remove()">✕</button>
      </div>
      <div class="modal-body">${body}</div>
      <div class="modal-footer">
        <button class="btn btn-ghost" onclick="document.getElementById('${id}').remove()">Cancelar</button>
        <button class="btn ${confirmClass}" id="${id}-confirm">${confirmLabel}</button>
      </div>
    </div>`;
  document.body.appendChild(div);
  if (onConfirm) document.getElementById(`${id}-confirm`).addEventListener('click', onConfirm);
  div.addEventListener('click', e => { if (e.target === div) div.remove(); });
  return div;
}

// ---------- Formato ----------
function fmtMoney(v) {
  if (!v && v !== 0) return '—';
  return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function fmtDate(d) {
  if (!d) return '—';
  // Parsear como data local para evitar deslocamento de fuso
  const s = String(d).slice(0, 10);
  const [y, m, day] = s.split('-').map(Number);
  return new Date(y, m - 1, day).toLocaleDateString();
}

function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString();
}

function fmtDateInput(d) {
  if (!d) return '';
  return d.substring(0, 16);
}

function timeAgo(d) {
  if (!d) return '';
  const diff = (Date.now() - new Date(d)) / 1000;
  if (diff < 60)   return 'agora';
  if (diff < 3600) return Math.floor(diff / 60) + 'm atrás';
  if (diff < 86400)return Math.floor(diff / 3600) + 'h atrás';
  return Math.floor(diff / 86400) + 'd atrás';
}

// ---------- Paginação ----------
function renderPagination(container, current, total, limit, onPage) {
  const pages = Math.ceil(total / limit);
  if (pages <= 1) { container.innerHTML = ''; return; }
  let html = '';
  html += `<button class="page-btn" ${current <= 1 ? 'disabled' : ''} data-p="${current-1}">‹</button>`;
  for (let i = 1; i <= pages; i++) {
    if (pages > 7 && Math.abs(i - current) > 2 && i !== 1 && i !== pages) {
      if (i === 2 || i === pages - 1) html += '<span class="page-btn" style="cursor:default">…</span>';
      continue;
    }
    html += `<button class="page-btn ${i === current ? 'active' : ''}" data-p="${i}">${i}</button>`;
  }
  html += `<button class="page-btn" ${current >= pages ? 'disabled' : ''} data-p="${current+1}">›</button>`;
  container.innerHTML = html;
  container.querySelectorAll('.page-btn[data-p]').forEach(btn => {
    btn.addEventListener('click', () => onPage(+btn.dataset.p));
  });
}

// ---------- Status badge ----------
function statusBadge(status) {
  const map = {
    em_andamento: ['badge-info', 'Em andamento'],
    ganho:        ['badge-success', 'Ganho'],
    perdido:      ['badge-danger', 'Perdido'],
    ativo:        ['badge-success', 'Ativo'],
    inativo:      ['badge-muted', 'Inativo'],
  };
  const [cls, label] = map[status] || ['badge-muted', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function tipoIcon(tipo) {
  const icons = { ligacao:'📞', email:'✉️', reuniao:'📅', whatsapp:'💬', observacao:'📝', outro:'🔖' };
  return icons[tipo] || '📝';
}

// ---------- Escape HTML ----------
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
