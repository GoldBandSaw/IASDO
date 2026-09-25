# Investigation Report: Resources & Upload Modules (Requirements R2 and R5)

**Date**: 2026-09-25  
**Investigator**: `teamwork_preview_explorer` (Explorer Survey 3)  
**Target Project**: CampusFlow (`c:\Users\anton\Projets\IASDO`)  
**Scope**: Requirements R2 (Recent Resources Performance & Query Harmonization) and R5 (Multi-File Resource Upload up to 10 Files)

---

## 1. Executive Summary

CampusFlow is a collaborative student platform built with **PHP 8.3 + Apache** on the backend and **vanilla ES6 JavaScript** on the frontend, using **Supabase PostgreSQL** (`pg` / `pdo_pgsql`) for structured data and **Supabase Storage** for binary file attachments.

This investigation analyzes two key requirements:
1. **R2 (Performances : Ressources récentes)**: Replace the current unoptimized recent resources query with the canonical "Ressources" page query (sharing the same filters and light joins), sorted in descending order by date, and bounded by a strict limit of 5 to 10 items.
2. **R5 (Ressources : Upload multiple de fichiers)**: Enable simultaneous selection of up to 10 files in the resource addition form. Files must be uploaded to cloud storage via controlled sequential or concurrency-bounded `Promise.all` requests to prevent HTTP timeouts and PHP payload size limits, while preserving the existing single-file upload workflow intact.

---

## 2. Architecture & File Inventory

The modules and files directly involved in Requirements R2 and R5 are:

| File Path | Role | Key Functions / Responsibilities |
|---|---|---|
| `courses.php` | Main Resources Page | Renders `#courses-list`, `#resource-form`, source selector (`link` vs `file`), and file input. |
| `index.php` | Dashboard Page | Renders `#recent-resources` container next to `#upcoming-list`. |
| `app.js` | Frontend Controller | `syncInitialState()`, `loadAllResources()`, `renderDashboardResources()`, `renderCoursesPage()`, `resourceForm` submit listener. |
| `api.php` | Backend API & Router | Endpoints: `GET /api/state`, `GET /api/resources`, `POST /api/resources`, `POST /api/resources/upload`, `GET /api/resources/{id}/download`, `DELETE /api/resources/{id}`. |
| `db.php` | Database & Storage Client | `database()` PDO singleton, `initializeSchema()`, `storageConfig()`, `storageRequest()`. |
| `courses.css` | Component Styling | Form layouts (`.resource-form`), cards (`.resource-card`), responsive grid. |
| `supabase/migrations/20260923111500_create_campusflow_schema.sql` | PostgreSQL Schema | Table `resources (id TEXT PRIMARY KEY, payload JSONB NOT NULL)`. |
| `supabase/migrations/20260924020000_create_resource_bucket.sql` | Storage Bucket Definition | Bucket `campusflow-resources` (private, 50MB per-file limit). |

---

## 3. Deep Dive: Requirement R2 (Recent Resources Performance & Query Harmonization)

### 3.1 Current State Analysis

#### A. How Recent Resources are Currently Loaded
1. On application load (Dashboard `index.php` or any page), `app.js` executes `syncInitialState()`:
   ```javascript
   // app.js lines 86-103
   const state = await apiRequest("/api/state", "GET");
   ...
   if (Array.isArray(state.resources)) {
     courseResources = state.resources;
     saveCourseResources();
   }
   render();
   ```
2. In `api.php` lines 152-156 (`GET /api/state`):
   ```php
   // Resources loaded separately via /api/resources for performance — only send recent 20 here
   $resStmt = $db->prepare("SELECT payload FROM resources ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT 20");
   $resStmt->execute();
   $resources = array_values(array_filter(array_map(fn($row) => json_decode($row['payload'], true), $resStmt->fetchAll(PDO::FETCH_ASSOC)), 'is_array'));
   respond(['tasks' => $tasks, 'settings' => $settings, 'courses' => $courses, 'resources' => $resources]);
   ```
