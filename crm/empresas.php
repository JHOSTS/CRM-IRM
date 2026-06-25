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
          <thead><tr><th>Nome</th><th>Usuários</th><th>Status</th><th>Criada em</th><th></th></tr></thead>
          <tbody id="emp-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div id="modal-emp" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-emp-title">Nova Empresa</span>
      <button class="modal-close" onclick="closeModal('modal-emp')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="emp-id">
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-control" id="emp-nome">
      </div>
      <div class="form-group" id="emp-status-group" class="hidden">
        <label class="form-label">Status</label>
        <select class="form-select" id="emp-status">
          <option value="ativo">Ativo</option>
          <option value="inativo">Inativo</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-emp')">Cancelar</button>
      <button class="btn btn-primary" onclick="emps.salvar()">Salvar</button>
    </div>
  </div>
</div>

<script>
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
    tbody.innerHTML = rows.map(e => `
      <tr>
        <td><strong>${esc(e.nome)}</strong></td>
        <td class="text-sm">${e.total_usuarios}</td>
        <td>${statusBadge(e.status)}</td>
        <td class="text-sm text-muted">${fmtDate(e.data_criacao)}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="emps.editar(${e.id},'${esc(e.nome)}','${esc(e.status)}')">✏️ Editar</button>
        </td>
      </tr>`).join('');
  }

  function abrirCriar() {
    document.getElementById('modal-emp-title').textContent = 'Nova Empresa';
    document.getElementById('emp-id').value = '';
    document.getElementById('emp-nome').value = '';
    document.getElementById('emp-status-group').classList.add('hidden');
    openModal('modal-emp');
  }

  function editar(id, nome, status) {
    document.getElementById('modal-emp-title').textContent = 'Editar Empresa';
    document.getElementById('emp-id').value = id;
    document.getElementById('emp-nome').value = nome;
    document.getElementById('emp-status').value = status;
    document.getElementById('emp-status-group').classList.remove('hidden');
    openModal('modal-emp');
  }

  async function salvar() {
    const id   = document.getElementById('emp-id').value;
    const nome = document.getElementById('emp-nome').value.trim();
    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
    const body = { nome };
    if (id) { body.id = +id; body.status = document.getElementById('emp-status').value; }
    try {
      await api('/crm/api/empresas.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST', body: JSON.stringify(body),
      });
      toast(id ? 'Empresa atualizada!' : 'Empresa criada! Etapas padrão geradas.');
      closeModal('modal-emp');
      load();
    } catch(e) { toast(e.message, 'error'); }
  }

  load();
  return { abrirCriar, editar, salvar };
})();
</script>

<?php layoutEnd(); ?>
