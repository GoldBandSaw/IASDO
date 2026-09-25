<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Mes tâches</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="modern.css">
</head>
<body data-page="tasks">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <section class="page-card">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
                    <div>
                        <h1 class="page-title" style="margin:0">Mes tâches</h1>
                        <p id="task-subtitle" class="page-subtitle" style="margin:4px 0 0">Toutes tes tâches</p>
                    </div>
                    <a href="add.php" class="primary-button" style="text-decoration:none;font-size:13px;padding:8px 18px">+ Nouveau devoir</a>
                </div>
                <div class="filters">
                    <button class="filter" data-filter="all">Toutes</button>
                    <button class="filter active" data-filter="open">À faire</button>
                    <button class="filter" data-filter="done">Terminées</button>
                </div>
                <div id="all-tasks-list" class="task-list"></div>
            </section>
        </main>
    </div>
    <script src="app.js?v=5"></script>
</body>
</html>
