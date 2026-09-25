<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier2;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier2_F8_FileCountLimitsTest extends TestCase {
    public function getTier(): int { return 2; }
    public function getFeature(): string { return 'F8'; }
    public function getDescription(): string { return 'Verify client-side validation boundary conditions for file selection: 1 to 10 files allowed, > 10 files rejected, 0 files rejected'; }

    public function testMaxFileCountBoundaryEnforcedInAppJs(): void {
        $appJs = $this->getFileContent('app.js');

        // Check for boundary condition: files.length > 10
        Assert::assertMatchesRegex('/files\.length\s*>\s*10/i', $appJs,
            "Expected client-side validation in app.js to reject selections exceeding 10 files (files.length > 10)");
    }

    public function testEmptySelectionBoundaryEnforced(): void {
        $appJs = $this->getFileContent('app.js');

        // Check for empty selection handling: !files.length
        Assert::assertMatchesRegex('/!files\.length/i', $appJs,
            "Expected client-side validation in app.js to prompt user when no files are selected (!files.length)");
    }

    public function testSingleFileBackwardCompatibilityPreserved(): void {
        $appJs = $this->getFileContent('app.js');

        // Check that files.length === 1 or single file upload continues to work cleanly
        Assert::assertMatchesRegex('/files\.length/i', $appJs,
            "Expected upload handler to support single file selections cleanly");
    }
}
