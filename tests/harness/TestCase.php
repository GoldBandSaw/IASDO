<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

use Throwable;

abstract class TestCase {
    protected string $projectRoot;
    protected ?HttpClient $http = null;

    public function __construct(string $projectRoot) {
        $this->projectRoot = $projectRoot;
    }

    abstract public function getTier(): int;
    abstract public function getFeature(): string;
    abstract public function getDescription(): string;

    public function setUp(): void {}
    public function tearDown(): void {}

    protected function http(): HttpClient {
        if ($this->http === null) {
            $this->http = new HttpClient('http://localhost:8000');
        }
        return $this->http;
    }

    protected function getFileContent(string $relativePath): string {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!file_exists($path)) {
            throw new \RuntimeException("Target file not found: {$path}");
        }
        return file_get_contents($path);
    }

    /**
     * Executes all test methods (prefixed with `test`) and returns array of TestResult objects
     * @return TestResult[]
     */
    public function run(): array {
        $results = [];
        $methods = get_class_methods($this);
        $testMethods = array_filter($methods, fn($m) => str_starts_with($m, 'test'));

        foreach ($testMethods as $method) {
            $testName = (new \ReflectionClass($this))->getShortName() . '::' . $method;
            $start = microtime(true);
            try {
                $this->setUp();
                $this->$method();
                $this->tearDown();
                $duration = microtime(true) - $start;
                $results[] = new TestResult(
                    tier: $this->getTier(),
                    feature: $this->getFeature(),
                    testName: $testName,
                    passed: true,
                    duration: $duration,
                    message: 'PASS'
                );
            } catch (Throwable $e) {
                try { $this->tearDown(); } catch (Throwable) {}
                $duration = microtime(true) - $start;
                $results[] = new TestResult(
                    tier: $this->getTier(),
                    feature: $this->getFeature(),
                    testName: $testName,
                    passed: false,
                    duration: $duration,
                    message: $e->getMessage(),
                    trace: $e->getFile() . ':' . $e->getLine()
                );
            }
        }

        return $results;
    }
}

class TestResult {
    public function __construct(
        public int $tier,
        public string $feature,
        public string $testName,
        public bool $passed,
        public float $duration,
        public string $message = '',
        public string $trace = ''
    ) {}
}
