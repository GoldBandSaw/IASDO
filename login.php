<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CampusFlow — Connexion</title><link rel="stylesheet" href="modern.css"><link rel="stylesheet" href="auth.css"></head>
<body class="auth-page">
  <main class="auth-card">
    <div class="auth-brand"><span class="brand-mark">C</span><strong>Campus<span>Flow</span></strong></div>
    <p class="eyebrow">Espace privé de la promo</p>
    <h1>Bienvenue dans votre espace commun.</h1>
    <p class="auth-intro">Connecte-toi avec ton identifiant personnel pour retrouver les cours, ressources et échéances de la classe.</p>
    <form id="login-form">
      <label>Identifiant<input id="username" autocomplete="username" required placeholder="ex. antonin"></label>
      <label>Mot de passe<input id="password" type="password" autocomplete="current-password" required></label>
      <button class="primary-button" type="submit">Se connecter</button>
      <a class="secondary-button" href="guest.php">Continuer en invité</a>
      <p id="login-status" class="auth-status" role="alert"></p>
    </form>
    <p class="auth-note">Identifiants disponibles : antonin, lucas, aymen, youssef, maelle, jason, nolann, leon, roman et cedric. Le mot de passe initial est communiqué séparément.</p>
  </main>
  <script src="auth.js"></script>
</body>
</html>
