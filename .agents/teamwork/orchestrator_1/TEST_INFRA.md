# Test Infrastructure Documentation (TEST_INFRA.md)

## 1. Overview & Architecture

The CampusFlow Test Infrastructure provides an automated, opaque-box, requirement-driven verification system for Requirements R1 through R5 (Features F1 through F9). It is built with zero external third-party dependencies, executing natively via PHP 8.2+ CLI and PowerShell on Windows.

### Directory Structure
```
tests/
├── harness/
│   ├── Assert.php             # Type-safe test assertions with contextual diffs
│   ├── DevServer.php          # PHP built-in server lifecycle and health checks
│   ├── DomInspector.php       # CSS parser, DOM/XPath loader, and layout inspector
│   ├── Env.php                # .env environment configuration loader
│   ├── HttpClient.php         # cURL HTTP client with session/cookies & JSON/multipart support
│   ├── TestCase.php           # Base TestCase class and TestResult model
│   ├── TestReporter.php       # Formatted color CLI reporter with tier metrics
│   └── bootstrap.php          # Autoloader and environment bootstrapper
├── tier1_features/            # Tier 1: Happy-path feature verification (F1 to F9)
│   ├── Tier1_F1_StickySidebarTest.php
│   ├── Tier1_F2_RecentResourcesTest.php
│   ├── Tier1_F3_ColumnConfinementTest.php
│   ├── Tier1_F4_HorizontalScrollTest.php
│   ├── Tier1_F5_GraduationVisibilityTest.php
│   ├── Tier1_F6_CourseModalTest.php
│   ├── Tier1_F7_TaskModalTest.php
│   ├── Tier1_F8_MultiFileSelectionTest.php
│   └── Tier1_F9_SequentialUploadTest.php
├── tier2_boundaries/          # Tier 2: Boundary, limits, and edge case verification
│   ├── Tier2_F1_SidebarResponsiveBoundariesTest.php
│   ├── Tier2_F2_QueryLimitsAndFiltersTest.php
│   ├── Tier2_F3_SundayAllDayConfinementTest.php
│   ├── Tier2_F4_HorizontalScrollLimitsTest.php
│   ├── Tier2_F5_GraduationMathProportionsTest.php
│   ├── Tier2_F6_SpecialCharsEmptyCourseModalTest.php
│   ├── Tier2_F7_AllDayTaskModalFieldsTest.php
│   ├── Tier2_F8_FileCountLimitsTest.php
│   └── Tier2_F9_UploadSizeAndErrorResilienceTest.php
├── tier3_interactions/        # Tier 3: Cross-feature contracts and data pipelines
│   ├── Tier3_CourseModalToResourceApiInteractionTest.php
│   ├── Tier3_SidebarTimetableLayoutInteractionTest.php
│   ├── Tier3_TimetableTaskToEditModalInteractionTest.php
│   └── Tier3_MultiUploadToDashboardInteractionTest.php
├── tier4_scenarios/           # Tier 4: Complete end-to-end user workflows
│   ├── Tier4_MultiResourceContributionScenarioTest.php
│   ├── Tier4_StudentDailyDashboardScenarioTest.php
│   ├── Tier4_TimetableCourseExplorationScenarioTest.php
│   └── Tier4_TimetableTaskManagementScenarioTest.php
├── run_all.php                # Master CLI test runner
└── run_tests.ps1              # Single-command PowerShell wrapper
```

---

## 2. 4-Tier Test Methodology

| Tier | Name | Focus | Test Count |
|---|---|---|:---:|
| **Tier 1** | Feature Coverage | Primary behavior and happy-path acceptance criteria for F1 through F9. | 25 |
| **Tier 2** | Boundary & Corner Cases | Off-by-one limits, responsive breakpoints, empty inputs, file counts (1, 10, 11), 50MB limits, Sunday boundary. | 24 |
| **Tier 3** | Cross-Feature Interactions | Modals ↔ REST API, client upload ↔ dashboard state, sticky sidebar ↔ timetable grid layout coexistence. | 12 |
| **Tier 4** | Real-World Application Scenarios | Complete multi-step student user journeys across navigation, editing, exploration, and contribution. | 12 |
| **Total** | | **Comprehensive Test Suite** | **73** |

