<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
}
layoutStart('Log de Atividades', 'log');
?>

<div class="page-header">
  <h1>Log de Atividades</h1>
</div>

<div class="page-body">
  <div class="card" style="margin-bottom:16px;">
    <div class="toolbar" style="flex-wrap:wrap;gap:10px;">
      <input type="date" class="form-control" id="log-inicio" style="width:160px">
      <span class="text-muted">até</span>
      <input type="date" class="form-control" id="log-fim" style="width:160px">
      <select class="form-select" id="log-usuario" style="width:auto">
        <option value="">Todos os usuários</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="logPage.load(1)">Buscar</button>
    </div>
  </div>

  <div class="card">
    <div id="log-loading" class="loading-state hidden"><div class="spinner"></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data/hora</th><th>Usuário</th><th>Cargo</th><th>Ação</th><th>Ref.</th><th>Detalhes</th></tr></thead>
        <tbody id="log-tbody">
          <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Selecione o período e clique em Buscar.</td></tr>
        </tbody>
      </table>
    </div>
    <div id="log-pagination" class="pagination"></div>
  </div>
</div>

<script>
const hoje = new Date().toISOString().slice(0,10);
document.getElementById('log-inicio').value = hoje;
document.getElementById('log-fim').value    = hoje;

// Carregar usuários no select
api('/crm/api/usuarios.php?action=lista').then(d => {
  const sel = document.getElementById('log-usuario');
  d.data.forEach(u => {
    const opt = document.createElement('option');
    opt.value = u.id; opt.textContent = u.nome;
    sel.appendChild(opt);
  });
}).catch(() => {});

const logPage = (() => {
  let _page = 1, _total = 0, _limit = 50;

  const acaoLabels = {
    login: 'Login',
    logout: 'Logout',
    criou_contato: 'Criou contato',
    editou_contato: 'Editou contato',
    excluiu_contato: 'Excluiu contato',
    adicionou_interacao: 'Adicionou interação',
    criou_negociacao: 'Criou negociação',
    editou_negociacao: 'Editou negociação',
    moveu_negociacao: 'Moveu negociação',
    excluiu_negociacao: 'Excluiu negociação',
    criou_tarefa: 'Criou tarefa',
    editou_tarefa: 'Editou tarefa',
    concluiu_tarefa: 'Concluiu tarefa',
    excluiu_tarefa: 'Excluiu tarefa',
    criou_usuario: 'Criou usuário',
    editou_usuario: 'Editou usuário',
    criou_empresa: 'Criou empresa',
    editou_empresa: 'Editou empresa',
  };

  async function load(page = 1) {
    _page = page;
    document.getElementById('log-loading').classList.remove('hidden');
    try {
      const params = new URLSearchParams({
        inicio:     document.getElementById('log-inicio').value,
        fim:        document.getElementById('log-fim').value,
        usuario_id: document.getElementById('log-usuario').value,
        page:       _page,
      });
      const d = await api('/crm/api/log.php?' + params);
      _total = d.total; _limit = d.limit;
      renderTabela(d.data);
      renderPagination(document.getElementById('log-pagination'), _page, _total, _limit, load);
    } catch(e) { toast(e.message, 'error'); }
    finally { document.getElementById('log-loading').classList.add('hidden'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('log-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted)">Nenhum registro.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const det = r.detalhes ? Object.entries(r.detalhes).map(([k,v]) => `${esc(k)}: ${esc(String(v))}`).join(', ') : '—';
      return `
        <tr>
          <td class="text-sm">${fmtDateTime(r.data_criacao)}</td>
          <td><strong>${esc(r.usuario_nome)}</strong></td>
          <td><span class="badge badge-muted">${esc(r.usuario_cargo)}</span></td>
          <td>${esc(acaoLabels[r.acao] || r.acao)}</td>
          <td class="text-muted text-sm">${r.referencia_id || '—'}</td>
          <td class="text-muted text-sm">${det}</td>
        </tr>`;
    }).join('');
  }

  return { load };
})();
</script>

<?php layoutEnd(); ?>
