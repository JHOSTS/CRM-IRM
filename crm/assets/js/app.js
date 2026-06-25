/* ===================================================
   App — bootstrap global (carrega em todas as páginas)
   =================================================== */

document.addEventListener('DOMContentLoaded', () => {
  loadBadgeAtividades();
});

// Logout
async function logout() {
  try {
    await fetch('/crm/api/logout.php', { method: 'POST' });
  } finally {
    window.location.href = '/crm/index.php';
  }
}

// Badge de tarefas atrasadas/hoje no menu
async function loadBadgeAtividades() {
  try {
    const d = await api('/crm/api/atividades.php?action=contagem');
    const badge = document.getElementById('badge-atv');
    if (!badge) return;
    const n = (d.atrasadas || 0) + (d.hoje || 0);
    if (n > 0) { badge.textContent = n; badge.classList.remove('hidden'); }
  } catch {}
}
