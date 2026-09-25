<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F7_TaskModalTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F7'; }
    public function getDescription(): string { return 'Verify timetable task card click opens Edit Task modal, preserves task ID in dayTasks, and binds data-action="edit-task"'; }

    public function testDayTasksPreservesTaskId(): void {
        $appJs = $this->getFileContent('app.js');

        // Check that dayTasks in renderWeekGrid includes id: task.id
        Assert::assertMatchesRegex('/dayTasks\s*=\s*tasks\.filter\(.*?\)\.map\(task\s*=>\s*\(?\{[^}]*id\s*:\s*task\.id/s', $appJs,
            "Expected dayTasks mapping in renderWeekGrid (app.js) to preserve 'id: task.id'");
    }

    public function testTaskEventsOutputEditTaskActionAndId(): void {
        $appJs = $this->getFileContent('app.js');

        // Check that task articles in renderWeekGrid include data-action="edit-task"
        Assert::assertMatchesRegex('/data-action=["\']edit-task["\']/i', $appJs,
            "Expected task articles in timetable to output data-action='edit-task'");

        // Check that task articles bind data-id
        Assert::assertMatchesRegex('/data-id=/i', $appJs,
            "Expected task articles in timetable to output data-id");
    }

    public function testEditTaskActionDelegatesToOpenTaskEditor(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify click listener delegates edit-task action to openTaskEditor
        Assert::assertMatchesRegex('/action\.dataset\.action\s*===\s*["\']edit-task["\'][^}]*openTaskEditor/s', $appJs,
            "Expected click handler in app.js to invoke openTaskEditor when data-action is edit-task");
    }
}
