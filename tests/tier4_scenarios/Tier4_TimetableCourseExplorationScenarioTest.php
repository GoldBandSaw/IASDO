<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier4;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier4_TimetableCourseExplorationScenarioTest extends TestCase {
    public function getTier(): int { return 4; }
    public function getFeature(): string { return 'E2E-TIMETABLE-COURSE'; }
    public function getDescription(): string { return 'End-to-end scenario: Student navigates to timetable, inspects schedule without horizontal overflow, and clicks course to open resources modal'; }

    public function testTimetableMarkupAndStylingDependencies(): void {
        $timetablePhp = $this->getFileContent('timetable.php');

        // Check required elements and stylesheets
        Assert::assertContains('id="timetable-grid"', $timetablePhp, "Timetable page must contain #timetable-grid");
        Assert::assertContains('courses.css', $timetablePhp, "Timetable page must link courses.css for course modal styling");
        Assert::assertContains('student-sidebar.php', $timetablePhp, "Timetable page must include sidebar");
    }

    public function testCourseInteractionPipelineIntegrity(): void {
        $appJs = $this->getFileContent('app.js');

        // Course card generation in renderWeekGrid -> action binding -> modal function
        Assert::assertMatchesRegex('/view-course-resources/i', $appJs,
            "Expected timetable renderer to bind view-course-resources action to course cards");
        Assert::assertMatchesRegex('/openCourseResourcesModal/i', $appJs,
            "Expected openCourseResourcesModal to handle course card click");
        Assert::assertMatchesRegex('/course-resources-modal/i', $appJs,
            "Expected modal dialog #course-resources-modal to be managed");
    }

    public function testGridVisualDefectFreeDeclarations(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // Verify absence of right: -100vw and presence of relative day col
        Assert::assertNotContains('right: -100vw', $combined, "Grid must not contain right: -100vw");
        Assert::assertMatchesRegex('/\.tt-day-col\s*\{[^}]*position\s*:\s*relative/i', $combined,
            "Day columns must be position: relative");
    }
}
