<?php
require_once __DIR__ . '/includes/layout.php';
$negFiltro = isset($_GET['neg']) ? (int)$_GET['neg'] : 0;
$pageUser  = $_LAYOUT_USER;
layoutStart('Atividades', 'atividades');
?>

<div class="page-header">
  <h1>Atividades & Tarefas</h1>
  <button class="btn btn-primary" onclick="atv.abrirCriar()">+ Nova tarefa</button>
</div>

<div class="page-body">
  <div class="toolbar">
    <select class="form-select" id="filtro-atv" style="width:auto">
      <option value="">Todas</option>
      <option value="hoje">Hoje</option>
      <option value="atrasadas">Atrasadas</option>
      <option value="abertas">Em aberto</option>
      <option value="concluidas">Concluídas</option>
    </select>
  </div>

  <div class="card">
    <div id="atv-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="atv-content" class="hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Tarefa</th><th>Negociação</th><th>Responsável</th><th>Vencimento</th><th>Status</th><th></th></tr>
          </thead>
          <tbody id="atv-tbody"></tbody>
        </table>
      </div>
      <div id="atv-pagination" class="pagination"></div>
    </div>
  </div>
</div>

<!-- Modal Nova Tarefa -->
<div id="modal-atv" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-atv-title">Nova Tarefa</span>
      <button class="modal-close" onclick="closeModal('modal-atv')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="atv-id">
      <div class="form-group">
        <label class="form-label">Título *</label>
        <input class="form-control" id="atv-titulo">
      </div>
      <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea class="form-control" id="atv-desc" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Data de vencimento</label>
          <input class="form-control" id="atv-venc" type="datetime-local">
        </div>
        <div class="form-group" id="grp-atv-resp">
          <label class="form-label">Responsável</label>
          <select class="form-select" id="atv-resp"></select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-atv')">Cancelar</button>
      <button class="btn btn-primary" onclick="atv.salvar()">Salvar</button>
    </div>
  </div>
</div>

<script>
const negFiltroInicial = <?= $negFiltro ?>;
const PAGE_USER = {
  id:    <?= (int)$pageUser['id'] ?>,
  cargo: <?= json_encode($pageUser['cargo']) ?>,
  nome:  <?= json_encode($pageUser['nome']) ?>,
};
const atv = (() => {
  let _page = 1, _filtro = '', _total = 0, _limit = 30;

  async function load(page = 1) {
    _page = page;
    show('loading');
    try {
      const params = new URLSearchParams({ action: 'lista', page, filtro: _filtro });
      if (negFiltroInicial) params.set('negociacao_id', negFiltroInicial);
      const d = await api('/crm/api/atividades.php?' + params);
      _total = d.total; _limit = d.limit;
      renderTabela(d.data);
      renderPagination(document.getElementById('atv-pagination'), _page, _total, _limit, load);
      show('content');
    } catch(e) { toast(e.message, 'error'); show('content'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('atv-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Nenhuma tarefa encontrada.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(a => {
      const atrasada = !a.concluida && a.data_vencimento && new Date(a.data_vencimento) < new Date();
      const statusLabel = a.concluida ? '<span class="badge badge-success">Concluída</span>'
        : atrasada ? '<span class="badge badge-danger">Atrasada</span>'
        : '<span class="badge badge-warning">Aberta</span>';
      return `
        <tr style="${atrasada && !a.concluida ? 'background:rgba(239,35,60,.05)' : ''}">
          <td><strong>${esc(a.titulo)}</strong>${a.descricao ? `<br><small class="text-muted">${esc(a.descricao)}</small>` : ''}</td>
          <td class="text-muted text-sm">${esc(a.negociacao_titulo || '—')}</td>
          <td class="text-sm">${esc(a.responsavel_nome)}</td>
          <td class="text-sm">${a.data_vencimento ? fmtDateTime(a.data_vencimento) : '—'}</td>
          <td>${statusLabel}</td>
          <td>
            <div style="display:flex;gap:4px;">
              ${!a.concluida ? `<button class="btn btn-success btn-sm btn-icon" onclick="atv.concluir(${a.id})" title="Concluir">✓</button>` : ''}
              <button class="btn btn-ghost btn-sm btn-icon" onclick="atv.editar(${a.id})">✏️</button>
            </div>
          </td>
        </tr>`;
    }).join('');
  }

  function show(s) {
    document.getElementById('atv-loading').classList.toggle('hidden', s !== 'loading');
    document.getElementById('atv-content').classList.toggle('hidden', s !== 'content');
  }

  async function abrirCriar() {
    document.getElementById('modal-atv-title').textContent = 'Nova Tarefa';
    ['id','titulo','desc','venc'].forEach(f => document.getElementById('atv-' + f).value = '');
    await carregarUsuarios();
    openModal('modal-atv');
  }

  async function editar(id) {
    // Buscar dados da tarefa e preencher form (simplificado via API lista)
    toast('Para editar, recarregue e abra nova.', 'info');
  }

  async function carregarUsuarios() {
    try {
      const d = await api('/crm/api/usuarios.php?action=responsaveis');
      const sel = document.getElementById('atv-resp');
      sel.innerHTML = d.data.map(u => `<option value="${u.id}">${esc(u.nome)}</option>`).join('');
      // Atendente só pode ser responsável de si mesmo
      if (PAGE_USER.cargo === 'atendente') {
        sel.value    = PAGE_USER.id;
        sel.disabled = true;
      }
    } catch {}
  }

  async function salvar() {
    const titulo = document.getElementById('atv-titulo').value.trim();
    if (!titulo) { toast('Título é obrigatório.', 'error'); return; }
    const body = {
      titulo,
      descricao:      document.getElementById('atv-desc').value,
      data_vencimento:document.getElementById('atv-venc').value,
      responsavel_id: +document.getElementById('atv-resp').value,
    };
    if (negFiltroInicial) body.negociacao_id = negFiltroInicial;
    try {
      await api('/crm/api/atividades.php?action=criar', { method: 'POST', body: JSON.stringify(body) });
      toast('Tarefa criada!');
      closeModal('modal-atv');
      load(_page);
    } catch(e) { toast(e.message, 'error'); }
  }

  async function concluir(id) {
    if (!confirm('Marcar esta tarefa como concluída?')) return;
    try {
      await api('/crm/api/atividades.php?action=concluir', { method: 'POST', body: JSON.stringify({ id }) });
      toast('Tarefa concluída!');
      load(_page);
    } catch(e) { toast(e.message, 'error'); }
  }

  document.getElementById('filtro-atv').addEventListener('change', e => {
    _filtro = e.target.value; load(1);
  });

  load();
  return { abrirCriar, editar, salvar, concluir };
})();
</script>

<?php layoutEnd(); ?>
