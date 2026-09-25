<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier4;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier4_TimetableTaskManagementScenarioTest extends TestCase {
    public function getTier(): int { return 4; }
    public function getFeature(): string { return 'E2E-TIMETABLE-TASK'; }
    public function getDescription(): string { return 'End-to-end scenario: Student inspects timetable task card (confined to day column), clicks to edit, and updates task via modal dialog'; }

    public function testTaskCardConfinedAndInteractive(): void {
        $appJs = $this->getFileContent('app.js');
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // Verify task cards generated in renderWeekGrid have edit-task action
        Assert::assertMatchesRegex('/data-action=["\']edit-task["\']/i', $appJs,
            "Expected timetable task cards to have data-action='edit-task'");

        // Verify day columns are position: relative so Sunday task cannot stretch across week
        Assert::assertMatchesRegex('/\.tt-day-col\s*\{[^}]*position\s*:\s*relative/i', $combined,
            "Expected .tt-day-col to have position: relative to confine Sunday tasks");
    }

    public function testTaskEditorDialogContract(): void {
        $appJs = $this->getFileContent('app.js');
        $modernCss = $this->getFileContent('modern.css');

        // Task editor dialog styling and JS existence
        Assert::assertContains('openTaskEditor', $appJs, "openTaskEditor function must exist in app.js");
        Assert::assertMatchesRegex('/(?:#task-editor|dialog\.task-editor|\.task-editor-form)/i', $modernCss,
            "Expected task editor dialog or form styles in modern.css");
    }

    public function testBackendTaskUpdateEndpointContract(): void {
        $apiPhp = $this->getFileContent('api.php');

        // Verify api.php handles PUT /api/tasks/{id}
        Assert::assertMatchesRegex('/api[\'"]\s*&&\s*\$parts\[1\]\s*===\s*[\'"]tasks[\'"]\s*&&\s*\$method\s*===\s*[\'"]PUT[\'"]/i', $apiPhp,
            "Expected api.php to implement PUT /api/tasks/{id} endpoint");
    }
}
