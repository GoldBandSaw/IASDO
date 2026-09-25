<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Emploi du temps</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="modern.css?v=7">
    <link rel="stylesheet" href="courses.css">
</head>
<body data-page="timetable">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <section class="page-card">
                <p class="eyebrow">Planning étudiant</p>
                <h1 class="page-title">Mon emploi du temps</h1>
                <p id="week-label" class="page-subtitle">Semaine actuelle</p>
                <div class="timetable-actions">
                    <button class="secondary-button" id="previous-week" aria-label="Semaine précédente">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4.5L6.5 10l5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <button class="week-current" id="current-week">Cette semaine</button>
                    <button class="secondary-button" id="next-week" aria-label="Semaine suivante">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 4.5L13.5 10 8 15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <button class="secondary-button" id="refresh-timetable">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.5 8.5A5.8 5.8 0 004.7 7.3M4.5 11.5a5.8 5.8 0 0010.8 1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            <path d="M15.2 5v3.5h-3.5M4.8 15v-3.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Actualiser
                    </button>
                </div>
                <p id="timetable-status" class="calendar-status">Synchronisation du planning commun en cours…</p>
                <div id="timetable-grid" class="timetable-page-grid"></div>
            </section>
        </main>
    </div>
    <dialog id="task-editor"></dialog>
    <dialog id="course-resources-modal"></dialog>
    <script src="app.js?v=6"></script>
</body>
</html>
