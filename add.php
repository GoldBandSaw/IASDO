<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CampusFlow — Ajouter une tâche</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="modern.css">
</head>
<body data-page="add">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <section class="page-card">
                <h1 class="page-title">Ajouter un travail</h1>
                <p class="page-subtitle">Note-le maintenant, libère ton esprit.</p>
                <form id="task-form">
                    <label>Titre du travail
                        <input id="task-title" required placeholder="Ex. Rapport de sociologie">
                    </label>
                    <label>Matière
                        <input id="task-course" list="course-options" required placeholder="Tape pour chercher une matière">
                        <datalist id="course-options"></datalist>
                    </label>
                    <label>Date limite
                        <input id="task-date" type="date" required>
                    </label>
                    <div class="form-row">
                        <label>Heure (facultative)
                            <input id="task-time" type="time">
                        </label>
                        <label class="switch-label">
                            <input id="task-all-day" type="checkbox" checked>
                            <span class="switch"></span>
                            <span>Journée entière</span>
                        </label>
                    </div>
                    <label class="share-task-label switch-label">
                        <input id="task-shared" type="checkbox">
                        <span class="switch"></span>
                        <span>Partager avec la promotion</span>
                    </label>
                    <label>Priorité
                        <select id="task-priority">
                            <option value="high">Haute</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="low">Basse</option>
                        </select>
                    </label>
                    <button class="primary-button" type="submit">Ajouter à mon planning</button>
                </form>
            </section>
        </main>
    </div>
    <script src="app.js?v=6"></script>
</body>
</html>
