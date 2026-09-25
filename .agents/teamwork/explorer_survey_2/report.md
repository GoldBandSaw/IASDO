# Rapport d'Investigation : Module Emploi du Temps et Interactions (R3 & R4)

**Auteur** : Teamwork Explorer 2 (`teamwork_preview_explorer`)  
**Date** : 2026-09-25  
**Cible** : CampusFlow — Requirements R3 & R4  
**Statut** : Investigation achevée — Recommandations prêtes pour implémentation  

---

## Résumé Exécutif

L'investigation technique sur le module Emploi du temps (`timetable.php`, `app.js`, `shared-pages.css`, `modern.css`) a permis d'isoler avec une précision absolue les causes racines de l'ensemble des anomalies visuelles (R3) et de définir l'architecture exacte des interactions interactives par modales (R4) :

1. **Étirement horizontal du dimanche / des événements (R3)** :  
   Dans `shared-pages.css`, `.tt-day-col` n'a **aucune déclaration `position: relative`**. Ses enfants `.tt-day-header` et `.tt-allday-strip` possèdent `position: absolute; left: 0; right: 0;`. Leur bloc conteneur effectif est donc `.tt-days-row` (la ligne entière de 7 jours). Le dimanche étant le dernier jour rendu dans la boucle DOM, l'en-tête du dimanche et tout événement "journée entière" du dimanche recouvrent l'intégralité des 7 colonnes de gauche à droite.
2. **Barre de défilement horizontal parasite (R3)** :  
   Dans `shared-pages.css` (ligne 17), `.tt-hour-line` possède `right: -100vw;` à l'intérieur d'un conteneur `.timetable-page-grid` ayant `overflow-x: auto;`. Cet élément dépasse de 100 largeurs d'écran vers la droite, générant un défilement horizontal permanent et massif.
3. **Graduations "06h" et "07h" rognées et décalées (R3)** :  
   `.tt-time-ruler` a `padding-top: 44px; position: relative;`. En CSS, les pourcentages `top` des éléments absolus (`position: absolute; top: 0%`) se calculent par rapport à la bordure de padding (`y = 0px`) et **non** à `y = 44px`. Le label "06h" se place donc à `y = 0px` avec `transform: translateY(-50%)`, ce qui le coupe en deux au sommet du composant. De plus, toutes les graduations horaires sont décalées de 44px par rapport aux événements de `.tt-events-area` qui démarrent eux à `y = 44px`.
4. **Clic sur les tâches de l'emploi du temps (R4)** :  
   La fonction `openTaskEditor(task)` existe déjà dans `app.js` (lignes 498-537) et le dispatcher global de clic écoute déjà `[data-action="edit-task"]`. Cependant, lors de la génération de la grille (`app.js`, lignes 251-258), la propriété `id: task.id` est omise dans `dayTasks`, et les balises `<article>` générées n'ont aucun attribut `data-action` ni `data-id`.
5. **Clic sur les cartes de cours -> Modale de ressources (R4)** :  
   Les cartes de cours n'ont aucun écouteur. Il convient de leur associer `data-action="view-course-resources" data-course="..."`, de créer dynamiquement un composant `<dialog id="course-resources-modal">`, de réutiliser la requête `GET /api/resources?limit=50` (identique à la page Ressources `courses.php`), et d'inclure `courses.css` dans `timetable.php` pour que les cartes de ressources s'affichent avec leur style complet.

---

## 1. Cartographie des Fichiers & Composants

