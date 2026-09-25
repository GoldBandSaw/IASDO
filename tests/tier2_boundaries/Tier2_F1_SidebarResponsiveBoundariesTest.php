<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F1_SidebarResponsiveBoundariesTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F1'; }
    public function getDescription(): string { return 'Verify sidebar boundary behaviors across responsive breakpoints (desktop, tablet, mobile) and vertical overflow containment'; }

    public function testDesktopSidebarWidthAndOverflow(): void {
        $modernCss = $this->getFileContent('modern.css');

        // Sidebar width should be fixed on desktop (248px)
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*(?:width|flex)\s*:\s*(?:0\s+0\s+)?248px/i', $modernCss,
            "Expected .sidebar to declare width: 248px or flex: 0 0 248px on desktop");

        // Independent scroll containment
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*overflow-x\s*:\s*hidden/i', $modernCss,
            "Expected .sidebar to set overflow-x: hidden to prevent horizontal scroll leaks");
    }

    public function testTabletBreakpointWidthAdaptation(): void {
        $modernCss = $this->getFileContent('modern.css');
        $stylesCss = $this->getFileContent('styles.css');
        $combined = $modernCss . "\n" . $stylesCss;

        // Tablet adaptation (max-width: 900px or between 650px and 900px)
        Assert::assertMatchesRegex('/@media[^{]*max-width\s*:\s*900px[^{]*\{.*?\.sidebar\s*\{[^}]*width\s*:\s*205px/s', $combined,
            "Expected sidebar to adapt to 205px on tablet viewport (<= 900px)");
    }

    public function testMobileDrawerBoundaryTransform(): void {
        $modernCss = $this->getFileContent('modern.css');

        // Verify off-canvas translation (-105% or -100%) and menu-open translation (0)
        Assert::assertMatchesRegex('/transform\s*:\s*translateX\(-10[05]%\)/i', $modernCss,
            "Expected mobile sidebar to be hidden off-screen with translateX(-105%) or (-100%)");
        Assert::assertMatchesRegex('/body\.menu-open\s+\.sidebar\s*\{[^}]*transform\s*:\s*translateX\(0\)/i', $modernCss,
            "Expected body.menu-open .sidebar to slide in with translateX(0)");
    }
}
