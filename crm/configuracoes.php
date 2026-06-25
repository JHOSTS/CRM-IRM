<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
}
layoutStart('Configurações', 'configuracoes');
?>

<div class="page-header">
  <h1>Configurações da Empresa</h1>
</div>

<div class="page-body">

  <!-- Etapas do funil -->
  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <div style="font-weight:600;font-size:.95rem;">Etapas do Funil (Kanban)</div>
      <button class="btn btn-primary btn-sm" onclick="cfg.abrirCriarEtapa()">+ Adicionar etapa</button>
    </div>
    <div id="etapas-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="etapas-list" class="hidden"></div>
  </div>

</div>

<!-- Modal Etapa -->
<div id="modal-etapa" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-etapa-title">Nova Etapa</span>
      <button class="modal-close" onclick="closeModal('modal-etapa')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="etapa-id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nome *</label>
          <input class="form-control" id="etapa-nome">
        </div>
        <div class="form-group">
          <label class="form-label">Cor</label>
          <input class="form-control" id="etapa-cor" type="color" value="#4361ee" style="height:42px;padding:4px;">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Ordem</label>
        <input class="form-control" id="etapa-ordem" type="number" min="1" value="1">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-etapa')">Cancelar</button>
      <button class="btn btn-primary" onclick="cfg.salvarEtapa()">Salvar</button>
    </div>
  </div>
</div>

<script>
const cfg = (() => {
  async function loadEtapas() {
    try {
      const d = await api('/crm/api/etapas.php?action=lista');
      renderEtapas(d.data);
      document.getElementById('etapas-loading').classList.add('hidden');
      document.getElementById('etapas-list').classList.remove('hidden');
    } catch(e) { toast(e.message, 'error'); }
  }

  function renderEtapas(etapas) {
    const el = document.getElementById('etapas-list');
    if (!etapas.length) {
      el.innerHTML = '<p class="text-muted text-sm">Nenhuma etapa cadastrada.</p>';
      return;
    }
    el.innerHTML = `
      <div class="table-wrap">
        <table>
          <thead><tr><th>Ordem</th><th>Cor</th><th>Nome</th><th></th></tr></thead>
          <tbody>
            ${etapas.map(e => `
              <tr>
                <td class="text-muted text-sm">${e.ordem}</td>
                <td><span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:${esc(e.cor)};"></span></td>
                <td><strong>${esc(e.nome)}</strong></td>
                <td>
                  <div style="display:flex;gap:4px;">
                    <button class="btn btn-ghost btn-sm" onclick="cfg.editarEtapa(${e.id},'${esc(e.nome)}','${esc(e.cor)}',${e.ordem})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="cfg.excluirEtapa(${e.id})">🗑</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
  }

  function abrirCriarEtapa() {
    document.getElementById('modal-etapa-title').textContent = 'Nova Etapa';
    document.getElementById('etapa-id').value    = '';
    document.getElementById('etapa-nome').value  = '';
    document.getElementById('etapa-cor').value   = '#4361ee';
    document.getElementById('etapa-ordem').value = '99';
    openModal('modal-etapa');
  }

  function editarEtapa(id, nome, cor, ordem) {
    document.getElementById('modal-etapa-title').textContent = 'Editar Etapa';
    document.getElementById('etapa-id').value    = id;
    document.getElementById('etapa-nome').value  = nome;
    document.getElementById('etapa-cor').value   = cor;
    document.getElementById('etapa-ordem').value = ordem;
    openModal('modal-etapa');
  }

  async function salvarEtapa() {
    const id    = document.getElementById('etapa-id').value;
    const nome  = document.getElementById('etapa-nome').value.trim();
    const cor   = document.getElementById('etapa-cor').value;
    const ordem = +document.getElementById('etapa-ordem').value;
    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }

    const body = { nome, cor, ordem };
    if (id) body.id = +id;

    try {
      await api('/crm/api/etapas.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST', body: JSON.stringify(body),
      });
      toast(id ? 'Etapa atualizada!' : 'Etapa criada!');
      closeModal('modal-etapa');
      loadEtapas();
    } catch(e) { toast(e.message, 'error'); }
  }

  async function excluirEtapa(id) {
    if (!confirm('Excluir esta etapa? Não será possível se houver negociações vinculadas.')) return;
    try {
      await api('/crm/api/etapas.php?action=excluir', { method: 'POST', body: JSON.stringify({ id }) });
      toast('Etapa excluída.');
      loadEtapas();
    } catch(e) { toast(e.message, 'error'); }
  }

  loadEtapas();
  return { abrirCriarEtapa, editarEtapa, salvarEtapa, excluirEtapa };
})();
</script>

<?php layoutEnd(); ?>
