<?php
require_once __DIR__ . '/auth.php';
$_LAYOUT_USER = requireLogin(false);

function layoutStart(string $title, string $activeNav): void {
    global $_LAYOUT_USER;
    $cargo     = $_LAYOUT_USER['cargo'];
    $nome      = e($_LAYOUT_USER['nome']);
    $inicial   = mb_strtoupper(mb_substr($_LAYOUT_USER['nome'], 0, 1));
    $isMaster  = $cargo === 'master';
    $isGerente = in_array($cargo, ['gerente','master'], true);
    $empId     = getEmpresaId($_LAYOUT_USER);
    $branding  = getEmpresaBranding($empId);

    // Lista de empresas para o seletor do master
    $empresasLista = [];
    if ($isMaster) {
        try {
            $pdo2 = getDB();
            $s2   = $pdo2->prepare("SELECT id, nome FROM empresas WHERE status = 'ativo' ORDER BY nome ASC");
            $s2->execute();
            $empresasLista = $s2->fetchAll();
        } catch (PDOException $e) {}
    }
    $corPrim   = e($branding['cor_primaria'] ?: '#4361ee');
    $corSec    = e($branding['cor_secundaria'] ?: '#1a1d27');
    $logo      = $branding['logo'] ? '/crm/' . e($branding['logo']) : null;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — CRM IRM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/crm/assets/css/style.css">
  <!-- Branding da empresa -->
  <style>
    :root {
      --accent:   <?= $corPrim ?>;
      --accent-h: <?= $corPrim ?>cc;
      --surface:  <?= $corSec ?>;
    }
    .logo-watermark {
      position: fixed;
      top: 18px;
      left: calc(var(--sidebar-w, 240px) + 18px);
      opacity: 0.07;
      pointer-events: none;
      z-index: 0;
    }
    .logo-watermark img {
      height: 52px;
      width: auto;
      max-width: 160px;
      object-fit: contain;
      filter: grayscale(30%);
    }
    .main-content > *:not(.logo-watermark) { position: relative; z-index: 1; }
  </style>
  <script src="/crm/assets/js/utils.js"></script>
  <script src="/crm/assets/js/app.js"></script>
</head>
<body>
<div class="app-layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <?php if ($logo): ?>
        <img src="<?= $logo ?>" alt="Logo" style="height:32px;max-width:130px;object-fit:contain;">
      <?php else: ?>
        <div class="dot" style="background:<?= $corPrim ?>"></div>
        <span>CRM IRM</span>
      <?php endif; ?>
    </div>
    <div style="padding:4px 12px 10px;border-bottom:1px solid var(--border);text-align:center;">
      <a href="https://www.irmcomunicacao.com" target="_blank" rel="noopener"
         style="font-size:.6rem;color:var(--text-muted);opacity:.55;text-decoration:none;letter-spacing:.03em;">
        desenvolvido por IRM Comunicação
      </a>
    </div>

    <?php if ($isMaster && !empty($empresasLista)): ?>
    <!-- Seletor de empresa (master) -->
    <div style="padding:10px 12px;border-bottom:1px solid var(--border);">
      <div style="font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:6px;">Empresa ativa</div>
      <select id="master-empresa-sel"
        style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:.82rem;cursor:pointer;"
        onchange="masterTrocarEmpresa(this.value)">
        <?php foreach ($empresasLista as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= (int)$emp['id'] === $empId ? 'selected' : '' ?>>
            <?= e($emp['nome']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <nav class="sidebar-nav">
      <div class="nav-group-label">Principal</div>

      <div class="nav-item <?= $activeNav === 'contatos' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/contatos.php'">
        <span class="icon">🤝</span> Contatos
      </div>

      <div class="nav-item <?= $activeNav === 'kanban' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/kanban.php'">
        <span class="icon">🗂️</span> Negociações
      </div>

      <div class="nav-item <?= $activeNav === 'atividades' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/atividades.php'">
        <span class="icon">📅</span> Atividades
        <span class="nav-badge hidden" id="badge-atv"></span>
      </div>

      <?php if ($isGerente): ?>
      <div class="nav-group-label">Gerência</div>

      <div class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/dashboard.php'">
        <span class="icon">📊</span> Dashboard
      </div>

      <div class="nav-item <?= $activeNav === 'relatorios' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/relatorios.php'">
        <span class="icon">📈</span> Relatórios
      </div>

      <div class="nav-item <?= $activeNav === 'log' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/log.php'">
        <span class="icon">📜</span> Log de Atividades
      </div>

      <div class="nav-item <?= $activeNav === 'usuarios' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/usuarios.php'">
        <span class="icon">👥</span> Usuários
      </div>

      <div class="nav-item <?= $activeNav === 'configuracoes' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/configuracoes.php'">
        <span class="icon">⚙️</span> Configurações
      </div>
      <?php endif; ?>

      <?php if ($isMaster): ?>
      <div class="nav-group-label">Master</div>
      <div class="nav-item <?= $activeNav === 'empresas' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/empresas.php'">
        <span class="icon">🏢</span> Empresas
      </div>
      <?php endif; ?>
    </nav>

    <div class="sidebar-user">
      <div class="user-avatar" style="background:<?= $corPrim ?>"><?= $inicial ?></div>
      <div class="user-info">
        <div class="name"><?= $nome ?></div>
        <div class="role"><?= $cargo ?></div>
      </div>
      <button class="btn-logout" onclick="logout()" title="Sair">⏏</button>
    </div>
  </aside>

  <!-- Main -->
  <div class="main-content">
    <?php if ($logo): ?>
    <div class="logo-watermark">
      <img src="<?= $logo ?>" alt="">
    </div>
    <?php endif; ?>
    <?php
}

function layoutEnd(): void {
    ?>
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<div id="toast-container"></div>

<script>
async function masterTrocarEmpresa(empresaId) {
  try {
    await api('/crm/api/trocar_empresa.php', {
      method: 'POST',
      body: JSON.stringify({ empresa_id: +empresaId }),
    });
    window.location.reload();
  } catch(e) {
    toast(e.message || 'Erro ao trocar empresa.', 'error');
  }
}
</script>
</body>
</html>
    <?php
}
