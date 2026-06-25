<?php
require_once __DIR__ . '/includes/layout.php';
layoutStart('Contatos', 'contatos');
?>

<div class="page-header">
  <h1>Contatos</h1>
  <button class="btn btn-primary" onclick="contatos.abrirCriar()">+ Novo contato</button>
</div>

<div class="page-body">
  <div class="toolbar">
    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input class="form-control" type="text" id="busca" placeholder="Buscar por nome, e-mail ou telefone…">
    </div>
  </div>

  <div class="card">
    <div id="contatos-loading" class="loading-state"><div class="spinner"></div></div>
    <div id="contatos-content" class="hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th><th>Telefone</th><th>E-mail</th><th>Origem</th>
              <th>Negociações</th><th>Criado em</th><th></th>
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
          <label class="form-label">Telefone</label>
          <input class="form-control" id="contato-tel" type="tel">
        </div>
        <div class="form-group">
          <label class="form-label">E-mail</label>
          <input class="form-control" id="contato-email" type="email">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Origem (ex: Indicação, Site, Anúncio)</label>
        <input class="form-control" id="contato-origem">
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

  async function load(page = 1) {
    _page = page;
    show('loading');
    try {
      const params = new URLSearchParams({ action: 'lista', page, busca: _busca });
      const d = await api('/crm/api/contatos.php?' + params);
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
        <td>${esc(c.email || '—')}</td>
        <td>${esc(c.origem || '—')}</td>
        <td><span class="badge badge-info">${c.total_negociacoes}</span></td>
        <td class="text-muted text-sm">${fmtDate(c.data_criacao)}</td>
        <td>
          <div style="display:flex;gap:4px;">
            <button class="btn btn-ghost btn-sm btn-icon" onclick="contatos.ver(${c.id})" title="Ver detalhe">👁</button>
            <button class="btn btn-ghost btn-sm btn-icon" onclick="contatos.editar(${c.id},'${esc(c.nome)}','${esc(c.telefone||'')}','${esc(c.email||'')}','${esc(c.origem||'')}')">✏️</button>
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
    ['id','nome','tel','email','origem'].forEach(f => document.getElementById('contato-' + f).value = '');
    openModal('modal-contato');
  }

  function editar(id, nome, tel, email, origem) {
    document.getElementById('modal-contato-title').textContent = 'Editar Contato';
    document.getElementById('contato-id').value    = id;
    document.getElementById('contato-nome').value  = nome;
    document.getElementById('contato-tel').value   = tel;
    document.getElementById('contato-email').value = email;
    document.getElementById('contato-origem').value= origem;
    openModal('modal-contato');
  }

  async function salvar() {
    const id     = document.getElementById('contato-id').value;
    const nome   = document.getElementById('contato-nome').value.trim();
    if (!nome) { toast('Nome é obrigatório.', 'error'); return; }
    const body = {
      nome,
      telefone: document.getElementById('contato-tel').value,
      email:    document.getElementById('contato-email').value,
      origem:   document.getElementById('contato-origem').value,
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

  // Busca com debounce
  let _debounce;
  document.getElementById('busca').addEventListener('input', e => {
    clearTimeout(_debounce);
    _debounce = setTimeout(() => { _busca = e.target.value.trim(); load(1); }, 400);
  });

  load();
  return { abrirCriar, editar, salvar, ver, addInteracao };
})();
</script>

<?php layoutEnd(); ?>
