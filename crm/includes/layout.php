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

    <nav class="sidebar-nav">
      <div class="nav-group-label">Principal</div>

      <div class="nav-item <?= $activeNav === 'contatos' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/contatos.php'">
        <span class="icon">👥</span> Contatos
      </div>

      <div class="nav-item <?= $activeNav === 'kanban' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/kanban.php'">
        <span class="icon">⬛</span> Negociações
      </div>

      <div class="nav-item <?= $activeNav === 'atividades' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/atividades.php'">
        <span class="icon">✅</span> Atividades
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
        <span class="icon">📋</span> Relatórios
      </div>

      <div class="nav-item <?= $activeNav === 'log' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/log.php'">
        <span class="icon">🔍</span> Log de Atividades
      </div>

      <div class="nav-item <?= $activeNav === 'usuarios' ? 'active' : '' ?>"
           onclick="window.location.href='/crm/usuarios.php'">
        <span class="icon">👤</span> Usuários
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
    <?php
}

function layoutEnd(): void {
    ?>
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<div id="toast-container"></div>
</body>
</html>
    <?php
}
