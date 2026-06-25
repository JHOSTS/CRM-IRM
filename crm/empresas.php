<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if ($user['cargo'] !== 'master') {
    header('Location: /crm/kanban.php'); exit;
}
layoutStart('Empresas (Master)', 'empresas');
?>

<div class="page-header">
  <h1>Empresas</h1>
  <button class="btn btn-primary" onclick="emps.abrirCriar()">+ Nova empresa</button>
</div>

<div class="page-body">
  <div class="card">
    <div id="emp-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="emp-content" class="hidden">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Logo</th><th>Nome</th><th>Cores</th><th>Usuários</th><th>Status</th><th>Criada em</th><th></th></tr></thead>
          <tbody id="emp-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Criar/Editar Empresa -->
<div id="modal-emp" class="modal-overlay hidden">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title" id="modal-emp-title">Nova Empresa</span>
      <button class="modal-close" onclick="closeModal('modal-emp')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="emp-id">

      <div class="form-group">
        <label class="form-label">Nome da empresa *</label>
        <input class="form-control" id="emp-nome" placeholder="Ex: Agência XYZ">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cor primária (botões e destaques)</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="color" id="emp-cor-prim" value="#4361ee"
              style="width:48px;height:40px;padding:2px;border-radius:6px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;">
            <input class="form-control" id="emp-cor-prim-hex" value="#4361ee" placeholder="#4361ee" maxlength="7">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Cor secundária (sidebar)</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="color" id="emp-cor-sec" value="#1a1d27"
              style="width:48px;height:40px;padding:2px;border-radius:6px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;">
            <input class="form-control" id="emp-cor-sec-hex" value="#1a1d27" placeholder="#1a1d27" maxlength="7">
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div style="margin:4px 0 16px;display:flex;align-items:center;gap:12px;">
        <div id="prev-sidebar" style="width:14px;height:44px;border-radius:4px;transition:background .2s;"></div>
        <button id="prev-btn" style="padding:8px 16px;border-radius:6px;border:none;font-size:.85rem;font-weight:600;cursor:default;color:#fff;transition:background .2s;">Botão preview</button>
        <span class="text-muted text-sm">← Preview das cores selecionadas</span>
      </div>

      <div id="emp-status-group" class="hidden">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-select" id="emp-status">
            <option value="ativo">Ativo</option>
            <option value="inativo">Inativo</option>
          </select>
        </div>
      </div>

      <div id="emp-logo-group" class="hidden">
        <div class="form-group">
          <label class="form-label">Logo da empresa</label>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <img id="emp-logo-preview" src="" alt="Logo"
              style="height:48px;max-width:160px;object-fit:contain;border-radius:6px;border:1px solid var(--border);padding:4px;display:none;">
            <label class="btn btn-ghost btn-sm" style="cursor:pointer;">
              📁 Escolher imagem
              <input type="file" id="emp-logo-file" accept="image/*" style="display:none;"
                onchange="emps.previewLogo(this)">
            </label>
            <span class="text-muted text-sm">JPG, PNG, WebP ou SVG · Máx 2MB</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-emp')">Cancelar</button>
      <button class="btn btn-primary" id="btn-salvar-emp" onclick="emps.salvar()">Salvar</button>
    </div>
  </div>
</div>

<script>
function updatePreview() {
  const prim = document.getElementById('emp-cor-prim').value;
  const sec  = document.getElementById('emp-cor-sec').value;
  document.getElementById('prev-sidebar').style.background = sec;
  document.getElementById('prev-btn').style.background     = prim;
}

function syncColor(pickerId, hexId) {
  const picker = document.getElementById(pickerId);
  const hex    = document.getElementById(hexId);
  picker.addEventListener('input', () => { hex.value = picker.value; updatePreview(); });
  hex.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; updatePreview(); }
  });
}

syncColor('emp-cor-prim', 'emp-cor-prim-hex');
syncColor('emp-cor-sec',  'emp-cor-sec-hex');
updatePreview();