3. In `app.js` lines 372-388 (`renderDashboardResources`):
   ```javascript
   function renderDashboardResources() {
     const list = document.querySelector("#recent-resources");
     if (!list) return;
     const recent = [...courseResources]
       .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
       .slice(0, 4);
     ...
   ```

#### B. How the Main "Ressources" Page is Currently Loaded
In `app.js` lines 107 & 112-120:
```javascript
if (document.body.dataset.page === "courses") loadAllResources();

async function loadAllResources() {
  try {
    const data = await apiRequest("/api/resources?limit=50", "GET");
    if (Array.isArray(data.resources)) {
      courseResources = data.resources;
      saveCourseResources();
      renderCoursesPage();
      renderDashboardResources();
    }
  } catch (error) { ... }
}
```
And in `api.php` lines 159-173 (`GET /api/resources`):
```php
// GET /api/resources — paginated resource listing
if ($path === 'api/resources' && $method === 'GET') {
    // Create index on first access if not exists (idempotent, fast if already exists)
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_created_at ON resources ((payload->>'created_at') DESC NULLS LAST)"); } catch (Throwable) {}
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 50)));
    $before = isset($_GET['before']) ? (string)$_GET['before'] : null;
    if ($before) {
        $stmt = $db->prepare("SELECT payload FROM resources WHERE payload->>'created_at' < ? ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT ?");
        $stmt->execute([$before, $limit]);
    } else {
        $stmt = $db->prepare("SELECT payload FROM resources ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT ?");
        $stmt->execute([$limit]);
    }
    $resources = array_values(array_filter(array_map(fn($row) => json_decode($row['payload'], true), $stmt->fetchAll(PDO::FETCH_ASSOC)), 'is_array'));
    respond(['resources' => $resources, 'total' => count($resources)]);
}
```

### 3.2 Inefficiencies & Gaps Identified

1. **Overfetching in `/api/state`**: The server fetches 20 full JSONB records from the database on every `/api/state` call, yet the dashboard UI only displays 4 items (`.slice(0, 4)`).
2. **Duplicated & Inconsistent Query Logic**:
   - `/api/state` uses inline `SELECT payload FROM resources ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT 20`.
   - `/api/resources` uses a separate query block with cursor pagination.
   - Neither query joins with the `users` table, so `resource.owner` remains the raw username (e.g., `'antonin'`), instead of showing the user's formatted `display_name` (e.g., `'Antonin Leblond'`).
3. **Repeated `CREATE INDEX IF NOT EXISTS` on Every Request**:
   - In `api.php` line 161, `CREATE INDEX IF NOT EXISTS` is called on *every* `GET /api/resources` request. In PostgreSQL, this requires an exclusive catalog lock check and introduces needless latency.
4. **Missing Filters for Requirement R4**:
   - `/api/resources` does not currently accept a `?course=` query parameter. Requirement R4 requires opening a modal listing resources for a specific course when clicking a timetable card ("réutiliser la requête de la page Ressources"). Adding `?course=` to `/api/resources` satisfies both R2 and R4.

### 3.3 Proposed Architecture for R2

1. **Unified Query Helper in `api.php`**:
   Extract resource querying into a clean, parameterized helper function:
   ```php
   function fetchResources(PDO $db, array $options = []): array {
       $limit = min(50, max(1, (int)($options['limit'] ?? 50)));
       $course = !empty($options['course']) ? trim((string)$options['course']) : null;
       $before = !empty($options['before']) ? trim((string)$options['before']) : null;

       $where = [];
       $params = [];

       if ($course !== null) {
           $where[] = "r.payload->>'course' = ?";
           $params[] = $course;
       }
       if ($before !== null) {
           $where[] = "r.payload->>'created_at' < ?";
           $params[] = $before;
       }

       $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

       // Light join with users to enrich resource with display_name
       $sql = "SELECT r.id, r.payload, u.display_name AS owner_display_name
               FROM resources r
               LEFT JOIN users u ON u.username = (r.payload->>'owner')
               $whereClause
               ORDER BY (r.payload->>'created_at') DESC NULLS LAST
               LIMIT ?";
       $params[] = $limit;

       $stmt = $db->prepare($sql);
       $stmt->execute($params);
       $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

       return array_values(array_filter(array_map(function ($row) {
           $data = json_decode((string)$row['payload'], true);
           if (!is_array($data)) return null;
           if (!empty($row['owner_display_name'])) {
               $data['owner_name'] = $row['owner_display_name'];
           }
           return $data;
       }, $rows), 'is_array'));
   }
   ```

