# Survey Report: Project Architecture & Global UI (Requirement R1)

**Agent**: teamwork_preview_explorer (explorer_survey_1)  
**Date**: 2026-09-25  
**Working Directory**: `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_1\`  
**Target Milestone**: Survey & Decomposition / M1 UI Sticky Sidebar (R1)

---

## Executive Summary

CampusFlow is a server-rendered **PHP 8.2 / 8.3** application backed by PostgreSQL (Supabase) and styled with modular vanilla CSS stylesheets (`page-shell.css`, `styles.css`, `shared-pages.css`, `modern.css`). It is **not** a React, Next.js, or Vite SPA. There is no frontend bundling step (no Webpack/Tailwind CLI/Vite).

Regarding **Requirement R1 (UI Globale : Fixer la barre latérale droite)**:
1. **Sidebar Inventory**: There is **only one** sidebar element across the entire project: `<aside class="sidebar" id="student-sidebar">` (included via `partials/student-sidebar.php` on student pages, and `<aside class="sidebar">` on `admin.php`).
2. **Left vs Right Terminology**: The sidebar is rendered on the left of `.page-content` in a horizontal flex layout (`.app-shell { display: flex; }`). The requirement title ("Fixer la barre latérale droite") is a common terminology slip for the primary navigation sidebar (or refers to making navigation accessible alongside the main content). We document both standard left-sticky behavior and the configuration if right-side positioning is literally requested.
3. **Root Cause of R1 Defect**:
   - `modern.css` (loaded last on every page) defines `.sidebar` styles (colors, brand, paddings) but **omits** `position: sticky`, `top: 0`, and `height: 100vh` for student pages. (It was only added for `.admin-shell .sidebar`).
   - Several key pages (`courses.php`, `timetable.php`, `settings.php`, `propose-course.php`) **do not load `styles.css`** at all. On these pages, `.sidebar` has default `position: static` and scrolls off the screen immediately when scrolling down.
   - On pages that do load `styles.css` (`index.php`, `tasks.php`, `add.php`, `chat.php`), `.sidebar` has `position: sticky; top: 0; height: 100vh`, but **lacks `overflow-y: auto`**. If the viewport height is shorter than the sidebar's content (~700px), bottom elements (account card, logout button, tip box) become inaccessible.
4. **Resolution Plan**: Add `position: sticky; top: 0; height: 100vh; max-height: 100vh; overflow-y: auto; flex: 0 0 248px;` directly into `.sidebar` in `modern.css`. This fixes all pages globally without breaking the existing mobile off-canvas drawer (`@media (max-width: 650px)`).

---

## 1. Project Architecture & Tooling

### 1.1 Technology Stack

| Layer | Technology | Details |
|---|---|---|
| **Backend Runtime** | PHP 8.2.12 / PHP 8.3 | Server-rendered pages (`.php`), PDO PostgreSQL extension (`pdo_pgsql`), cURL. |
| **Local PHP Binary** | `C:\xampp\php\php.exe` | PHP 8.2.12 CLI installed locally. |
| **Database** | PostgreSQL / Supabase | Connected via `DATABASE_URL` PDO connection string with SSL. |
| **Frontend Framework** | Vanilla HTML / ES6+ JS / Vanilla CSS | No React, Vue, Svelte, Next.js, or Vite. |
| **Client Scripts** | `app.js`, `auth.js`, `setup.js` | Direct script tags with version query strings (e.g. `app.js?v=6`). |
| **Stylesheets** | `page-shell.css`, `styles.css`, `shared-pages.css`, `modern.css?v=7`, `courses.css`, `chat.css` | Native CSS, CSS custom properties, responsive media queries. |
| **Container & Cloud** | Docker (`php:8.3-apache`) & Render | `Dockerfile`, `apache-vhost.conf`, `render.yaml`. |

### 1.2 Package Manager & Deprecated Files
- `package.json` contains:
  ```json
  {
    "name": "campusflow",
    "version": "1.0.0",
    "private": true,
    "scripts": {
      "start": "node proxy-server.js"
    },
    "dependencies": {
      "dotenv": "^16.4.7",
      "pg": "^8.13.1"
    }
  }
  ```
- **Important**: `proxy-server.js` is explicitly marked as **DEPRECATED** (header lines 1-12: *"⚠️ DEPRECATED — This file is no longer used in production. The application now runs on PHP 8.3 + Apache"*). Running `npm start` / `proxy-server.js` must be avoided.

### 1.3 Build, Dev, and Test Commands

| Action | Command | Purpose / Notes |
|---|---|---|
| **Local Dev Server** | `& "C:\xampp\php\php.exe" -S localhost:8000 router.php` | Runs PHP built-in web server with `router.php`. Equivalent to `start-campusflow-php.cmd`. |
| **Static Script** | `start-campusflow-php.cmd` | Batch launcher opening browser at `localhost:8000` and starting router. |
| **Lint / Syntax Check** | `& "C:\xampp\php\php.exe" -l <filename>.php` | Verifies PHP syntax for any modified file. |
| **Automated Tests** | None currently present | No PHPUnit or Jest suite. Verification relies on dev server visual/functional testing and HTTP API curl tests. |

---

## 2. Global Layout & Component Architecture

### 2.1 Component Structure

All authenticated student pages follow this DOM structure:

```html
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — ...</title>
    <!-- Stylesheets loaded here -->
