<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F3_ColumnConfinementTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F3'; }
    public function getDescription(): string { return 'Verify timetable column confinement (.tt-day-col position: relative) preventing horizontal event stretching across columns'; }

    public function testDayColumnHasPositionRelative(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combinedCss = $sharedCss . "\n" . $modernCss;

        // Verify .tt-day-col has position: relative
        Assert::assertMatchesRegex('/\.tt-day-col\s*\{[^}]*position\s*:\s*relative/i', $combinedCss,
            "Expected .tt-day-col in shared-pages.css or modern.css to declare 'position: relative'");
    }

    public function testDaysRowHasMultiColumnLayout(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combinedCss = $sharedCss . "\n" . $modernCss;

        // Verify .tt-days-row has display: flex or display: grid with 7 columns
        Assert::assertMatchesRegex('/\.tt-days-row\s*\{[^}]*(?:display\s*:\s*(?:grid|flex))/i', $combinedCss,
            "Expected .tt-days-row to use display: grid or flex layout");
    }

    public function testAllDayStripContainedWithinDayCol(): void {
        $sharedCss = $this->getFileContent('shared-pages.css');
        $modernCss = $this->getFileContent('modern.css');
        $combinedCss = $sharedCss . "\n" . $modernCss;

        // .tt-allday-strip should either not be absolute across row or contained inside relative day column
        Assert::assertMatchesRegex('/\.tt-day-col\s*\{[^}]*position\s*:\s*relative/i', $combinedCss,
            "Confining .tt-allday-strip requires .tt-day-col to be positioned relatively");
    }
}