| Fichier | Rôle | Éléments Clés |
|---|---|---|
| `c:\Users\anton\Projets\IASDO\timetable.php` | Page PHP de l'emploi du temps | `<div id="timetable-grid" class="timetable-page-grid">` (L41), feuilles de style CSS incluses |
| `c:\Users\anton\Projets\IASDO\shared-pages.css` | Styles de la grille proportionnelle | `.timetable-page-grid` (L15), `.tt-time-ruler` (L16), `.tt-hour-line` (L17-19), `.tt-days-row` (L20), `.tt-day-col` (L21), `.tt-day-header` (L24), `.tt-allday-strip` (L27), `.tt-events-area` (L28-30), `.timetable-event--abs` (L32-36) |
| `c:\Users\anton\Projets\IASDO\modern.css` | Styles globaux & boîte de dialogue | `.timetable-page-grid, .timetable-grid { gap: 12px }` (L220), `dialog` & `.task-editor-form` (L351-355), responsive (L386-389) |
| `c:\Users\anton\Projets\IASDO\courses.css` | Styles des fiches de ressources | `.resources-grid` (L15), `.resource-card` (L16), `.resource-icon` (L18), `.resource-link` (L22), `.resource-actions` (L23) |
| `c:\Users\anton\Projets\IASDO\app.js` | Logique client JS | `renderTimetable()` (L215-226), `renderWeekGrid()` (L233-295), `resourceMarkup()` (L327-339), `openTaskEditor()` (L498-537), gestionnaire de clics (L430-481), `loadAllResources()` (L112-122) |
| `c:\Users\anton\Projets\IASDO\api.php` | API REST Backend | `GET /api/timetable` (L124), `GET /api/resources` (L158-173) |

---

## 2. Investigation Approfondie R3 : Correctifs d'Affichage & CSS Grid

### 2.1. Pourquoi le dimanche (ou bloc d'événement) s'étale horizontalement et casse la grille

#### Observation
Dans `renderWeekGrid` (`app.js`, lignes 247-292) :
```javascript
const days = Array.from({ length: 7 }, (_, index) => {
  ...
  return `<div class="tt-day-col${isToday ? " tt-today" : ""}">
    <div class="tt-day-header">
      <span>${date.toLocaleDateString("fr-FR", { weekday: "short" })}</span>
      <strong>${date.getDate()}</strong>
    </div>
    ${allDayHtml ? `<div class="tt-allday-strip">${allDayHtml}</div>` : ""}
    <div class="tt-events-area">${timedHtml || '<p class="no-course">Aucun cours</p>'}</div>
  </div>`;
});
```

Dans `shared-pages.css` (lignes 20-28) :
```css
.tt-days-row{display:flex;flex:1;position:relative;min-height:700px;padding-top:44px}
.tt-day-col{flex:1;min-width:80px;display:flex;flex-direction:column;border-right:1px solid #e4eaf3}
.tt-day-header{display:flex;justify-content:space-between;align-items:center;padding:8px 8px 6px;background:#fff;border-bottom:2px solid #e4eaf3;position:absolute;top:0;left:0;right:0;height:44px;box-sizing:border-box;z-index:3}
.tt-allday-strip{padding:3px 5px;border-bottom:1px solid #e4eaf3;background:#fafbff;display:flex;flex-direction:column;gap:3px;position:absolute;top:44px;left:0;right:0;z-index:2}
.tt-events-area{flex:1;position:relative;min-height:700px}
```

#### Chaîne logique
1. `.tt-days-row` possède `position: relative`.
2. `.tt-day-col` possède `position: static` (valeur par défaut, aucune déclaration de position).
3. `.tt-day-header` possède `position: absolute; top: 0; left: 0; right: 0;`.
4. `.tt-allday-strip` possède `position: absolute; top: 44px; left: 0; right: 0;`.
5. En spécification CSS (CSS Positioned Layout), un élément absolument positionné recherche son ancêtre positionné le plus proche (`position != static`).
6. Comme `.tt-day-col` n'est pas positionné, le bloc conteneur de `.tt-day-header` et de `.tt-allday-strip` est `.tt-days-row` (qui englobe les 7 jours de la semaine).
7. Par conséquent, `left: 0; right: 0;` force chaque en-tête de jour et chaque bandeau d'événement "toute la journée" à s'étendre sur **toute la largeur de la semaine (100% de `.tt-days-row`)** au lieu de rester confiné dans sa colonne.
8. Dans la boucle `Array.from({ length: 7 })`, le dimanche (index 6) est le dernier élément rendu dans l'arbre DOM. Son en-tête et son bandeau `.tt-allday-strip` sont donc empilés au-dessus de tous les autres jours, s'étalant de l'extrême gauche (Lundi) à l'extrême droite (Dimanche).
9. Tout événement ou tâche "toute la journée" tombant un dimanche (notamment les tâches par défaut sans heure définie `task.time == ""` qui sont converties en `allDay: true`) s'étale horizontalement sur toute la grille et masque les cours des autres jours.

