<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CampusFlow — Première connexion</title><link rel="stylesheet" href="modern.css"><link rel="stylesheet" href="auth.css"></head>
<body class="auth-page">
  <main class="auth-card">
    <div class="auth-brand"><span class="brand-mark">C</span><strong>Campus<span>Flow</span></strong></div>
    <p class="eyebrow">Première connexion</p>
    <h1>Choisis ton mot de passe.</h1>
    <p class="auth-intro">Ce lien est personnel et ne peut être utilisé qu'une seule fois. Ton identifiant restera inchangé.</p>
    <form id="setup-form">
      <label>Identifiant<input id="setup-username" autocomplete="username" required readonly></label>
      <label>Nouveau mot de passe<input id="setup-password" type="password" minlength="10" autocomplete="new-password" required></label>
      <label>Confirmer le mot de passe<input id="setup-confirm" type="password" minlength="10" autocomplete="new-password" required></label>
      <button class="primary-button" type="submit">Activer mon compte</button>
      <p id="setup-status" class="auth-status" role="alert"></p>
    </form>
  </main>
  <script src="setup.js"></script>
</body>
</html>