---

## 3. Feature Mapping to Requirements

| Feature | Requirement | Description | Test Modules |
|---|---|---|---|
| **F1** | R1 (Sticky Sidebar) | Sticky positioning, 100vh height, independent scroll, desktop width | `Tier1_F1`, `Tier2_F1`, `Tier3_Sidebar`, `Tier4_Dashboard` |
| **F2** | R2 (Recent Resources) | Unified query, created_at DESC sort, light join for display name, ?course= filter | `Tier1_F2`, `Tier2_F2`, `Tier3_MultiUpload`, `Tier4_Dashboard` |
| **F3** | R3 (Column Confinement) | `.tt-day-col` with `position: relative`, Sunday confinement, 7-col grid | `Tier1_F3`, `Tier2_F3`, `Tier3_Sidebar`, `Tier4_TimetableTask` |
| **F4** | R3 (Horizontal Scroll) | Elimination of `right: -100vw`, fixed container without horizontal scrollbar | `Tier1_F4`, `Tier2_F4`, `Tier4_TimetableCourse` |
| **F5** | R3 (06h/07h Graduation) | Ruler alignment with 44px header, 06h not clipped, proportional math | `Tier1_F5`, `Tier2_F5` |
| **F6** | R4 (Course Card Modal) | Click course card opens `#course-resources-modal`, reuses `/api/resources` | `Tier1_F6`, `Tier2_F6`, `Tier3_CourseModal`, `Tier4_TimetableCourse` |
| **F7** | R4 (Task Edit Modal) | Click task card opens Edit Task modal, preserves task ID in dayTasks | `Tier1_F7`, `Tier2_F7`, `Tier3_TaskModal`, `Tier4_TimetableTask` |
| **F8** | R5 (Multi-File Selection) | `<input id="resource-file" multiple>`, guidance up to 10 files | `Tier1_F8`, `Tier2_F8`, `Tier4_MultiResource` |
| **F9** | R5 (Sequential Upload) | Sequential file-by-file upload to `/api/resources/upload`, progress feedback | `Tier1_F9`, `Tier2_F9`, `Tier3_MultiUpload`, `Tier4_MultiResource` |

---

## 4. Test Execution Commands

### Primary Execution (PowerShell)
```powershell
powershell -ExecutionPolicy Bypass -File tests\run_tests.ps1
```

### Direct PHP CLI Execution
```powershell
& "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php
```

### Selective Execution Options
- **Filter by Tier**:
  ```powershell
  & "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php --tier=1
  & "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php --tier=2
  ```
- **Filter by Feature**:
  ```powershell
  & "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php --feature=F1
  & "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php --feature=F2
  ```
- **With Dev Server Management**:
  ```powershell
  & "C:\xampp\php\php.exe" -d extension=pdo_pgsql tests\run_all.php --with-server
  ```

---

## 5. Harness Components

1. **`Assert` (`tests/harness/Assert.php`)**:
   Provides assertions: `assertTrue`, `assertFalse`, `assertEquals`, `assertSame`, `assertContains`, `assertNotContains`, `assertMatchesRegex`, `assertGreaterThanOrEqual`, `assertLessThanOrEqual`, `assertArrayHasKey`, `assertCount`, `assertHttpStatus`.
2. **`Env` (`tests/harness/Env.php`)**:
   Loads `.env` keys into PHP environment runtime (`DATABASE_URL`, `SUPABASE_URL`, etc.).
3. **`HttpClient` (`tests/harness/HttpClient.php`)**:
   Full HTTP client for API testing supporting cookies/session, JSON payloads, multipart uploads, and status inspection.
4. **`DevServer` (`tests/harness/DevServer.php`)**:
   Checks port 8000 health and can spawn/stop local PHP router server during automated runs.
5. **`DomInspector` (`tests/harness/DomInspector.php`)**:
   Parses modular CSS stylesheets and HTML/DOM trees.
6. **`TestCase` & `TestReporter` (`tests/harness/TestCase.php` & `TestReporter.php`)**:
   Automates test method discovery, timings, tier aggregations, and formatted terminal output.
