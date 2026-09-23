<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CampusFlow — Administration</title><link rel="stylesheet" href="modern.css"><link rel="stylesheet" href="auth.css"></head>
<body class="auth-page">
  <main class="auth-card">
    <div class="auth-brand"><span class="brand-mark">C</span><strong>Campus<span>Flow</span></strong></div>
    <p class="eyebrow">Accès séparé</p>
    <h1>Administration</h1>
    <p class="auth-intro">Cette connexion est indépendante de l’espace étudiant.</p>
    <form id="admin-login-form">
      <label>Identifiant administrateur<input id="admin-username" autocomplete="username" required></label>
      <label>Mot de passe<input id="admin-password" type="password" autocomplete="current-password" required></label>
      <button class="primary-button" type="submit">Ouvrir le panneau</button>
      <p id="admin-login-status" class="auth-status" role="alert"></p>
    </form>
    <p class="auth-note"><a href="login.php">Retour à l’espace étudiant</a></p>
  </main>
  <script>
  document.querySelector('#admin-login-form').addEventListener('submit', async event => {
    event.preventDefault();
    const status = document.querySelector('#admin-login-status');
    status.textContent = 'Connexion…';
    const response = await fetch('/api/admin/login', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({username:document.querySelector('#admin-username').value,password:document.querySelector('#admin-password').value})});
    const result = await response.json();
    if (!response.ok) { status.textContent = result.error || 'Connexion impossible.'; return; }
    window.location.href = 'admin.php';
  });
  </script>
</body>
</html>
