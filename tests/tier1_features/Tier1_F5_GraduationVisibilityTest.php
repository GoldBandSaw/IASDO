<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F5_GraduationVisibilityTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F5'; }
    public function getDescription(): string { return 'Verify timetable 06h and 07h graduation visibility and vertical alignment with event slots'; }

    public function testRulerLayoutStructureAlignsWithDayHeader(): void {
        $appJs = $this->getFileContent('app.js');
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $appJs . "\n" . $sharedCss . "\n" . $modernCss;

        // Verify ruler accounts for the day header offset (either via .tt-ruler-header, .tt-ruler-track, or aligned padding)
        $hasRulerHeader = str_contains($appJs, 'tt-ruler-header') || str_contains($combined, 'tt-ruler-track');
        $hasAlignedPadding = preg_match('/\.tt-time-ruler\s*\{[^}]*padding-top\s*:\s*44px/i', $combined);

        Assert::assertTrue($hasRulerHeader || (bool)$hasAlignedPadding,
            "Expected timetable time ruler to have a header offset structure matching day headers (44px)");
    }

    public function testGraduationRangeIncludes06hAnd07h(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify GRID_START_H is 6 (06:00)
        Assert::assertMatchesRegex('/GRID_START_H\s*=\s*6/i', $appJs,
            "Expected timetable grid to start at 06h (GRID_START_H = 6)");

        // Verify rulerHours generates 06h and 07h strings
        Assert::assertMatchesRegex('/padStart\(2,\s*["\']0["\']\)\s*\+\s*["\']h["\']/i', $appJs,
            "Expected timetable ruler to format hours with leading zeros (06h, 07h)");
    }
}
