<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

class TestReporter {
    /**
     * @param TestResult[] $results
     */
    public static function printReport(array $results, float $totalTime): int {
        $total = count($results);
        $passed = count(array_filter($results, fn($r) => $r->passed));
        $failed = $total - $passed;

        echo "\n" . str_repeat('=', 78) . "\n";
        echo "               CAMPUSFLOW 4-TIER E2E TEST SUITE REPORT\n";
        echo str_repeat('=', 78) . "\n\n";

        // Group by Tier
        $byTier = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($results as $res) {
            $byTier[$res->tier][] = $res;
        }

        $tierNames = [
            1 => "Tier 1: Feature Coverage (Happy Path)",
            2 => "Tier 2: Boundary & Corner Cases",
            3 => "Tier 3: Cross-Feature Interactions",
            4 => "Tier 4: Real-World Application Scenarios"
        ];

        foreach ($byTier as $tier => $tierResults) {
            if (empty($tierResults)) continue;
            echo "--- " . $tierNames[$tier] . " ---\n";
            foreach ($tierResults as $res) {
                $statusTag = $res->passed ? "\033[32m[PASS]\033[0m" : "\033[31m[FAIL]\033[0m";
                $featureTag = sprintf("[%-4s]", $res->feature);
                $duration = sprintf("(%.2f ms)", $res->duration * 1000);
                echo "  {$statusTag} {$featureTag} {$res->testName} {$duration}\n";
                if (!$res->passed) {
                    echo "         \033[31mReason:\033[0m {$res->message}\n";
                    if ($res->trace) {
                        echo "         \033[33mLocation:\033[0m {$res->trace}\n";
                    }
                }
            }
            echo "\n";
        }

        echo str_repeat('-', 78) . "\n";
        echo "SUMMARY STATISTICS:\n";
        for ($t = 1; $t <= 4; $t++) {
            $tResults = $byTier[$t];
            $tTotal = count($tResults);
            $tPassed = count(array_filter($tResults, fn($r) => $r->passed));
            $tFailed = $tTotal - $tPassed;
            $percent = $tTotal > 0 ? sprintf("%.1f%%", ($tPassed / $tTotal) * 100) : "N/A";
            echo sprintf("  Tier %d: %2d tests | %2d passed | %2d failed | Pass Rate: %s\n", $t, $tTotal, $tPassed, $tFailed, $percent);
        }

        echo str_repeat('-', 78) . "\n";
        echo sprintf("Total Tests: %d | Passed: \033[32m%d\033[0m | Failed: \033[31m%d\033[0m | Total Duration: %.2f s\n", $total, $passed, $failed, $totalTime);
        echo str_repeat('=', 78) . "\n\n";

        return $failed === 0 ? 0 : 1;
    }
}
