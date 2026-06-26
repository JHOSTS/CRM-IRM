<?php
require_once __DIR__ . '/includes/layout.php';
layoutStart('Contatos', 'contatos');
?>

<div class="page-header">
  <h1>Contatos</h1>
  <button class="btn btn-primary" onclick="contatos.abrirCriar()">+ Novo contato</button>
</div>

<div class="page-body">
  <div class="toolbar" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrap" style="flex:1;min-width:200px;">
      <span class="search-icon">🔍</span>
      <input class="form-control" type="text" id="busca" placeholder="Buscar por nome, e-mail ou telefone…">
    </div>
    <select class="form-select" id="filtro-origem" style="width:auto;">
      <option value="">Todas as origens</option>
    </select>
    <input class="form-control" type="date" id="filtro-ini" style="width:150px;" title="Data de entrada de:">
    <input class="form-control" type="date" id="filtro-fim" style="width:150px;" title="Data de entrada até:">
    <button class="btn btn-ghost btn-sm" onclick="contatos.limparFiltros()">Limpar</button>
  </div>

  <div class="card">
    <div id="contatos-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="contatos-content" class="hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th><th>Telefone</th><th>E-mail</th><th>Origem</th>
              <th>Negociações</th><th>Última compra</th><th>Entrada</th><th></th>
            </tr>
          </thead>
          <tbody id="contatos-tbody"></tbody>
        </table>
      </div>
      <div id="contatos-pagination" class="pagination"></div>
    </div>
  </div>
</div>

<!-- Modal Criar/Editar -->
<div id="modal-contato" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="modal-contato-title">Novo Contato</span>
      <button class="modal-close" onclick="closeModal('modal-contato')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="contato-id">
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input class="form-control" id="contato-nome">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Telefone *</label>
          <input class="form-control" id="contato-tel" type="tel" required>
        </div>
        <div class="form-group">
          <label class="form-label">E-mail</label>
          <input class="form-control" id="contato-email" type="email">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Origem</label>
        <select class="form-select" id="contato-origem">
          <option value="">— Selecione —</option>
          <optgroup label="Meios Digitais">
            <option>Anúncios Google</option>
            <option>Anúncios Facebook/Instagram</option>
            <option>Site / Formulário web</option>
            <option>WhatsApp</option>
            <option>E-mail marketing</option>
            <option>LinkedIn</option>
            <option>YouTube</option>
            <option>SEO / Busca orgânica</option>
            <option>Loja virtual / E-commerce</option>
          </optgroup>
          <optgroup label="Meios Físicos">
            <option>Indicação de cliente</option>
            <option>Evento / Feira</option>
            <option>Visita presencial</option>
            <option>Panfleto / Material impresso</option>
            <option>Outdoor / Mídia exterior</option>
            <option>Rádio / TV</option>
            <option>Telefonema (receptivo)</option>
          </optgroup>
          <optgroup label="Outros">
            <option>Parceiro / Revendedor</option>
            <option>Outro</option>
          </optgroup>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Data de nascimento</label>
          <input class="form-control" id="contato-nascimento" type="date">
        </div>
        <div class="form-group">
          <label class="form-label">Data de entrada</label>
          <input class="form-control" id="contato-entrada" type="date">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Data da última compra</label>
        <input class="form-control" id="contato-ultima-compra" type="date">
        <small class="text-muted">Atualizada automaticamente quando uma negociação é marcada como ganha.</small>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-contato')">Cancelar</button>
      <button class="btn btn-primary" onclick="contatos.salvar()">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal Detalhe -->
<div id="modal-detalhe-contato" class="modal-overlay hidden">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title" id="detalhe-contato-nome">Contato</span>
      <button class="modal-close" onclick="closeModal('modal-detalhe-contato')">✕</button>
    </div>
    <div class="modal-body" id="detalhe-contato-body"></div>
  </div>
</div>

