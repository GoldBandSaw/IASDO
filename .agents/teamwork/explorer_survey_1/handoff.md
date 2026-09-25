# Handoff Report: Architecture & Global UI Survey (Requirement R1)

**Type**: Hard Handoff  
**Author**: teamwork_preview_explorer (explorer_survey_1)  
**Recipient**: Parent Orchestrator (79ccaab4-e4d4-488e-9e38-63a5017a9974)  
**Date**: 2026-09-25  
**Working Directory**: `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_1\`

---

## 1. Observation

1. **Stack & Framework**:
   - `package.json:1-13`: Only contains `"dependencies": { "dotenv": "^16.4.7", "pg": "^8.13.1" }` and `"scripts": { "start": "node proxy-server.js" }`.
   - `proxy-server.js:1-12`: Contains `⚠️ DEPRECATED — This file is no longer used in production. The application now runs on PHP 8.3 + Apache (see Dockerfile)`.
   - `Dockerfile:1-22`: `FROM php:8.3-apache`, installs `libpq-dev`, `libcurl4-openssl-dev`, `pdo_pgsql`, `curl`.
   - Local PHP binary: `C:\xampp\php\php.exe` (PHP 8.2.12).
   - Dev server script: `start-campusflow-php.cmd:5` runs `php -S localhost:8000 router.php`.
   - There are no React, Next.js, Vue, or Vite components in the project.

2. **DOM Layout & Single Sidebar**:
   - `partials/student-sidebar.php:22-49`:
     ```html
     <aside class="sidebar" id="student-sidebar">
       <a class="brand" href="index.php" ...>...</a>
       <p class="sidebar-label">Espace étudiant</p>
       <nav aria-label="Navigation principale">...</nav>
       <a class="account-card" href="settings.php">...</a>
       <button class="sidebar-logout" type="button" data-logout>Se déconnecter</button>
       <div class="sidebar-tip">...</div>
       <div class="sidebar-footer">Espace privé de la promotion.</div>
     </aside>
     ```
   - All student pages (`index.php:16-18`, `tasks.php:14-16`, `courses.php:14-16`, `timetable.php:13-15`, `settings.php:13-15`, `add.php:14-16`, `chat.php:16-18`) render:
     ```html
     <div class="app-shell">
         <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
         <main class="page-content">...
     ```
   - Grep for `<aside` in codebase (`c:\Users\anton\Projets\IASDO`):
     - `admin.php:21`: `<aside class="sidebar">`
     - `partials/student-sidebar.php:22`: `<aside class="sidebar" id="student-sidebar">`
     There are zero other sidebars or right-side `aside` elements in the codebase.

3. **CSS File Link Inconsistencies**:
   - `index.php:10-13`, `tasks.php:8-11`, `add.php:8-11`: Load `page-shell.css`, `styles.css`, `shared-pages.css`, `modern.css?v=7`.
   - `courses.php:8-11`, `timetable.php:8-10`, `settings.php:8-10`: Load `page-shell.css`, `shared-pages.css`, `modern.css?v=7` — **`styles.css` is completely missing**.
   - `modern.css` is loaded last on every page.

4. **Sidebar CSS Rules**:
   - `page-shell.css:3`:
     ```css
     .app-shell{display:flex;min-height:100vh}.sidebar{width:248px;background:#fff;border-right:1px solid var(--line);padding:32px 20px 24px;display:flex;flex-direction:column;box-shadow:8px 0 30px #1b2b4b08}
     ```
   - `styles.css:4`:
     ```css
     .sidebar{box-shadow:8px 0 30px #1b2b4b08;position:sticky;top:0;height:100vh}
     ```
     (Does NOT have `overflow-y: auto`).
   - `modern.css:58-63`:
     ```css
     .sidebar{
       background:linear-gradient(190deg,var(--cover) 0%,var(--cover-2) 100%);
       border-right:0;
       box-shadow:0 0 0 1px var(--cover-line),18px 0 40px -28px rgba(10,14,32,.6);
       padding:30px 18px;
     }
     ```
     (Does NOT specify `position`, `top`, or `height` for student pages; only line 328 specifies `.admin-shell .sidebar{position:sticky;top:0;height:100vh}`).
   - `modern.css:365-368` (Mobile breakpoint):
     ```css
     @media(max-width:650px){
       .sidebar{position:fixed;z-index:35;inset:0 auto 0 0;width:min(82vw,290px);height:100vh;transform:translateX(-105%);transition:transform .25s ease;overflow-y:auto;box-shadow:18px 0 40px rgba(10,14,32,.25)}
       body.menu-open .sidebar{transform:translateX(0)}
     ...
     ```

---

## 2. Logic Chain

1. **Premise 1 (Layout Architecture)**: The application uses a horizontal flex container `.app-shell` containing `.sidebar` and `.page-content` (Observation 2).
2. **Premise 2 (Cascade & Inconsistency)**: `modern.css` is the final cascade override for all pages. However, `modern.css` omitted desktop sticky and height rules for student `.sidebar`. Furthermore, pages like `courses.php` and `timetable.php` omit `styles.css`.
3. **Inference 1 (Disappearing Sidebar)**: Because `styles.css` is absent on `courses.php`, `timetable.php`, and `settings.php`, `.sidebar` on those pages has default `position: static`. Scrolling down through course cards or calendar events causes the sidebar to scroll out of view and disappear.
4. **Inference 2 (Cut-off Sidebar on Small Displays)**: Where `styles.css` is present (`index.php`, `tasks.php`), `.sidebar` has `height: 100vh` without `overflow-y: auto`. On laptops or displays with vertical space < ~720px, the account card, logout button, tip card, and footer are clipped and unscrollable.
5. **Inference 3 ("Barre latérale droite" Intent)**: In Observation 2, only one sidebar exists (`<aside class="sidebar">`). The user requirement specifies "Fixer la barre latérale droite... sticky ou fixed sur desktop, avec son propre scroll si nécessaire, pour rester accessible après un défilement vers le bas dans le contenu principal." The verb "Fixer" confirms the primary objective is viewport anchoring and independent scrolling, while "droite" is either an informal slip for the navigation aside or indicates that the layout may be placed on the right.
6. **Inference 4 (Fix Location & Scope)**: Placing the desktop sticky, fixed height, and `overflow-y: auto` rules inside `.sidebar` in `modern.css` guarantees universal coverage across all pages while preserving the mobile off-canvas drawer (`@media (max-width: 650px)`).

---

## 3. Caveats

1. **"Droite" (Right) Placement Interpretation**:
   - Primary interpretation: Standard navigation sidebar is on the left; user meant fixing the existing sidebar so it stays visible during downward scroll.
   - Secondary interpretation: If the user literally demands that the sidebar be positioned on the right edge of the screen on desktop, this can be achieved with `flex-direction: row-reverse` on `.app-shell` or `order: 2` on `.sidebar` for `@media (min-width: 651px)`. Both code options are documented in `report.md`.
2. **No automated UI test framework**: There is no Cypress/Playwright setup in this repository. Verification must be performed via visual checks in a browser using the local PHP dev server.
3. **Cache Busting**: When updating `modern.css`, query string versions in `.php` files (e.g. `modern.css?v=7` -> `modern.css?v=8`) should be bumped to avoid browser caching issues.

---

## 4. Conclusion

Requirement R1 should be implemented by updating `modern.css` at line 58:
```css
.sidebar {
  background: linear-gradient(190deg, var(--cover) 0%, var(--cover-2) 100%);
  border-right: 0;
  box-shadow: 0 0 0 1px var(--cover-line), 18px 0 40px -28px rgba(10,14,32,.6);
  padding: 30px 18px;
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

.sidebar::-webkit-scrollbar { width: 5px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
.sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.28); }

@media (min-width: 651px) and (max-width: 900px) {
  .sidebar {
    flex: 0 0 205px;
    width: 205px;
  }
}
```
This cleanly fulfills all R1 criteria across all pages without breaking existing mobile responsive behavior (`@media (max-width: 650px)`).

---

## 5. Verification Method

1. **Start Dev Server**:
   ```powershell
   & "C:\xampp\php\php.exe" -S localhost:8000 router.php
   ```
2. **Visual Desktop Inspection**:
   - Open `http://localhost:8000/courses.php` and `http://localhost:8000/index.php`.
   - Scroll down the main content: verify `.sidebar` remains visible and pinned to the top of the viewport.
   - Resize viewport height to 500px: verify that hovering over `.sidebar` and scrolling allows reaching the account card, logout button, and footer.
3. **Responsive Verification**:
   - Resize viewport width to <= 650px (mobile mode):
   - Verify hamburger icon (`.mobile-menu-toggle`) appears.
   - Verify clicking hamburger slides out `.sidebar` drawer.
   - Verify clicking backdrop closes drawer.
4. **Invalidation Conditions**:
   - If `.sidebar` moves out of the viewport on downward scroll on any page -> verification failed.
   - If mobile hamburger menu fails to open/close or layout overflows horizontally -> verification failed.
