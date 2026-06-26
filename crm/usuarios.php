<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
}
$isMaster = $user['cargo'] === 'master';
layoutStart('Usuários', 'usuarios');
?>

<div class="page-header">
  <h1>Usuários</h1>
  <button class="btn btn-primary" onclick="usuarios.abrirCriar()">+ Novo usuário</button>
</div>

<div class="page-body">
  <div class="card">
    <div id="usr-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="usr-content" class="hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Cargo</th>
              <?php if ($isMaster): ?><th>Empresa</th><?php endif; ?>
              <th>Status</th>
              <th>Último login</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="usr-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal criar/editar -->
<div id="modal-usr" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-usr-title">Novo Usuário</span>
      <button class="modal-close" onclick="closeModal('modal-usr')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="usr-id">
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-control" id="usr-nome">
      </div>
      <div class="form-group">
        <label class="form-label">E-mail *</label>
        <input class="form-control" id="usr-email" type="email">
      </div>
      <?php if ($isMaster): ?>
      <div class="form-group" id="grp-empresa">
        <label class="form-label">Empresa *</label>
        <select class="form-select" id="usr-empresa-id">
          <option value="">Carregando...</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cargo</label>
          <select class="form-select" id="usr-cargo">
            <option value="atendente">Atendente</option>
            <option value="gerente">Gerente</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-select" id="usr-status">
            <option value="ativo">Ativo</option>
            <option value="inativo">Inativo</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" id="usr-senha-label">Senha * (mín. 8 caracteres)</label>
        <input class="form-control" id="usr-senha" type="password" placeholder="••••••••">
        <small class="text-muted" id="usr-senha-hint"></small>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-usr')">Cancelar</button>
      <button class="btn btn-primary" onclick="usuarios.salvar()">Salvar</button>
    </div>
  </div>
</div>

<script>
const IS_MASTER = <?= json_encode($isMaster) ?>;

const usuarios = (() => {
  async function load() {
    try {
      const d = await api('/crm/api/usuarios.php?action=lista');
      renderTabela(d.data);
      document.getElementById('usr-loading').classList.add('hidden');
      document.getElementById('usr-content').classList.remove('hidden');
    } catch(e) { toast(e.message, 'error'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('usr-tbody');
    const cols = IS_MASTER ? 7 : 6;
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:32px;color:var(--text-muted)">Nenhum usuário cadastrado.</td></tr>`;
      return;
    }
    tbody.innerHTML = rows.map(u => `
      <tr>
        <td><strong>${esc(u.nome)}</strong></td>
        <td class="text-sm">${esc(u.email)}</td>
        <td><span class="badge badge-muted">${esc(u.cargo)}</span></td>
        ${IS_MASTER ? `<td class="text-sm">${esc(u.empresa_nome || '—')}</td>` : ''}
        <td>${statusBadge(u.status)}</td>
        <td class="text-sm text-muted">${u.ultimo_login ? fmtDateTime(u.ultimo_login) : 'Nunca'}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="usuarios.editar(${u.id},'${esc(u.nome)}','${esc(u.email)}','${esc(u.cargo)}','${esc(u.status)}',${u.empresa_id||0})">✏️ Editar</button>
        </td>
      </tr>`).join('');
  }

  async function carregarEmpresas(selecionadaId) {
    const sel = document.getElementById('usr-empresa-id');
    if (!sel) return;
    try {
      const d = await api('/crm/api/empresas.php?action=lista');
      sel.innerHTML = d.data.map(e =>
        `<option value="${e.id}" ${e.id == selecionadaId ? 'selected' : ''}>${esc(e.nome)}</option>`
      ).join('');
    } catch(e) {
      sel.innerHTML = '<option value="">Erro ao carregar</option>';
    }
  }

  async function abrirCriar() {
    document.getElementById('modal-usr-title').textContent = 'Novo Usuário';
    document.getElementById('usr-id').value     = '';
    document.getElementById('usr-nome').value   = '';
    document.getElementById('usr-email').value  = '';
    document.getElementById('usr-email').disabled = false;
    document.getElementById('usr-cargo').value  = 'atendente';
    document.getElementById('usr-status').value = 'ativo';
    document.getElementById('usr-senha').value  = '';
    document.getElementById('usr-senha-label').textContent = 'Senha * (mín. 8 caracteres)';
    document.getElementById('usr-senha-hint').textContent  = '';

    const empSel = document.getElementById('usr-empresa-id');
    if (empSel) {
      empSel.disabled = false;
      await carregarEmpresas(null);
    }
    openModal('modal-usr');
  }

  async function editar(id, nome, email, cargo, status, empresaId) {
    document.getElementById('modal-usr-title').textContent = 'Editar Usuário';
    document.getElementById('usr-id').value     = id;
    document.getElementById('usr-nome').value   = nome;
    document.getElementById('usr-email').value  = email;
    document.getElementById('usr-email').disabled = true;
    document.getElementById('usr-cargo').value  = cargo;
    document.getElementById('usr-status').value = status;
    document.getElementById('usr-senha').value  = '';
    document.getElementById('usr-senha-label').textContent = 'Nova senha (deixe em branco para não alterar)';
    document.getElementById('usr-senha-hint').textContent  = 'Mínimo 8 caracteres se preencher.';

    const empSel = document.getElementById('usr-empresa-id');
    if (empSel) {
      await carregarEmpresas(empresaId);
      empSel.disabled = true;
    }
    openModal('modal-usr');
  }

  async function salvar() {
    const id     = document.getElementById('usr-id').value;
    const nome   = document.getElementById('usr-nome').value.trim();
    const email  = document.getElementById('usr-email').value.trim();
    const cargo  = document.getElementById('usr-cargo').value;
    const status = document.getElementById('usr-status').value;
    const senha  = document.getElementById('usr-senha').value;
    const empSel = document.getElementById('usr-empresa-id');
    const empresaId = empSel ? +empSel.value : null;

    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
    if (!id && !email) { toast('E-mail é obrigatório.', 'error'); return; }
    if (!id && !senha) { toast('Senha é obrigatória para novo usuário.', 'error'); return; }
    if (!id && IS_MASTER && !empresaId) { toast('Selecione a empresa do usuário.', 'error'); return; }

    const body = { nome, cargo, status };
    if (!id) {
      body.email = email;
      body.senha = senha;
      if (IS_MASTER && empresaId) body.empresa_id = empresaId;
    } else {
      body.id = +id;
      if (senha) body.senha = senha;
    }

    if (empSel) empSel.disabled = false;

    try {
      await api('/crm/api/usuarios.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST', body: JSON.stringify(body),
      });
      toast(id ? 'Usuário atualizado!' : 'Usuário criado!');
      closeModal('modal-usr');
      load();
    } catch(e) { toast(e.message, 'error'); }
  }

  load();
  return { abrirCriar, editar, salvar };
})();
</script>

<?php layoutEnd(); ?>
