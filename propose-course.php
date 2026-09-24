<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Proposer un cours</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="modern.css">
</head>
<body data-page="proposal">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <section class="page-card">
                <h1 class="page-title">Proposer un cours</h1>
                <p class="page-subtitle">Soumets une ressource à faire valider par l'administration.</p>
                <form id="proposal-form">
                    <label>Matière<input id="proposal-course" required placeholder="Ex. Microéconomie II"></label>
                    <label>Nom du cours<input id="proposal-title" required placeholder="Ex. Fiche de révision"></label>
                    <label>Type
                        <select id="proposal-type">
                            <option value="notion">Notion</option>
                            <option value="google-docs">Google Docs</option>
                            <option value="pdf">PDF</option>
                            <option value="gemini">Gemini Notebook</option>
                            <option value="other">Autre</option>
                        </select>
                    </label>
                    <label>Lien<input id="proposal-url" type="url" required placeholder="https://..."></label>
                    <button class="primary-button" type="submit">Envoyer la proposition</button>
                    <p id="proposal-status" class="calendar-status" role="alert"></p>
                </form>
            </section>
        </main>
    </div>
    <script>
    document.querySelector('#proposal-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.target;
        const status = document.querySelector('#proposal-status');
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        status.className = 'calendar-status';
        status.textContent = 'Envoi…';
        try {
            const body = {
                course: document.querySelector('#proposal-course').value.trim(),
                title: document.querySelector('#proposal-title').value.trim(),
                type: document.querySelector('#proposal-type').value,
                url: document.querySelector('#proposal-url').value.trim()
            };
            const response = await fetch('/api/proposals', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            });
            if (response.ok) {
                status.className = 'calendar-status success';
                status.textContent = '✓ Proposition envoyée à l\'administration.';
                form.reset();
            } else {
                status.className = 'calendar-status error';
                status.textContent = 'Impossible d\'envoyer la proposition.';
            }
        } catch (e) {
            status.className = 'calendar-status error';
            status.textContent = 'Erreur réseau. Vérifiez votre connexion.';
        } finally {
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>
