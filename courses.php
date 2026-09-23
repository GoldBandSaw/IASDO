<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CampusFlow — Mes cours</title>
  <link rel="stylesheet" href="page-shell.css">
  <link rel="stylesheet" href="shared-pages.css">
  <link rel="stylesheet" href="modern.css">
  <link rel="stylesheet" href="courses.css">
</head>
<body data-page="courses">
  <div class="app-shell">
    <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
    <main class="page-content">
      <section class="page-card">
        <p class="eyebrow">Bibliothèque personnelle</p>
        <h1 class="page-title">Bibliothèque de ressources</h1>
        <p class="page-subtitle">Retrouve tes ressources par matière, sans quitter ton espace étudiant.</p>
        <div class="course-tools">
          <div class="privacy-note"><strong>Bibliothèque commune</strong><span>Les matières sont gérées à partir du planning et par l'administration. Choisis simplement une matière existante lorsque tu ajoutes une ressource.</span></div>
        </div>
        <p><a class="resource-link" href="propose-course.php">Proposer une ressource à faire valider par l'administration ↗</a></p>
      </section>
      <section class="page-card add-resource-card">
        <div class="panel-heading"><div><h2>Partager une ressource</h2><p>Ajoute un lien ou un fichier de 50 Mo maximum.</p></div></div>
        <form id="resource-form" class="resource-form">
          <label>Matière<select id="resource-course" required></select></label>
          <label>Source<select id="resource-source"><option value="link">Lien externe</option><option value="file">Fichier</option></select></label>
          <label>Type<select id="resource-type"><option value="notion">Notion</option><option value="pdf">PDF</option><option value="markdown">Markdown</option><option value="other">Autre</option></select></label>
          <label>Titre<input id="resource-title" placeholder="Ex. Chapitre 3 — Probabilités"></label>
          <label class="resource-url-field" id="resource-link-field">Lien<input id="resource-url" type="url" placeholder="https://..."></label>
          <label class="resource-file-field" id="resource-file-field">Fichier<input id="resource-file" type="file" accept=".pdf,.md,.markdown,.txt,.docx,.pptx,.xlsx,.png,.jpg,.jpeg,.gif,.webp"></label>
          <button class="primary-button" type="submit">Partager la ressource</button>
        </form>
        <p class="embed-hint">Les liens s'ouvrent dans un nouvel onglet. Les fichiers sont téléchargeables par les étudiants connectés, sans embed Notion.</p>
      </section>
      <div id="courses-list"></div>
    </main>
  </div>
  <div id="toast" class="toast"></div>
  <script src="app.js"></script>
</body>
</html>