</head>
<body data-page="<page_id>">
    <div class="app-shell">
        <!-- 1. Left/Global Navigation Sidebar -->
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>

        <!-- 2. Main Content Area -->
        <main class="page-content">
            <!-- Page-specific sections, page-card, grids -->
        </main>
    </div>
    <script src="/app.js?v=6"></script>
</body>
</html>
```

### 2.2 Files Responsible for Layout

1. **`partials/student-sidebar.php`**:
   - Contains `<button class="mobile-menu-toggle">` (mobile hamburger button).
   - Contains `<div class="sidebar-backdrop" data-sidebar-close>` (mobile drawer backdrop).
   - Contains `<aside class="sidebar" id="student-sidebar">`:
     - `.brand`: Logo and brand name.
     - `.sidebar-label`: "Espace étudiant".
     - `<nav>`: 7 navigation links (Vue d'ensemble, Mes tâches, Ajouter une tâche, Ressources, Chat promo, Emploi du temps, Paramètres).
     - `.account-card`: Current user avatar and name, links to `settings.php`.
     - `.sidebar-logout`: Logout action button.
     - `.sidebar-tip`: Helpful hint card.
     - `.sidebar-footer`: "Espace privé de la promotion."
   - Script for mobile menu toggle and logout click handling.

2. **`partials/student-bootstrap.php`**:
   - Verifies session authentication via `auth.php` and `currentUser()`. Redirects unauthenticated users to `/login.php`.

3. **Page Templates**:
   - `index.php` (`data-page="dashboard"`)
   - `tasks.php` (`data-page="tasks"`)
   - `courses.php` (`data-page="courses"`)
   - `timetable.php` (`data-page="timetable"`)
   - `chat.php` (`data-page="chat"`)
   - `settings.php` (`data-page="settings"`)
   - `add.php` (`data-page="add"`)
   - `propose-course.php` (`data-page="proposal"`)
   - `admin.php` (`data-page="admin"`)

---

## 3. CSS Cascade & Stylesheet Audit

### 3.1 Stylesheet Inclusions Across Pages

| Page | `page-shell.css` | `styles.css` | `shared-pages.css` | `modern.css` | Page-specific CSS |
|---|:---:|:---:|:---:|:---:|---|
| `index.php` | ✅ | ✅ | ✅ | ✅ `modern.css?v=7` | — |
| `tasks.php` | ✅ | ✅ | ✅ | ✅ `modern.css?v=7` | — |
| `add.php` | ✅ | ✅ | ✅ | ✅ `modern.css?v=7` | — |
| `chat.php` | ✅ | ✅ | ❌ | ✅ `modern.css?v=7` | `chat.css` |
| `courses.php` | ✅ | ❌ **MISSING** | ✅ | ✅ `modern.css?v=7` | `courses.css` |
| `timetable.php` | ✅ | ❌ **MISSING** | ✅ | ✅ `modern.css?v=7` | — |
| `settings.php` | ✅ | ❌ **MISSING** | ✅ | ✅ `modern.css?v=7` | — |
| `propose-course.php` | ✅ | ❌ **MISSING** | ✅ | ✅ `modern.css` | — |
| `admin.php` | ✅ | ❌ **MISSING** | ✅ | ✅ `modern.css` | — |

### 3.2 Existing CSS Rules Impacting Layout

1. **`page-shell.css`**:
   ```css
   .app-shell { display: flex; min-height: 100vh; }
   .sidebar { width: 248px; background: #fff; border-right: 1px solid var(--line); padding: 32px 20px 24px; display: flex; flex-direction: column; box-shadow: 8px 0 30px #1b2b4b08; }
   ```

2. **`styles.css`**:
   ```css
   .sidebar { width: 248px; background: #fff; border-right: 1px solid var(--line); padding: 32px 20px 24px; display: flex; flex-direction: column; }
   .sidebar { box-shadow: 8px 0 30px #1b2b4b08; position: sticky; top: 0; height: 100vh; }
   @media(max-width: 900px) { .sidebar { width: 205px; } }
   ```

3. **`modern.css` (lines 58-63 & 328 & 365 & 405)**:
   ```css
   /* Line 58 */
   .sidebar {
     background: linear-gradient(190deg, var(--cover) 0%, var(--cover-2) 100%);
     border-right: 0;
     box-shadow: 0 0 0 1px var(--cover-line), 18px 0 40px -28px rgba(10,14,32,.6);
     padding: 30px 18px;
   }
   /* Line 328 (Only for admin) */
   .admin-shell .sidebar { position: sticky; top: 0; height: 100vh; }

   /* Line 359 (Mobile <= 650px) */
   @media(max-width: 650px) {
     .sidebar {
       position: fixed;
       z-index: 35;
       inset: 0 auto 0 0;
       width: min(82vw, 290px);
       height: 100vh;
       transform: translateX(-105%);
       transition: transform .25s ease;
       overflow-y: auto;
       box-shadow: 18px 0 40px rgba(10,14,32,.25);
     }
     body.menu-open .sidebar { transform: translateX(0); }
   }
   ```

---

## 4. Requirement R1 In-Depth Analysis

### 4.1 Clarification: "Barre latérale droite" vs Left Sidebar
Requirement text:
> *"R1. UI Globale : Fixer la barre latérale droite. La barre latérale droite doit être 'sticky' ou 'fixed' sur desktop, avec son propre scroll si nécessaire, pour rester accessible après un défilement vers le bas dans le contenu principal."*

Analysis of the layout:
1. **Single Sidebar Reality**: There is **no right sidebar** in CampusFlow. The only sidebar is the main navigation `<aside class="sidebar">`.
2. **Intent**:
   - The user/author refers to fixing the navigation sidebar so that when a user scrolls down a long page of tasks, courses, or timetable events, the sidebar remains pinned to the viewport and accessible.
   - The phrase "Fixer la barre latérale droite" in French commonly arises when the author either:
     - Confused left and right ("gauche" vs "droite"), or
     - Colloquially thought of the secondary side panel alongside the main content, or
     - Intended the sidebar to sit on the right side of the screen.
3. **Behavioral Scope**:
   - Making `.sidebar` sticky with its own scroll (`overflow-y: auto`) on desktop is universally needed across all pages.
   - If right-side positioning is also required by the stakeholder, it can be toggled via `flex-direction: row-reverse` on `.app-shell` on desktop without altering the DOM.

### 4.2 Why the Sidebar Currently Breaks on Desktop
1. **`courses.php`, `timetable.php`, `settings.php`**:
   Because `styles.css` is not loaded on these pages, `.sidebar` has default `position: static`. When scrolling down in `courses.php` (which can list dozens of items) or `timetable.php`, the sidebar scrolls off the screen and disappears.
2. **`index.php`, `tasks.php`, `add.php`**:
   `styles.css` declares `position: sticky; top: 0; height: 100vh`, but does **not** declare `overflow-y: auto`. On screens with height < ~720px, the account card, logout button, tip card, and footer are cut off with no way to scroll to them.
3. **No `flex-shrink: 0`**:
   Without `flex-shrink: 0`, if `.page-content` contains wide grids or tables, the sidebar can shrink narrower than its designed width.

---

## 5. Concrete Implementation Recommendations

### 5.1 Primary Recommended Implementation (in `modern.css`)

Since `modern.css` is loaded last on **all** pages (`modern.css?v=7`), adding the sticky and independent scroll rules directly to `.sidebar` in `modern.css` fixes every page universally and cleanly.

#### Code Change in `modern.css` (around line 58):

```css
/* ---------- Sidebar (the cover) ---------- */
.sidebar {
  background: linear-gradient(190deg, var(--cover) 0%, var(--cover-2) 100%);
  border-right: 0;
  box-shadow: 0 0 0 1px var(--cover-line), 18px 0 40px -28px rgba(10,14,32,.6);
  padding: 30px 18px;
  /* R1: Sticky on desktop with independent scroll */
  position: sticky;
  top: 0;
  height: 100vh;
  max-height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
  flex: 0 0 248px;
  width: 248px;
  box-sizing: border-box;
}

/* Custom discreet scrollbar for sidebar */
.sidebar::-webkit-scrollbar {
  width: 5px;
}
.sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.15);
  border-radius: 4px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.28);
}
```

### 5.2 Responsive Media Query Safeguard

Between 650px and 900px, `styles.css` reduces `.sidebar` width to 205px. To unify this in `modern.css`:
```css
@media (min-width: 651px) and (max-width: 900px) {
  .sidebar {
    flex: 0 0 205px;
    width: 205px;
  }
}
```

On mobile (`@media (max-width: 650px)`):
Lines 365-372 of `modern.css` already configure:
```css
.sidebar {
  position: fixed;
  z-index: 35;
  inset: 0 auto 0 0;
  width: min(82vw, 290px);
  height: 100vh;
  transform: translateX(-105%);
  transition: transform .25s ease;
  overflow-y: auto;
  box-shadow: 18px 0 40px rgba(10,14,32,.25);
}
body.menu-open .sidebar { transform: translateX(0); }
```
Because the mobile rules are in `@media (max-width: 650px)` located later in `modern.css`, mobile drawer functionality will remain completely intact.

### 5.3 If Right-Side Placement Is Literally Required
If the user/stakeholder confirms they want the sidebar physically moved to the right side of the screen on desktop:
```css
@media (min-width: 651px) {
  .app-shell {
    flex-direction: row-reverse;
  }
  .sidebar {
    border-left: 1px solid var(--cover-line);
    border-right: 0;
  }
}
```
This requires zero DOM restructuring and keeps mobile off-canvas behavior unchanged.

---

## 6. Actionable Implementation Checklist for M1 Worker

- [ ] Edit `modern.css`: Add `position: sticky; top: 0; height: 100vh; max-height: 100vh; overflow-y: auto; overflow-x: hidden; flex: 0 0 248px; width: 248px; box-sizing: border-box;` to `.sidebar`.
- [ ] Add discreet webkit scrollbar styling for `.sidebar`.
- [ ] Verify desktop scroll on `index.php`, `tasks.php`, `courses.php`, `timetable.php`, `settings.php`.
- [ ] Verify mobile drawer behavior on viewports <= 650px.
- [ ] Increment cache-busting query parameter `modern.css?v=8` across all referencing `.php` files to ensure browsers load updated styles immediately.
