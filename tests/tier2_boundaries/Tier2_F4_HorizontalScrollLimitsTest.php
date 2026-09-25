<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F4_HorizontalScrollLimitsTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F4'; }
    public function getDescription(): string { return 'Verify timetable hour lines do not extend beyond container width and avoid horizontal scrollbar leaks'; }

    public function testHourLineRightBoundaryIsConstrained(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // Hour lines must be bounded, e.g. right: 0 or width: 100%
        Assert::assertMatchesRegex('/\.tt-hour-line\s*\{[^}]*right\s*:\s*0/i', $combined,
            "Expected .tt-hour-line in shared-pages.css or modern.css to set 'right: 0' (replacing right: -100vw)");
    }

    public function testTimeRulerFlexBasisPreserved(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $sharedCss . "\n" . $modernCss;

        // Time ruler flex basis should be around 40px - 44px
        Assert::assertMatchesRegex('/\.tt-time-ruler\s*\{[^}]*flex\s*:\s*0\s+0\s+(?:40|44)px/i', $combined,
            "Expected .tt-time-ruler to have flex basis between 40px and 44px");
    }
}