---

### 2.2. Pourquoi la grille déborde et génère une barre de défilement horizontal

#### Observation
Dans `shared-pages.css` (lignes 15-17) :
```css
.timetable-page-grid{display:flex;gap:0;overflow-x:auto;border:1px solid #e4eaf3;border-radius:12px;background:#fff}
.tt-time-ruler{flex:0 0 40px;position:relative;min-height:700px;padding-top:44px;border-right:1px solid #e4eaf3;background:#f8faff}
.tt-hour-line{position:absolute;left:0;right:-100vw;display:flex;align-items:flex-start;pointer-events:none;border-top:1px solid #e8edf5}
```
Dans `modern.css` (ligne 220) :
```css
.timetable-page-grid,.timetable-grid{gap:12px}
```

#### Chaîne logique
1. L'auteur précédent a tenté de faire "déborder" les lignes d'heures au travers des jours en écrivant `right: -100vw` sur `.tt-hour-line`.
2. Cependant, `.timetable-page-grid` est configuré avec `overflow-x: auto;`.
3. Un enfant absolument positionné avec `right: -100vw` dépasse de 100vw à droite de sa boîte d'origine. Le moteur de rendu du navigateur calcule donc une zone de scroll débordant de 100vw supplémentaires.
4. Cela crée une barre de scroll horizontal permanente sur la grille, visible même sur grand écran desktop (2560px), permettant de scroller vers un vide blanc.
5. De plus, `.tt-events-area::before` (ligne 30) implémente déjà un `repeating-linear-gradient` qui dessine exactement les lignes horizontales d'heures sous les événements. L'artifice `right: -100vw` est donc à la fois redondant et destructeur.
6. Enfin, `modern.css` applique `gap: 12px` sur `.timetable-page-grid`, introduisant un écart de 12px entre la règle des heures et les colonnes de jours, brisant l'alignement continu des lignes.

---

### 2.3. Graduations "06h" et "07h" rognées et décalées

#### Observation
Dans `shared-pages.css` (lignes 16-19) :
```css
.tt-time-ruler{flex:0 0 40px;position:relative;min-height:700px;padding-top:44px;border-right:1px solid #e4eaf3;background:#f8faff}
.tt-hour-line{position:absolute;left:0;right:-100vw;display:flex;align-items:flex-start;pointer-events:none;border-top:1px solid #e8edf5}
.tt-hour-line span{font-size:9px;color:var(--muted,#9ba7b8);line-height:1;padding:0 3px;white-space:nowrap;transform:translateY(-50%);background:#f8faff}
```
Dans `app.js` (lignes 234-245) :
```javascript
const GRID_START_H = 6;   // 06:00
const GRID_END_H   = 20;  // 20:00
const TOTAL_MINS   = (GRID_END_H - GRID_START_H) * 60; // 840 min
function pct(mins) { return (Math.max(0, Math.min(TOTAL_MINS, mins)) / TOTAL_MINS * 100).toFixed(3); }

for (let h = GRID_START_H; h <= GRID_END_H; h++) {
  const top = pct((h - GRID_START_H) * 60);
  rulerHours.push(`<div class="tt-hour-line" style="top:${top}%"><span>${String(h).padStart(2,"0")}h</span></div>`);
}
```

