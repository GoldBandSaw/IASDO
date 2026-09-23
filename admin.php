<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
if (!currentAdmin()) {
    header('Location: /admin-login.php');
    exit;
}
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
  <div class="app-shell admin-shell">
    <aside class="sidebar">
      <a class="brand" href="admin.php"><span class="brand-mark">C</span><span>Campus<span>Flow</span></span></a>
      <p class="sidebar-label">Administration</p>
      <nav><a class="nav-item active" href="#proposals"><span class="icon">▦</span> Modération</a></nav>
      <button class="sidebar-logout" id="admin-logout" type="button">Se déconnecter</button>
      <div class="sidebar-footer">Panneau indépendant de l’espace étudiant.</div>
    </aside>
    <main class="page-content">
      <section class="page-card">
        <p class="eyebrow">Espace administrateur</p>
        <h1 class="page-title">Propositions de cours</h1>
        <p class="page-subtitle">Consulte les liens complets, supprime une ressource problématique et traite les signalements.</p>
        <div id="admin-proposals" class="admin-list"><p class="calendar-status">Chargement…</p></div>
      </section>
      <section class="page-card admin-resource-panel">
        <p class="eyebrow">Bibliothèque</p><h2>Ressources publiées</h2>
        <div id="admin-resources" class="admin-list"><p class="calendar-status">Chargement…</p></div>
      </section>
      <section class="page-card admin-resource-panel">
        <p class="eyebrow">Modération</p><h2>Signalements ouverts</h2>
        <div id="admin-reports" class="admin-list"><p class="calendar-status">Chargement…</p></div>
      </section>
    </main>
  </div>
  <script>
    async function loadProposals() {
      const response = await fetch('/api/proposals');
      if (response.status === 401) { window.location.href = 'admin-login.php'; return; }
      const proposals = await response.json();
      const target = document.querySelector('#admin-proposals');
      target.innerHTML = proposals.length ? proposals.map(item => `<article class="admin-item"><div><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.course)} · ${escapeHtml(item.resource_type)} · <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">Ouvrir le lien ↗</a></span></div><div><button class="primary-button" data-action="approve" data-id="${item.id}">Valider</button><button class="secondary-button" data-action="reject" data-id="${item.id}">Refuser</button></div></article>`).join('') : '<p class="calendar-status">Aucune proposition.</p>';
    }
    async function loadResources() {
      const response = await fetch('/api/admin/resources');
      if (response.status === 401) { window.location.href = 'admin-login.php'; return; }
      const resources = await response.json();
      document.querySelector('#admin-resources').innerHTML = resources.length ? resources.map(item => `<article class="admin-item"><div><strong>${escapeHtml(item.title)}</strong><span>${escapeHtml(item.course)} · ${escapeHtml(item.type)} · ${escapeHtml(item.owner || 'inconnu')}</span></div><div><a class="secondary-button" href="${item.file_path ? `/api/resources/${encodeURIComponent(item.id)}/download` : escapeHtml(item.url || '#')}" target="_blank" rel="noopener">${item.file_path ? 'Télécharger' : 'Ouvrir'}</a><button class="secondary-button" data-resource-delete="${escapeHtml(item.id)}">Supprimer</button></div></article>`).join('') : '<p class="calendar-status">Aucune ressource publiée.</p>';
    }
    async function loadReports() {
      const response = await fetch('/api/admin/reports');
      if (response.status === 401) { window.location.href = 'admin-login.php'; return; }
      const reports = await response.json();
      document.querySelector('#admin-reports').innerHTML = reports.length ? reports.map(item => `<article class="admin-item"><div><strong>${escapeHtml(item.resource?.title || 'Ressource supprimée')}</strong><span>${escapeHtml(item.reporter)} · ${escapeHtml(item.reason)}</span></div><button class="secondary-button" data-report-close="${item.id}">Classer</button></article>`).join('') : '<p class="calendar-status">Aucun signalement ouvert.</p>';
    }
    function escapeHtml(value) { return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      await fetch(`/api/proposals/${button.dataset.id}/${button.dataset.action}`, { method: 'POST' });
      loadProposals();
    });
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-report-close]');
      if (!button) return;
      await fetch(`/api/admin/reports/${encodeURIComponent(button.dataset.reportClose)}`, {method:'POST'});
      loadReports();
    });
    document.addEventListener('click', async event => {
      const button = event.target.closest('[data-resource-delete]');
      if (!button || !confirm('Supprimer définitivement cette ressource ?')) return;
      await fetch(`/api/admin/resources/${encodeURIComponent(button.dataset.resourceDelete)}`, {method:'DELETE'});
      loadResources();
    });
    document.querySelector('#admin-logout').addEventListener('click', async () => { await fetch('/api/admin/logout', {method:'POST'}); window.location.href = 'admin-login.php'; });
    loadProposals(); loadResources(); loadReports();
  </script>
</body>
</html>
