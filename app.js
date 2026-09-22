const STORAGE_KEY = "campusflow-tasks";
const COURSES_STORAGE_KEY = "campusflow-courses";
const COURSE_RESOURCES_STORAGE_KEY = "campusflow-course-resources";
const CALENDAR_URL_KEY = "campusflow-calendar-url";
const SETTINGS_KEY = "campusflow-settings";
const today = new Date();
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
let weekOffset = 0;
const apiEnabled = window.location.protocol !== "file:";

function addDays(amount) {
  const date = new Date(today);
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
function saveCourses() { localStorage.setItem(COURSES_STORAGE_KEY, JSON.stringify(courses)); }
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
function calendarProxyUrl(url) {
  const encodedUrl = encodeURIComponent(url);
  const base = window.location.protocol === "file:" ? "http://localhost:3000" : "";
  return `${base}/api/calendar?url=${encodedUrl}`;
}
function fetchCalendar(url) {
  return fetch(calendarProxyUrl(url), { cache: "no-store" });
}
function dateFromString(value) { return new Date(`${value}T12:00:00`); }
function daysUntil(value) {
  return Math.ceil((dateFromString(value) - new Date(`${localDateKey(today)}T12:00:00`)) / 86400000);
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
  return `<div class="task-row">
    <button class="check ${task.done ? "done" : ""}" data-action="toggle" data-id="${task.id}" aria-label="${task.done ? "Marquer à faire" : "Marquer terminée"}">${task.done ? "✓" : ""}</button>
    <div class="task-body"><p class="task-title ${task.done ? "done" : ""}">${escapeHtml(task.title)}</p><div class="task-meta"><span class="course">${escapeHtml(task.course)}</span> · <span class="priority ${task.priority}" title="Priorité"></span> ${priorityName(task.priority)}</div></div>
    <span class="due ${urgent ? "urgent" : "normal"}">${formatDue(task.due)}</span>
    <button class="delete-button" data-action="delete" data-id="${task.id}" aria-label="Supprimer">×</button>
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
  document.querySelector("#today-label").textContent = today.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
  document.querySelector("#week-count").textContent = weekTasks.length;
  document.querySelector("#week-trend").textContent = weekTasks.length ? "à planifier" : "semaine libre";
  document.querySelector("#urgent-count").textContent = urgentTasks.length;
  document.querySelector("#progress-value").textContent = `${progress}%`;
  document.querySelector(".progress-ring").style.setProperty("--progress", `${progress}%`);
  document.querySelector("#task-subtitle").textContent = `${tasks.length} tâche${tasks.length > 1 ? "s" : ""} enregistrée${tasks.length > 1 ? "s" : ""}`;
  const sorted = [...openTasks].sort((a, b) => dateFromString(a.due) - dateFromString(b.due));
  document.querySelector("#upcoming-list").innerHTML = sorted.length ? sorted.slice(0, 4).map(taskMarkup).join("") : emptyMarkup("Tout est à jour !", "Profite de ce temps pour souffler.");
  const filtered = tasks.filter(task => currentFilter === "all" || (currentFilter === "done" ? task.done : !task.done)).sort((a, b) => dateFromString(a.due) - dateFromString(b.due));
  document.querySelector("#all-tasks-list").innerHTML = filtered.length ? filtered.map(taskMarkup).join("") : emptyMarkup("Aucune tâche ici", "Ajoute un travail pour commencer.");
  renderCourses();
  renderSettings();
  renderTimetable();
  renderWeekChart();
}
function renderSettings() {
  document.querySelector("#first-name").value = settings.firstName;
  document.querySelector("#last-name").value = settings.lastName;
  document.querySelector("#dark-mode").checked = settings.darkMode;
  document.querySelector("#saved-calendar-url").value = localStorage.getItem(CALENDAR_URL_KEY) || "";
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
  const date = new Date(today); date.setHours(0, 0, 0, 0);
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
  return { notion: "Notion", pdf: "PDF", gemini: "Gemini Notebook", other: "Autre" }[type] || "Lien";
}
function resourceIcon(type) {
  return { notion: "N", pdf: "PDF", gemini: "*", other: "↗" }[type] || "->";
}
function previewMarkup(resource) {
  if (resource.type === "pdf") {
    return `<div class="resource-preview"><iframe src="${escapeHtml(resource.url)}" title="Prévisualisation PDF : ${escapeHtml(resource.title)}"></iframe></div>`;
  }
  if (resource.type === "notion" || resource.type === "gemini") {
    return `<div class="resource-preview"><iframe src="${escapeHtml(resource.url)}" title="Prévisualisation : ${escapeHtml(resource.title)}" loading="lazy"></iframe></div>`;
  }
  return "";
}
function resourceMarkup(resource) {
  return `<article class="resource-card">
    <div class="resource-card-header"><span class="resource-icon ${escapeHtml(resource.type)}">${resourceIcon(resource.type)}</span><div class="resource-heading"><h3>${escapeHtml(resource.title)}</h3><span>${resourceTypeName(resource.type)}</span></div><button class="delete-button" data-resource-delete="${resource.id}" aria-label="Supprimer ${escapeHtml(resource.title)}">×</button></div>
    ${previewMarkup(resource)}
    <a class="resource-link" href="${escapeHtml(resource.url)}" target="_blank" rel="noopener noreferrer">Ouvrir la ressource <span>↗</span></a>
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
  const start = new Date(today); start.setDate(today.getDate() - (today.getDay() || 7) + 1);
  const counts = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(start); date.setDate(start.getDate() + index);
    return tasks.filter(task => !task.done && task.due === localDateKey(date)).length;
  });
  const max = Math.max(...counts, 1);
  chart.innerHTML = counts.map((count, index) => {
    const date = new Date(start); date.setDate(start.getDate() + index);
    const isToday = localDateKey(date) === localDateKey(today);
    return `<div class="bar-wrap"><span class="bar-count">${count || ""}</span><div class="bar ${isToday ? "today" : ""}" style="height:${Math.max(count / max * 82, 4)}px"></div><span class="bar-label">${dayNames[date.getDay()]}</span></div>`;
  }).join("");
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
  if (!nav || nav.querySelector('a[href="courses.html"]')) return;
  const link = document.createElement("a");
  link.className = "nav-item";
  link.href = "courses.html";
  link.innerHTML = '<span class="icon">▣</span> Mes cours';
  nav.insertBefore(link, nav.querySelector('a[href="timetable.html"]'));
}
document.addEventListener("click", event => {
  const nav = event.target.closest(".nav-item");
  if (nav && nav.dataset.view) {
    event.preventDefault();
    showView(nav.dataset.view);
  }
  const action = event.target.closest("[data-action]");
  if (action) {
    const id = Number(action.dataset.id);
    if (action.dataset.action === "toggle") {
      tasks = tasks.map(task => task.id === id ? { ...task, done: !task.done } : task);
      const changed = tasks.find(task => task.id === id);
      if (changed) apiRequest(`/api/tasks/${id}`, "PUT", changed).catch(error => console.error("Mise à jour de la tâche impossible.", error));
      showToast("Progression mise à jour");
    }
    const resourceDelete = event.target.closest("[data-resource-delete]");
    if (resourceDelete) {
      const resourceId = Number(resourceDelete.dataset.resourceDelete);
      courseResources = courseResources.filter(resource => resource.id !== resourceId);
      saveCourseResources();
      renderCoursesPage();
      showToast("Ressource supprimée");
    }
    if (action.dataset.action === "delete") {
      tasks = tasks.filter(task => task.id !== id);
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
  const task = { id: Date.now(), title: document.querySelector("#task-title").value.trim(), course: document.querySelector("#task-course").value.trim(), due: document.querySelector("#task-date").value, time: allDay ? "" : (document.querySelector("#task-time")?.value || ""), allDay, priority: document.querySelector("#task-priority").value, done: false };
  tasks.push(task); saveTasks();
  apiRequest("/api/tasks", "POST", task).catch(error => console.error("Enregistrement de la tâche impossible.", error));
  event.target.reset(); render(); showToast("Tâche ajoutée à ton planning");
  const tasksSection = document.querySelector("#tasks");
  if (tasksSection) tasksSection.scrollIntoView({ behavior: "smooth" });
});
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
if (resourceForm) resourceForm.addEventListener("submit", event => {
  event.preventDefault();
  const title = document.querySelector("#resource-title").value.trim();
  const course = document.querySelector("#resource-course").value;
  const type = document.querySelector("#resource-type").value;
  const url = document.querySelector("#resource-url").value.trim();
  if (!title || !course || !url) return;
  try {
    const parsed = new URL(url);
    if (!["http:", "https:"].includes(parsed.protocol)) throw new Error("URL non sécurisée");
  } catch (error) {
    showToast("Ajoute une URL HTTPS valide");
    return;
  }
  courseResources.push({ id: Date.now(), title, course, type, url });
  saveCourseResources();
  event.target.reset();
  renderCoursesPage();
  showToast("Cours ajouté");
});
const calendarForm = document.querySelector("#calendar-form");
if (calendarForm) calendarForm.addEventListener("submit", async event => {
  event.preventDefault();
  const url = document.querySelector("#calendar-url").value.trim();
  const status = document.querySelector("#calendar-status");
  status.className = "calendar-status";
  status.textContent = "Import des matières en cours…";
  try {
    const response = await fetchCalendar(url);
    if (!response.ok) throw new Error(`Le lien a répondu avec le statut ${response.status}.`);
    const icsText = await response.text();
    const importedCourses = extractCoursesFromIcs(icsText);
    timetableEvents = extractEventsFromIcs(icsText);
    if (!importedCourses.length) throw new Error("Aucune matière lisible n'a été trouvée dans ce calendrier.");
    courses = importedCourses;
    saveCourses();
    localStorage.setItem(CALENDAR_URL_KEY, url);
    render();
    status.className = "calendar-status success";
    status.textContent = `${courses.length} matière${courses.length > 1 ? "s" : ""} importée${courses.length > 1 ? "s" : ""}. Tu peux maintenant les rechercher dans le champ Matière.`;
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
  const url = localStorage.getItem(CALENDAR_URL_KEY);
  const status = document.querySelector("#timetable-status");
  if (!url) {
    if (status) {
      status.className = "calendar-status error";
      status.textContent = "Aucun lien iCalendar enregistré. Ajoute-le dans Paramètres.";
    }
    return;
  }
  try {
    const response = await fetchCalendar(url);
    if (!response.ok) throw new Error(`Statut ${response.status}`);
    const text = await response.text();
    timetableEvents = extractEventsFromIcs(text);
    courses = extractCoursesFromIcs(text);
    saveCourses();
    renderTimetable();
    status.className = "calendar-status success";
    status.textContent = `Synchronisé à ${new Date().toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}.`;
  } catch (error) {
    console.error("Synchronisation impossible.", error);
    status.className = "calendar-status error";
    status.textContent = manual ? "Synchronisation impossible. Vérifie le lien ou sa disponibilité." : "Dernière synchronisation impossible.";
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
function parseIcsDate(value) {
  const clean = value.replace("Z", "");
  return new Date(`${clean.slice(0, 4)}-${clean.slice(4, 6)}-${clean.slice(6, 8)}T${clean.slice(9, 11)}:${clean.slice(11, 13)}:${clean.slice(13, 15)}${value.endsWith("Z") ? "Z" : ""}`).toISOString();
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
if (taskDate) taskDate.min = localDateKey(today);
ensureCoursesNav();
render();
syncInitialState();
if (!document.body.dataset.page) showView("dashboard");
if (document.body.dataset.page === "timetable") {
  syncCalendar();
  setInterval(syncCalendar, 15 * 60 * 1000);
}
