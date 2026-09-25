# Handoff Report — Explorer Survey 3: Resources & Upload Modules (Requirements R2 and R5)

**Agent**: `teamwork_preview_explorer`  
**Working Directory**: `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_3\`  
**Parent Conversation ID**: `79ccaab4-e4d4-488e-9e38-63a5017a9974`  
**Handoff Type**: Hard (Task Complete)

---

## 1. Observation

### A. Requirement R2: Recent Resources Queries vs Main "Ressources" Page Query
1. **Initial State Query (`GET /api/state`) in `api.php` (lines 152-156)**:
   ```php
   // Resources loaded separately via /api/resources for performance — only send recent 20 here
   $resStmt = $db->prepare("SELECT payload FROM resources ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT 20");
   $resStmt->execute();
   $resources = array_values(array_filter(array_map(fn($row) => json_decode($row['payload'], true), $resStmt->fetchAll(PDO::FETCH_ASSOC)), 'is_array'));
   respond(['tasks' => $tasks, 'settings' => $settings, 'courses' => $courses, 'resources' => $resources]);
   ```
2. **Main "Ressources" Page Query (`GET /api/resources`) in `api.php` (lines 159-173)**:
   ```php
   if ($path === 'api/resources' && $method === 'GET') {
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
3. **Frontend Dashboard Consumption in `app.js` (lines 372-377)**:
   ```javascript
   function renderDashboardResources() {
     const list = document.querySelector("#recent-resources");
     if (!list) return;
     const recent = [...courseResources]
       .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
       .slice(0, 4);
   ```
   *Observation*: `/api/state` retrieves 20 resources from the database on every page load, but `renderDashboardResources()` only displays 4 resources (`.slice(0, 4)`). Furthermore, neither query performs a join with `users`, leaving `owner` as a raw identifier.

4. **Redundant Catalog Inspection in `api.php` line 161**:
   `try { $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_created_at ON resources ((payload->>'created_at') DESC NULLS LAST)"); } catch (Throwable) {}` is executed on every single `GET /api/resources` call instead of running once during schema setup in `db.php` (`initializeSchema()`).

### B. Requirement R5: Resource Upload Form and Storage Logic
1. **Frontend Form in `courses.php` line 31**:
   ```html
   <label class="resource-file-field" id="resource-file-field" style="display:none">Fichier<input id="resource-file" type="file" accept=".pdf,.md,.markdown,.txt,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp"></label>
   ```
   *Observation*: The `<input>` element currently lacks the `multiple` attribute, restricting the browser file picker to a single file.

2. **Frontend Submission Handler in `app.js` lines 575-593**:
   ```javascript
   const file = document.querySelector("#resource-file").files[0];
   ...
   const form = new FormData();
   form.append("course", course);
   form.append("file", file);
   try {
     const response = await fetch("/api/resources/upload", { method: "POST", body: form });
     const data = await response.json();
     if (!response.ok) throw new Error(data.error || "Upload impossible");
     courseResources.unshift(data);
   ```
   *Observation*: Only `files[0]` is read. An upload failure or progress is not looped or batched.

3. **Backend Upload Handler in `api.php` lines 250-324**:
   - Accepts single file via `$_FILES['file']` (`tmp_name`, `error`, `size`).
   - Validates size `<= 50 * 1024 * 1024` (50MB).
   - Generates unique ID: `$resourceId = bin2hex(random_bytes(12))`.
   - Generates safe object path: `$objectPath = $username . '/' . $resourceId . '-' . $safeName`.
   - Streams file to Supabase Storage: `storageRequest('POST', '/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($objectPath)), $content, ['Content-Type: ' . $mime, 'x-upsert: false'])`.
   - Persists JSONB row to PostgreSQL: `$db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb)')->execute([$resourceId, json_encode($resource)])`.
   - Responds with HTTP 201 and JSON of the created resource.

---

## 2. Logic Chain

1. **Step 1 (Performance & Alignment - R2)**:
   - Observation A.1 shows `/api/state` retrieves 20 resources, while Observation A.3 shows `app.js` only displays 4.
   - Reducing the limit in `/api/state` from 20 to 5 (or 6) directly reduces database query execution time, JSON serialization size, and network transmission overhead.
   - Observation A.2 shows that `GET /api/resources` is the canonical query for resources, but it is currently duplicated and separated from `/api/state`. Extracting a shared `fetchResources(PDO $db, array $options)` helper in `api.php` harmonizes both endpoints, ensures descending sorting by `payload->>'created_at'`, and applies light joins (`LEFT JOIN users u ON u.username = (r.payload->>'owner')`) to provide the author's `display_name`.
   - Furthermore, adding `$course` filtering to `fetchResources()` and `GET /api/resources?course=...` immediately satisfies Requirement R4 ("réutiliser la requête de la page Ressources" for timetable course click modals).
   - Moving index creation from `api.php:161` to `db.php:initializeSchema()` eliminates runtime DDL lock checks on every GET request.

2. **Step 2 (Multi-file Upload Architecture - R5)**:
   - Requirement R5 mandates allowing up to 10 files simultaneously while "avoiding timeouts" and "preserving existing unit upload behavior".
   - If a client were to bundle 10 files (up to 50MB each, potentially 500MB) in a single HTTP POST request, standard PHP configurations (`post_max_size`, default 8M-32M) would reject the payload at the web server/FastCGI level before PHP executes. Moreover, sequentially streaming 10 files from a single PHP process to Supabase Storage would exceed the 30-second gateway timeout on host platforms like Render.
   - Therefore, the client-side controller in `app.js` must iterate over the selected files (up to 10), uploading them file-by-file sequentially (or with a controlled concurrency limit of 2) to the existing `/api/resources/upload` endpoint.
   - This architecture completely preserves the existing `/api/resources/upload` endpoint, isolates errors per file (partial success resilience), provides exact real-time feedback (`Envoi (1/3)…`), and guarantees zero timeout risk.

---

## 3. Caveats

1. **Local PHP Binary**: The Windows environment does not currently have `php` in the system PATH, but Node v24.1.0 is available and production runs on Docker PHP 8.3 (`Dockerfile`). Any syntax changes to PHP files must be carefully validated against standard PHP 8.3 syntax.
2. **Supabase Storage Quota**: The bucket file size limit is 50MB per file (`supabase/migrations/20260924020000_create_resource_bucket.sql`). Client-side validation must reject any single file exceeding 50MB before initiating uploads.
3. **No Caveats on Implementation Feasibility**: Both R2 and R5 are well-scoped, have clean architectural solutions, and touch only `api.php`, `courses.php`, `app.js`, and `db.php`.

---

## 4. Conclusion

1. **R2 Solution**:
   - Refactor `api.php` to define `fetchResources(PDO $db, array $options)` with:
     - Sorting: `ORDER BY (r.payload->>'created_at') DESC NULLS LAST`
     - Light Join: `LEFT JOIN users u ON u.username = (r.payload->>'owner')` providing `owner_name`
     - Filters: `limit` (bounded 1-50), `course` (optional, for R4), `before` (cursor)
   - Update `GET /api/state` to use `fetchResources($db, ['limit' => 6])`.
   - Update `GET /api/resources` to use `fetchResources($db, ['limit' => $limit, 'course' => $course, 'before' => $before])`.
   - Move index creation to `initializeSchema()` in `db.php`.
   - Update `app.js:renderDashboardResources()` to display up to 5 resources with `resource.owner_name || resource.owner`.

2. **R5 Solution**:
   - Add `multiple` to `#resource-file` in `courses.php`.
   - In `app.js`, update the submit handler to:
     - Read `Array.from(fileInput.files)`.
     - Reject if `files.length === 0` or `files.length > 10`.
     - Reject if any file exceeds 50MB.
     - Upload file-by-file to `POST /api/resources/upload` with dynamic button progress (`Envoi (i+1/N)…`).
     - Collect results, update `courseResources`, re-render views, and display toast summary.
   - Keep `/api/resources/upload` backend endpoint intact.

---

## 5. Verification Method

1. **File Inspection**:
   - Inspect `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_3\report.md` for full implementation diffs and detailed technical analysis.
2. **Static Code Validation**:
   - Verify `courses.php` contains `<input id="resource-file" type="file" multiple ...>`.
   - Verify `app.js` contains the 10-file maximum check and the file-by-file upload loop.
   - Verify `api.php` contains the unified `fetchResources()` function with the light join and descending sorting.
3. **Functional Verification (Once Implemented)**:
   - Start local application (`npm start` or Docker container).
   - Navigate to `/index.php`: Confirm recent resources load quickly, showing max 5 items with author names.
   - Navigate to `/courses.php`: Switch source to "Fichier", select 3 to 10 valid files, click "Partager la ressource".
   - Confirm button shows progress `Envoi (1/3)…`, all files are created in Supabase Storage and `resources` table, and appear immediately in the course grid and dashboard.
   - Test invalidation condition: Select 11 files -> verify client toast blocks upload ("10 fichiers maximum à la fois").
   - Test invalidation condition: Select a file > 50MB -> verify client toast blocks upload.