const emps = (() => {
  async function load() {
    try {
      const d = await api('/crm/api/empresas.php?action=lista');
      renderTabela(d.data);
      document.getElementById('emp-loading').classList.add('hidden');
      document.getElementById('emp-content').classList.remove('hidden');
    } catch(e) { toast(e.message, 'error'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('emp-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Nenhuma empresa cadastrada.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(e => `
      <tr>
        <td>
          ${e.logo
            ? `<img src="/crm/${esc(e.logo)}" alt="Logo" style="height:32px;max-width:80px;object-fit:contain;">`
            : '<span class="text-muted text-sm">—</span>'}
        </td>
        <td><strong>${esc(e.nome)}</strong></td>
        <td>
          <div style="display:flex;gap:6px;align-items:center;">
            <span title="${esc(e.cor_primaria)}" style="display:inline-block;width:18px;height:18px;border-radius:50%;background:${esc(e.cor_primaria)};border:1px solid var(--border)"></span>
            <span title="${esc(e.cor_secundaria)}" style="display:inline-block;width:18px;height:18px;border-radius:50%;background:${esc(e.cor_secundaria)};border:1px solid var(--border)"></span>
          </div>
        </td>
        <td class="text-sm">${e.total_usuarios}</td>
        <td>${statusBadge(e.status)}</td>
        <td class="text-sm text-muted">${fmtDate(e.data_criacao)}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="emps.editar(${e.id})">✏️ Editar</button>
        </td>
      </tr>`).join('');
  }

  function setForm(emp = null) {
    const prim = emp?.cor_primaria || '#4361ee';
    const sec  = emp?.cor_secundaria || '#1a1d27';
    document.getElementById('emp-id').value           = emp?.id || '';
    document.getElementById('emp-nome').value         = emp?.nome || '';
    document.getElementById('emp-cor-prim').value     = prim;
    document.getElementById('emp-cor-prim-hex').value = prim;
    document.getElementById('emp-cor-sec').value      = sec;
    document.getElementById('emp-cor-sec-hex').value  = sec;
    updatePreview();
  }

  function abrirCriar() {
    document.getElementById('modal-emp-title').textContent = 'Nova Empresa';
    setForm();
    document.getElementById('emp-status-group').classList.add('hidden');
    document.getElementById('emp-logo-group').classList.add('hidden');
    openModal('modal-emp');
  }

  async function editar(id) {
    try {
      const d   = await api('/crm/api/empresas.php?action=lista');
      const emp = d.data.find(e => e.id == id);
      if (!emp) { toast('Empresa não encontrada.', 'error'); return; }

      document.getElementById('modal-emp-title').textContent = 'Editar Empresa';
      setForm(emp);
      document.getElementById('emp-status').value = emp.status;
      document.getElementById('emp-status-group').classList.remove('hidden');
      document.getElementById('emp-logo-group').classList.remove('hidden');
      document.getElementById('emp-logo-file').value = '';

      const preview = document.getElementById('emp-logo-preview');
      if (emp.logo) {
        preview.src = '/crm/' + emp.logo;
        preview.style.display = 'block';
      } else {
        preview.style.display = 'none';
      }
      openModal('modal-emp');
    } catch(e) { toast(e.message, 'error'); }
  }

  function previewLogo(input) {
    const file = input.files[0];
    if (!file) return;
    const preview = document.getElementById('emp-logo-preview');
    preview.src   = URL.createObjectURL(file);
    preview.style.display = 'block';
  }

  async function salvar() {
    const id      = document.getElementById('emp-id').value;
    const nome    = document.getElementById('emp-nome').value.trim();
    const corPrim = document.getElementById('emp-cor-prim').value;
    const corSec  = document.getElementById('emp-cor-sec').value;
    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }

    const btn = document.getElementById('btn-salvar-emp');
    btn.disabled = true;
    try {
      const body = { nome, cor_primaria: corPrim, cor_secundaria: corSec };
      if (id) { body.id = +id; body.status = document.getElementById('emp-status').value; }

      const res = await api('/crm/api/empresas.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST', body: JSON.stringify(body),
      });

      // Upload de logo se houver arquivo
      const fileInput = document.getElementById('emp-logo-file');
      const empId     = id || res.id;
      if (fileInput.files[0] && empId) {
        const form = new FormData();
        form.append('logo', fileInput.files[0]);
        const upRes = await fetch(`/crm/api/upload.php?tipo=logo&empresa_id=${empId}`, {
          method: 'POST', body: form,
        });
        const upData = await upRes.json();
        if (!upRes.ok) toast('Logo: ' + (upData.error || 'Erro no upload'), 'error');
      }

      toast(id ? 'Empresa atualizada!' : 'Empresa criada! Etapas padrão geradas.');
      closeModal('modal-emp');
      load();
    } catch(e) { toast(e.message, 'error'); }
    finally { btn.disabled = false; }
  }

  load();
  return { abrirCriar, editar, salvar, previewLogo };
})();
</script>

<?php layoutEnd(); ?>
