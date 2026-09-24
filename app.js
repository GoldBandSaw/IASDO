const STORAGE_KEY = "campusflow-tasks";
const COURSES_STORAGE_KEY = "campusflow-courses";
const COURSE_RESOURCES_STORAGE_KEY = "campusflow-course-resources";
const CALENDAR_URL_KEY = "campusflow-calendar-url";
const SETTINGS_KEY = "campusflow-settings";
function getToday() { return new Date(); }
const dayNames = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
const defaultTasks = [
  { id: 1, title: "Fiche de lecture — chapitre 4", course: "Sociologie", due: addDays(1), priority: "high", done: false },
  { id: 2, title: "Exercices de probabilités", course: "Mathématiques", due: addDays(3), priority: "medium", done: false },
  { id: 3, title: "Préparer la présentation de groupe", course: "Communication", due: addDays(5), priority: "high", done: false },
  { id: 4, title: "Relire les notes du CM", course: "Histoire", due: addDays(-1), priority: "low", done: true }
];
let tasks = loadTasks();
let courses = loadCourses();
let courseResources = loadCourseResources();
let currentFilter = "open";
let timetableEvents = [];
let settings = loadSettings();
let currentUser = null;
let weekOffset = 0;
const apiEnabled = window.location.protocol !== "file:";

