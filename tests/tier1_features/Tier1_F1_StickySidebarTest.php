<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;
use CampusFlow\Tests\Harness\DomInspector;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F1_StickySidebarTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F1'; }
    public function getDescription(): string { return 'Verify sticky sidebar positioning, height, and independent scroll in CSS and presence across all pages'; }

    public function testSidebarCssDeclarationInModernCss(): void {
        $modernCss = $this->getFileContent('modern.css');
        
        // Check that .sidebar has sticky positioning
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*position\s*:\s*sticky/i', $modernCss,
            "Expected .sidebar in modern.css to declare 'position: sticky'");

        // Check that .sidebar has top: 0
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*top\s*:\s*0/i', $modernCss,
            "Expected .sidebar in modern.css to declare 'top: 0'");

        // Check that .sidebar has height: 100vh
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*height\s*:\s*100vh/i', $modernCss,
            "Expected .sidebar in modern.css to declare 'height: 100vh'");

        // Check that .sidebar has overflow-y: auto
        Assert::assertMatchesRegex('/\.sidebar\s*\{[^}]*overflow-y\s*:\s*auto/i', $modernCss,
            "Expected .sidebar in modern.css to declare 'overflow-y: auto' for independent scroll");
    }

    public function testSidebarPresentOnAllStudentPages(): void {
        $pages = [
            'index.php',
            'tasks.php',
            'courses.php',
            'timetable.php',
            'settings.php',
            'add.php',
            'chat.php'
        ];

        foreach ($pages as $page) {
            $content = $this->getFileContent($page);
            Assert::assertContains('student-sidebar.php', $content,
                "Expected {$page} to include partials/student-sidebar.php");
            Assert::assertContains('modern.css', $content,
                "Expected {$page} to link modern.css");
        }
    }

    public function testMobileDrawerMediaQueryPreserved(): void {
        $modernCss = $this->getFileContent('modern.css');
        // Mobile drawer should still use fixed positioning when <= 650px
        Assert::assertMatchesRegex('/@media[^{]*max-width\s*:\s*650px[^{]*\{.*?\.sidebar\s*\{[^}]*position\s*:\s*fixed/s', $modernCss,
            "Expected mobile drawer (@media max-width: 650px) to maintain position: fixed");
    }
}
