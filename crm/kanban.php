<?php
require_once __DIR__ . '/includes/layout.php';
layoutStart('Negociações', 'kanban');
$user = getSessionUser();
?>

<div class="kanban-toolbar">
  <div>
    <h1 style="font-size:1.2rem;font-weight:700;">Negociações</h1>
  </div>
  <div style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap;">
    <button class="btn btn-ghost btn-sm" onclick="kanban.reload()">⟳ Atualizar</button>
    <button class="btn btn-ghost btn-sm" onclick="kanban.abrirNovoContato()">👤 Novo contato</button>
    <button class="btn btn-primary btn-sm" onclick="kanban.abrirCriar()">+ Nova negociação</button>
  </div>
</div>

<div id="kanban-loading" class="loading-state">
  <div class="spinner"></div> Carregando…
</div>

<div id="kanban-board" class="kanban-board hidden"></div>

<!-- Modal: Nova/Editar Negociação -->
<div id="modal-neg" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-neg-title">Nova Negociação</span>
      <button class="modal-close" onclick="closeModal('modal-neg')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="neg-id">
      <div class="form-group">
        <label class="form-label">Título *</label>
        <input class="form-control" id="neg-titulo" placeholder="Ex: Proposta de social media">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Contato *</label>
          <select class="form-select" id="neg-contato"></select>
        </div>
        <div class="form-group">
          <label class="form-label">Etapa *</label>
          <select class="form-select" id="neg-etapa"></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Valor estimado (R$)</label>
          <input class="form-control" type="number" id="neg-valor" placeholder="0,00" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Responsável</label>
          <select class="form-select" id="neg-responsavel"></select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-neg')">Cancelar</button>
      <button class="btn btn-primary" id="btn-salvar-neg" onclick="kanban.salvar()">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal: Detalhe da Negociação -->
<div id="modal-detalhe" class="modal-overlay hidden">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title" id="detalhe-titulo">Detalhe</span>
      <button class="modal-close" onclick="closeModal('modal-detalhe')">✕</button>
    </div>
    <div class="modal-body" id="detalhe-body"></div>
  </div>
</div>

