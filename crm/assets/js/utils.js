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
  el.innerHTML = `<span class="toast-icon">${icons[type] || '•'}</span><span>${esc(String(msg))}</span>`;
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
  const [cls, label] = map[status] || ['badge-muted', esc(String(status))];
  return `<span class="badge ${cls}">${label}</span>`;
}

function tipoIcon(tipo) {
  const icons = { ligacao:'📞', email:'✉️', reuniao:'📅', whatsapp:'💬', observacao:'📝', outro:'🔖' };
  return icons[tipo] || '📝';
}

// ---------- Escape HTML ----------
function esc(str) {
  if (!str && str !== 0) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;');
}

// ---------- WhatsApp ----------
function waTel(tel) {
  if (!tel) return null;
  const digits = String(tel).replace(/\D/g, '');
  if (!digits) return null;
  // Já tem código do país (55 + 10-11 dígitos = 12-13)
  if (digits.length >= 12 && digits.startsWith('55')) return `https://wa.me/${digits}`;
  // Adiciona código do Brasil
  if (digits.length >= 10) return `https://wa.me/55${digits}`;
  return null;
}

function waTelLink(tel, label) {
  const url = waTel(tel);
  if (!url) return esc(tel || '—');
  return `<a href="${url}" target="_blank" rel="noopener noreferrer"
             style="display:inline-flex;align-items:center;gap:4px;color:inherit;text-decoration:none;"
             title="Abrir no WhatsApp">
             ${esc(tel)}
             <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366" style="flex-shrink:0">
               <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
               <path d="M12 0C5.373 0 0 5.373 0 12c0 2.091.537 4.058 1.475 5.775L0 24l6.435-1.686A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.8 9.8 0 01-5.003-1.368l-.36-.214-3.716.974.994-3.62-.234-.373A9.786 9.786 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182c5.429 0 9.818 4.388 9.818 9.818 0 5.43-4.389 9.818-9.818 9.818z"/>
             </svg>
           </a>`;
}