2. **Harmonized Use Across Endpoints**:
   - In `GET /api/state`:
     ```php
     // Only send recent 5-10 resources using the unified helper
     $resources = fetchResources($db, ['limit' => 6]);
     ```
   - In `GET /api/resources`:
     ```php
     $limit = min(50, max(1, (int)($_GET['limit'] ?? 50)));
     $course = $_GET['course'] ?? null;
     $before = $_GET['before'] ?? null;
     $resources = fetchResources($db, ['limit' => $limit, 'course' => $course, 'before' => $before]);
     respond(['resources' => $resources, 'total' => count($resources)]);
     ```

3. **Performance Optimization in `db.php`**:
   Move index creation to `initializeSchema()` in `db.php`:
   ```php
   $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_created_at ON resources ((payload->>'created_at') DESC NULLS LAST)");
   $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_course ON resources ((payload->>'course'))");
   ```
   Remove line 161 in `api.php`.

4. **Frontend Updates in `app.js`**:
   - In `renderDashboardResources()`: Render up to 5 items (`.slice(0, 5)`).
   - Use `resource.owner_name || resource.owner` so student display names are shown.

---

## 4. Deep Dive: Requirement R5 (Multi-File Resource Upload)

### 4.1 Current Upload Flow Analysis

#### A. Frontend (`courses.php` & `app.js`)
- `courses.php` line 31:
  ```html
  <label class="resource-file-field" id="resource-file-field" style="display:none">
    Fichier
    <input id="resource-file" type="file" accept=".pdf,.md,.markdown,.txt,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp">
  </label>
  ```
- `app.js` lines 575-603:
  - Takes `const file = document.querySelector("#resource-file").files[0];`.
  - Builds `FormData` with single `file`.
  - POSTs to `/api/resources/upload`.
  - Unshifts result into `courseResources`, saves to local cache, re-renders, and displays toast.

#### B. Backend & Cloud Storage (`api.php` lines 250-324)
- Authenticates session user (`requireUser()`).
- Validates single `$_FILES['file']` (size <= 50MB, MIME / extension check).
- Generates unique ID: `$resourceId = bin2hex(random_bytes(12))`.
- Sanitizes file name and creates unique object path: `$objectPath = $username . '/' . $resourceId . '-' . $safeName`.
- Calls Supabase Storage API via cURL:
  ```php
  storageRequest('POST', '/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($objectPath)), $content, ['Content-Type: ' . $mime, 'x-upsert: false']);
  ```
- Inserts JSONB row into `resources` table.
- Responds with HTTP 201 and resource JSON payload.

### 4.2 Why Client-Side Controlled Concurrency / Sequential Processing is Mandatory

Requirement R5 specifies:
> *"Permettre l'upload simultané jusqu'à 10 fichiers dans le formulaire d'ajout de ressources. Le traitement vers le stockage cloud doit se faire fichier par fichier (ou Promise.all maîtrisée) pour éviter les timeouts, sans altérer l'upload unitaire existant."*

There are critical architectural reasons why batching 10 files in a single HTTP request to PHP would fail in production:

1. **PHP `post_max_size` Constraint**:
   - The default PHP `post_max_size` on Apache/Docker is typically 8MB to 32MB.
   - If a user uploads ten 10MB or 20MB files simultaneously in a single POST request (up to 200MB+), Apache/PHP silently drops `$_POST` and `$_FILES`, resulting in an empty request error (`UPLOAD_ERR_NO_FILE`).
