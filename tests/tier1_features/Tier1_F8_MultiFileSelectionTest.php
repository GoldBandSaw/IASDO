<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Tier1;

use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\Assert;

require_once __DIR__ . '/../harness/bootstrap.php';

class Tier1_F8_MultiFileSelectionTest extends TestCase {
    public function getTier(): int { return 1; }
    public function getFeature(): string { return 'F8'; }
    public function getDescription(): string { return 'Verify multi-file selection support on #resource-file input in courses.php with multiple attribute and guidance'; }

    public function testResourceFileInputHasMultipleAttribute(): void {
        $coursesHtml = $this->getFileContent('courses.php');

        // Check that #resource-file has the multiple attribute
        Assert::assertMatchesRegex('/<input[^>]*id=["\']resource-file["\'][^>]*multiple/i', $coursesHtml,
            "Expected <input id='resource-file'> in courses.php to have 'multiple' attribute");
    }

    public function testResourceFormIndicatesMultipleFilesGuidance(): void {
        $coursesHtml = $this->getFileContent('courses.php');

        // Check that label or text mentions multiple files or up to 10
        Assert::assertMatchesRegex('/(?:10\s*fichiers|Fichier\(s\)|jusqu[\'’]à\s*10)/i', $coursesHtml,
            "Expected courses.php form label or hint to indicate support for multiple files (up to 10)");
    }

    public function testAcceptedDocumentExtensionsPresent(): void {
        $coursesHtml = $this->getFileContent('courses.php');

        // Check accept attribute covers standard formats
        Assert::assertContains('.pdf', $coursesHtml, "Expected accept attribute to allow .pdf");
        Assert::assertContains('.docx', $coursesHtml, "Expected accept attribute to allow .docx");
        Assert::assertContains('.pptx', $coursesHtml, "Expected accept attribute to allow .pptx");
        Assert::assertContains('.xlsx', $coursesHtml, "Expected accept attribute to allow .xlsx");
    }
}
