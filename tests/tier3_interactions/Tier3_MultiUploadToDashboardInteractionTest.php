<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier3;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier3_MultiUploadToDashboardInteractionTest extends TestCase {
    public function getTier(): int { return 3; }
    public function getFeature(): string { return 'F9-F2'; }
    public function getDescription(): string { return 'Verify cross-feature contract: uploading multiple resources updates local cache and triggers dashboard recent resources re-rendering'; }

    public function testUploadedFilesPrependToLocalResources(): void {
        $appJs = $this->getFileContent('app.js');

        // Check courseResources.unshift(data) or similar prepend logic
        Assert::assertMatchesRegex('/courseResources\.unshift\(/i', $appJs,
            "Expected uploaded resources to be prepended to courseResources in app.js");
    }

    public function testUploadRefreshesDashboardAndCoursesViews(): void {
        $appJs = $this->getFileContent('app.js');

        // Check that successful upload triggers renderCoursesPage and renderDashboardResources
        Assert::assertMatchesRegex('/renderCoursesPage\(\)/i', $appJs,
            "Expected upload handler to refresh courses page");
        Assert::assertMatchesRegex('/renderDashboardResources\(\)/i', $appJs,
            "Expected upload handler to refresh dashboard recent resources");
    }

    public function testDashboardRendersRecentItemsWithAuthor(): void {
        $appJs = $this->getFileContent('app.js');

        // renderDashboardResources should display author name
        Assert::assertMatchesRegex('/(?:owner_name|owner)/i', $appJs,
            "Expected renderDashboardResources to display resource creator information");
    }
}
