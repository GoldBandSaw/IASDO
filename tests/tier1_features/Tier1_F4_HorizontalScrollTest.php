<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F4_HorizontalScrollTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F4'; }
    public function getDescription(): string { return 'Verify timetable horizontal scroll fix (elimination of right: -100vw on .tt-hour-line and proper grid container overflow)'; }

    public function testHourLineDoesNotHaveNegative100vw(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combinedCss = $sharedCss . "\n" . $modernCss;

        // Ensure right: -100vw is completely removed from .tt-hour-line
        Assert::assertNotContains('right:-100vw', str_replace(' ', '', $combinedCss),
            "Expected 'right: -100vw' to be removed from CSS to avoid breaking timetable grid width");
        Assert::assertNotContains('right: -100vw', $combinedCss,
            "Expected 'right: -100vw' to be eliminated from .tt-hour-line");
    }

    public function testTimetableGridOverflowIsConstrained(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combinedCss = $sharedCss . "\n" . $modernCss;

        // Timetable page grid should avoid unconstrained overflow
        Assert::assertMatchesRegex('/\.timetable-page-grid\s*\{[^}]*(?:overflow(?:-x)?\s*:\s*(?:hidden|clip|auto))/i', $combinedCss,
            "Expected .timetable-page-grid to manage overflow properly");
    }
}
