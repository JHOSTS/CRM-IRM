<?php
require_once __DIR__ . '/includes/auth.php';

// Se já está logado, redireciona para o kanban
if (getSessionUser()) {
    header('Location: /crm/kanban.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CRM — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/crm/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <h1>CRM <span style="color:var(--accent)">IRM</span></h1>
      <p>Gestão de relacionamentos e negociações</p>
    </div>

    <div id="login-error" class="login-error hidden"></div>

    <form id="login-form" autocomplete="off">
      <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="seu@email.com" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="senha">Senha</label>
        <input class="form-control" type="password" id="senha" name="senha"
               placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100" id="btn-login" style="margin-top:8px;">
        Entrar
      </button>
    </form>
  </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-login');
  const errEl = document.getElementById('login-error');
  errEl.classList.add('hidden');
  btn.disabled = true;
  btn.textContent = 'Entrando…';

  try {
    const res = await fetch('/crm/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: document.getElementById('email').value,
        senha: document.getElementById('senha').value,
      }),
    });
    const data = await res.json();
    if (res.ok && data.success) {
      window.location.href = '/crm/kanban.php';
    } else {
      errEl.textContent = data.error || 'Erro ao fazer login.';
      errEl.classList.remove('hidden');
    }
  } catch {
    errEl.textContent = 'Falha de conexão. Tente novamente.';
    errEl.classList.remove('hidden');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});
</script>
</body>
</html>
