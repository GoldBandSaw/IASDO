# Project: IASDO UI/UX, Performance & Feature Enhancements (R1 to R5)

## Architecture
- **Environment**: PHP 8.2+ / Apache CLI dev server (`php -S localhost:8000 router.php`), PostgreSQL (Supabase).
- **Frontend**: Vanilla PHP pages (`index.php`, `timetable.php`, `courses.php`, `tasks.php`, `settings.php`, `add.php`, `chat.php`), Vanilla JavaScript (`app.js`, `chat.js`), Modular CSS (`modern.css`, `page-shell.css`, `styles.css`, `timetable.css`, `courses.css`).
- **Core APIs**: `api.php`, `db.php`, `router.php`.
- **Dev Server**: `php -S localhost:8000 router.php` on port 8000.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | F1: Sticky Right Sidebar | Sidebar pinned to top with independent scroll (`height: 100vh; overflow-y: auto; position: sticky; top: 0;`), visible on scroll on desktop across all pages | M1 | ORIGINAL_REQUEST R1, survey_1 |
| 2 | F2: Optimized Recent Resources Query | Harmonized `fetchResources()` query with descending `created_at` sort, light join for `owner_name`, limit 5-10, index initialization in `db.php`, `?course=` filtering | M2 | ORIGINAL_REQUEST R2, survey_3 |
| 3 | F3: Timetable Column Confinement | `.tt-day-col` with `position: relative;` so headers and all-day/event strips remain strictly within their respective day column (no horizontal stretching across Sunday/all days) | M3 | ORIGINAL_REQUEST R3, survey_2 |
| 4 | F4: Timetable Horizontal Scroll Fix | Eliminate `right: -100vw;` on `.tt-hour-line`, ensuring timetable grid fits within fixed container without horizontal scroll | M3 | ORIGINAL_REQUEST R3, survey_2 |
| 5 | F5: Timetable 06h/07h Graduation Visibility | Adjust `.tt-time-ruler` top padding/alignment so 06h and 07h graduations are fully rendered without clipping | M3 | ORIGINAL_REQUEST R3, survey_2 |
| 6 | F6: Timetable Course Card Modal | Click on course card opens `#course-resources-modal` displaying associated resources (reusing `/api/resources?course=...`) | M4 | ORIGINAL_REQUEST R4, survey_2 |
| 7 | F7: Timetable Task Card Edit Modal | Click on task card opens pre-filled Edit Task modal (`openTaskEditor`) via `data-action="edit-task"` and task ID mapping | M4 | ORIGINAL_REQUEST R4, survey_2 |
| 8 | F8: Multi-File Resource Selection | File input `#resource-file` in `courses.php` accepts `multiple` (up to 10 files) with client validation | M5 | ORIGINAL_REQUEST R5, survey_3 |
| 9 | F9: Sequential/Batched Resource Upload | Client-side controlled upload in `app.js` sending files file-by-file to `/api/resources/upload` with progress indicators and error resilience | M5 | ORIGINAL_REQUEST R5, survey_3 |
| 10 | F10: E2E Verification & Hardening | 100% pass of E2E test suite (Tiers 1-4) on dev server and Tier 5 adversarial coverage hardening | M-FINAL | ORIGINAL_REQUEST Verification, Project Pattern |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M-TEST | E2E Testing Track | Design and implement automated & verification test suite (Tiers 1-4) -> TEST_READY.md | none | IN_PROGRESS |
| M1 | UI Globale : Sticky Sidebar | F1: Fix desktop sticky sidebar with internal scroll in `modern.css` | none | PLANNED |
| M2 | Performances : Ressources Récentes | F2: Unified `fetchResources` query, limit 5-10, `db.php` index, course filter in `api.php` | none | PLANNED |
| M3 | Emploi du temps : CSS Grid Fixes | F3, F4, F5: Column confinement, remove horizontal scroll, fix 06h/07h in `timetable.css` | none | PLANNED |
| M4 | Interactions Emploi du temps : Modales | F6, F7: Course resources modal dialog & Task edit modal integration in `timetable.php`, `app.js` | M2, M3 | PLANNED |
| M5 | Ressources : Multi-file Upload | F8, F9: Multiple file selection and sequential upload in `courses.php`, `app.js` | none | PLANNED |
| M-FINAL | Final Milestone | F10: Pass 100% E2E tests (Tiers 1-4) & Adversarial Coverage Hardening (Tier 5) | M-TEST, M1, M2, M3, M4, M5 | PLANNED |

## Interface Contracts
### M2 (API) ↔ M4 (Timetable Course Modal)
- Endpoint: `GET /api/resources?course={course_name}&limit=50`
- Response: JSON `{ "status": "ok", "resources": [ { "id": "...", "title": "...", "course": "...", "type": "...", "url": "...", "owner_name": "...", "created_at": "..." } ] }`

### M2 (API) ↔ Dashboard (Recent Resources)
- Endpoint: `GET /api/state` or `GET /api/resources?limit=6`
- Response includes `resources` array sorted by `created_at` DESC, limited to 5-10 items with `owner_name`.

### M4 (Timetable) ↔ Task Editor Modal
- Event: Click on `.timetable-item[data-action="edit-task"]` with `data-task-id="{id}"`
- Function: `openTaskEditor(taskObject)` with properties `{ id, title, course, deadline, priority, description, status }`.

### M5 (Multi-file Upload) ↔ Storage API
- Endpoint: `POST /api/resources/upload` (multipart/form-data with single `file` field per request)
- Client behavior: loops sequentially over selected files `1..N` (N <= 10).
- Progress indicator: updates button / alert text `Envoi de l'élément ${i+1}/${total}...`.

## Code Layout
- `modern.css`: Global styles, layout, `.sidebar`, dark mode, responsive overrides. (Owned by M1 for sidebar rules, M3 for timetable grid overrides).
- `timetable.css`: Timetable grid, `.tt-day-col`, `.tt-hour-line`, `.tt-time-ruler`, course/task cards. (Owned by M3 and M4).
- `api.php`: REST API endpoints (`/api/state`, `/api/resources`, `/api/resources/upload`). (Owned by M2).
- `db.php`: Database connection, table schemas, index initialization. (Owned by M2).
- `timetable.php`: Timetable page markup, course resources dialog modal. (Owned by M4).
- `courses.php`: Courses & resources page markup, resource upload form. (Owned by M5).
- `app.js`: Client-side state, modal controls, upload handlers, click listeners. (Owned by M4 for timetable modals, M5 for upload handling).
- `tests/` / `e2e/`: Test infrastructure and test runners. (Owned by E2E Testing Track).