#### Chaîne logique
1. Pour `h = 6` (06h), `mins = 0`, donc `top = 0%`.
2. Le bloc conteneur est `.tt-time-ruler`, qui a `padding-top: 44px; position: relative;`.
3. Selon la norme CSS, le positionnement d'un enfant `position: absolute; top: 0%` se fait par rapport à la bordure de padding (le sommet du conteneur, à `y = 0px`).
4. L'élément `<span>06h</span>` subit `transform: translateY(-50%)`. Le texte est donc décalé de moitié vers le haut, atteignant environ `y = -5px`.
5. `.timetable-page-grid` possède `border-radius: 12px` et `overflow-x: auto;` (qui force un contexte de clipping). Tout pixel situé à `y < 0` est rogné. La moitié supérieure du texte "06h" est donc tronquée.
6. Pour `h = 7` (07h), `top = (60 / 840) * 100% = 7.143%`. Sur une hauteur totale de ~744px, `7.143% = 53px`. Le texte "07h" apparaît donc écrasé juste sous la bordure d'en-tête (44px).
7. **Pire encore : Décalage général de 44px**. Les cours dans `.tt-events-area` commencent sous l'en-tête (à `y = 44px`). Un cours à 06:00 est placé à `top: 0%` de `.tt-events-area` (soit `y = 44px`). Mais sur la règle, "06h" est affiché à `y = 0px` et "07h" à `y = 53px`. **Tous les marqueurs d'heures de la règle sont désynchronisés des cours de 44px vers le haut.**

---

## 3. Investigation Approfondie R4 : Modales au Clic

### 3.1. Clic sur une Tâche (`Edit Task`)

#### Observation du code existant
Dans `app.js` (lignes 498-537) :
La fonction `openTaskEditor(task)` est **déjà entièrement implémentée** :
- Elle crée la boîte de dialogue native `<dialog id="task-editor">` si elle n'existe pas.
- Elle pré-remplit les champs `#edit-task-title`, `#edit-task-course`, `#edit-task-date`, `#edit-task-priority` et `#edit-task-shared`.
- Elle gère la soumission du formulaire et appelle `PUT /api/tasks/${id}`.
- Elle actualise l'affichage avec `saveTasks()`, `render()` et `showToast("Tâche modifiée")`.

Dans `app.js` (lignes 455-461) :
Le gestionnaire de clic global traite déjà :
```javascript
const action = event.target.closest("[data-action]");
if (action) {
  const id = action.dataset.id;
  if (action.dataset.action === "edit-task") {
    openTaskEditor(tasks.find(task => String(task.id) === id));
    return;
  }
...
```

#### Ce qui manque dans la grille d'emploi du temps
1. Dans `app.js` (lignes 251-258), la projection des tâches `dayTasks` ne retient pas l'identifiant `id` :
   ```javascript
   const dayTasks = tasks.filter(task => !task.done && task.due === key).map(task => ({
     // MANQUE: id: task.id,
     start: task.allDay || !task.time ? `${key}T00:00:00` : `${key}T${task.time}:00`,
     end: task.allDay || !task.time ? null : `${key}T${task.time}:00`,
     subject: task.title,
     location: task.course,
     isTask: true,
     allDay: task.allDay || !task.time
   }));
   ```
2. Lors de la génération HTML (lignes 271-281) :
   ```javascript
   // Événements minutés
   `<article class="timetable-event timetable-event--abs ${event.isTask ? "task-event" : ""}" style="top:${topPct}%;height:${heightPct}%">`
   // Événements toute la journée
   `<article class="timetable-event timetable-event--allday ${event.isTask ? "task-event" : ""}">`
   ```
   Aucun de ces éléments n'a `data-action="edit-task"` ni `data-id="${event.id}"`, et le curseur reste `cursor: default`.

---

### 3.2. Clic sur une Carte de Cours (`Course Resources Modal`)

#### Spécification du besoin (R4)
- Clic sur une carte de cours (événement où `!event.isTask`) -> ouverture d'une modale listant les ressources associées au cours (`event.subject`).
- La modale doit réutiliser la requête de la page "Ressources".

#### Observation du mécanisme de la page Ressources
1. Dans `app.js` (lignes 112-122) :
   ```javascript
   async function loadAllResources() {
     try {
       const data = await apiRequest("/api/resources?limit=50", "GET");
       if (Array.isArray(data.resources)) {
         courseResources = data.resources;
         saveCourseResources();
         renderCoursesPage();
         renderDashboardResources();
       }
     } catch (e) { /* non-critical */ }
   }
   ```
2. La requête utilisée par la page Ressources est donc :
   `apiRequest("/api/resources?limit=50", "GET")`.
