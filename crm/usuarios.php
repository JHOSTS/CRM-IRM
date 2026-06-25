<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
}
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
          <thead><tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th>Status</th><th>Último login</th><th></th></tr></thead>
          <tbody id="usr-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
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
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Nenhum usuário cadastrado.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(u => `
      <tr>
        <td><strong>${esc(u.nome)}</strong></td>
        <td class="text-sm">${esc(u.email)}</td>
        <td><span class="badge badge-muted">${esc(u.cargo)}</span></td>
        <td>${statusBadge(u.status)}</td>
        <td class="text-sm text-muted">${u.ultimo_login ? fmtDateTime(u.ultimo_login) : 'Nunca'}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="usuarios.editar(${u.id},'${esc(u.nome)}','${esc(u.email)}','${esc(u.cargo)}','${esc(u.status)}')">✏️ Editar</button>
        </td>
      </tr>`).join('');
  }

  function abrirCriar() {
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
    openModal('modal-usr');
  }

  function editar(id, nome, email, cargo, status) {
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
    openModal('modal-usr');
  }

  async function salvar() {
    const id    = document.getElementById('usr-id').value;
    const nome  = document.getElementById('usr-nome').value.trim();
    const email = document.getElementById('usr-email').value.trim();
    const cargo = document.getElementById('usr-cargo').value;
    const status= document.getElementById('usr-status').value;
    const senha = document.getElementById('usr-senha').value;

    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
    if (!id && !email) { toast('E-mail é obrigatório.', 'error'); return; }
    if (!id && !senha) { toast('Senha é obrigatória para novo usuário.', 'error'); return; }

    const body = { nome, cargo, status };
    if (!id) { body.email = email; body.senha = senha; }
    else { body.id = +id; if (senha) body.senha = senha; }

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
