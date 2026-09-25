<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F6_CourseModalTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F6'; }
    public function getDescription(): string { return 'Verify timetable course card modal integration, interactive attributes, modal function in app.js and stylesheet link in timetable.php'; }

    public function testCourseCardGeneratesActionAttributes(): void {
        $appJs = $this->getFileContent('app.js');

        // Course events should have data-action="view-course-resources"
        Assert::assertMatchesRegex('/data-action=["\']view-course-resources["\']/i', $appJs,
            "Expected timetable course events in app.js to output data-action='view-course-resources'");

        // Course events should attach data-course
        Assert::assertMatchesRegex('/data-course=/i', $appJs,
            "Expected timetable course events in app.js to bind data-course attribute");
    }

    public function testOpenCourseResourcesModalFunctionExists(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify openCourseResourcesModal function is defined
        Assert::assertMatchesRegex('/(?:function\s+openCourseResourcesModal|const\s+openCourseResourcesModal\s*=)/i', $appJs,
            "Expected openCourseResourcesModal function to be defined in app.js");

        // Verify it targets course-resources-modal dialog
        Assert::assertMatchesRegex('/course-resources-modal/i', $appJs,
            "Expected openCourseResourcesModal to reference #course-resources-modal");
    }

    public function testTimetablePhpIncludesCoursesCss(): void {
        $timetablePhp = $this->getFileContent('timetable.php');

        // Verify timetable.php links courses.css for modal resource styling
        Assert::assertContains('courses.css', $timetablePhp,
            "Expected timetable.php to include courses.css so modal resource cards are properly styled");
    }
}
