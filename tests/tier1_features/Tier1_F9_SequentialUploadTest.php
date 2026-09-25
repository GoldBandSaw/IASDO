<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F9_SequentialUploadTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F9'; }
    public function getDescription(): string { return 'Verify client-side sequential file upload processing in app.js and single-file API compatibility in api.php'; }

    public function testClientSideHandlesMultipleFilesSequentially(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify iteration over files array (e.g. for loop or files.length)
        Assert::assertMatchesRegex('/(?:files\s*=\s*(?:Array\.from\(|\[\.\.\.)?[^;]*\.files|for\s*\([^)]*files\.length)/i', $appJs,
            "Expected app.js resource form submission to read files list and iterate over it");

        // Verify sequential POST requests to /api/resources/upload
        Assert::assertMatchesRegex('/\/api\/resources\/upload/i', $appJs,
            "Expected app.js to post each file to /api/resources/upload");
    }

    public function testProgressFeedbackProvidedDuringUpload(): void {
        $appJs = $this->getFileContent('app.js');

        // Check for progress message pattern like "Envoi (i/total)..." or "Envoi en cours..."
        Assert::assertMatchesRegex('/(?:Envoi|uploading|progress)[^"`\']*[\$\{]/i', $appJs,
            "Expected app.js to provide dynamic progress feedback during multi-file upload");
    }

    public function testApiUploadEndpointPreserved(): void {
        $apiPhp = $this->getFileContent('api.php');

        // Endpoint /api/resources/upload should remain intact
        Assert::assertContains('api/resources/upload', $apiPhp,
            "Expected api.php to maintain the /api/resources/upload endpoint");

        // Checks for file size limit (50MB)
        Assert::assertContains('50 * 1024 * 1024', $apiPhp,
            "Expected api.php to enforce 50MB file size limit");
    }
}
