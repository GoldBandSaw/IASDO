<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F7_AllDayTaskModalFieldsTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F7'; }
    public function getDescription(): string { return 'Verify timetable task modal opens correctly for both timed and all-day tasks, and safely populates task editor fields'; }

    public function testAllDayTaskMarkupIncludesActionAndId(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify allDayHtml mapping also includes data-action="edit-task" and data-id
        Assert::assertMatchesRegex('/allDayHtml\s*=\s*allDay\.map\(.*?data-action=["\']edit-task["\']/s', $appJs,
            "Expected all-day task items in timetable to include data-action='edit-task'");
        Assert::assertMatchesRegex('/allDayHtml\s*=\s*allDay\.map\(.*?data-id=/s', $appJs,
            "Expected all-day task items in timetable to include data-id");
    }

    public function testOpenTaskEditorPopulatesAllFormInputs(): void {
        $appJs = $this->getFileContent('app.js');

        // Check that openTaskEditor populates title, course, date/due, and priority
        Assert::assertContains('edit-task-title', $appJs, "Expected openTaskEditor to populate #edit-task-title");
        Assert::assertContains('edit-task-course', $appJs, "Expected openTaskEditor to populate #edit-task-course");
        Assert::assertContains('edit-task-date', $appJs, "Expected openTaskEditor to populate #edit-task-date");
        Assert::assertContains('edit-task-priority', $appJs, "Expected openTaskEditor to populate #edit-task-priority");
    }
}
