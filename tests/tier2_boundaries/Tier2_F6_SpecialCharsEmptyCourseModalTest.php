<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F6_SpecialCharsEmptyCourseModalTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F6'; }
    public function getDescription(): string { return 'Verify course resources modal edge cases: special character escaping, empty resources fallback state, and backdrop dismissal'; }

    public function testCourseNameEscapingInModalTitleAndAttributes(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify escapeHtml is used for course names in modal title or attributes
        Assert::assertMatchesRegex('/escapeHtml\(courseName\)/i', $appJs,
            "Expected courseName in openCourseResourcesModal to be sanitized via escapeHtml");
    }

    public function testEmptyResourcesStateHandled(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify empty state display when matching.length === 0
        Assert::assertMatchesRegex('/(?:Aucune ressource|empty-state)/i', $appJs,
            "Expected openCourseResourcesModal to provide an empty-state message when no resources exist for the course");
    }

    public function testBackdropClickDismissalHandled(): void {
        $appJs = $this->getFileContent('app.js');

        // Check modal click outside closes dialog
        Assert::assertMatchesRegex('/event\.target\s*===\s*modal\s*\)\s*modal\.close\(\)/i', $appJs,
            "Expected modal backdrop click listener to call modal.close()");
    }
}
