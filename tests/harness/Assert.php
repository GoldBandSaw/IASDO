<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

use RuntimeException;

class AssertionException extends RuntimeException {}

class Assert {
    public static function assertTrue(bool $condition, string $message = ''): void {
        if (!$condition) {
            throw new AssertionException($message ?: "Failed asserting that condition is true.");
        }
    }

    public static function assertFalse(bool $condition, string $message = ''): void {
        if ($condition) {
            throw new AssertionException($message ?: "Failed asserting that condition is false.");
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected != $actual) {
            $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            throw new AssertionException($message ?: "Failed asserting that [{$actStr}] equals expected [{$expStr}].");
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            throw new AssertionException($message ?: "Failed asserting that [{$actStr}] is identical to expected [{$expStr}].");
        }
    }

    public static function assertContains(string $needle, string $haystack, string $message = ''): void {
        if (!str_contains($haystack, $needle)) {
            $preview = strlen($haystack) > 160 ? substr($haystack, 0, 160) . '...' : $haystack;
            throw new AssertionException($message ?: "Failed asserting that string contains '{$needle}'. Haystack preview: '{$preview}'");
        }
    }

    public static function assertNotContains(string $needle, string $haystack, string $message = ''): void {
        if (str_contains($haystack, $needle)) {
            throw new AssertionException($message ?: "Failed asserting that string does NOT contain '{$needle}'.");
        }
    }

    public static function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void {
        if (!preg_match($pattern, $subject)) {
            $preview = strlen($subject) > 160 ? substr($subject, 0, 160) . '...' : $subject;
            throw new AssertionException($message ?: "Failed asserting that content matches pattern '{$pattern}'. Content preview: '{$preview}'");
        }
    }

    public static function assertGreaterThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void {
        if ($actual < $expected) {
            throw new AssertionException($message ?: "Failed asserting that {$actual} is >= {$expected}.");
        }
    }

    public static function assertLessThanOrEqual(int|float $expected, int|float $actual, string $message = ''): void {
        if ($actual > $expected) {
            throw new AssertionException($message ?: "Failed asserting that {$actual} is <= {$expected}.");
        }
    }

    public static function assertArrayHasKey(string|int $key, array $array, string $message = ''): void {
        if (!array_key_exists($key, $array)) {
            throw new AssertionException($message ?: "Failed asserting that array has key '{$key}'.");
        }
    }

    public static function assertCount(int $expectedCount, countable|array $countable, string $message = ''): void {
        $actualCount = count($countable);
        if ($actualCount !== $expectedCount) {
            throw new AssertionException($message ?: "Failed asserting that count {$actualCount} matches expected {$expectedCount}.");
        }
    }

    public static function assertHttpStatus(int $expectedStatus, int $actualStatus, string $message = ''): void {
        if ($expectedStatus !== $actualStatus) {
            throw new AssertionException($message ?: "Failed asserting that HTTP status {$actualStatus} matches expected {$expectedStatus}.");
        }
    }
}