2. **Reverse Proxy & Gateway Timeouts (Render / Cloudflare)**:
   - Hosting services enforce strict 30-second HTTP request timeouts.
   - Streaming 10 large files sequentially from a single PHP process to Supabase Storage can easily exceed 30 seconds, causing HTTP 504 Gateway Timeout.
3. **Preserving Single-File Upload Behavior**:
   - By retaining `/api/resources/upload` as the single-file handler, existing mobile/desktop behavior, API clients, and single uploads remain 100% untouched and backward compatible.
4. **Superior UX & Partial Failure Handling**:
   - If the 4th file out of 10 has an unsupported extension or network hiccup, files 1, 2, 3, 5, etc. are still saved successfully.
   - The user gets exact real-time progress feedback (`Envoi en cours (2/5)…`).

### 4.3 Proposed Implementation for R5

#### A. Form UI in `courses.php`
Add `multiple` attribute, update label, and update explanatory hint:
```html
<label class="resource-file-field" id="resource-file-field" style="display:none">
  Fichier(s) (jusqu'à 10)
  <input id="resource-file" type="file" multiple accept=".pdf,.md,.markdown,.txt,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp">
</label>
```

#### B. Client-side Controller in `app.js`
Replace the single-file submit handler with a controlled sequential/pool loop:

```javascript
if (resourceForm) resourceForm.addEventListener("submit", async event => {
  event.preventDefault();
  const course = document.querySelector("#resource-course").value;
  const url = document.querySelector("#resource-url").value.trim();
  const fileInput = document.querySelector("#resource-file");
  const files = Array.from(fileInput.files);
  if (!course) return;

  if (resourceSource?.value === "file") {
    if (!files.length) { showToast("Choisis au moins un fichier"); return; }
    if (files.length > 10) { showToast("10 fichiers maximum à la fois"); return; }

    const MAX_FILE_SIZE = 50 * 1024 * 1024;
    for (const f of files) {
      if (f.size > MAX_FILE_SIZE) {
        showToast(`Le fichier "${f.name}" dépasse la limite de 50 Mo`);
        return;
      }
    }

    const submitBtn = resourceForm.querySelector("button[type=submit]");
    const origLabel = submitBtn?.textContent;
    if (submitBtn) submitBtn.disabled = true;

    const uploaded = [];
    const errors = [];

    // Sequential processing to avoid timeouts and rate limits
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      if (submitBtn) {
        submitBtn.textContent = files.length > 1
          ? `Envoi (${i + 1}/${files.length})…`
          : "Envoi en cours…";
      }

      const form = new FormData();
      form.append("course", course);
      form.append("file", file);

      try {
        const response = await fetch("/api/resources/upload", { method: "POST", body: form });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || `Erreur sur ${file.name}`);
        uploaded.push(data);
        courseResources.unshift(data);
      } catch (err) {
        errors.push(`${file.name}: ${err.message}`);
      }
    }

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = origLabel;
    }

    if (uploaded.length > 0) {
      saveCourseResources();
      event.target.reset();
      applyResourceSourceMode();
      renderCoursesPage();
      renderDashboardResources();

      if (errors.length === 0) {
        showToast(files.length > 1 ? `${uploaded.length} fichiers partagés ✓` : "Fichier partagé ✓");
      } else {
        showToast(`${uploaded.length} fichier(s) partagé(s), ${errors.length} échec(s)`);
      }
    } else if (errors.length > 0) {
      showToast(`Échec : ${errors[0]}`);
    }

    return;
  }

  // URL sharing continues unchanged...
```

---

## 5. Database Schema & Storage Verification

### 5.1 Tables Schema Summary