<script>
const kanban = (() => {
  let _etapas = [];
  let _contatos = [];
  let _usuarios = [];
  let _editId = null;

  async function reload() {
    document.getElementById('kanban-loading').classList.remove('hidden');
    document.getElementById('kanban-board').classList.add('hidden');
    try {
      const [kb] = await Promise.all([
        api('/crm/api/negociacoes.php?action=kanban'),
      ]);
      _etapas = kb.etapas;
      renderBoard(kb.etapas);
    } catch(e) {
      toast(e.message || 'Erro ao carregar', 'error');
    } finally {
      document.getElementById('kanban-loading').classList.add('hidden');
      document.getElementById('kanban-board').classList.remove('hidden');
    }
  }

  function renderBoard(etapas) {
    const board = document.getElementById('kanban-board');
    board.innerHTML = '';
    etapas.forEach(etapa => {
      const col = document.createElement('div');
      col.className = 'kanban-col';
      col.innerHTML = `
        <div class="kanban-col-header">
          <div class="col-title-wrap">
            <div class="col-dot" style="background:${esc(etapa.cor)}"></div>
            <span class="col-name">${esc(etapa.nome)}</span>
          </div>
          <span class="col-count">${etapa.cards.length}</span>
        </div>
        <div class="kanban-cards" data-etapa="${etapa.id}" id="col-${etapa.id}">
          ${etapa.cards.length === 0
            ? '<div style="color:var(--text-muted);font-size:.75rem;text-align:center;padding:16px 0;">Nenhuma negociação</div>'
            : etapa.cards.map(renderCard).join('')}
        </div>`;
      board.appendChild(col);
    });
    setupDragDrop();
  }

  function renderCard(card) {
    const atrasado = card.tarefas_atrasadas > 0
      ? `<span class="card-late">⚠ ${card.tarefas_atrasadas} atrasada${card.tarefas_atrasadas > 1 ? 's' : ''}</span>` : '';
    const valor = card.valor_estimado ? `<span class="card-value">${fmtMoney(card.valor_estimado)}</span>` : '';
    return `
      <div class="kanban-card" draggable="true" data-id="${card.id}" onclick="kanban.abrirDetalhe(${card.id})">
        <div class="card-title">${esc(card.titulo)}</div>
        <div class="card-contact">👤 ${esc(card.contato_nome)}</div>
        <div class="card-footer">
          ${valor}
          <span class="card-resp">👥 ${esc(card.responsavel)}</span>
          ${atrasado}
        </div>
      </div>`;
  }

  function setupDragDrop() {
    let dragging = null;

    document.querySelectorAll('.kanban-card').forEach(card => {
      card.addEventListener('dragstart', e => {
        dragging = card;
        card.classList.add('dragging');
        e.dataTransfer.setData('text/plain', card.dataset.id);
      });
      card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        dragging = null;
      });
    });

    document.querySelectorAll('.kanban-cards').forEach(col => {
      col.addEventListener('dragover', e => {
        e.preventDefault();
        col.classList.add('drag-over');
      });
      col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
      col.addEventListener('drop', async e => {
        e.preventDefault();
        col.classList.remove('drag-over');
        const negId   = +e.dataTransfer.getData('text/plain');
        const etapaId = +col.dataset.etapa;
        if (!negId || !etapaId) return;
        try {
          await api('/crm/api/negociacoes.php?action=mover', {
            method: 'POST',
            body: JSON.stringify({ id: negId, etapa_id: etapaId }),
          });
          reload();
        } catch(err) {
          toast(err.message, 'error');
        }
      });
    });
  }

  async function abrirCriar() {
    _editId = null;
    document.getElementById('modal-neg-title').textContent = 'Nova Negociação';
    document.getElementById('neg-id').value = '';
    document.getElementById('neg-titulo').value = '';
    document.getElementById('neg-valor').value = '';
    await carregarSelectsNeg();
    openModal('modal-neg');
  }

  async function carregarSelectsNeg() {
    try {
      const [cs, us, es] = await Promise.all([
        api('/crm/api/contatos.php?action=lista&limit=100'),
        api('/crm/api/usuarios.php?action=lista'),
        api('/crm/api/etapas.php?action=lista'),
      ]);
      _contatos = cs.data;
      _usuarios = us.data;

      document.getElementById('neg-contato').innerHTML =
        '<option value="">Selecione…</option>' +
        _contatos.map(c => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');

      document.getElementById('neg-etapa').innerHTML =
        '<option value="">Selecione…</option>' +
        es.data.map(e => `<option value="${e.id}">${esc(e.nome)}</option>`).join('');

      document.getElementById('neg-responsavel').innerHTML =
        _usuarios.map(u => `<option value="${u.id}">${esc(u.nome)}</option>`).join('');
    } catch(e) {
      toast('Erro ao carregar dados', 'error');
    }
  }

  async function salvar() {
    const titulo     = document.getElementById('neg-titulo').value.trim();
    const contatoId  = +document.getElementById('neg-contato').value;
    const etapaId    = +document.getElementById('neg-etapa').value;
    const valor      = document.getElementById('neg-valor').value;
    const respId     = +document.getElementById('neg-responsavel').value;

    if (!titulo || !contatoId || !etapaId) {
      toast('Preencha os campos obrigatórios.', 'error'); return;
    }

    const btn = document.getElementById('btn-salvar-neg');
    btn.disabled = true;
    try {
      const action = _editId ? 'editar' : 'criar';
      const body = { titulo, contato_id: contatoId, etapa_id: etapaId, responsavel_id: respId };
      if (valor !== '') body.valor_estimado = +valor;
      if (_editId) body.id = _editId;

      await api(`/crm/api/negociacoes.php?action=${action}`, {
        method: 'POST', body: JSON.stringify(body),
      });
      closeModal('modal-neg');
      toast(_editId ? 'Negociação atualizada!' : 'Negociação criada!');
      reload();
    } catch(e) {
      toast(e.message, 'error');
    } finally { btn.disabled = false; }
  }

  async function abrirDetalhe(id) {
    document.getElementById('detalhe-body').innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';
    openModal('modal-detalhe');
    try {
      const { negociacao: n } = await api(`/crm/api/negociacoes.php?action=detalhe&id=${id}`);
      document.getElementById('detalhe-titulo').textContent = n.titulo;

      const timeline = (n.interacoes || []).map(i => `
        <div class="timeline-item">
          <div class="timeline-dot">${tipoIcon(i.tipo)}</div>
          <div class="timeline-content">
            <div class="timeline-meta">${esc(i.autor)} · ${timeAgo(i.data_criacao)}</div>
            <div class="timeline-text">${esc(i.descricao)}</div>
          </div>
        </div>`).join('') || '<p class="text-muted text-sm">Nenhuma interação registrada.</p>';

      const tarefas = (n.atividades || []).map(a => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
          <span style="font-size:.8rem;flex:1">${esc(a.titulo)}</span>
          <span class="badge ${a.concluida ? 'badge-success' : a.data_vencimento && new Date(a.data_vencimento) < new Date() ? 'badge-danger' : 'badge-warning'}">
            ${a.concluida ? 'Concluída' : (a.data_vencimento ? fmtDate(a.data_vencimento) : 'Aberta')}
          </span>
        </div>`).join('') || '<p class="text-muted text-sm">Nenhuma tarefa.</p>';

      document.getElementById('detalhe-body').innerHTML = `
        <div class="detail-panel">
          <div class="detail-main">
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
              ${statusBadge(n.status)}
              <span class="badge badge-info" style="background:${esc(n.etapa_cor)}20;color:${esc(n.etapa_cor)}">${esc(n.etapa_nome)}</span>
              ${n.valor_estimado ? `<span class="badge badge-success">${fmtMoney(n.valor_estimado)}</span>` : ''}
            </div>
            <div class="card mb-3" style="margin-bottom:16px;">
              <div style="font-weight:600;margin-bottom:10px;font-size:.85rem;">📞 Contato</div>
              <p><strong>${esc(n.contato_nome)}</strong></p>
              ${n.telefone ? `<p class="text-muted text-sm">📱 ${esc(n.telefone)}</p>` : ''}
              ${n.contato_email ? `<p class="text-muted text-sm">✉️ ${esc(n.contato_email)}</p>` : ''}
            </div>
            <div class="card">
              <div style="font-weight:600;margin-bottom:12px;font-size:.85rem;">📋 Histórico</div>
              <div class="timeline">${timeline}</div>
              <div style="margin-top:14px;">
                <textarea class="form-control" id="nova-interacao-desc" placeholder="Registrar nova interação…" rows="2"></textarea>
                <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
                  <select class="form-select" id="nova-interacao-tipo" style="flex:0 0 150px">
                    <option value="observacao">Observação</option>
                    <option value="ligacao">Ligação</option>
                    <option value="email">E-mail</option>
                    <option value="reuniao">Reunião</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="outro">Outro</option>
                  </select>
                  <button class="btn btn-primary btn-sm" onclick="kanban.addInteracao(${n.id}, ${n.contato_id})">Registrar</button>
                </div>
              </div>
            </div>
          </div>
          <div class="detail-side">
            <div class="card mb-3" style="margin-bottom:16px;">
              <div style="font-weight:600;margin-bottom:10px;font-size:.85rem;">✅ Tarefas</div>
              ${tarefas}
              <button class="btn btn-ghost btn-sm w-100" style="margin-top:10px;"
                onclick="window.location.href='/crm/atividades.php?neg=${n.id}'">Ver todas</button>
            </div>
            <div class="card">
              <div style="font-weight:600;margin-bottom:10px;font-size:.85rem;">⚙️ Ações</div>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <button class="btn btn-ghost btn-sm w-100" onclick="kanban.marcarGanho(${n.id})">🏆 Marcar como Ganho</button>
                <button class="btn btn-ghost btn-sm w-100" onclick="kanban.marcarPerdido(${n.id})">❌ Marcar como Perdido</button>
              </div>
            </div>
          </div>
        </div>`;
    } catch(e) {
      document.getElementById('detalhe-body').innerHTML = `<p class="text-muted">Erro: ${esc(e.message)}</p>`;
    }
  }

  async function addInteracao(negId, contatoId) {
    const desc = document.getElementById('nova-interacao-desc').value.trim();
    const tipo = document.getElementById('nova-interacao-tipo').value;
    if (!desc) { toast('Digite a descrição.', 'error'); return; }
    try {
      await api('/crm/api/contatos.php?action=interacao', {
        method: 'POST',
        body: JSON.stringify({ contato_id: contatoId, negociacao_id: negId, tipo, descricao: desc }),
      });
      toast('Interação registrada!');
      abrirDetalhe(negId);
    } catch(e) { toast(e.message, 'error'); }
  }

  async function marcarGanho(id) {
    if (!confirm('Marcar esta negociação como Ganha?')) return;
    try {
      await api('/crm/api/negociacoes.php?action=editar', {
        method: 'POST',
        body: JSON.stringify({ id, status: 'ganho' }),
      });
      toast('Negociação marcada como ganha!', 'success');
      closeModal('modal-detalhe');
      reload();
    } catch(e) { toast(e.message, 'error'); }
  }

  async function marcarPerdido(id) {
    const motivo = prompt('Motivo da perda (opcional):') ?? '';
    try {
      await api('/crm/api/negociacoes.php?action=editar', {
        method: 'POST',
        body: JSON.stringify({ id, status: 'perdido', motivo_perda: motivo }),
      });
      toast('Negociação marcada como perdida.', 'warning');
      closeModal('modal-detalhe');
      reload();
    } catch(e) { toast(e.message, 'error'); }
  }

  // Init
  reload();

  return { reload, abrirCriar, abrirDetalhe, salvar, addInteracao, marcarGanho, marcarPerdido };
})();
</script>

<!-- Modal: Novo Contato (atalho rápido) -->
<div id="modal-novo-contato" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Novo Contato</span>
      <button class="modal-close" onclick="closeModal('modal-novo-contato')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-control" id="nc-nome">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Telefone</label>
          <input class="form-control" id="nc-tel" type="tel">
        </div>
        <div class="form-group">
          <label class="form-label">E-mail</label>
          <input class="form-control" id="nc-email" type="email">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Origem</label>
        <input class="form-control" id="nc-origem" placeholder="Ex: Indicação, Site, Anúncio">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-novo-contato')">Cancelar</button>
      <button class="btn btn-primary" onclick="kanban.salvarNovoContato()">Salvar contato</button>
    </div>
  </div>
</div>

<script>
// Estender kanban com função de novo contato
kanban.abrirNovoContato = function() {
  ['nc-nome','nc-tel','nc-email','nc-origem'].forEach(id => document.getElementById(id).value = '');
  openModal('modal-novo-contato');
};

kanban.salvarNovoContato = async function() {
  const nome = document.getElementById('nc-nome').value.trim();
  if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
  try {
    const r = await api('/crm/api/contatos.php?action=criar', {
      method: 'POST',
      body: JSON.stringify({
        nome,
        telefone: document.getElementById('nc-tel').value,
        email:    document.getElementById('nc-email').value,
        origem:   document.getElementById('nc-origem').value,
      }),
    });
    toast('Contato criado!');
    closeModal('modal-novo-contato');
    // Recarregar selects se o modal de negociação estiver aberto
    if (!document.getElementById('modal-neg').classList.contains('hidden')) {
      kanban.abrirCriar();
    }
  } catch(e) { toast(e.message, 'error'); }
};
</script>

<?php layoutEnd(); ?>
