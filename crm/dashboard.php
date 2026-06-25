<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php');
    exit;
}
layoutStart('Dashboard', 'dashboard');
?>

<div class="page-header">
  <h1>Dashboard</h1>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="date" class="form-control" id="filtro-inicio" style="width:160px">
    <span style="color:var(--text-muted)">até</span>
    <input type="date" class="form-control" id="filtro-fim" style="width:160px">
    <button class="btn btn-primary btn-sm" onclick="dash.load()">Aplicar</button>
  </div>
</div>

<div class="page-body">
  <div id="dash-loading" class="loading-state"><div class="spinner"></div></div>
  <div id="dash-content" class="hidden">

    <!-- Métricas principais -->
    <div class="metrics-grid" id="dash-metrics"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;flex-wrap:wrap;">
      <!-- Por etapa -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:14px;font-size:.88rem;">Negociações por Etapa (em andamento)</div>
        <div class="bar-chart" id="dash-etapas"></div>
      </div>
      <!-- Ranking -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:14px;font-size:.88rem;">Ranking de Atendentes</div>
        <div id="dash-ranking"></div>
      </div>
    </div>

  </div>
</div>

<script>
// Defaults: mês atual
const hoje = new Date();
const ini = hoje.getFullYear() + '-' + String(hoje.getMonth()+1).padStart(2,'0') + '-01';
const fim = new Date(hoje.getFullYear(), hoje.getMonth()+1, 0).toISOString().slice(0,10);
document.getElementById('filtro-inicio').value = ini;
document.getElementById('filtro-fim').value    = fim;

const dash = (() => {
  async function load() {
    const inicio = document.getElementById('filtro-inicio').value;
    const fim    = document.getElementById('filtro-fim').value;
    document.getElementById('dash-loading').classList.remove('hidden');
    document.getElementById('dash-content').classList.add('hidden');
    try {
      const d = await api(`/crm/api/dashboard.php?inicio=${inicio}&fim=${fim}`);
      renderMetrics(d);
      renderEtapas(d.por_etapa);
      renderRanking(d.ranking);
      document.getElementById('dash-loading').classList.add('hidden');
      document.getElementById('dash-content').classList.remove('hidden');
    } catch(e) {
      toast(e.message, 'error');
      document.getElementById('dash-loading').classList.add('hidden');
    }
  }

  function renderMetrics(d) {
    const t = d.totais;
    document.getElementById('dash-metrics').innerHTML = `
      <div class="metric-card">
        <div class="metric-label">Total negociações</div>
        <div class="metric-value">${t.total}</div>
        <div class="metric-sub">no período</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Ganhas</div>
        <div class="metric-value green">${t.ganhas}</div>
        <div class="metric-sub">${fmtMoney(t.valor_ganho)}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Perdidas</div>
        <div class="metric-value red">${t.perdidas}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Taxa de conversão</div>
        <div class="metric-value blue">${t.taxa_conversao}%</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Em Negociação</div>
        <div class="metric-value yellow">${t.em_negociacao}</div>
        <div class="metric-sub">${fmtMoney(t.valor_em_negociacao)}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Pipeline total</div>
        <div class="metric-value">${t.pipeline}</div>
        <div class="metric-sub">${fmtMoney(t.valor_pipeline)}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Tarefas atrasadas</div>
        <div class="metric-value ${d.tarefas_atrasadas > 0 ? 'red' : ''}">${d.tarefas_atrasadas}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Novos contatos</div>
        <div class="metric-value">${d.novos_contatos}</div>
        <div class="metric-sub">no período</div>
      </div>`;
  }

  function renderEtapas(etapas) {
    const max = Math.max(...etapas.map(e => +e.total), 1);
    document.getElementById('dash-etapas').innerHTML = etapas.map(e => `
      <div class="bar-item">
        <div class="bar-label">${esc(e.nome)}</div>
        <div class="bar-track">
          <div class="bar-fill" style="width:${(e.total/max*100).toFixed(1)}%;background:${esc(e.cor)}"></div>
        </div>
        <div class="bar-val">${e.total}</div>
      </div>`).join('');
  }

  function renderRanking(ranking) {
    if (!ranking.length) {
      document.getElementById('dash-ranking').innerHTML = '<p class="text-muted text-sm">Nenhum atendente.</p>';
      return;
    }
    const maxGanhas = Math.max(...ranking.map(r => +r.ganhas), 1);
    document.getElementById('dash-ranking').innerHTML = ranking.map((r, i) => `
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
        <span style="font-size:1rem;width:24px;text-align:center">${i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i+1}.`}</span>
        <div style="flex:1;">
          <div style="font-size:.85rem;font-weight:600">${esc(r.nome)}</div>
          <div style="font-size:.72rem;color:var(--text-muted)">${r.total} neg · ${r.ganhas} ganhas · ${fmtMoney(r.valor_ganho)}</div>
        </div>
        <div style="width:80px;">
          <div class="bar-track"><div class="bar-fill" style="width:${(r.ganhas/maxGanhas*100).toFixed(1)}%;background:var(--success)"></div></div>
        </div>
      </div>`).join('');
  }

  load();
  return { load };
})();
</script>

<?php layoutEnd(); ?>