3. Dans `renderCoursesPage()` (ligne 351) :
   Le filtrage par cours s'effectue sur le tableau `courseResources` :
   ```javascript
   const resources = courseResources.filter(resource => resource.course === course);
   ```
4. Le markup individuel de chaque ressource est produit par `resourceMarkup(resource)` (lignes 327-339) :
   Il affiche l'icône du type de ressource, le titre, l'auteur, l'ancienneté, le bouton de téléchargement ou lien externe (`target="_blank"`), et le bouton de signalement.
5. **Point d'attention CSS** :
   Dans `timetable.php`, la feuille `courses.css` n'est actuellement **pas liée**. Si `resourceMarkup` est rendu dans une boîte de dialogue sur `timetable.php`, les classes `.resource-card`, `.resource-icon`, `.resource-link` seront privées de style. Il est impératif d'ajouter `<link rel="stylesheet" href="courses.css">` dans `timetable.php`.

---

## 4. Recommandations Précises & Extraits de Code

### 4.1. Refonte CSS du Layout de la Grille (`shared-pages.css`)

Remplacer le système flottant fragile par un CSS Grid robuste à 7 colonnes pour les jours et un flux vertical naturel pour les en-têtes et bandeaux :

```css
/* ── Proportional Timetable Grid ── */
.timetable-page-grid {
  display: flex;
  gap: 0 !important;
  overflow-x: hidden; /* Empêche tout défilement horizontal sur desktop */
  border: 1px solid #e4eaf3;
  border-radius: 12px;
  background: #fff;
}

/* Règle horaire */
.tt-time-ruler {
  flex: 0 0 44px;
  display: flex;
  flex-direction: column;
  position: relative;
  min-height: 744px; /* 44px d'en-tête + 700px de zone horaire */
  border-right: 1px solid #e4eaf3;
  background: #f8faff;
}

.tt-ruler-header {
  height: 44px;
  flex: 0 0 44px;
  box-sizing: border-box;
  border-bottom: 2px solid #e4eaf3;
  background: #fff;
}

.tt-ruler-track {
  flex: 1;
  position: relative;
  min-height: 700px;
}

.tt-hour-line {
  position: absolute;
  left: 0;
  right: 0; /* CORRECTION: remplace right: -100vw qui cassait la largeur */
  display: flex;
  align-items: flex-start;
  pointer-events: none;
  border-top: 1px solid #e8edf5;
}

.tt-hour-line:first-child {
  border-top: none;
}

.tt-hour-line span {
  font-size: 9px;
  color: var(--muted, #9ba7b8);
  line-height: 1;
  padding: 0 3px;
  white-space: nowrap;
  transform: translateY(-50%);
  background: #f8faff;
}

/* Ligne des jours : CSS Grid à 7 colonnes égales */
.tt-days-row {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  flex: 1;
  position: relative;
  min-height: 744px;
}

/* Colonne d'un jour : flex vertical contenant l'en-tête, les événements allday, et les cours minutés */
.tt-day-col {
  display: flex;
  flex-direction: column;
  position: relative; /* CORRECTION MAJEURE: bloque les éléments absolus dans leur colonne */
  min-width: 0;
  border-right: 1px solid #e4eaf3;
}

.tt-day-col:last-child {
  border-right: none;
}

.tt-day-header {
  height: 44px;
  flex: 0 0 44px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 8px 6px;
  background: #fff;
  border-bottom: 2px solid #e4eaf3;
  box-sizing: border-box;
  z-index: 3;
}

.tt-day-header span {
  text-transform: uppercase;
  color: var(--muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .5px;
}

.tt-day-header strong {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  background: #eef2ff;
  color: var(--blue, #3867f5);
  font-size: 12px;
}

.tt-day-col.tt-today .tt-day-header strong {
  background: var(--accent, #A6620C);
  color: #fff;
  border-radius: 50%;
}

/* Bandeau toute la journée : en flux normal sous l'en-tête de la colonne */
.tt-allday-strip {
  padding: 3px 5px;
  border-bottom: 1px solid #e4eaf3;
  background: #fafbff;
  display: flex;
  flex-direction: column;
  gap: 3px;
  z-index: 2;
}

/* Zone des cours minutés proportionnels */
.tt-events-area {
  flex: 1;
  position: relative;
  min-height: 700px;
}

.tt-events-area::before {
  content: "";
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(to bottom, transparent, transparent calc(100% / 14 - 1px), #e8edf5 calc(100% / 14));
  pointer-events: none;
  z-index: 0;
}

/* Événements minutés et cliquables */
.timetable-event--abs {
  position: absolute;
  left: 3px;
  right: 3px;
  overflow: hidden;
  background: #fff;
  border: 1px solid #dde3f0;
  border-left: 3px solid var(--accent, #A6620C);
  border-radius: 6px;
  padding: 4px 6px;
  box-shadow: 0 2px 6px #243b6308;
  box-sizing: border-box;
  z-index: 1;
  cursor: pointer; /* Curseur interactif pour cours et tâches */
  min-height: 22px;
  transition: transform .12s ease, box-shadow .12s ease;
}

.timetable-event--abs:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px #243b6315;
  z-index: 2;
}

.timetable-event--abs.task-event {
  border-left-color: var(--purple, #6366f1);
  background: #f8f8ff;
}

.timetable-event--allday {
  padding: 3px 6px;
  background: #eef2ff;
  border-radius: 4px;
  border-left: 3px solid var(--blue, #3867f5);
  cursor: pointer;
}

.timetable-event--allday.task-event {
  border-left-color: var(--purple, #6366f1);
  background: #f8f8ff;
}
```

