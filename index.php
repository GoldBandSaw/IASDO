<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Tableau de bord</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/modern.css">
    <link rel="stylesheet" href="/page-shell.css">
    <link rel="stylesheet" href="/shared-pages.css">
</head>
<body data-page="dashboard">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content">
            <noscript>
                <div style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;margin:20px">
                    <strong>JavaScript requis</strong> — Cette application nécessite JavaScript pour fonctionner.
                </div>
            </noscript>

            <div class="dashboard-welcome" style="margin-bottom:24px">
                <h1 class="page-title" style="margin:0">Bonjour, <?= htmlspecialchars($currentUser['display_name'] ?? $currentUser['username'], ENT_QUOTES, 'UTF-8') ?> 👋</h1>
                <p id="today-label" class="page-subtitle" style="margin:6px 0 0">Chargement…</p>
            </div>

            <div class="stat-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:28px">
                <div class="page-card" style="padding:18px;text-align:center">
                    <div id="week-count" style="font-size:2rem;font-weight:700;font-family:'Fraunces',serif;color:var(--accent,#A6620C)">—</div>
                    <div style="font-size:0.82rem;color:var(--text-muted,#8a7e6b);margin-top:4px">Tâches cette semaine</div>
                    <div id="week-trend" style="font-size:0.75rem;color:var(--text-muted,#8a7e6b)"></div>
                </div>
                <div class="page-card" style="padding:18px;text-align:center">
                    <div id="urgent-count" style="font-size:2rem;font-weight:700;font-family:'Fraunces',serif;color:#C44536">—</div>
                    <div style="font-size:0.82rem;color:var(--text-muted,#8a7e6b);margin-top:4px">Urgentes (≤ 3j)</div>
                </div>
                <div class="page-card" style="padding:18px;text-align:center">
                    <div id="progress-value" style="font-size:2rem;font-weight:700;font-family:'Fraunces',serif;color:#2D6A4F">—</div>
                    <div style="font-size:0.82rem;color:var(--text-muted,#8a7e6b);margin-top:4px">Progression</div>
                    <div class="progress-ring"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:28px">
                <section class="page-card" style="padding:20px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <h2 style="margin:0;font-size:1.05rem;font-family:'Fraunces',serif">📅 Prochaines échéances</h2>
                        <a href="/tasks.php" style="font-size:0.8rem;color:var(--accent,#A6620C);text-decoration:none">Voir tout →</a>
                    </div>
                    <div id="upcoming-list">
                        <p style="color:var(--text-muted,#8a7e6b);font-size:0.88rem">Chargement…</p>
                    </div>
                </section>

                <section class="page-card" style="padding:20px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <h2 style="margin:0;font-size:1.05rem;font-family:'Fraunces',serif">📚 Ressources récentes</h2>
                        <a href="/courses.php" style="font-size:0.8rem;color:var(--accent,#A6620C);text-decoration:none">Voir tout →</a>
                    </div>
                    <div id="recent-resources">
                        <p style="color:var(--text-muted,#8a7e6b);font-size:0.88rem">Chargement…</p>
                    </div>
                </section>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <a href="/add.php" class="primary-button" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:0.88rem">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajouter un devoir
                </a>
                <a href="/chat.php" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:0.88rem;border:1px solid var(--border,#e0d6c8);border-radius:10px;color:var(--text-main,#2c2417);background:var(--card-bg,#fff)">
                    💬 Chat promo
                </a>
                <a href="/timetable.php" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:10px 20px;font-size:0.88rem;border:1px solid var(--border,#e0d6c8);border-radius:10px;color:var(--text-main,#2c2417);background:var(--card-bg,#fff)">
                    📆 Emploi du temps
                </a>
            </div>

            <!-- Hidden elements that app.js might reference -->
            <div id="all-tasks-list" style="display:none"></div>
            <div id="task-subtitle" style="display:none"></div>
            <dialog id="task-editor"></dialog>
        </main>
    </div>
    <script src="/app.js?v=6"></script>
</body>
</html>
