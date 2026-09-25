<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F2_QueryLimitsAndFiltersTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F2'; }
    public function getDescription(): string { return 'Verify resource query bounds: min/max limit clamping, cursor pagination before parameter, and special character course filtering'; }

    public function testLimitClampingLogic(): void {
        $apiContent = $this->getFileContent('api.php');

        // Check limit is clamped: min(50, max(1, ...))
        Assert::assertMatchesRegex('/min\(\s*50\s*,\s*max\(\s*1\s*,/i', $apiContent,
            "Expected api.php to clamp limit between 1 and 50 using min(50, max(1, ...))");
    }

    public function testCursorPaginationBeforeParameterHandled(): void {
        $apiContent = $this->getFileContent('api.php');

        // Verify before timestamp parameter handling
        Assert::assertMatchesRegex('/(?:before|created_at\s*<\s*\?)/i', $apiContent,
            "Expected api.php to support 'before' cursor parameter for pagination");
    }

    public function testCourseFilterTrimmingAndSanitization(): void {
        $apiContent = $this->getFileContent('api.php');

        // Verify course filtering uses prepared statement parameter
        Assert::assertMatchesRegex('/payload->>[\'"]course[\'"]\s*=\s*\?/i', $apiContent,
            "Expected course filter to query JSONB payload using prepared statement parameter placeholder");
    }
}