---

### 4.2. Mise à jour du Générateur de Grille et Modales (`app.js`)

#### 1. Correction de `renderWeekGrid` (lignes 233-295)
- Encapsuler les `rulerHours` dans `<div class="tt-ruler-track">` avec un `<div class="tt-ruler-header"></div>` au-dessus.
- Conserver `id: task.id` dans `dayTasks`.
- Ajouter `data-action="edit-task" data-id="${event.id}"` sur les événements de tâches.
- Ajouter `data-action="view-course-resources" data-course="${escapeHtml(event.subject)}"` sur les événements de cours.

```javascript
function renderWeekGrid(weekStart, events) {
  const GRID_START_H = 6;   // 06:00
  const GRID_END_H   = 20;  // 20:00
  const TOTAL_MINS   = (GRID_END_H - GRID_START_H) * 60; // 840 min

  function pct(mins) { return (Math.max(0, Math.min(TOTAL_MINS, mins)) / TOTAL_MINS * 100).toFixed(3); }

  const rulerHours = [];
  for (let h = GRID_START_H; h <= GRID_END_H; h++) {
    const top = pct((h - GRID_START_H) * 60);
    rulerHours.push(`<div class="tt-hour-line" style="top:${top}%"><span>${String(h).padStart(2,"0")}h</span></div>`);
  }

  const days = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(weekStart); date.setDate(weekStart.getDate() + index);
    const key = localDateKey(date);
    const dayEvents = events.filter(event => event.start.slice(0, 10) === key);
    const dayTasks  = tasks.filter(task => !task.done && task.due === key).map(task => ({
      id: task.id, // CORRECTION : passage de l'id
      start: task.allDay || !task.time ? `${key}T00:00:00` : `${key}T${task.time}:00`,
      end:   task.allDay || !task.time ? null : `${key}T${task.time}:00`,
      subject: task.title,
      location: task.course,
      isTask: true,
      allDay: task.allDay || !task.time
    }));
    const allDay = [...dayEvents.filter(e => !e.end), ...dayTasks.filter(t => t.allDay)];
    const timed  = [...dayEvents.filter(e => e.end), ...dayTasks.filter(t => !t.allDay)];

    const timedHtml = timed.map(event => {
      const startDate = new Date(event.start);
      const endDate   = event.end ? new Date(event.end) : new Date(startDate.getTime() + 60 * 60 * 1000);
      const startMins = (startDate.getHours() - GRID_START_H) * 60 + startDate.getMinutes();
      const endMins   = (endDate.getHours()   - GRID_START_H) * 60 + endDate.getMinutes();
      const topPct    = pct(startMins);
      const heightPct = pct(Math.max(30 / TOTAL_MINS * 100, endMins - startMins));
      const label     = event.allDay ? "Toute la journée"
                      : `${startDate.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}–${endDate.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}`;
      const actionAttr = event.isTask
        ? `data-action="edit-task" data-id="${escapeHtml(event.id)}"`
        : `data-action="view-course-resources" data-course="${escapeHtml(event.subject)}"`;

      return `<article class="timetable-event timetable-event--abs ${event.isTask ? "task-event" : "course-event"}" style="top:${topPct}%;height:${heightPct}%" ${actionAttr} role="button" tabindex="0">
        <time>${label}</time>
        <strong>${escapeHtml(event.subject)}</strong>
        <span>${escapeHtml(event.isTask ? event.location : (event.location || ""))}</span>
      </article>`;
    }).join("");

    const allDayHtml = allDay.map(event => {
      const actionAttr = event.isTask
        ? `data-action="edit-task" data-id="${escapeHtml(event.id)}"`
        : `data-action="view-course-resources" data-course="${escapeHtml(event.subject)}"`;
      return `<article class="timetable-event timetable-event--allday ${event.isTask ? "task-event" : "course-event"}" ${actionAttr} role="button" tabindex="0">
        <time>Toute la journée</time><strong>${escapeHtml(event.subject)}</strong>
        <span>${escapeHtml(event.location || "")}</span>
      </article>`;
    }).join("");

    const isToday = key === localDateKey(getToday());
    return `<div class="tt-day-col${isToday ? " tt-today" : ""}">
      <div class="tt-day-header">
        <span>${date.toLocaleDateString("fr-FR", { weekday: "short" })}</span>
        <strong>${date.getDate()}</strong>
      </div>
      ${allDayHtml ? `<div class="tt-allday-strip">${allDayHtml}</div>` : ""}
      <div class="tt-events-area">${timedHtml || '<p class="no-course">Aucun cours</p>'}</div>
    </div>`;
  });

  return `<div class="tt-time-ruler"><div class="tt-ruler-header"></div><div class="tt-ruler-track">${rulerHours.join("")}</div></div><div class="tt-days-row">${days.join("")}</div>`;
}
```