<script>
const contatos = (() => {
  let _page = 1, _busca = '', _total = 0, _limit = 30;
  let _origem = '', _ini = '', _fim = '';

  async function load(page = 1) {
    _page = page;
    show('loading');
    try {
      const params = new URLSearchParams({ action: 'lista', page, busca: _busca });
      if (_origem) params.set('origem', _origem);
      if (_ini)    params.set('data_ini', _ini);
      if (_fim)    params.set('data_fim', _fim);
      const d = await api('/crm/api/contatos.php?' + params);
      // Preencher filtro de origens
      const sel = document.getElementById('filtro-origem');
      const prev = sel.value;
      sel.innerHTML = '<option value="">Todas as origens</option>' +
        (d.origens || []).map(o => `<option value="${esc(o)}" ${o===prev?'selected':''}>${esc(o)}</option>`).join('');
      _total = d.total; _limit = d.limit;
      renderTabela(d.data);
      renderPagination(
        document.getElementById('contatos-pagination'),
        _page, _total, _limit, load
      );
      show('content');
    } catch(e) { toast(e.message, 'error'); show('content'); }
  }

  function renderTabela(rows) {
    const tbody = document.getElementById('contatos-tbody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><div class="empty-icon">👥</div><p>Nenhum contato encontrado.</p></td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(c => `
      <tr>
        <td><strong>${esc(c.nome)}</strong></td>
        <td>${esc(c.telefone || '—')}</td>
        <td class="text-sm">${esc(c.email || '—')}</td>
        <td class="text-sm">${esc(c.origem || '—')}</td>
        <td><span class="badge badge-info">${c.total_negociacoes}</span></td>
        <td class="text-sm text-muted">${c.data_ultima_compra ? fmtDate(c.data_ultima_compra) : '—'}</td>
        <td class="text-sm text-muted">${c.data_entrada ? fmtDate(c.data_entrada) : fmtDate(c.data_criacao)}</td>
        <td>
          <div style="display:flex;gap:4px;">
            <button class="btn btn-ghost btn-sm btn-icon" onclick="contatos.ver(${c.id})" title="Ver detalhe">👁</button>
            <button class="btn btn-ghost btn-sm btn-icon" onclick="contatos.editar(${c.id})">✏️</button>
          </div>
        </td>
      </tr>`).join('');
  }

  function show(s) {
    document.getElementById('contatos-loading').classList.toggle('hidden', s !== 'loading');
    document.getElementById('contatos-content').classList.toggle('hidden', s !== 'content');
  }

  function abrirCriar() {
    document.getElementById('modal-contato-title').textContent = 'Novo Contato';
    ['id','nome','tel','email','origem','nascimento','entrada','ultima-compra']
      .forEach(f => document.getElementById('contato-' + f).value = '');
    openModal('modal-contato');
  }

  async function editar(id) {
    document.getElementById('modal-contato-title').textContent = 'Editar Contato';
    document.getElementById('contato-id').value = id;
    try {
      const { contato: c } = await api(`/crm/api/contatos.php?action=detalhe&id=${id}`);
      document.getElementById('contato-nome').value        = c.nome || '';
      document.getElementById('contato-tel').value         = c.telefone || '';
      document.getElementById('contato-email').value       = c.email || '';
      document.getElementById('contato-origem').value      = c.origem || '';
      document.getElementById('contato-nascimento').value  = (c.data_nascimento || '').slice(0,10);
      document.getElementById('contato-entrada').value     = (c.data_entrada || '').slice(0,10);
      document.getElementById('contato-ultima-compra').value = (c.data_ultima_compra || '').slice(0,10);
      openModal('modal-contato');
    } catch(e) { toast(e.message, 'error'); }
  }

  function limparFiltros() {
    document.getElementById('filtro-origem').value = '';
    document.getElementById('filtro-ini').value    = '';
    document.getElementById('filtro-fim').value    = '';
    _origem = ''; _ini = ''; _fim = '';
    load(1);
  }

  async function salvar() {
    const id   = document.getElementById('contato-id').value;
    const nome = document.getElementById('contato-nome').value.trim();
    const tel  = document.getElementById('contato-tel').value.trim();
    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
    if (!tel)  { toast('Telefone é obrigatório.', 'error'); return; }
    const body = {
      nome,
      telefone:          document.getElementById('contato-tel').value,
      email:             document.getElementById('contato-email').value,
      origem:            document.getElementById('contato-origem').value,
      data_nascimento:   document.getElementById('contato-nascimento').value || null,
      data_entrada:      document.getElementById('contato-entrada').value || null,
      data_ultima_compra:document.getElementById('contato-ultima-compra').value || null,
    };
    if (id) body.id = +id;
    try {
      await api('/crm/api/contatos.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST', body: JSON.stringify(body),
      });
      toast(id ? 'Contato atualizado!' : 'Contato criado!');
      closeModal('modal-contato');
      load(_page);
    } catch(e) { toast(e.message, 'error'); }
  }

  async function ver(id) {
    document.getElementById('detalhe-contato-body').innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';
    openModal('modal-detalhe-contato');
    try {
      const { contato: c } = await api(`/crm/api/contatos.php?action=detalhe&id=${id}`);
      document.getElementById('detalhe-contato-nome').textContent = c.nome;

      const negs = (c.negociacoes || []).map(n => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
          <span style="font-size:.8rem;flex:1">${esc(n.titulo)}</span>
          <span class="badge badge-info" style="background:${esc(n.cor)}20;color:${esc(n.cor)}">${esc(n.etapa_nome)}</span>
          ${statusBadge(n.status)}
        </div>`).join('') || '<p class="text-muted text-sm">Nenhuma negociação.</p>';

      const timeline = (c.interacoes || []).map(i => `
        <div class="timeline-item">
          <div class="timeline-dot">${tipoIcon(i.tipo)}</div>
          <div class="timeline-content">
            <div class="timeline-meta">${esc(i.autor)} · ${timeAgo(i.data_criacao)}</div>
            <div class="timeline-text">${esc(i.descricao)}</div>
          </div>
        </div>`).join('') || '<p class="text-muted text-sm">Nenhuma interação.</p>';

      document.getElementById('detalhe-contato-body').innerHTML = `
        <div class="detail-panel">
          <div class="detail-main">
            <div class="card mb-3" style="margin-bottom:16px;">
              <div style="font-weight:600;font-size:.85rem;margin-bottom:10px;">📋 Histórico de Interações</div>
              <div class="timeline">${timeline}</div>
              <div style="margin-top:14px;">
                <textarea class="form-control" id="ci-desc" placeholder="Registrar nova interação…" rows="2"></textarea>
                <div style="display:flex;gap:8px;margin-top:8px;">
                  <select class="form-select" id="ci-tipo" style="flex:0 0 150px">
                    <option value="observacao">Observação</option>
                    <option value="ligacao">Ligação</option>
                    <option value="email">E-mail</option>
                    <option value="reuniao">Reunião</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="outro">Outro</option>
                  </select>
                  <button class="btn btn-primary btn-sm" onclick="contatos.addInteracao(${c.id})">Registrar</button>
                </div>
              </div>
            </div>
          </div>
          <div class="detail-side">
            <div class="card mb-3" style="margin-bottom:16px;">
              <div style="font-weight:600;font-size:.85rem;margin-bottom:8px;">ℹ️ Dados</div>
              <p class="text-sm">${c.telefone ? '📱 ' + esc(c.telefone) : ''}</p>
              <p class="text-sm">${c.email    ? '✉️ ' + esc(c.email)    : ''}</p>
              <p class="text-sm text-muted">Origem: ${esc(c.origem || '—')}</p>
              ${c.data_nascimento ? `<p class="text-sm text-muted">🎂 Nascimento: ${fmtDate(c.data_nascimento)}</p>` : ''}
              ${c.data_entrada    ? `<p class="text-sm text-muted">📅 Entrada: ${fmtDate(c.data_entrada)}</p>` : ''}
              ${c.data_ultima_compra ? `<p class="text-sm text-muted">🛍️ Última compra: ${fmtDate(c.data_ultima_compra)}</p>` : ''}
              <p class="text-sm text-muted mt-1">Cadastrado em: ${fmtDate(c.data_criacao)}</p>
            </div>
            <div class="card">
              <div style="font-weight:600;font-size:.85rem;margin-bottom:8px;">🤝 Negociações</div>
              ${negs}
            </div>
          </div>
        </div>`;
    } catch(e) {
      document.getElementById('detalhe-contato-body').innerHTML = `<p class="text-muted">Erro: ${esc(e.message)}</p>`;
    }
  }

  async function addInteracao(contatoId) {
    const desc = document.getElementById('ci-desc').value.trim();
    const tipo = document.getElementById('ci-tipo').value;
    if (!desc) { toast('Digite a descrição.', 'error'); return; }
    try {
      await api('/crm/api/contatos.php?action=interacao', {
        method: 'POST',
        body: JSON.stringify({ contato_id: contatoId, tipo, descricao: desc }),
      });
      toast('Interação registrada!');
      ver(contatoId);
    } catch(e) { toast(e.message, 'error'); }
  }

  // Listeners de filtros
  let _debounce;
  document.getElementById('busca').addEventListener('input', e => {
    clearTimeout(_debounce);
    _debounce = setTimeout(() => { _busca = e.target.value.trim(); load(1); }, 400);
  });
  document.getElementById('filtro-origem').addEventListener('change', e => { _origem = e.target.value; load(1); });
  document.getElementById('filtro-ini').addEventListener('change', e => { _ini = e.target.value; load(1); });
  document.getElementById('filtro-fim').addEventListener('change', e => { _fim = e.target.value; load(1); });

  load();
  return { abrirCriar, editar, salvar, ver, addInteracao, limparFiltros };
})();
</script>

<?php layoutEnd(); ?>
