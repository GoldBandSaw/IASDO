# Handoff Report — Survey of Timetable Module & Interactions (R3 & R4)

**Agent**: `teamwork_preview_explorer`  
**Folder**: `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_2\`  
**Target Milestone**: Survey / Investigation of Requirements R3 & R4  
**Date**: 2026-09-25  

---

## 1. Observation

Direct observations from codebase inspection:

1. **Missing `position: relative` on `.tt-day-col`**:
   - File: `c:\Users\anton\Projets\IASDO\shared-pages.css`, lines 20-27:
     ```css
     20: .tt-days-row{display:flex;flex:1;position:relative;min-height:700px;padding-top:44px}
     21: .tt-day-col{flex:1;min-width:80px;display:flex;flex-direction:column;border-right:1px solid #e4eaf3}
     ...
     24: .tt-day-header{display:flex;justify-content:space-between;align-items:center;padding:8px 8px 6px;background:#fff;border-bottom:2px solid #e4eaf3;position:absolute;top:0;left:0;right:0;height:44px;box-sizing:border-box;z-index:3}
     ...
     27: .tt-allday-strip{padding:3px 5px;border-bottom:1px solid #e4eaf3;background:#fafbff;display:flex;flex-direction:column;gap:3px;position:absolute;top:44px;left:0;right:0;z-index:2}
     ```
   - Note: `.tt-day-col` has `position: static` (unspecified). Both `.tt-day-header` and `.tt-allday-strip` use `position: absolute; left: 0; right: 0;`.
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 247-292:
     ```javascript
     const days = Array.from({ length: 7 }, (_, index) => {
       ...
       return `<div class="tt-day-col${isToday ? " tt-today" : ""}">
         <div class="tt-day-header">...</div>
         ${allDayHtml ? `<div class="tt-allday-strip">${allDayHtml}</div>` : ""}
         <div class="tt-events-area">${timedHtml || '<p class="no-course">Aucun cours</p>'}</div>
       </div>`;
     });
     ```
   - Note: Sunday is `index = 6` (last element in the loop). Any all-day event or Sunday's header renders last and paints across the entire row width.

2. **Ruler line horizontal overflow**:
   - File: `c:\Users\anton\Projets\IASDO\shared-pages.css`, line 15 & 17:
     ```css
     15: .timetable-page-grid{display:flex;gap:0;overflow-x:auto;border:1px solid #e4eaf3;border-radius:12px;background:#fff}
     ...
     17: .tt-hour-line{position:absolute;left:0;right:-100vw;display:flex;align-items:flex-start;pointer-events:none;border-top:1px solid #e8edf5}
     ```
   - Note: `.tt-hour-line` has `right: -100vw`, extending 100vw out of its container inside `.timetable-page-grid` (`overflow-x: auto`), generating a permanent horizontal scrollbar.
   - Line 30 already contains:
     ```css
     .tt-events-area::before{content:"";position:absolute;inset:0;background:repeating-linear-gradient(to bottom,transparent,transparent calc(100% / 14 - 1px),#e8edf5 calc(100% / 14));pointer-events:none;z-index:0}
     ```
     which draws the horizontal grid lines across the events area without overflowing.

3. **Time ruler padding-top clipping and 44px offset**:
   - File: `c:\Users\anton\Projets\IASDO\shared-pages.css`, lines 16 & 19:
     ```css
     16: .tt-time-ruler{flex:0 0 40px;position:relative;min-height:700px;padding-top:44px;border-right:1px solid #e4eaf3;background:#f8faff}
     ...
     19: .tt-hour-line span{font-size:9px;color:var(--muted,#9ba7b8);line-height:1;padding:0 3px;white-space:nowrap;transform:translateY(-50%);background:#f8faff}
     ```
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 242-245:
     ```javascript
     for (let h = GRID_START_H; h <= GRID_END_H; h++) {
       const top = pct((h - GRID_START_H) * 60);
       rulerHours.push(`<div class="tt-hour-line" style="top:${top}%"><span>${String(h).padStart(2,"0")}h</span></div>`);
     }
     ```
   - Note: For `h = 6`, `pct(0) = 0%`. Inside `.tt-time-ruler`, `position: absolute; top: 0%` evaluates to `y = 0px` (padding edge). With `transform: translateY(-50%)`, half the label is positioned at negative y and clipped by `.timetable-page-grid` (`overflow-x: auto; border-radius: 12px`).
   - In `.tt-events-area`, 06:00 events start below the 44px header (`y = 44px`). The hour labels are 44px out of alignment with the actual event placement.

4. **Task card data attributes and ID**:
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 251-258:
     ```javascript
     const dayTasks  = tasks.filter(task => !task.done && task.due === key).map(task => ({
       start: task.allDay || !task.time ? `${key}T00:00:00` : `${key}T${task.time}:00`,
       end:   task.allDay || !task.time ? null : `${key}T${task.time}:00`,
       subject: task.title,
       location: task.course,
       isTask: true,
       allDay: task.allDay || !task.time
     }));
     ```
   - Note: `id: task.id` is omitted from `dayTasks`.
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 271-281: Neither timed task cards nor allDay cards output `data-action="edit-task"` or `data-id`.
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 455-461 & 498-537: `openTaskEditor(task)` already exists and handles pre-filling `<dialog id="task-editor">` and saving via `PUT /api/tasks/${id}`.

5. **Course card click interaction and resources modal**:
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 262-281: Course cards have no click handler or attributes.
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 112-122: Resources page queries `/api/resources?limit=50`.
   - File: `c:\Users\anton\Projets\IASDO\app.js`, lines 327-339: `resourceMarkup(resource)` formats individual resources.
   - File: `c:\Users\anton\Projets\IASDO\timetable.php`, lines 8-11: `courses.css` is not linked.

---

## 2. Logic Chain

1. **Sunday / Event horizontal stretching**:
   - From (1), `.tt-day-col` is static.
   - Therefore, child `.tt-allday-strip` and `.tt-day-header` resolve `position: absolute; left: 0; right: 0;` relative to `.tt-days-row`.
   - Since Sunday is evaluated last in the 7-day array, Sunday's block renders over all previous columns from left to right, covering the entire width of the schedule.
   - Placing `.tt-day-col` into `position: relative` (or keeping en-têtes and allday strips in normal flex flow) confines all events strictly within their respective 1-day column.

2. **Horizontal scrollbar**:
   - From (2), `.tt-hour-line` specifies `right: -100vw`.
   - In any scrollable container (`overflow-x: auto`), an element extending beyond the right padding edge expands the scrollable width by that amount.
   - Replacing `right: -100vw` with `right: 0` inside `.tt-time-ruler` and setting `.timetable-page-grid { overflow-x: hidden }` eliminates horizontal overflow completely.

3. **06h / 07h clipping and misalignment**:
   - From (3), `.tt-time-ruler` has `padding-top: 44px`. Percentage-based absolute positioning evaluates `top: 0%` at `y = 0px` (the padding edge, not the content edge).
   - This puts `06h` at `y = 0px`, where `translateY(-50%)` cuts off the top half of the text.
   - Events in `.tt-events-area` start at `y = 44px`, creating a 44px vertical offset between ruler ticks and event blocks.
   - Separating `.tt-time-ruler` into a 44px header (`.tt-ruler-header`) and an event-aligned track (`.tt-ruler-track`) places `06h` at `y = 44px` (safe from clipping) and aligns all hour ticks with event blocks.

4. **Task modal interaction**:
   - From (4), `openTaskEditor(task)` and the `[data-action="edit-task"]` click listener already exist.
   - By retaining `id: task.id` in `dayTasks` and attaching `data-action="edit-task" data-id="${event.id}"` to the rendered `<article>`, clicking any task in the schedule immediately opens the pre-filled edit modal.

5. **Course modal interaction**:
   - From (5), course cards need `data-action="view-course-resources" data-course="${event.subject}"`.
   - A dedicated `openCourseResourcesModal(courseName)` function can lazily instantiate `<dialog id="course-resources-modal">`, fetch `/api/resources?limit=50` (reusing the Resources page query), filter by course, render via `resourceMarkup(resource)`, and display the modal.
   - Linking `courses.css` in `timetable.php` provides styling for the resource cards.

---

## 3. Caveats

1. **Shared Tasks / Edit Rights**: In `app.js` (line 144), shared tasks not owned by the current user cannot be edited on the task list. If a shared task from another user appears in the schedule, `openTaskEditor` will still allow modifying the local view or attempt `PUT /api/tasks/${id}` (which returns 404/403 on the server if not owner). The implementer may consider checking ownership or displaying read-only details if non-owned tasks are shared.
2. **Mobile Viewport (max-width: 650px)**: On screens below 650px, displaying 7 proportional columns side-by-side without horizontal scrolling is physically constrained. `modern.css` (line 386) enables `overflow-x: auto` on mobile. The fix should ensure desktop (desktop/tablets > 650px) has no scroll (`overflow-x: hidden`), while retaining mobile responsiveness.
3. No other caveats.

---

## 4. Conclusion

All reported issues for Requirements R3 and R4 have clear, isolated root causes and deterministic, low-risk fixes:
- **R3**: Add `position: relative; min-width: 0;` to `.tt-day-col` (or CSS Grid on `.tt-days-row`), remove `right: -100vw` from `.tt-hour-line`, structure `.tt-time-ruler` with a 44px header spacer to align hour labels with events, and set `overflow-x: hidden` on `.timetable-page-grid`.
- **R4**: Preserve `id: task.id` in `dayTasks`, attach `data-action="edit-task"` to task cards, attach `data-action="view-course-resources"` to course cards, implement `openCourseResourcesModal(courseName)` querying `/api/resources?limit=50`, and link `courses.css` in `timetable.php`.

Detailed code changes, exact line numbers, and implementation snippets are documented in `c:\Users\anton\Projets\IASDO\.agents\teamwork\explorer_survey_2\report.md`.

---

## 5. Verification Method

To independently verify after implementation:

1. **Verify Grid confinement and absence of horizontal scroll (R3)**:
   - Run the application: `php -S localhost:8000 router.php` (or node proxy server).
   - Log in and navigate to `http://localhost:8000/timetable.php`.
   - Inspect the `.timetable-page-grid` element in DevTools:
     - Check `scrollWidth === clientWidth` (no horizontal scrollbar visible).
     - Check that all 7 day columns (Monday to Sunday) have equal width.
     - Check that events on Sunday do not stretch across Monday–Saturday.
2. **Verify Hour Labels 06h and 07h (R3)**:
   - Check the time ruler at the top left of the timetable grid:
     - "06h" label is completely visible, not clipped at the top border.
     - "07h" label is positioned at hour 7, well below the 44px day header line.
     - An event starting at 08:00 has its top border perfectly aligned with the "08h" tick line.
3. **Verify Task Click Interaction (R4)**:
   - Click on any task card (purple border) in the timetable grid.
   - Verify that `<dialog id="task-editor">` opens as a modal.
   - Verify that the Title, Course, Due Date, and Priority fields are pre-filled with the task's existing data.
4. **Verify Course Click Interaction (R4)**:
   - Click on any course card in the timetable grid.
   - Verify that `<dialog id="course-resources-modal">` opens as a modal.
   - Verify that associated resources (links/files) for that course are rendered using `resourceMarkup`.
   - Verify that network tab records `GET /api/resources?limit=50`.
