<?php
declare(strict_types=1);

namespace CampusFlow\Tests;

use CampusFlow\Tests\Harness\TestReporter;
use CampusFlow\Tests\Harness\TestCase;
use CampusFlow\Tests\Harness\DevServer;

require_once __DIR__ . '/harness/bootstrap.php';

$projectRoot = dirname(__DIR__);

// Parse command line options
$options = getopt('', ['tier:', 'feature:', 'with-server', 'help']);

if (isset($options['help'])) {
    echo "Usage: php tests/run_all.php [OPTIONS]\n";
    echo "Options:\n";
    echo "  --tier=<1|2|3|4>     Run tests only for the specified tier\n";
    echo "  --feature=<F1..F9>   Run tests matching a specific feature\n";
    echo "  --with-server        Ensure local dev server (localhost:8000) is running\n";
    echo "  --help               Display this help message\n";
    exit(0);
}

$filterTier = isset($options['tier']) ? (int)$options['tier'] : null;
$filterFeature = isset($options['feature']) ? strtoupper((string)$options['feature']) : null;
$withServer = isset($options['with-server']);

if ($withServer) {
    echo "Checking dev server status...\n";
    $serverUrl = DevServer::ensureRunning($projectRoot);
    echo "Dev server ready at {$serverUrl}\n\n";
}

$testDirs = [
    1 => __DIR__ . '/tier1_features',
    2 => __DIR__ . '/tier2_boundaries',
    3 => __DIR__ . '/tier3_interactions',
    4 => __DIR__ . '/tier4_scenarios'
];

$allResults = [];
$startTime = microtime(true);

foreach ($testDirs as $tier => $dir) {
    if ($filterTier !== null && $filterTier !== $tier) {
        continue;
    }

    if (!is_dir($dir)) continue;

    $files = glob($dir . '/*Test.php');
    sort($files);

    foreach ($files as $file) {
        require_once $file;

        // Extract class name from filename
        $className = pathinfo($file, PATHINFO_FILENAME);
        $fullClass = match($tier) {
            1 => "CampusFlow\\Tests\\Tier1\\{$className}",
            2 => "CampusFlow\\Tests\\Tier2\\{$className}",
            3 => "CampusFlow\\Tests\\Tier3\\{$className}",
            4 => "CampusFlow\\Tests\\Tier4\\{$className}",
            default => $className
        };

        if (!class_exists($fullClass)) {
            continue;
        }

        /** @var TestCase $testInstance */
        $testInstance = new $fullClass($projectRoot);

        if ($filterFeature !== null && !str_contains(strtoupper($testInstance->getFeature()), $filterFeature)) {
            continue;
        }

        $results = $testInstance->run();
        foreach ($results as $res) {
            $allResults[] = $res;
        }
    }
}

$totalDuration = microtime(true) - $startTime;
$exitCode = TestReporter::printReport($allResults, $totalDuration);

if ($withServer) {
    DevServer::stop();
}

exit($exitCode);
