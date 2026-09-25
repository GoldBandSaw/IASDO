<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier3;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier3_SidebarTimetableLayoutInteractionTest extends TestCase {
    public function getTier(): int { return 3; }
    public function getFeature(): string { return 'F1-F3'; }
    public function getDescription(): string { return 'Verify cross-feature coexistence: sticky sidebar and 7-column timetable grid inside .app-shell maintain proper flex structure without horizontal page spill'; }

    public function testAppShellFlexContainerCoexistence(): void {
        $pageShellCss = $this->getFileContent('page-shell.css');
        $modernCss = $this->getFileContent('modern.css');
        $combined = $pageShellCss . "\n" . $modernCss;

        // .app-shell should declare display: flex
        Assert::assertMatchesRegex('/\.app-shell\s*\{[^}]*display\s*:\s*flex/i', $combined,
            "Expected .app-shell to declare display: flex");
    }

    public function testSidebarFlexBasisDoesNotCollapseUnderTimetable(): void {
        $modernCss = $this->getFileContent('modern.css');

        // Sidebar flex: 0 0 248px prevents flex-shrink from squashing the navigation
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*flex\s*:\s*0\s+0\s+248px/i', $modernCss,
            "Expected .sidebar to declare flex: 0 0 248px to prevent flex shrinking beside wide grids");
    }

    public function testTimetablePageShellIntegration(): void {
        $timetablePhp = $this->getFileContent('timetable.php');

        // Timetable markup must nest inside .app-shell and .page-content
        Assert::assertContains('class="app-shell"', $timetablePhp, "Timetable should be inside .app-shell");
        Assert::assertContains('class="page-content"', $timetablePhp, "Timetable should be inside .page-content");
    }
}