#### 2. Implémentation de `openCourseResourcesModal` (dans `app.js`)
```javascript
async function openCourseResourcesModal(courseName) {
  if (!courseName) return;
  let modal = document.querySelector("#course-resources-modal");
  if (!modal) {
    modal = document.createElement("dialog");
    modal.id = "course-resources-modal";
    modal.className = "course-resources-dialog";
    document.body.appendChild(modal);
    modal.addEventListener("click", event => {
      if (event.target === modal) modal.close();
    });
  }

  modal.innerHTML = `
    <div style="min-width:min(92vw,500px);max-width:540px;padding:24px;display:grid;gap:16px">
      <div class="panel-heading" style="display:flex;justify-content:space-between;align-items:flex-start;margin:0">
        <div>
          <p class="eyebrow" style="margin:0 0 4px">Ressources associées</p>
          <h2 style="margin:0;font:600 20px 'Fraunces',serif">${escapeHtml(courseName)}</h2>
        </div>
        <button class="delete-button" aria-label="Fermer" onclick="this.closest('dialog').close()" style="font-size:20px;line-height:1;background:none;border:0;cursor:pointer">×</button>
      </div>
      <div id="course-modal-list">
        <p style="color:var(--muted);font-size:12px">Chargement des ressources…</p>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;border-top:1px solid var(--line);padding-top:14px">
        <a href="courses.php" class="secondary-button" style="text-decoration:none;font-size:12px;padding:8px 12px">Voir la bibliothèque</a>
        <button class="primary-button" onclick="this.closest('dialog').close()" style="font-size:12px;padding:8px 16px">Fermer</button>
      </div>
    </div>
  `;
  modal.showModal();

  // Réutilisation de la requête de la page Ressources (/api/resources?limit=50)
  try {
    const data = await apiRequest("/api/resources?limit=50", "GET");
    if (Array.isArray(data.resources)) {
      courseResources = data.resources;
      saveCourseResources();
    }
  } catch (err) {
    console.warn("Utilisation du cache local pour les ressources:", err);
  }

  const listContainer = modal.querySelector("#course-modal-list");
  const normalizedCourse = courseName.trim().toLowerCase();
  const matching = courseResources.filter(r => {
    if (!r.course) return false;
    const rc = r.course.trim().toLowerCase();
    return rc === normalizedCourse || normalizedCourse.includes(rc) || rc.includes(normalizedCourse);
  });

  if (matching.length) {
    listContainer.innerHTML = `<div class="resources-grid" style="display:grid;grid-template-columns:1fr;gap:10px;max-height:55vh;overflow-y:auto;padding-right:4px">${matching.map(resourceMarkup).join("")}</div>`;
  } else {
    listContainer.innerHTML = `<div class="empty-state" style="padding:24px 16px;text-align:center"><strong>Aucune ressource disponible</strong><p style="margin:4px 0 0;font-size:12px;color:var(--muted)">Aucun document partagé pour cette matière pour le moment.</p></div>`;
  }
}
```

