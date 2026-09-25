<?php
declare(strict_types=1);

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$displayName = trim((string)($currentUser['display_name'] ?? 'Étudiant'));
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($displayName, 0, 1)) : strtoupper(substr($displayName, 0, 1));
$profilePic = $currentUser['profile_picture'] ?? '';
$navigation = [
    ['index.php', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>', "Vue d'ensemble"],
    ['tasks.php', '✓', 'Mes tâches'],
    ['add.php', '+', 'Ajouter une tâche'],
    ['courses.php', '▣', 'Ressources'],
    ['chat.php', '💬', 'Chat promo'],
    ['timetable.php', '▦', 'Emploi du temps'],
    ['settings.php', '⚙', 'Paramètres'],
];
?>
<button class="mobile-menu-toggle" type="button" aria-controls="student-sidebar" aria-expanded="false">
  <span></span><span></span><span></span><span class="sr-only">Ouvrir le menu</span>
</button>
<div class="sidebar-backdrop" data-sidebar-close></div>
<aside class="sidebar" id="student-sidebar">
  <a class="brand" href="index.php" aria-label="CampusFlow accueil">
    <span class="brand-mark">C</span><span>Campus<span>Flow</span></span>
  </a>
  <p class="sidebar-label">Espace étudiant</p>
  <nav aria-label="Navigation principale">
    <?php foreach ($navigation as [$href, $icon, $label]): ?>
      <a class="nav-item<?= $currentPage === $href ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
        <span class="icon" aria-hidden="true"><?= str_starts_with($icon, '<svg') ? $icon : htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <a class="account-card" href="settings.php">
    <?php if ($profilePic): ?>
        <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES, 'UTF-8') ?>" class="avatar" style="object-fit:cover" alt="">
    <?php else: ?>
        <span class="avatar"><?= htmlspecialchars($initial ?: 'É', ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <span class="account-copy"><strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong><small>Compte étudiant</small></span>
    <span class="account-arrow" aria-hidden="true">→</span>
  </a>
  <button class="sidebar-logout" type="button" data-logout>Se déconnecter</button>
  <div class="sidebar-tip">
    <strong>Petit conseil</strong>
    <p>Commence par la tâche la plus proche. Une petite victoire débloque souvent le reste.</p>
  </div>
  <div class="sidebar-footer">Espace privé de la promotion.</div>
</aside>
<script>
document.addEventListener('click', event => {
  const toggle = event.target.closest('.mobile-menu-toggle');
  if (toggle) {
    const open = document.body.classList.toggle('menu-open');
    toggle.setAttribute('aria-expanded', String(open));
  }
  if (event.target.closest('[data-sidebar-close]') || event.target.closest('.sidebar .nav-item')) {
    document.body.classList.remove('menu-open');
    document.querySelector('.mobile-menu-toggle')?.setAttribute('aria-expanded', 'false');
  }
  if (event.target.closest('[data-logout]')) {
    fetch('/api/auth/logout', { method: 'POST' }).finally(() => { window.location.href = 'login.php'; });
  }
});
</script>
