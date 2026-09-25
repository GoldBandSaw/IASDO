<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier3;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier3_TimetableTaskToEditModalInteractionTest extends TestCase {
    public function getTier(): int { return 3; }
    public function getFeature(): string { return 'F7-API'; }
    public function getDescription(): string { return 'Verify cross-feature contract: clicking timetable task item opens modal and submitting updates backend via PUT /api/tasks/{id}'; }

    public function testTimetableTaskPassesIdToEditor(): void {
        $appJs = $this->getFileContent('app.js');

        // Look for click delegation: tasks.find(task => String(task.id) === id) -> openTaskEditor
        Assert::assertMatchesRegex('/openTaskEditor\(\s*tasks\.find\(/i', $appJs,
            "Expected timetable click handler to look up task by id and pass to openTaskEditor");
    }

    public function testTaskEditorSubmitsPutRequest(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify openTaskEditor form submission sends PUT /api/tasks/${id}
        Assert::assertMatchesRegex('/apiRequest\([`\'"]\/api\/tasks\/\$\{task\.id\}[`\'"],\s*["\']PUT["\']/i', $appJs,
            "Expected openTaskEditor in app.js to send PUT /api/tasks/{id} upon form submission");
    }

    public function testTaskEditorRefreshesTimetableOnSave(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify openTaskEditor calls render() or renderTimetable() after successful update
        Assert::assertMatchesRegex('/openTaskEditor.*?(?:render\(\)|renderTimetable\(\))/s', $appJs,
            "Expected openTaskEditor to re-render view after saving task modifications");
    }
}
