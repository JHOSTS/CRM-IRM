<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
}
layoutStart('Relatórios', 'relatorios');
?>

<div class="page-header">
  <h1>Relatórios</h1>
</div>

<div class="page-body">
  <div class="card" style="margin-bottom:16px;">
    <div class="toolbar" style="flex-wrap:wrap;gap:10px;">
      <input type="date" class="form-control" id="rel-inicio" style="width:160px">
      <span class="text-muted">até</span>
      <input type="date" class="form-control" id="rel-fim" style="width:160px">
      <select class="form-select" id="rel-status" style="width:auto">
        <option value="">Todos os status</option>
        <option value="em_andamento">Em andamento</option>
        <option value="ganho">Ganho</option>
        <option value="perdido">Perdido</option>
      </select>
      <select class="form-select" id="rel-responsavel" style="width:auto">
        <option value="">Todos os responsáveis</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="rel.load(1)">Buscar</button>
      <button class="btn btn-ghost btn-sm" onclick="rel.exportCSV()">⬇ CSV</button>
    </div>
  </div>

  <div class="card">
    <div id="rel-loading" class="loading-state hidden"><div class="spinner"></div></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Negociação</th><th>Contato</th><th>Responsável</th><th>Etapa</th><th>Status</th><th>Valor</th><th>Criado em</th></tr>
        </thead>
        <tbody id="rel-tbody"><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Use os filtros acima e clique em Buscar.</td></tr></tbody>
      </table>
    </div>
    <div id="rel-pagination" class="pagination"></div>
  </div>
</div>

<script>
const hoje = new Date();
const ini  = hoje.getFullYear() + '-' + String(hoje.getMonth()+1).padStart(2,'0') + '-01';
const fim  = new Date(hoje.getFullYear(), hoje.getMonth()+1, 0).toISOString().slice(0,10);
document.getElementById('rel-inicio').value = ini;
document.getElementById('rel-fim').value    = fim;

const rel = (() => {
  let _page = 1, _total = 0, _limit = 50;

  async function carregarResponsaveis() {
    try {
      const d = await api('/crm/api/usuarios.php?action=responsaveis');
      const sel = document.getElementById('rel-responsavel');
      sel.innerHTML = '<option value="">Todos os responsáveis</option>' +
        (d.data || []).map(u => `<option value="${u.id}">${esc(u.nome)}</option>`).join('');
    } catch(e) { /* silencioso */ }
  }

  function getParams() {
    const params = new URLSearchParams({
      action: 'negociacoes',
      inicio: document.getElementById('rel-inicio').value,
      fim:    document.getElementById('rel-fim').value,
      status: document.getElementById('rel-status').value,
      page:   _page,
    });
    const resp = document.getElementById('rel-responsavel').value;
    if (resp) params.set('responsavel_id', resp);
    return params;
  }

  async function load(page = 1) {
    _page = page;
    document.getElementById('rel-loading').classList.remove('hidden');
    try {
      const d = await api('/crm/api/relatorios.php?' + getParams());
      _total = d.total; _limit = d.limit;
      renderTabela(d.data);
      renderPagination(document.getElementById('rel-pagination'), _page, _total, _limit, load);
    } catch(e) { toast(e.message, 'error'); }
    finally { document.getElementById('rel-loading').classList.add('hidden'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('rel-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Nenhum resultado.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td><strong>${esc(r.titulo)}</strong></td>
        <td>${esc(r.contato_nome)}</td>
        <td>${esc(r.responsavel_nome)}</td>
        <td><span class="badge badge-muted">${esc(r.etapa_nome)}</span></td>
        <td>${statusBadge(r.status)}</td>
        <td class="text-sm">${fmtMoney(r.valor_estimado)}</td>
        <td class="text-sm text-muted">${fmtDate(r.data_criacao)}</td>
      </tr>`).join('');
  }

  function exportCSV() {
    const params = getParams();
    params.set('export', 'csv');
    window.location.href = '/crm/api/relatorios.php?' + params;
  }

  carregarResponsaveis();
  return { load, exportCSV };
})();
</script>

<?php layoutEnd(); ?>