#### 3. Écouteur global de clic (`app.js`, autour de la ligne 455)
```javascript
  const action = event.target.closest("[data-action]");
  if (action) {
    const id = action.dataset.id;
    if (action.dataset.action === "edit-task") {
      openTaskEditor(tasks.find(task => String(task.id) === id));
      return;
    }
    if (action.dataset.action === "view-course-resources") {
      openCourseResourcesModal(action.dataset.course);
      return;
    }
...
```

---

### 4.3. Inclusion des styles dans `timetable.php`

Dans `c:\Users\anton\Projets\IASDO\timetable.php` (lignes 8-11), ajouter `courses.css` :

```html
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Emploi du temps</title>
    <link rel="stylesheet" href="page-shell.css">
    <link rel="stylesheet" href="shared-pages.css">
    <link rel="stylesheet" href="courses.css">
    <link rel="stylesheet" href="modern.css?v=7">
</head>
```

---

## 5. Synthèse des Critères d'Acceptation & Plan de Validation

| Critère d'Acceptation | Cause Racine | Solution Préconisée | Vérification Prévue |
|---|---|---|---|
| **Grille sans barre de défilement horizontal** | `.tt-hour-line { right: -100vw }` et `.timetable-page-grid { overflow-x: auto }` | Supprimer `-100vw` (`right: 0`), positionner `.timetable-page-grid { overflow-x: hidden }` | Inspecter la grille sur écran standard 1440px : absence de barre de défilement horizontal. |
| **Graduations 06h et 07h intégralement visibles** | `.tt-time-ruler` avec `padding-top: 44px` calcule `top: 0%` à `y = 0px` (coupé par `overflow` et `border-radius`), décalage de 44px par rapport aux cours | Placer un `.tt-ruler-header` (44px) et une piste `.tt-ruler-track` avec lignes d'heures alignées | Vérifier que "06h" et "07h" ne sont pas tronqués et sont alignés au pixel près avec les cours de 06h00 et 07h00. |
| **Tâches/événements confinés à leur colonne respective** | `.tt-day-col` sans `position: relative`, `.tt-allday-strip` avec `position: absolute; left: 0; right: 0` | `.tt-day-col { position: relative }`, `.tt-days-row` en CSS Grid 7 colonnes, `.tt-allday-strip` en flux normal | Créer une tâche ou événement "toute la journée" un dimanche : constater qu'il reste strictement dans la colonne Dimanche. |
| **Clic sur une tâche ouvre la modale Edit Task pré-remplie** | `dayTasks` sans `task.id`, balises sans `data-action` | Conserver `id: task.id` et ajouter `data-action="edit-task"` | Cliquer sur une tâche dans l'emploi du temps : la modale d'édition s'ouvre avec titre, date, matière et priorité pré-remplis. |
| **Clic sur un cours ouvre la modale des ressources** | Aucun attribut interactif ni gestionnaire | Ajouter `data-action="view-course-resources"`, fonction `openCourseResourcesModal`, lien vers `courses.css` | Cliquer sur un cours ayant des ressources : la modale s'ouvre avec la liste exacte des ressources de ce cours. |
