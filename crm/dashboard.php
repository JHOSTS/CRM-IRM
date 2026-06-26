<?php
require_once __DIR__ . '/includes/layout.php';
$user = getSessionUser();
if (!in_array($user['cargo'], ['gerente','master'], true)) {
    header('Location: /crm/kanban.php'); exit;
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

    <!-- Receita -->
    <div class="metrics-grid" id="dash-receita" style="margin-top:0;"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
      <!-- Leads por dia -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:14px;font-size:.88rem;">Leads por Dia (no período)</div>
        <div id="dash-leads-dia"></div>
      </div>
      <!-- Leads por usuário -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:14px;font-size:.88rem;">Leads por Usuário</div>
        <div class="bar-chart" id="dash-leads-user"></div>
      </div>
    </div>

  </div>
</div>

<script>
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
      renderReceita(d);
      renderEtapas(d.por_etapa || []);
      renderRanking(d.ranking || []);
      renderLeadsDia(d.leads_dia || []);
      renderLeadsUser(d.leads_por_user || []);
      document.getElementById('dash-loading').classList.add('hidden');
      document.getElementById('dash-content').classList.remove('hidden');
    } catch(e) {
      toast(e.message, 'error');
      document.getElementById('dash-loading').classList.add('hidden');
    }
  }

  function val(v) { return v != null ? v : 0; }

  function renderMetrics(d) {
    const t = d.totais || {};
    document.getElementById('dash-metrics').innerHTML = `
      <div class="metric-card">
        <div class="metric-label">Total negociações</div>
        <div class="metric-value">${val(t.total)}</div>
        <div class="metric-sub">no período</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Ganhas</div>
        <div class="metric-value green">${val(t.ganhas)}</div>
        <div class="metric-sub">${fmtMoney(val(t.valor_ganho))}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Perdidas</div>
        <div class="metric-value red">${val(t.perdidas)}</div>
        <div class="metric-sub">${fmtMoney(val(t.valor_perdido))}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Taxa de conversão</div>
        <div class="metric-value blue">${val(t.taxa_conversao)}%</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Em Negociação</div>
        <div class="metric-value yellow">${val(t.em_negociacao)}</div>
        <div class="metric-sub">${fmtMoney(val(t.valor_em_negociacao))}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Ganhos Possíveis</div>
        <div class="metric-value">${val(t.pipeline)}</div>
        <div class="metric-sub">${fmtMoney(val(t.valor_pipeline))}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Tarefas atrasadas</div>
        <div class="metric-value ${(d.tarefas_atrasadas||0) > 0 ? 'red' : ''}">${val(d.tarefas_atrasadas)}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Novos contatos</div>
        <div class="metric-value">${val(d.novos_contatos)}</div>
        <div class="metric-sub">no período</div>
      </div>`;
  }

  function renderReceita(d) {
    const t = d.totais || {};
    document.getElementById('dash-receita').innerHTML = `
      <div class="metric-card" style="border-left:3px solid var(--success)">
        <div class="metric-label">💰 Receita Ganha</div>
        <div class="metric-value green">${fmtMoney(val(t.valor_ganho))}</div>
        <div class="metric-sub">${val(t.ganhas)} negociações fechadas</div>
      </div>
      <div class="metric-card" style="border-left:3px solid var(--danger)">
        <div class="metric-label">❌ Valor Perdido</div>
        <div class="metric-value red">${fmtMoney(val(t.valor_perdido))}</div>
        <div class="metric-sub">${val(t.perdidas)} negociações perdidas</div>
      </div>
      <div class="metric-card" style="border-left:3px solid var(--accent)">
        <div class="metric-label">📈 Potencial em Pipeline</div>
        <div class="metric-value blue">${fmtMoney(val(t.valor_pipeline))}</div>
        <div class="metric-sub">${val(t.pipeline)} negociações em aberto</div>
      </div>`;
  }

  function renderEtapas(etapas) {
    const max = Math.max(...etapas.map(e => +(e.total||0)), 1);
    document.getElementById('dash-etapas').innerHTML = etapas.length
      ? etapas.map(e => `
        <div class="bar-item">
          <div class="bar-label">${esc(e.nome||'—')}</div>
          <div class="bar-track">
            <div class="bar-fill" style="width:${((e.total||0)/max*100).toFixed(1)}%;background:${esc(e.cor||'var(--accent)')}"></div>
          </div>
          <div class="bar-val">${e.total||0}</div>
        </div>`).join('')
      : '<p class="text-muted text-sm">Nenhuma etapa configurada.</p>';
  }

  function renderRanking(ranking) {
    if (!ranking.length) {
      document.getElementById('dash-ranking').innerHTML = '<p class="text-muted text-sm">Nenhum atendente.</p>';
      return;
    }
    const maxGanhos = Math.max(...ranking.map(r => +(r.ganhos||0)), 1);
    document.getElementById('dash-ranking').innerHTML = ranking.map((r, i) => `
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:1rem;width:24px;text-align:center;flex-shrink:0">${i===0?'🥇':i===1?'🥈':i===2?'🥉':`${i+1}.`}</span>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(r.nome||'—')}</div>
            <div style="display:flex;gap:10px;font-size:.72rem;color:var(--text-muted);margin-top:2px;flex-wrap:wrap;">
              <span>📋 ${r.total_leads||0} leads</span>
              <span style="color:var(--success)">✓ ${r.ganhos||0} ganhos</span>
              <span style="color:var(--danger)">✗ ${r.perdidos||0} perdidos</span>
              <span>💰 ${fmtMoney(r.valor_ganho||0)}</span>
              <span style="font-weight:600;color:var(--accent)">${r.conversao||0}% conversão</span>
            </div>
          </div>
        </div>
        <div style="margin-top:6px;margin-left:34px;">
          <div class="bar-track"><div class="bar-fill" style="width:${((r.ganhos||0)/maxGanhos*100).toFixed(1)}%;background:var(--success)"></div></div>
        </div>
      </div>`).join('');
  }

  function renderLeadsDia(dados) {
    const el = document.getElementById('dash-leads-dia');
    if (!dados.length) { el.innerHTML = '<p class="text-muted text-sm">Sem dados no período.</p>'; return; }

    const W = 500, H = 120, PL = 32, PR = 8, PT = 10, PB = 24;
    const max  = Math.max(...dados.map(d => +(d.total||0)), 1);
    const n    = dados.length;
    const xPos = i => PL + (n > 1 ? (i / (n - 1)) * (W - PL - PR) : (W - PL - PR) / 2);
    const yPos = v => PT + (1 - v / max) * (H - PT - PB);

    const points = dados.map((d, i) => `${xPos(i).toFixed(1)},${yPos(+d.total).toFixed(1)}`).join(' ');
    const area   = `${xPos(0).toFixed(1)},${(H-PB).toFixed(1)} ` + points + ` ${xPos(n-1).toFixed(1)},${(H-PB).toFixed(1)}`;

    // Eixo X: mostrar apenas início, meio e fim
    const labels = [0, Math.floor(n/2), n-1].filter((v,i,a) => a.indexOf(v)===i && v < n);
    const axisX  = labels.map(i => `
      <text x="${xPos(i).toFixed(1)}" y="${H}" text-anchor="middle" font-size="9" fill="var(--text-muted)">
        ${fmtDate(dados[i].dia)}
      </text>`).join('');

    // Linhas horizontais
    const gridLines = [0, 0.5, 1].map(t => {
      const y = yPos(max * t).toFixed(1);
      return `<line x1="${PL}" y1="${y}" x2="${W-PR}" y2="${y}" stroke="var(--border)" stroke-width="1"/>
              <text x="${PL-2}" y="${(+y+3).toFixed(1)}" text-anchor="end" font-size="9" fill="var(--text-muted)">${Math.round(max*t)}</text>`;
    }).join('');

    // Dots
    const dots = dados.map((d, i) => `
      <circle cx="${xPos(i).toFixed(1)}" cy="${yPos(+d.total).toFixed(1)}" r="3"
              fill="var(--accent)" stroke="var(--bg)" stroke-width="1.5">
        <title>${fmtDate(d.dia)}: ${d.total} lead(s)</title>
      </circle>`).join('');

    el.innerHTML = `
      <svg viewBox="0 0 ${W} ${H+4}" xmlns="http://www.w3.org/2000/svg" style="width:100%;overflow:visible;">
        ${gridLines}
        <polygon points="${area}" fill="var(--accent)" opacity="0.08"/>
        <polyline points="${points}" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linejoin="round"/>
        ${dots}
        ${axisX}
      </svg>`;
  }

  function renderLeadsUser(dados) {
    const el  = document.getElementById('dash-leads-user');
    const max = Math.max(...dados.map(d => +(d.total_leads||0)), 1);
    el.innerHTML = dados.length
      ? dados.map(u => `
        <div class="bar-item">
          <div class="bar-label" style="min-width:90px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(u.nome||'—')}</div>
          <div class="bar-track">
            <div class="bar-fill" style="width:${((u.total_leads||0)/max*100).toFixed(1)}%;background:var(--accent)"></div>
          </div>
          <div class="bar-val" style="min-width:60px;font-size:.72rem;">${u.total_leads||0} (${u.ganhos||0} g)</div>
        </div>`).join('')
      : '<p class="text-muted text-sm">Nenhum usuário com leads.</p>';
  }

  load();
  return { load };
})();
</script>

<?php layoutEnd(); ?>
