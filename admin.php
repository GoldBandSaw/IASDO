<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requireAdmin();
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CampusFlow — Administration</title>
  <link rel="stylesheet" href="page-shell.css">
  <link rel="stylesheet" href="shared-pages.css">
  <link rel="stylesheet" href="modern.css">
</head>
<body data-page="admin">
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="index.html"><span class="brand-mark">C</span><span>Campus<span>Flow</span></span></a>
      <p class="sidebar-label">Administration</p>
      <nav>
        <a class="nav-item active" href="admin.php"><span class="icon">▦</span> Propositions</a>
        <a class="nav-item" href="index.html"><span class="icon">⌂</span> Vue d'ensemble</a>
        <a class="nav-item" href="timetable.html"><span class="icon">▦</span> Emploi du temps</a>
        <a class="nav-item" href="settings.html"><span class="icon">⚙</span> Paramètres</a>
      </nav>
      <div class="sidebar-footer">Validation des ressources de la promotion.</div>
    </aside>
    <main class="page-content">
      <section class="page-card">
        <p class="eyebrow">Espace administrateur</p>
        <h1 class="page-title">Propositions de cours</h1>
        <p class="page-subtitle">Valide les matières proposées par les étudiants avant leur publication.</p>
        <div id="admin-proposals" class="admin-list"><p class="calendar-status">Chargement…</p></div>
      </section>
    </main>
  </div>
  <script>
    async function loadProposals() {
      const response = await fetch('/api/proposals');
      const proposals = await response.json();
      const target = document.querySelector('#admin-proposals');
      target.innerHTML = proposals.length ? proposals.map(item => `<article class="admin-item"><div><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.course)} · ${escapeHtml(item.resource_type)}</span></div><div><button class="primary-button" data-action="approve" data-id="${item.id}">Valider</button><button class="secondary-button" data-action="reject" data-id="${item.id}">Refuser</button></div></article>`).join('') : '<p class="calendar-status">Aucune proposition en attente.</p>';
    }
    function escapeHtml(value) { return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      await fetch(`/api/proposals/${button.dataset.id}/${button.dataset.action}`, { method: 'POST' });
      loadProposals();
    });
    loadProposals();
  </script>
</body>
</html>
