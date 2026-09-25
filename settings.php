<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Paramètres</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="modern.css">
</head>
<body data-page="settings">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <section class="page-card">
                <h1 class="page-title">Paramètres</h1>
                <p class="page-subtitle">Personnalise ton espace et gère la synchronisation.</p>
                <form id="settings-form">
                    <label>Prénom<input id="first-name" placeholder="Ex. Alex"></label>
                    <label>Nom<input id="last-name" placeholder="Ex. Martin"></label>
                    <label class="switch-label">
                        <input id="dark-mode" type="checkbox">
                        <span class="switch"></span>
                        <span>Mode sombre</span>
                    </label>
                    <label>Lien iCalendar permanent<input id="saved-calendar-url" type="url" placeholder="https://.../emploi-du-temps.ics"></label>
                    <button class="primary-button" type="submit">Enregistrer les paramètres</button>
                </form>
                <p id="settings-status" class="calendar-status"></p>
                <div class="account-settings">
                    <h2>Mon compte</h2>
                    <p>Ton identifiant ne peut pas être modifié. Seuls ton nom affiché et ton mot de passe sont modifiables.</p>
                    <form id="account-form">
                        <label>Photo de profil (URL)<input id="account-pic" type="url" placeholder="https://..."></label>
                        <label>Nom affiché<input id="account-name" maxlength="80" required></label>
                        <label>Mot de passe actuel<input id="current-password" type="password" placeholder="Requis pour changer le mot de passe"></label>
                        <label>Nouveau mot de passe<input id="account-password" type="password" minlength="10" placeholder="Laisser vide pour ne pas changer"></label>
                        <label>Confirmer le nouveau mot de passe<input id="confirm-password" type="password" minlength="10" placeholder="Confirmer le mot de passe"></label>
                        <button class="primary-button" type="submit">Mettre à jour mon compte</button>
                    </form>
                    <p id="account-status" class="calendar-status"></p>
                </div>
            </section>
        </main>
    </div>
    <script src="app.js?v=3"></script>
</body>
</html>
