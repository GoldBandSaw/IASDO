<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F2_RecentResourcesTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F2'; }
    public function getDescription(): string { return 'Verify optimized recent resources query, descending sort, owner display name join, and course filter in api.php and db.php'; }

    public function testUnifiedQueryImplementationInApiPhp(): void {
        $apiContent = $this->getFileContent('api.php');

        // Verify descending order by created_at
        Assert::assertMatchesRegex('/(?:ORDER\s+BY\s+.*created_at.*DESC)/i', $apiContent,
            "Expected api.php to order resources by created_at DESC");

        // Verify course filtering support (?course=...)
        Assert::assertMatchesRegex('/[\$_GET|options]\[[\'"]course[\'"]\]/i', $apiContent,
            "Expected api.php to accept and filter by 'course' parameter");

        // Verify owner name / display_name join with users table
        Assert::assertMatchesRegex('/(?:LEFT\s+JOIN\s+users|owner_name|display_name)/i', $apiContent,
            "Expected api.php to perform a light join or enrichment for owner display name");
    }

    public function testDatabaseIndexInitializationInDbPhp(): void {
        $dbContent = $this->getFileContent('db.php');

        // Check for index on created_at in db.php
        Assert::assertMatchesRegex('/CREATE\s+INDEX\s+IF\s+NOT\s+EXISTS\s+idx_resources_created_at/i', $dbContent,
            "Expected db.php to initialize idx_resources_created_at index");
    }

    public function testDashboardStateLimitsRecentResources(): void {
        $apiContent = $this->getFileContent('api.php');
        
        // In /api/state, resources should be bounded (limit 5 to 10 items)
        Assert::assertMatchesRegex('/(?:limit[\'"\s=>:]+(?:[5-9]|10)|fetchResources\(\$db,\s*\[[^\]]*limit[\'"\s=>:]+(?:[5-9]|10))/i', $apiContent,
            "Expected /api/state to limit recent resources to 5-10 items");
    }
}