function addDays(amount) {
  const date = new Date(getToday());
  date.setHours(12, 0, 0, 0);
  date.setDate(date.getDate() + amount);
  return localDateKey(date);
}
function localDateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}
function loadTasks() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    return saved ? JSON.parse(saved) : defaultTasks;
  } catch (error) {
    console.error("Impossible de charger les tâches sauvegardées.", error);
    return defaultTasks;
  }
}
function saveTasks() { localStorage.setItem(STORAGE_KEY, JSON.stringify(tasks)); }
function loadCourses() {
  try {
    const saved = localStorage.getItem(COURSES_STORAGE_KEY);
    return saved ? JSON.parse(saved) : [];
  } catch (error) {
    console.error("Impossible de charger les matières sauvegardées.", error);
    return [];
  }
}
function saveCourses() {
  localStorage.setItem(COURSES_STORAGE_KEY, JSON.stringify(courses));
  if (apiEnabled) {
    Promise.all(courses.map(name => apiRequest("/api/courses", "POST", { name })))
      .catch(error => console.error("Enregistrement de la matière impossible.", error));
    // Note: Future improvement: batch into a single API request if backend supports it
  }
}
function loadCourseResources() {
  try {
    const saved = localStorage.getItem(COURSE_RESOURCES_STORAGE_KEY);
    return saved ? JSON.parse(saved) : [];
  } catch (error) {
    console.error("Impossible de charger les ressources de cours sauvegardées.", error);
    return [];
  }
}
function saveCourseResources() { localStorage.setItem(COURSE_RESOURCES_STORAGE_KEY, JSON.stringify(courseResources)); }
function loadSettings() {
  try { return JSON.parse(localStorage.getItem(SETTINGS_KEY)) || { firstName: "", lastName: "", darkMode: false }; }
  catch (error) { console.error("Impossible de charger les paramètres.", error); return { firstName: "", lastName: "", darkMode: false }; }
}
function saveSettings() { localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings)); }
async function apiRequest(path, method, body) {
  if (!apiEnabled) return null;
  const response = await fetch(path, { method, headers: { "Content-Type": "application/json" }, body: body ? JSON.stringify(body) : undefined });
  if (!response.ok) throw new Error(`API ${response.status}`);
  return response.json();
}
async function syncInitialState() {
  if (!apiEnabled) return;
  try {
    const auth = await apiRequest("/api/auth/me", "GET");
    currentUser = auth.user;
    const state = await apiRequest("/api/state", "GET");
    if (state.tasks.length) tasks = state.tasks;
    else for (const task of tasks) await apiRequest("/api/tasks", "POST", task);
    if (state.settings) {
      settings = state.settings;
      saveSettings();
    } else {
      await apiRequest("/api/settings", "PUT", { ...settings, calendarUrl: localStorage.getItem(CALENDAR_URL_KEY) || "" });
    }
    if (Array.isArray(state.courses) && state.courses.length) {
      courses = state.courses;
      saveCourses();
    }
    if (Array.isArray(state.resources)) {
      courseResources = state.resources;
      saveCourseResources();
    }
    saveTasks();
    render();
  } catch (error) {
    console.error("Synchronisation de la base impossible.", error);
  }
}
function fetchCalendar() {
  return fetch("/api/timetable", { cache: "no-store" });
}
function dateFromString(value) { return new Date(`${value}T12:00:00`); }
function daysUntil(value) {
  return Math.ceil((dateFromString(value) - new Date(`${localDateKey(getToday())}T12:00:00`)) / 86400000);
}
function formatDue(value) {
  const days = daysUntil(value);
  if (days < 0) return `En retard de ${Math.abs(days)} jour${Math.abs(days) > 1 ? "s" : ""}`;
  if (days === 0) return "Aujourd'hui";
  if (days === 1) return "Demain";
  return `Dans ${days} jours`;
}
function taskMarkup(task) {
  const urgent = !task.done && daysUntil(task.due) <= 3;
  const canEdit = !task.owner || !currentUser || task.owner === currentUser.username;
  return `<div class="task-row">
    <button class="check ${task.done ? "done" : ""}" data-action="toggle" data-id="${task.id}" aria-label="${task.done ? "Marquer à faire" : "Marquer terminée"}">${task.done ? "✓" : ""}</button>
    <div class="task-body"><p class="task-title ${task.done ? "done" : ""}">${escapeHtml(task.title)}</p><div class="task-meta"><span class="course">${escapeHtml(task.course)}</span> · <span class="priority ${task.priority}" title="Priorité"></span> ${priorityName(task.priority)}${task.shared ? ' · <span class="shared-task-badge">Partagée</span>' : ""}</div></div>
    <span class="due ${urgent ? "urgent" : "normal"}">${formatDue(task.due)}</span>
    ${canEdit ? `<button class="edit-button" data-action="edit-task" data-id="${task.id}" aria-label="Modifier ${escapeHtml(task.title)}">Modifier</button><button class="delete-button" data-action="delete" data-id="${task.id}" aria-label="Supprimer">×</button>` : '<span class="task-shared-note">Partagée par la promotion</span>'}
  </div>`;
}
function priorityName(value) { return { high: "Haute", medium: "Moyenne", low: "Basse" }[value]; }
function escapeHtml(value) { return value.replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[char])); }
function render() {
  document.body.classList.toggle("dark-mode", settings.darkMode);
  const page = document.body.dataset.page || "dashboard";
  const name = [settings.firstName, settings.lastName].filter(Boolean).join(" ") || "étudiant·e";
  const studentName = document.querySelector("#student-name");
  if (studentName) studentName.textContent = name;
  const accountName = document.querySelector("#sidebar-account-name");
  if (accountName) accountName.textContent = currentUser?.display_name || (name === "étudiant·e" ? "Mon espace" : name);
  const avatar = document.querySelector("#sidebar-avatar");
  if (avatar) avatar.textContent = (currentUser?.display_name || settings.firstName || "É").charAt(0).toUpperCase();
  if (page === "timetable") { renderTimetable(); return; }
  if (page === "settings") { renderSettings(); return; }
  if (page === "tasks") {
    const list = document.querySelector("#all-tasks-list");
    const filtered = tasks.filter(task => currentFilter === "all" || (currentFilter === "done" ? task.done : !task.done));
    if (list) list.innerHTML = filtered.length ? filtered.sort((a, b) => dateFromString(a.due) - dateFromString(b.due)).map(taskMarkup).join("") : emptyMarkup("Aucune tâche ici", "Ajoute un travail pour commencer.");
    const subtitle = document.querySelector("#task-subtitle");
    if (subtitle) subtitle.textContent = `${tasks.length} tâche${tasks.length > 1 ? "s" : ""} enregistrée${tasks.length > 1 ? "s" : ""}`;
    return;
  }
  if (page === "add") { renderCourses(); return; }
  if (page === "courses") { renderCoursesPage(); return; }
  const openTasks = tasks.filter(task => !task.done);
  const weekTasks = openTasks.filter(task => daysUntil(task.due) >= 0 && daysUntil(task.due) <= 7);
  const urgentTasks = openTasks.filter(task => daysUntil(task.due) >= 0 && daysUntil(task.due) <= 3);
  const progress = tasks.length ? Math.round(tasks.filter(task => task.done).length / tasks.length * 100) : 0;
  const todayLabel = document.querySelector("#today-label");
  if (todayLabel) todayLabel.textContent = getToday().toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
  const weekCount = document.querySelector("#week-count");
  if (weekCount) weekCount.textContent = weekTasks.length;
  const weekTrend = document.querySelector("#week-trend");
  if (weekTrend) weekTrend.textContent = weekTasks.length ? "à planifier" : "semaine libre";
  const urgentCount = document.querySelector("#urgent-count");
  if (urgentCount) urgentCount.textContent = urgentTasks.length;
  const progressValue = document.querySelector("#progress-value");
  if (progressValue) progressValue.textContent = `${progress}%`;
  const progressRing = document.querySelector(".progress-ring");
  if (progressRing) progressRing.style.setProperty("--progress", `${progress}%`);
  const taskSubtitle = document.querySelector("#task-subtitle");
  if (taskSubtitle) taskSubtitle.textContent = `${tasks.length} tâche${tasks.length > 1 ? "s" : ""} enregistrée${tasks.length > 1 ? "s" : ""}`;
  const sorted = [...openTasks].sort((a, b) => dateFromString(a.due) - dateFromString(b.due));
  const upcomingList = document.querySelector("#upcoming-list");
  if (upcomingList) upcomingList.innerHTML = sorted.length ? sorted.slice(0, 4).map(taskMarkup).join("") : emptyMarkup("Tout est à jour !", "Profite de ce temps pour souffler.");
  const filtered = tasks.filter(task => currentFilter === "all" || (currentFilter === "done" ? task.done : !task.done)).sort((a, b) => dateFromString(a.due) - dateFromString(b.due));
  const allTasksList = document.querySelector("#all-tasks-list");
  if (allTasksList) allTasksList.innerHTML = filtered.length ? filtered.map(taskMarkup).join("") : emptyMarkup("Aucune tâche ici", "Ajoute un travail pour commencer.");
  renderCourses();
  renderSettings();
  renderTimetable();
  renderWeekChart();
  renderDashboardResources();
  renderNextStep();
}
function renderSettings() {
  document.querySelector("#first-name").value = settings.firstName;
  document.querySelector("#last-name").value = settings.lastName;
  document.querySelector("#dark-mode").checked = settings.darkMode;
  const calendarSetting = document.querySelector("#saved-calendar-url");
  if (calendarSetting) {
    calendarSetting.value = "Planning commun de la promotion";
    calendarSetting.readOnly = true;
  }
  const accountName = document.querySelector("#account-name");
  if (accountName) accountName.value = currentUser?.display_name || "";
}
function renderTimetable() {
  const grid = document.querySelector("#timetable-grid");
  const weekStart = getWeekStart(weekOffset);
  const weekEnd = new Date(weekStart); weekEnd.setDate(weekStart.getDate() + 7);
  const visibleEvents = timetableEvents.filter(event => {
    const date = new Date(event.start);
    return date >= weekStart && date < weekEnd;
  });
  document.querySelector("#week-label").textContent = `${weekStart.toLocaleDateString("fr-FR", { day: "numeric", month: "long" })} — ${new Date(weekEnd - 86400000).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" })}`;
  if (grid) grid.innerHTML = renderWeekGrid(weekStart, visibleEvents);
}
function getWeekStart(offset) {
  const date = new Date(getToday()); date.setHours(0, 0, 0, 0);
  const mondayOffset = date.getDay() === 0 ? -6 : 1 - date.getDay();
  date.setDate(date.getDate() + mondayOffset + offset * 7);
  return date;
}
function renderWeekGrid(weekStart, events) {
  const days = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(weekStart); date.setDate(weekStart.getDate() + index);
    const key = localDateKey(date);
    const dayEvents = events.filter(event => event.start.slice(0, 10) === key);
    const dayTasks = tasks.filter(task => !task.done && task.due === key).map(task => ({
      start: task.allDay || !task.time ? `${key}T00:00:00` : `${key}T${task.time}:00`,
      subject: task.title,
      location: task.course,
      isTask: true,
      allDay: task.allDay || !task.time
    }));
    const items = [...dayEvents, ...dayTasks].sort((a, b) => new Date(a.start) - new Date(b.start));
    return `<div class="day-column"><header><span>${date.toLocaleDateString("fr-FR", { weekday: "short" })}</span><strong>${date.getDate()}</strong></header><div class="day-events">${items.length ? items.map(event => `<article class="timetable-event ${event.isTask ? "task-event" : ""}"><time>${event.allDay ? "Toute la journée" : new Date(event.start).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}</time><strong>${escapeHtml(event.subject)}</strong><span>${escapeHtml(event.isTask ? event.location : (event.location || "Lieu non indiqué"))}</span></article>`).join("") : '<p class="no-course">Aucun cours</p>'}</div></div>`;
  });
  return days.join("");
  /*
  const list = document.querySelector("#timetable-list");
  if (!timetableEvents.length) {
    list.innerHTML = emptyMarkup("Aucun cours chargé", "Importe ton emploi du temps dans les paramètres.");
    return;
  }
  list.innerHTML = timetableEvents.slice(0, 30).map(event => `<article class="timetable-event"><time>${formatEventDate(event.start)}</time><div><strong>${escapeHtml(event.subject)}</strong><span>${escapeHtml(event.location || "Lieu non indiqué")}</span></div></article>`).join("");
  */
}
function formatEventDate(value) {
  const date = new Date(value);
  return `${date.toLocaleDateString("fr-FR", { weekday: "short", day: "numeric", month: "short" })} · ${date.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}`;
}
function renderCourses() {
  const options = document.querySelector("#course-options");
  if (options) options.innerHTML = courses.map(course => `<option value="${escapeHtml(course)}"></option>`).join("");
  const calendarUrl = document.querySelector("#calendar-url");
  if (calendarUrl) calendarUrl.value = localStorage.getItem(CALENDAR_URL_KEY) || "";
  const chips = document.querySelector("#course-chips");
  if (chips) chips.innerHTML = courses.length
    ? courses.map(course => `<span class="course-chip">${escapeHtml(course)}</span>`).join("")
    : `<span class="course-placeholder">Aucune matière importée pour le moment.</span>`;
}
function resourceTypeName(type) {
  return { notion: "Notion", pdf: "PDF", markdown: "Markdown", image: "Image", gemini: "Gemini Notebook", other: "Autre" }[type] || "Lien";
}
function resourceIcon(type) {
  return { notion: "N", pdf: "PDF", markdown: "MD", image: "IMG", gemini: "*", other: "↗" }[type] || "->";
}
function previewMarkup(resource) {
  return "";
}
function resourceMarkup(resource) {
  const href = resource.file_path ? `/api/resources/${encodeURIComponent(resource.id)}/download` : resource.url;
  const target = resource.file_path ? "" : ' target="_blank" rel="noopener noreferrer"';
  return `<article class="resource-card">
    <div class="resource-card-header"><span class="resource-icon ${escapeHtml(resource.type)}">${resourceIcon(resource.type)}</span><div class="resource-heading"><h3>${escapeHtml(resource.title)}</h3><span>${resourceTypeName(resource.type)}${resource.file_name ? ` · ${escapeHtml(resource.file_name)}` : ""}</span></div><button class="delete-button" data-resource-delete="${escapeHtml(resource.id)}" aria-label="Supprimer ${escapeHtml(resource.title)}">×</button></div>
    ${previewMarkup(resource)}
    <div class="resource-actions"><a class="resource-link" href="${escapeHtml(href)}"${target}>${resource.file_path ? "Télécharger le fichier" : "Ouvrir la ressource"} <span>↗</span></a><button class="report-resource" data-resource-report="${escapeHtml(resource.id)}">Signaler</button></div>
  </article>`;
}
function renderCoursesPage() {
  const select = document.querySelector("#resource-course");
  if (select) {
    const current = select.value;
    select.innerHTML = courses.map(course => `<option value="${escapeHtml(course)}">${escapeHtml(course)}</option>`).join("");
    if (current && courses.includes(current)) select.value = current;
  }
  const courseList = document.querySelector("#courses-list");
  if (!courseList) return;
  courseList.innerHTML = courses.length
    ? courses.map(course => {
      const resources = courseResources.filter(resource => resource.course === course);
      return `<section class="course-section"><div class="course-section-heading"><div><span class="eyebrow">Matière</span><h2>${escapeHtml(course)}</h2></div><span class="resource-count">${resources.length} ressource${resources.length > 1 ? "s" : ""}</span></div>${resources.length ? `<div class="resources-grid">${resources.map(resourceMarkup).join("")}</div>` : emptyMarkup("Aucun cours ajouté", "Ajoute un lien Notion, un PDF ou un Notebook Gemini.")}</section>`;
    }).join("")
    : emptyMarkup("Aucune matière", "Ajoute une matière ou importe ton emploi du temps dans Paramètres.");
}
function emptyMarkup(title, text) { return `<div class="empty-state"><strong>${title}</strong>${text}</div>`; }
function renderWeekChart() {
  const chart = document.querySelector("#week-chart");
  if (!chart) return;
  const start = new Date(getToday()); start.setDate(getToday().getDate() - (getToday().getDay() || 7) + 1);
  const counts = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(start); date.setDate(start.getDate() + index);
    return tasks.filter(task => !task.done && task.due === localDateKey(date)).length;
  });
  const max = Math.max(...counts, 1);
  chart.innerHTML = counts.map((count, index) => {
    const date = new Date(start); date.setDate(start.getDate() + index);
    const isToday = localDateKey(date) === localDateKey(getToday());
    return `<div class="bar-wrap"><span class="bar-count">${count || ""}</span><div class="bar ${isToday ? "today" : ""}" style="height:${Math.max(count / max * 82, 4)}px"></div><span class="bar-label">${dayNames[date.getDay()]}</span></div>`;
  }).join("");
}
function renderDashboardResources() {
  const list = document.querySelector("#recent-resources");
  if (!list) return;
  const recent = [...courseResources].slice(-4).reverse();
  list.innerHTML = recent.length
    ? recent.map(resource => {
        const href = resource.url || resource.signed_url;
        return href
          ? `<a class="recent-resource" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer"><span class="resource-icon ${escapeHtml(resource.type)}">${resourceIcon(resource.type)}</span><span><strong>${escapeHtml(resource.title)}</strong><small>${escapeHtml(resource.course)} · ${resourceTypeName(resource.type)}</small></span><span aria-hidden="true">↗</span></a>`
          : `<div class="recent-resource"><span class="resource-icon ${escapeHtml(resource.type)}">${resourceIcon(resource.type)}</span><span><strong>${escapeHtml(resource.title)}</strong><small>${escapeHtml(resource.course)} · ${resourceTypeName(resource.type)}</small></span></div>`;
      }).join("")
    : emptyMarkup("Aucune ressource partagée", "La bibliothèque se remplira dès que quelqu'un ajoutera un lien.");
}
function renderNextStep() {
  const card = document.querySelector("#next-step-card");
  const context = document.querySelector("#next-step-context");
  if (!card) return;
  const next = tasks.filter(task => !task.done).sort((a, b) => dateFromString(a.due) - dateFromString(b.due))[0];
  if (!next) {
    if (context) context.textContent = "Toutes tes tâches sont terminées.";
    card.innerHTML = `<strong>Tu es à jour !</strong><p>Profite de ton avance ou consulte la bibliothèque.</p><a class="secondary-button" href="courses.php">Ouvrir les ressources</a>`;
    return;
  }
  if (context) context.textContent = `${next.course} · ${formatDue(next.due)}`;
  card.innerHTML = `<strong>${escapeHtml(next.title)}</strong><p>Priorité ${priorityName(next.priority).toLowerCase()} · ${formatDue(next.due)}</p><a class="primary-button" href="tasks.php">Voir mes tâches</a>`;
}
function showToast(message) {
  const toast = document.querySelector("#toast");
  if (!toast) return;
  toast.textContent = message; toast.classList.add("visible");
  setTimeout(() => toast.classList.remove("visible"), 2400);
}
function showView(view) {
  document.querySelectorAll("[data-view-content]").forEach(element => element.classList.toggle("view-active", element.dataset.viewContent === view));
  document.querySelectorAll(".nav-item").forEach(item => item.classList.toggle("active", item.dataset.view === view));
}
function ensureCoursesNav() {
  const nav = document.querySelector(".sidebar nav");
  if (!nav || nav.querySelector('a[href="courses.php"]')) return;
  const link = document.createElement("a");
  link.className = "nav-item";
  link.href = "courses.php";
  link.innerHTML = '<span class="icon">▣</span> Mes cours';
  nav.insertBefore(link, nav.querySelector('a[href="timetable.php"]'));
}
function ensureAdminNav() {
  const nav = document.querySelector(".sidebar nav");
  if (!nav || !currentUser || currentUser.role !== "admin" || nav.querySelector('a[href="admin.php"]')) return;
  const link = document.createElement("a");
  link.className = "nav-item";
  link.href = "admin.php";
  link.innerHTML = '<span class="icon">▦</span> Administration';
  nav.appendChild(link);
}
document.addEventListener("click", event => {
  const reportButton = event.target.closest("[data-resource-report]");
  if (reportButton) {
    const reason = window.prompt("Pourquoi signales-tu cette ressource ?");
    if (!reason || !reason.trim()) return;
    apiRequest(`/api/resources/${encodeURIComponent(reportButton.dataset.resourceReport)}`, "POST", { report: true, reason: reason.trim() })
      .then(() => showToast("Signalement envoyé à l’administration"))
      .catch(() => showToast("Impossible d’envoyer le signalement"));
    return;
  }
  const nav = event.target.closest(".nav-item");
  if (nav && nav.dataset.view) {
    event.preventDefault();
    showView(nav.dataset.view);
  }
  const resourceDelete = event.target.closest("[data-resource-delete]");
  if (resourceDelete) {
    const resourceId = resourceDelete.dataset.resourceDelete;
    courseResources = courseResources.filter(resource => String(resource.id) !== resourceId);
    saveCourseResources();
    apiRequest(`/api/resources/${resourceId}`, "DELETE").catch(error => console.error("Suppression de la ressource impossible.", error));
    renderCoursesPage();
    showToast("Ressource supprimée");
    return;
  }
  const action = event.target.closest("[data-action]");
  if (action) {
    const id = action.dataset.id;
    if (action.dataset.action === "edit-task") {
      openTaskEditor(tasks.find(task => String(task.id) === id));
      return;
    }
    if (action.dataset.action === "toggle") {
      tasks = tasks.map(task => String(task.id) === id ? { ...task, done: !task.done } : task);
      const changed = tasks.find(task => String(task.id) === id);
      if (changed) apiRequest(`/api/tasks/${id}`, "PUT", changed).catch(error => console.error("Mise à jour de la tâche impossible.", error));
      showToast("Progression mise à jour");
    }
    if (action.dataset.action === "delete") {
      tasks = tasks.filter(task => String(task.id) !== id);
      apiRequest(`/api/tasks/${id}`, "DELETE").catch(error => console.error("Suppression de la tâche impossible.", error));
      showToast("Tâche supprimée");
    }
    saveTasks(); render();
  }
  const filter = event.target.closest(".filter");
  if (filter) { currentFilter = filter.dataset.filter; document.querySelectorAll(".filter").forEach(button => button.classList.toggle("active", button === filter)); render(); }
  if (event.target.closest("#open-add-top") && document.querySelector("#add")) document.querySelector("#add").scrollIntoView({ behavior: "smooth" });
  if (event.target.closest("#previous-week")) { weekOffset--; renderTimetable(); }
  if (event.target.closest("#next-week")) { weekOffset++; renderTimetable(); }
  if (event.target.closest("#current-week")) { weekOffset = 0; renderTimetable(); }
});
const taskForm = document.querySelector("#task-form");
if (taskForm) taskForm.addEventListener("submit", event => {
  event.preventDefault();
  const allDay = document.querySelector("#task-all-day")?.checked ?? true;
  const course = document.querySelector("#task-course").value.trim();
  if (courses.length && !courses.some(item => item.toLocaleLowerCase() === course.toLocaleLowerCase())) {
    showToast("Choisis une matière existante");
    return;
  }
  const task = { id: Date.now(), title: document.querySelector("#task-title").value.trim(), course, due: document.querySelector("#task-date").value, time: allDay ? "" : (document.querySelector("#task-time")?.value || ""), allDay, priority: document.querySelector("#task-priority").value, shared: Boolean(document.querySelector("#task-shared")?.checked), done: false };
  tasks.push(task); saveTasks();
  apiRequest("/api/tasks", "POST", task).catch(error => console.error("Enregistrement de la tâche impossible.", error));
  event.target.reset(); render(); showToast("Tâche ajoutée à ton planning");
  const tasksSection = document.querySelector("#tasks");
  if (tasksSection) tasksSection.scrollIntoView({ behavior: "smooth" });
});
function openTaskEditor(task) {
  if (!task) return;
  let modal = document.querySelector("#task-editor");
  if (!modal) {
    modal = document.createElement("dialog");
    modal.id = "task-editor";
    modal.innerHTML = `<form method="dialog" class="task-editor-form">
      <div class="panel-heading"><div><p class="eyebrow">Modifier la tâche</p><h2 id="task-editor-title">Tâche</h2></div><button class="delete-button" value="cancel" aria-label="Fermer">×</button></div>
      <label>Titre<input id="edit-task-title" required></label>
      <label>Matière<input id="edit-task-course" list="edit-course-options" required><datalist id="edit-course-options"></datalist></label>
      <label>Date limite<input id="edit-task-date" type="date" required></label>
      <label>Priorité<select id="edit-task-priority"><option value="high">Haute</option><option value="medium">Moyenne</option><option value="low">Basse</option></select></label>
      <label class="share-task-label"><input id="edit-task-shared" type="checkbox"> Partager avec la promotion</label>
      <div class="task-editor-actions"><button class="secondary-button" value="cancel">Annuler</button><button class="primary-button" id="save-task-edit" value="default">Enregistrer</button></div>
    </form>`;
    document.body.appendChild(modal);
    modal.addEventListener("submit", event => {
      event.preventDefault();
      const current = tasks.find(item => String(item.id) === modal.dataset.taskId);
      if (!current) return;
      Object.assign(current, {
        title: document.querySelector("#edit-task-title").value.trim(),
        course: document.querySelector("#edit-task-course").value.trim(),
        due: document.querySelector("#edit-task-date").value,
        priority: document.querySelector("#edit-task-priority").value,
        shared: document.querySelector("#edit-task-shared").checked
      });
      apiRequest(`/api/tasks/${current.id}`, "PUT", current).catch(error => console.error("Modification de la tâche impossible.", error));
      saveTasks(); modal.close(); render(); showToast("Tâche modifiée");
    });
  }
  modal.dataset.taskId = String(task.id);
  document.querySelector("#edit-task-title").value = task.title;
  document.querySelector("#edit-task-course").value = task.course;
  document.querySelector("#edit-task-date").value = task.due;
  document.querySelector("#edit-task-priority").value = task.priority;
  document.querySelector("#edit-task-shared").checked = Boolean(task.shared);
  document.querySelector("#edit-course-options").innerHTML = courses.map(course => `<option value="${escapeHtml(course)}"></option>`).join("");
  modal.showModal();
}
const courseForm = document.querySelector("#course-form");
if (courseForm) courseForm.addEventListener("submit", event => {
  event.preventDefault();
  const input = document.querySelector("#new-course");
  const name = input.value.trim();
  if (!name) return;
  if (!courses.some(course => course.toLocaleLowerCase() === name.toLocaleLowerCase())) {
    courses = [...courses, name].sort((a, b) => a.localeCompare(b, "fr"));
    saveCourses();
    input.value = "";
    render();
    showToast("Matière ajoutée");
  } else {
    showToast("Cette matière existe déjà");
  }
});
const resourceForm = document.querySelector("#resource-form");
const resourceSource = document.querySelector("#resource-source");
const resourceFile = document.querySelector("#resource-file");
if (resourceFile) resourceFile.addEventListener("change", () => {
  const title = document.querySelector("#resource-title");
  if (resourceFile.files[0] && title && !title.value.trim()) title.value = resourceFile.files[0].name.replace(/\.[^.]+$/, "");
});
if (resourceSource) resourceSource.addEventListener("change", () => {
  const fileMode = resourceSource.value === "file";
  document.querySelector("#resource-link-field").style.display = fileMode ? "none" : "";
  document.querySelector("#resource-file-field").style.display = fileMode ? "" : "none";
  document.querySelector("#resource-url").required = !fileMode;
  document.querySelector("#resource-file").required = fileMode;
});
if (resourceForm) resourceForm.addEventListener("submit", async event => {
  event.preventDefault();
  const title = document.querySelector("#resource-title").value.trim();
  const course = document.querySelector("#resource-course").value;
  const type = document.querySelector("#resource-type").value;
  const url = document.querySelector("#resource-url").value.trim();
  const file = document.querySelector("#resource-file").files[0];
  if (!course) return;
  if (resourceSource?.value === "file") {
    if (!file) { showToast("Choisis un fichier"); return; }
    if (file.size > 50 * 1024 * 1024) { showToast("Le fichier ne doit pas dépasser 50 Mo"); return; }
    const form = new FormData();
    form.append("course", course); form.append("title", title); form.append("file", file);
    try {
      const response = await fetch("/api/resources/upload", { method: "POST", body: form });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || "Upload impossible");
      courseResources.push(data); saveCourseResources(); event.target.reset(); renderCoursesPage(); showToast("Fichier partagé");
    } catch (error) { showToast(error.message); }
    return;
  }
  if (!title || !url) return;
  try {
    const parsed = new URL(url);
    if (!["http:", "https:"].includes(parsed.protocol)) throw new Error("URL non sécurisée");
  } catch (error) {
    showToast("Ajoute une URL HTTPS valide");
    return;
  }
  courseResources.push({ id: Date.now(), title, course, type, url });
  saveCourseResources();
  apiRequest("/api/resources", "POST", courseResources.at(-1)).catch(error => console.error("Enregistrement de la ressource impossible.", error));
  event.target.reset();
  renderCoursesPage();
  showToast("Cours ajouté");
});
const calendarForm = document.querySelector("#calendar-form");
if (calendarForm) calendarForm.addEventListener("submit", async event => {
  event.preventDefault();
  const status = document.querySelector("#calendar-status");
  status.className = "calendar-status";
  status.textContent = "Import des matières en cours…";
  try {
    const response = await fetchCalendar();
    if (!response.ok) throw new Error(`Le lien a répondu avec le statut ${response.status}.`);
    const icsText = await response.text();
    const importedCourses = extractCoursesFromIcs(icsText);
    timetableEvents = extractEventsFromIcs(icsText);
    if (!importedCourses.length) throw new Error("Aucune matière lisible n'a été trouvée dans ce calendrier.");
    courses = importedCourses;
    saveCourses();
    render();
    status.className = "calendar-status success";
    status.textContent = `${courses.length} matière${courses.length > 1 ? "s" : ""} synchronisée${courses.length > 1 ? "s" : ""}.`;
    renderTimetable();
  } catch (error) {
    console.error("Import de l'emploi du temps impossible.", error);
    status.className = "calendar-status error";
    status.textContent = "Import impossible. Vérifie le lien et assure-toi qu'il autorise l'accès depuis le navigateur (CORS).";
  }
});
const refreshTimetable = document.querySelector("#refresh-timetable");
if (refreshTimetable) refreshTimetable.addEventListener("click", () => syncCalendar(true));
const settingsForm = document.querySelector("#settings-form");
if (settingsForm) settingsForm.addEventListener("submit", event => {
  event.preventDefault();
  settings = { firstName: document.querySelector("#first-name").value.trim(), lastName: document.querySelector("#last-name").value.trim(), darkMode: document.querySelector("#dark-mode").checked };
  saveSettings();
  localStorage.setItem(CALENDAR_URL_KEY, document.querySelector("#saved-calendar-url").value.trim());
  apiRequest("/api/settings", "PUT", { ...settings, calendarUrl: localStorage.getItem(CALENDAR_URL_KEY) }).catch(error => console.error("Enregistrement des paramètres impossible.", error));
  render();
  if (document.body.dataset.page === "timetable") syncCalendar(true);
  const settingsStatus = document.querySelector("#settings-status");
  if (settingsStatus) {
    settingsStatus.className = "calendar-status success";
    settingsStatus.textContent = "Paramètres enregistrés.";
  }
  showToast("Paramètres enregistrés");
});
const accountForm = document.querySelector("#account-form");
if (accountForm) accountForm.addEventListener("submit", async event => {
  event.preventDefault();
  const status = document.querySelector("#account-status");
  const displayName = document.querySelector("#account-name").value.trim();
  const password = document.querySelector("#account-password").value;
  try {
    if (displayName) {
      const response = await fetch("/api/auth/profile", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ displayName }) });
      if (!response.ok) throw new Error((await response.json()).error || "Nom invalide.");
      currentUser.display_name = displayName;
    }
    if (password) {
      const response = await fetch("/api/auth/password", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ password }) });
      if (!response.ok) throw new Error((await response.json()).error || "Mot de passe invalide.");
    }
    document.querySelector("#account-password").value = "";
    render();
    status.className = "calendar-status success";
    status.textContent = "Compte mis à jour.";
  } catch (error) {
    status.className = "calendar-status error";
    status.textContent = error.message;
  }
});
const darkMode = document.querySelector("#dark-mode");
if (darkMode) darkMode.addEventListener("change", () => {
  settings.darkMode = darkMode.checked;
  saveSettings();
  apiRequest("/api/settings", "PUT", { ...settings, calendarUrl: localStorage.getItem(CALENDAR_URL_KEY) || "" }).catch(error => console.error("Enregistrement du thème impossible.", error));
  document.body.classList.toggle("dark-mode", settings.darkMode);
  const status = document.querySelector("#settings-status");
  if (status) {
    status.className = "calendar-status success";
    status.textContent = settings.darkMode ? "Mode sombre activé." : "Mode clair activé.";
  }
});
const allDayToggle = document.querySelector("#task-all-day");
if (allDayToggle) allDayToggle.addEventListener("change", () => {
  const time = document.querySelector("#task-time");
  if (time) time.disabled = allDayToggle.checked;
});
if (allDayToggle) {
  const time = document.querySelector("#task-time");
  if (time) time.disabled = allDayToggle.checked;
}
async function syncCalendar(manual = false) {
  const status = document.querySelector("#timetable-status");
  try {
    const response = await fetchCalendar();
    if (!response.ok) throw new Error(`Statut ${response.status}`);
    const text = await response.text();
    timetableEvents = extractEventsFromIcs(text);
    courses = extractCoursesFromIcs(text);
    saveCourses();
    renderTimetable();
    if (status) status.className = "calendar-status success";
    if (status) status.textContent = `Synchronisé à ${new Date().toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}.`;
  } catch (error) {
    console.error("Synchronisation impossible.", error);
    if (status) {
      status.className = "calendar-status error";
      status.textContent = manual ? "Synchronisation impossible. Le calendrier universitaire est indisponible." : "Dernière synchronisation impossible.";
    }
  }
}
function extractEventsFromIcs(icsText) {
  const unfolded = icsText.replace(/\r?\n[ \t]/g, "").split(/\r?\n/);
  const events = [];
  let current = null;
  unfolded.forEach(line => {
    if (line === "BEGIN:VEVENT") current = {};
    if (current && line.startsWith("SUMMARY:")) current.summary = line.slice(8);
    if (current && line.startsWith("DTSTART")) current.start = parseIcsDate(line.split(":").slice(1).join(":"));
    if (current && line.startsWith("LOCATION:")) current.location = line.slice(9);
    if (line === "END:VEVENT" && current?.start && current.summary) {
      events.push({ start: current.start, location: current.location || "", subject: current.summary.replace(/^(?:VG|CM|TD|CC|DS)\s*-\s*/i, "").replace(/\s*-\s*(?:CM|TD|TP|CC|DS)(?:\s*-\s*.*)?$/i, "").trim() });
      current = null;
    }
  });
  return events.sort((a, b) => new Date(a.start) - new Date(b.start));
}
function parseIcsDate(str) {
    if (!str) return null;
    try {
        // Remove any TZID prefix
        const dateStr = str.includes(':') ? str.split(':').pop() : str;
        if (dateStr.length === 8) {
            // All-day event: YYYYMMDD
            return new Date(dateStr.slice(0,4) + '-' + dateStr.slice(4,6) + '-' + dateStr.slice(6,8) + 'T00:00:00');
        }
        // Full datetime: YYYYMMDDTHHMMSSZ or YYYYMMDDTHHMMSS
        const y = dateStr.slice(0,4), mo = dateStr.slice(4,6), d = dateStr.slice(6,8);
        const h = dateStr.slice(9,11), mi = dateStr.slice(11,13), s = dateStr.slice(13,15);
        const date = new Date(y + '-' + mo + '-' + d + 'T' + h + ':' + mi + ':' + s + 'Z');
        if (isNaN(date.getTime())) return null;
        return date;
    } catch (e) {
        return null;
    }
}
function extractCoursesFromIcs(icsText) {
  const unfolded = icsText.replace(/\r?\n[ \t]/g, "").split(/\r?\n/);
  const subjects = unfolded
    .filter(line => line.startsWith("SUMMARY:"))
    .map(line => line.slice(8).replace(/\\[,;n]/g, " ").trim())
    .map(summary => summary
      .replace(/^(?:VG|CM|TD|CC|DS)\s*-\s*/i, "")
      .replace(/\s*-\s*(?:CM|TD|TP|CC|DS)(?:\s*-\s*.*)?$/i, "")
      .replace(/\s+/g, " ")
      .trim())
    .filter(Boolean);
  return [...new Set(subjects)].sort((a, b) => a.localeCompare(b, "fr"));
}
const taskDate = document.querySelector("#task-date");
if (taskDate) taskDate.min = localDateKey(getToday());
ensureCoursesNav();
render();
syncInitialState();
if (!document.body.dataset.page) showView("dashboard");
ensureAdminNav();
if (document.body.dataset.page === "timetable") {
  syncCalendar();
  setInterval(syncCalendar, 15 * 60 * 1000);
}
