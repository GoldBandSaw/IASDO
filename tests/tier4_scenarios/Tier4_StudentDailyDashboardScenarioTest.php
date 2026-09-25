<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier4;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier4_StudentDailyDashboardScenarioTest extends TestCase {
    public function getTier(): int { return 4; }
    public function getFeature(): string { return 'E2E-DASHBOARD'; }
    public function getDescription(): string { return 'End-to-end scenario: Student opens dashboard, verifies sticky navigation sidebar, stats layout, and optimized recent resources'; }

    public function testDashboardPageTemplateIntegrity(): void {
        $indexPhp = $this->getFileContent('index.php');

        // Check dashboard structure
        Assert::assertContains('student-sidebar.php', $indexPhp, "Dashboard must include student-sidebar");
        Assert::assertContains('id="recent-resources"', $indexPhp, "Dashboard must have #recent-resources container");
        Assert::assertContains('id="upcoming-list"', $indexPhp, "Dashboard must have #upcoming-list container");
    }

    public function testDashboardRecentResourcesContract(): void {
        $appJs = $this->getFileContent('app.js');
        $apiPhp = $this->getFileContent('api.php');

        // Verify api.php supplies resources in /api/state or /api/resources
        Assert::assertMatchesRegex('/(?:resources[\'"\s=>:]|fetchResources)/i', $apiPhp,
            "Expected api.php to supply recent resources for dashboard");

        // Verify app.js renders recent resources to #recent-resources
        Assert::assertMatchesRegex('/renderDashboardResources/i', $appJs,
            "Expected app.js to have renderDashboardResources function");
    }

    public function testDesktopStickySidebarPresentOnDashboard(): void {
        $modernCss = $this->getFileContent('modern.css');

        // Sticky sidebar rules must be active for dashboard layout
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*position\s*:\s*sticky/i', $modernCss,
            "Expected sticky sidebar to be enabled for student dashboard");
    }
}
