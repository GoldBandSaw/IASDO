<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F3_SundayAllDayConfinementTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F3'; }
    public function getDescription(): string { return 'Verify Sunday (day index 6) and all-day strip containment within day column without horizontal row bleed'; }

    public function testLastChildDayColBorderRightHandled(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // Verify .tt-day-col:last-child (Sunday) border right cleanup or grid cell assignment
        $hasLastChildRule = (bool)preg_match('/\.tt-day-col:last-child/i', $combined);
        $hasGridTemplate = (bool)preg_match('/grid-template-columns\s*:\s*repeat\(7/i', $combined);
        
        Assert::assertTrue($hasLastChildRule || $hasGridTemplate,
            "Expected timetable styling to handle Sunday (.tt-day-col:last-child) or 7-column grid layout cleanly");
    }

    public function testAllDayEventCardBoundaryWidth(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // .timetable-event--allday should be confined to normal flow or width: 100% of column
        Assert::assertMatchesRegex('/\.timetable-event--allday\s*\{/i', $combined,
            "Expected .timetable-event--allday styling to exist");
    }
}
