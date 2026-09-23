<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CampusFlow — Consultation</title><link rel="stylesheet" href="modern.css"><link rel="stylesheet" href="courses.css"></head>
<body class="guest-page">
  <main class="guest-container">
    <header class="guest-header"><a class="brand" href="guest.php"><span class="brand-mark">C</span><span>Campus<span>Flow</span></span></a><a class="secondary-button" href="login.php">Se connecter</a></header>
    <section class="page-card"><p class="eyebrow">Lecture seule</p><h1 class="page-title">Ressources de la promotion</h1><p class="page-subtitle">Consulte les ressources partagées sans accéder aux tâches personnelles.</p><div id="guest-resources" class="guest-resource-grid"><p class="calendar-status">Chargement…</p></div></section>
  </main>
  <script>
  const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  fetch('/api/public/state').then(response => response.json()).then(state => {
    const items = state.resources || [];
    document.querySelector('#guest-resources').innerHTML = items.length ? items.map(item => `<article class="resource-card"><div class="resource-card-header"><span class="resource-icon">${esc(item.type || 'Lien')}</span><div class="resource-heading"><h3>${esc(item.title)}</h3><span>${esc(item.course)}</span></div></div><a class="resource-link" href="${esc(item.url || '#')}" target="_blank" rel="noopener">Ouvrir la ressource <span>↗</span></a></article>`).join('') : '<p class="calendar-status">Aucune ressource publique pour le moment.</p>';
  }).catch(() => { document.querySelector('#guest-resources').textContent = 'Impossible de charger les ressources.'; });
  </script>
</body>
</html>
