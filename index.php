<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CampusFlow — Ton semestre, enfin clair</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="modern.css">
</head>
<body>
  <div class="app-shell">
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>

    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="eyebrow" id="today-label"></p>
          <h1>Bonjour, <span id="student-name">étudiant·e</span> <span>👋</span></h1>
          <p class="subtitle">Voici ce qui mérite ton attention aujourd'hui.</p>
        </div>
        <button class="primary-button" id="open-add-top"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4.5v11M4.5 10h11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg> Ajouter une tâche</button>
      </header>

      <div class="view" data-view-content="dashboard">
      <section class="stats-grid" aria-label="Résumé">
        <article class="stat-card accent-blue">
          <div class="stat-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="6.6" stroke="currentColor" stroke-width="1.6"/><path d="M10 6.5V10l2.6 1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div><p>À faire cette semaine</p><strong id="week-count">0</strong></div>
          <span class="stat-trend" id="week-trend"></span>
        </article>
        <article class="stat-card accent-orange">
          <div class="stat-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4.2l6.5 11.2a1 1 0 01-.9 1.5H4.4a1 1 0 01-.9-1.5L10 4.2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 8.6v3M10 14h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></div>
          <div><p>Échéances proches</p><strong id="urgent-count">0</strong></div>
          <span class="stat-note">sous 3 jours</span>
        </article>
        <article class="stat-card accent-green">
          <div class="stat-icon"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="6.6" stroke="currentColor" stroke-width="1.6"/><path d="M7 10.2l2 2 4-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div><p>Progression globale</p><strong id="progress-value">0%</strong></div>
          <div class="progress-ring"><span id="progress-ring-value"></span></div>
        </article>
      </section>
      </div>

      <section class="content-grid view" id="dashboard-content" data-view-content="dashboard">
        <div class="panel schedule-panel">
          <div class="panel-heading">
            <div><h2>Prochaines échéances</h2><p>Organise ton effort, pas seulement ton temps.</p></div>
            <a href="#tasks" class="text-link">Voir tout</a>
          </div>
          <div id="upcoming-list" class="task-list"></div>
        </div>
        <div class="panel focus-panel">
          <div class="panel-heading"><div><h2>Ta semaine</h2><p>Un aperçu rapide de ta charge.</p></div></div>
          <div id="week-chart" class="week-chart" aria-label="Charge de travail par jour"></div>
          <div class="chart-legend"><span class="legend-dot"></span> tâches à terminer</div>
        </div>
      </section>

      <section class="dashboard-lower view" data-view-content="dashboard">
        <div class="panel dashboard-resources">
          <div class="panel-heading">
            <div><h2>Ressources récentes</h2><p>Les derniers documents partagés par la promotion.</p></div>
            <a href="courses.php" class="text-link">Voir la bibliothèque</a>
          </div>
          <div id="recent-resources" class="recent-resource-list"></div>
        </div>
        <div class="panel dashboard-next">
          <div class="panel-heading"><div><h2>Prochaine étape</h2><p id="next-step-context">À partir de tes tâches ouvertes.</p></div></div>
          <div id="next-step-card" class="next-step-card"></div>
        </div>
      </section>

      <section class="workspace-strip view" data-view-content="dashboard" aria-label="Espace personnel">
        <div class="workspace-intro">
          <span class="workspace-icon">✦</span>
          <div>
            <p class="eyebrow">Ton espace, au même endroit</p>
            <h2>Avance sereinement, un pas après l'autre.</h2>
            <p>Retrouve tes cours, tes échéances et ton rythme de travail sans multiplier les outils.</p>
          </div>
        </div>
        <a class="workspace-link" href="timetable.php">Voir mon planning <span>↗</span></a>
      </section>

      <section class="privacy-card view" data-view-content="dashboard">
        <div class="privacy-badge"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 3.5 16 6v4.2c0 3.2-2.3 5.7-6 6.8-3.7-1.1-6-3.6-6-6.8V6l6-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m7.2 10 1.8 1.8 3.8-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div><strong>Ton espace reste privé</strong><p>Tes tâches personnelles et tes préférences ne sont visibles que par toi. Les ressources partagées restent accessibles aux étudiants connectés.</p></div>
        <a href="settings.php">Gérer mes données <span>→</span></a>
      </section>

      <section class="panel all-tasks view" id="tasks" data-view-content="tasks">
        <div class="panel-heading">
          <div><h2>Toutes tes tâches</h2><p id="task-subtitle">0 tâche enregistrée</p></div>
          <div class="filters" role="group" aria-label="Filtrer les tâches">
            <button class="filter" data-filter="all">Toutes</button>
            <button class="filter active" data-filter="open">À faire</button>
            <button class="filter" data-filter="done">Terminées</button>
          </div>
        </div>
        <div id="all-tasks-list" class="task-list"></div>
      </section>

      <section class="panel add-panel view" id="add" data-view-content="add">
        <div class="panel-heading"><div><h2>Ajouter un travail</h2><p>Note-le maintenant, libère ton esprit.</p></div></div>
        <form id="task-form">
          <label>Titre du travail<input id="task-title" required placeholder="Ex. Rapport de sociologie"></label>
          <label>Matière<input id="task-course" list="course-options" required placeholder="Tape pour chercher une matière"><datalist id="course-options"></datalist></label>
          <label>Date limite<input id="task-date" type="date" required></label>
          <label class="share-task-label"><input id="task-shared" type="checkbox"> Partager avec la promotion</label>
          <label>Priorité<select id="task-priority"><option value="high">Haute</option><option value="medium" selected>Moyenne</option><option value="low">Basse</option></select></label>
          <button class="primary-button" type="submit">Ajouter à mon planning</button>
        </form>
      </section>

      <section class="panel calendar-panel" id="calendar">
        <div class="panel-heading">
          <div><h2>Emploi du temps commun</h2><p>Le planning de la promotion est synchronisé automatiquement depuis l'Université d'Orléans.</p></div>
        </div>
        <form id="calendar-form" class="calendar-form">
          <button class="primary-button" type="submit">Synchroniser le planning</button>
        </form>
        <p id="calendar-status" class="calendar-status" role="status"></p>
        <div id="course-chips" class="course-chips"></div>
      </section>

      <section class="panel timetable-panel view" id="timetable" data-view-content="timetable">
        <div class="panel-heading"><div><p class="eyebrow">Planning étudiant</p><h2>Mon emploi du temps</h2><p id="week-label">Semaine actuelle</p></div><div class="timetable-actions"><button class="secondary-button" id="previous-week" aria-label="Semaine précédente"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4.5L6.5 10l5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button><button class="week-current" id="current-week">Cette semaine</button><button class="secondary-button" id="next-week" aria-label="Semaine suivante"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 4.5L13.5 10 8 15.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button><button class="secondary-button" id="refresh-timetable" aria-label="Actualiser"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 8.5A5.8 5.8 0 004.7 7.3M4.5 11.5a5.8 5.8 0 0010.8 1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M15.2 5v3.5h-3.5M4.8 15v-3.5h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div></div>
        <p id="timetable-status" class="calendar-status"></p>
        <div id="timetable-grid" class="timetable-grid"></div>
      </section>

      <section class="panel settings-panel view" id="settings" data-view-content="settings">
        <div class="panel-heading"><div><h2>Paramètres</h2><p>Personnalise ton espace et gère la synchronisation.</p></div></div>
        <form id="settings-form" class="settings-form">
          <label>Prénom<input id="first-name" placeholder="Ex. Alex"></label>
          <label>Nom<input id="last-name" placeholder="Ex. Martin"></label>
          <label class="toggle-label"><input id="dark-mode" type="checkbox"><span>Mode sombre</span></label>
          <label class="calendar-url-label">Lien iCalendar enregistré<input id="saved-calendar-url" type="url" placeholder="https://.../emploi-du-temps.ics"></label>
          <button class="primary-button" type="submit">Enregistrer les paramètres</button>
        </form>
      </section>
    </main>
  </div>
  <div id="toast" class="toast" role="status"></div>
  <script src="app.js"></script>
</body>
</html>
