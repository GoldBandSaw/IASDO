<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier4;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier4_MultiResourceContributionScenarioTest extends TestCase {
    public function getTier(): int { return 4; }
    public function getFeature(): string { return 'E2E-MULTI-UPLOAD'; }
    public function getDescription(): string { return 'End-to-end scenario: Student opens courses page, selects multiple lecture notes (up to 10), uploads sequentially with live progress feedback'; }

    public function testCoursePageResourceFormElements(): void {
        $coursesPhp = $this->getFileContent('courses.php');

        // Form elements for resource contribution
        Assert::assertContains('id="resource-form"', $coursesPhp, "Expected #resource-form on courses.php");
        Assert::assertContains('id="resource-course"', $coursesPhp, "Expected #resource-course selector on courses.php");
        Assert::assertContains('id="resource-file"', $coursesPhp, "Expected #resource-file input on courses.php");
        Assert::assertMatchesRegex('/<input[^>]*id=["\']resource-file["\'][^>]*multiple/i', $coursesPhp,
            "Expected #resource-file input to have 'multiple' attribute");
    }

    public function testMultiFileUploadLoopAndToastFeedback(): void {
        $appJs = $this->getFileContent('app.js');

        // Multi-file upload loop with progress feedback
        Assert::assertMatchesRegex('/for\s*\([^)]*files\.length[^)]*\)/i', $appJs,
            "Expected app.js to iterate over selected files in loop");
        Assert::assertMatchesRegex('/showToast/i', $appJs,
            "Expected app.js to trigger toast notifications upon upload completion");
    }

    public function testStorageAndResourceApiEndpointContract(): void {
        $apiPhp = $this->getFileContent('api.php');
        $dbPhp = $this->getFileContent('db.php');

        // Verify storage upload handling
        Assert::assertContains('api/resources/upload', $apiPhp, "api.php must route /api/resources/upload");
        Assert::assertContains('storageRequest', $apiPhp, "api.php must call storageRequest to store files in Supabase bucket");
        Assert::assertContains('function storageRequest', $dbPhp, "db.php must implement storageRequest");
    }
}
