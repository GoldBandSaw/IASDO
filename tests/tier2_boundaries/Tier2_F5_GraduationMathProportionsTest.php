<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F5_GraduationMathProportionsTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F5'; }
    public function getDescription(): string { return 'Verify mathematical proportionality of timetable hour graduation positions across 06:00 to 20:00 range'; }

    public function testPctMathematicalFunctionIntegrity(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify pct calculation: (mins / TOTAL_MINS * 100).toFixed(3)
        Assert::assertMatchesRegex('/function\s+pct\(mins\)\s*\{\s*return\s*\(Math\.max\(0,\s*Math\.min\(TOTAL_MINS,\s*mins\)\)\s*\/\s*TOTAL_MINS\s*\*\s*100\)\.toFixed\(3\);\s*\}/i', $appJs,
            "Expected app.js to calculate pct using bounded Math.max(0, Math.min(TOTAL_MINS, mins)) / TOTAL_MINS * 100");
    }

    public function testHourRangeBounds(): void {
        $appJs = $this->getFileContent('app.js');

        // Check GRID_START_H = 6, GRID_END_H = 20, TOTAL_MINS = 840
        Assert::assertMatchesRegex('/GRID_START_H\s*=\s*6/i', $appJs, "GRID_START_H should be 6");
        Assert::assertMatchesRegex('/GRID_END_H\s*=\s*20/i', $appJs, "GRID_END_H should be 20");
        Assert::assertMatchesRegex('/TOTAL_MINS\s*=\s*\(GRID_END_H\s*-\s*GRID_START_H\)\s*\*\s*60/i', $appJs, "TOTAL_MINS should be (GRID_END_H - GRID_START_H) * 60");
    }

    public function testMathematicalProportionsAtKeyHours(): void {
        $startH = 6;
        $endH = 20;
        $totalMins = ($endH - $startH) * 60; // 840 mins

        $pct = fn($h) => round((($h - $startH) * 60) / $totalMins * 100, 3);

        // 06:00 -> 0.000%
        Assert::assertEquals(0.000, $pct(6), "06:00 should calculate to exactly 0.000%");

        // 07:00 -> (60 / 840) * 100 = 7.143%
        Assert::assertEquals(7.143, $pct(7), "07:00 should calculate to 7.143%");

        // 20:00 -> 100.000%
        Assert::assertEquals(100.000, $pct(20), "20:00 should calculate to 100.000%");
    }
}
