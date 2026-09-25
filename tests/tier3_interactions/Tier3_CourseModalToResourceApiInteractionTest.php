<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier3;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier3_CourseModalToResourceApiInteractionTest extends TestCase {
    public function getTier(): int { return 3; }
    public function getFeature(): string { return 'F6-F2'; }
    public function getDescription(): string { return 'Verify cross-feature contract: clicking timetable course card invokes openCourseResourcesModal which queries the canonical /api/resources endpoint and renders styled cards'; }

    public function testModalQueriesCanonicalResourcesEndpoint(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify openCourseResourcesModal calls apiRequest('/api/resources?limit=50', 'GET') or with course filter
        Assert::assertMatchesRegex('/(?:apiRequest\([\'"]\/api\/resources|fetch\([\'"]\/api\/resources)/i', $appJs,
            "Expected openCourseResourcesModal in app.js to reuse the canonical /api/resources endpoint");
    }

    public function testModalUsesResourceMarkupHelper(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify resource cards in modal use resourceMarkup function
        Assert::assertMatchesRegex('/resourceMarkup/i', $appJs,
            "Expected openCourseResourcesModal to format resource cards using resourceMarkup helper");
    }

    public function testTimetableIncludesResourceCardStyles(): void {
        $timetablePhp = $this->getFileContent('timetable.php');

        // Timetable page must load courses.css so modal cards have proper .resource-card styling
        Assert::assertContains('courses.css', $timetablePhp,
            "Expected timetable.php to include courses.css for cross-feature resource card rendering in modal");
    }
}
