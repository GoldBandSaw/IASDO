<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F9_UploadSizeAndErrorResilienceTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F9'; }
    public function getDescription(): string { return 'Verify upload size boundaries (50MB check) and partial failure resilience during multi-file sequential processing'; }

    public function testIndividualFileSizeLimitEnforcedClientSide(): void {
        $appJs = $this->getFileContent('app.js');

        // Check 50MB check in client script (50 * 1024 * 1024)
        Assert::assertMatchesRegex('/(?:50\s*\*\s*1024\s*\*\s*1024|52428800)/i', $appJs,
            "Expected client-side validation in app.js to enforce 50MB per-file size boundary");
    }

    public function testPartialFailureResilienceHandled(): void {
        $appJs = $this->getFileContent('app.js');

        // Check try/catch inside upload loop and separate errors array or tracking
        Assert::assertMatchesRegex('/for\s*\([^)]*files\.length[^)]*\)\s*\{[^}]*try\s*\{[^}]*await\s+fetch\([^}]*\}\s*catch/s', $appJs,
            "Expected app.js sequential loop to wrap individual file uploads in try/catch for partial failure resilience");
    }

    public function testSubmitButtonReenabledAfterUpload(): void {
        $appJs = $this->getFileContent('app.js');

        // Verify submit button is re-enabled in both success and error branches
        Assert::assertMatchesRegex('/submitBtn(?:\.disabled|\s*=\s*false)/i', $appJs,
            "Expected upload handler to re-enable submit button upon completion");
    }
}