```sql
-- resources: Contains metadata and storage pointer
CREATE TABLE IF NOT EXISTS public.resources (
  id TEXT PRIMARY KEY,
  payload JSONB NOT NULL
);

-- users: Contains student identities and display names
CREATE TABLE IF NOT EXISTS public.users (
  username TEXT PRIMARY KEY,
  display_name TEXT NOT NULL,
  password_hash TEXT NOT NULL DEFAULT '',
  setup_token_hash TEXT NOT NULL DEFAULT '',
  setup_used BOOLEAN NOT NULL DEFAULT FALSE,
  role TEXT NOT NULL DEFAULT 'student',
  profile_picture TEXT NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- courses: Canonical course names
CREATE TABLE IF NOT EXISTS public.courses (
  name TEXT PRIMARY KEY
);

-- resource_reports: Reports for admin moderation
CREATE TABLE IF NOT EXISTS public.resource_reports (
  id BIGSERIAL PRIMARY KEY,
  resource_id TEXT NOT NULL,
  reporter TEXT NOT NULL REFERENCES public.users(username) ON DELETE CASCADE,
  reason TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (resource_id, reporter)
);
```

### 5.2 Resource `payload` Structure

Each row in `resources` stores a JSON object in `payload`:
```json
{
  "id": "e4f8b901a23c4d5e",
  "title": "Chapitre 1 - Introduction aux Modèles Linéaires",
  "course": "Modélisation Statistique",
  "type": "pdf",
  "file_name": "CM1_Modeles_Lineaires.pdf",
  "file_path": "antonin/e4f8b901a23c4d5e-CM1_Modeles_Lineaires.pdf",
  "owner": "antonin",
  "created_at": "2026-09-25T11:15:00+02:00"
}
```
Enriched via join with `users` (as proposed in R2):
```json
{
  ...
  "owner_name": "Antonin Leblond"
}
```

### 5.3 Storage Bucket Configuration

- **Bucket**: `campusflow-resources` (defined in `supabase/migrations/20260924020000_create_resource_bucket.sql`)
- **Access**: Private (downloads served via signed URLs with 300s expiration via `/api/resources/{id}/download`).
- **File size limit**: 52,428,800 bytes (50 MB).
- **Supported file types**: PDF, Word (`.doc`, `.docx`), PowerPoint (`.ppt`, `.pptx`), Excel (`.xls`, `.xlsx`), Markdown (`.md`, `.markdown`, `.txt`), Images (`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`).

---

## 6. Synergies with Requirements R3 & R4

1. **R4 (Interactions Emploi du temps : Modales au clic)**:
   - R4 requires opening a modal listing all resources associated with a course when the course card is clicked in the timetable.
   - R4 specifically mentions: *"réutiliser la requête de la page Ressources"*.
   - By enhancing `fetchResources()` and `GET /api/resources?course=...`, R4 can directly fetch `/api/resources?course=${encodeURIComponent(courseName)}` to populate the modal with zero duplicate query logic.
2. **Dashboard UI Consistency**:
   - Unifying the card markup and user display name formatting across `#recent-resources` and `#courses-list` provides a coherent, polished user experience.

---

## 7. Actionable Recommendations for Implementation

1. **Step 1: DB Index Migration in `db.php`**
   - Add permanent indexes in `initializeSchema()`:
     ```php
     $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_created_at ON resources ((payload->>'created_at') DESC NULLS LAST)");
     $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_course ON resources ((payload->>'course'))");
     ```
2. **Step 2: Refactor Query Helper in `api.php`**
   - Implement `fetchResources()` in `api.php`.
   - Update `GET /api/state` to use `fetchResources($db, ['limit' => 6])`.
   - Update `GET /api/resources` to use `fetchResources($db, ['limit' => $limit, 'course' => $course, 'before' => $before])`.
   - Remove redundant dynamic `CREATE INDEX` on line 161.
3. **Step 3: HTML Multi-File Selection in `courses.php`**
   - Add `multiple` attribute to `<input id="resource-file" type="file" multiple ...>`.
   - Update form labels and hints to indicate up to 10 files.
4. **Step 4: Multi-File Upload Controller in `app.js`**
   - Implement sequential or controlled concurrency upload in `resourceForm` submit listener.
   - Enforce 10-file maximum check and individual 50MB check.
   - Display real-time progress `(i + 1)/(total)`.
   - Update `renderDashboardResources()` to display up to 5 items and use `owner_name`.

---
*Report completed and verified against project files.*
