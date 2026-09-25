<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);
echo "=== INDEPENDENT VERIFICATION REVIEWER M1-2 ===\n\n";

$errors = [];
$passes = [];

// 1. CSS Syntax Validation
$cssPath = $projectRoot . '/modern.css';
if (!file_exists($cssPath)) {
    $errors[] = "modern.css not found at $cssPath";
} else {
    $css = file_get_contents($cssPath);
    $stack = [];
    $lines = explode("\n", $css);
    $matched = true;
    for ($i = 0; $i < strlen($css); $i++) {
        $c = $css[$i];
        if (in_array($c, ['{', '[', '('], true)) {
            $stack[] = $c;
        } elseif (in_array($c, ['}', ']', ')'], true)) {
            if (empty($stack)) {
                $matched = false;
                $errors[] = "Unmatched closing bracket '$c' at char $i";
                break;
            }
            $top = array_pop($stack);
            $valid = ($top === '{' && $c === '}') || ($top === '[' && $c === ']') || ($top === '(' && $c === ')');
            if (!$valid) {
                $matched = false;
                $errors[] = "Mismatched bracket '$top' and '$c' at char $i";
                break;
            }
        }
    }
    if (!empty($stack)) {
        $matched = false;
        $errors[] = "Unclosed brackets remaining: " . implode('', $stack);
    }
    if ($matched) {
        $passes[] = "CSS Syntax: All braces, brackets, and parentheses in modern.css are balanced.";
    }
}

// 2. Property checks in modern.css lines 58-96
preg_match('/\.sidebar\s*\{([^}]+)\}/s', $css, $sidebarMatch);
if (!$sidebarMatch) {
    $errors[] = "Could not find .sidebar block in modern.css";
} else {
    $block = $sidebarMatch[1];
    $requiredProps = [
        'position: sticky' => '/position\s*:\s*sticky/i',
        'top: 0' => '/top\s*:\s*0/i',
        'height: 100vh' => '/height\s*:\s*100vh/i',
        'max-height: 100vh' => '/max-height\s*:\s*100vh/i',
        'overflow-y: auto' => '/overflow-y\s*:\s*auto/i',
        'overflow-x: hidden' => '/overflow-x\s*:\s*hidden/i',
        'flex: 0 0 248px' => '/flex\s*:\s*0\s+0\s+248px/i',
        'width: 248px' => '/width\s*:\s*248px/i',
        'box-sizing: border-box' => '/box-sizing\s*:\s*border-box/i',
        'scrollbar-width: thin' => '/scrollbar-width\s*:\s*thin/i',
    ];
    foreach ($requiredProps as $name => $pattern) {
        if (preg_match($pattern, $block)) {
            $passes[] = "Property check: .sidebar has '$name'";
        } else {
            $errors[] = "Property check FAILED: .sidebar missing '$name'";
        }
    }
}

// Check custom webkit scrollbar
if (preg_match('/\.sidebar::-webkit-scrollbar\s*\{[^}]*width\s*:\s*5px/i', $css)) {
    $passes[] = "Custom scrollbar: .sidebar::-webkit-scrollbar defined with width: 5px";
} else {
    $errors[] = "Custom scrollbar missing width: 5px";
}

// Check tablet media query
if (preg_match('/@media\s*\(min-width:\s*651px\)\s*and\s*\(max-width:\s*900px\)\s*\{\s*\.sidebar\s*\{[^}]*width\s*:\s*205px/s', $css)) {
    $passes[] = "Tablet query: .sidebar adapts to 205px in 651px..900px";
} else {
    $errors[] = "Tablet media query missing or incorrect in modern.css";
}

// Check mobile media query preserves position: fixed
if (preg_match('/@media[^{]*max-width\s*:\s*650px[^{]*\{.*?\.sidebar\s*\{[^}]*position\s*:\s*fixed/s', $css)) {
    $passes[] = "Mobile query: .sidebar preserves position: fixed at <= 650px";
} else {
    $errors[] = "Mobile media query does not preserve position: fixed";
}

// 3. Dev Server HTTP Checks
$serverUrl = 'http://localhost:8000';
$ch = curl_init("$serverUrl/modern.css");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $passes[] = "Dev server: /modern.css returns HTTP 200";
} else {
    $errors[] = "Dev server: /modern.css returned HTTP $httpCode";
}

// Test student pages
$pages = ['courses.php', 'timetable.php', 'index.php', 'settings.php', 'tasks.php', 'add.php', 'chat.php'];
foreach ($pages as $page) {
    $ch = curl_init("$serverUrl/$page");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 || $code === 302) {
        $passes[] = "Dev server: /$page responds with code $code";
    } else {
        $errors[] = "Dev server: /$page returned unexpected code $code";
    }
}

// Output summary
echo "\n--- PASSES (" . count($passes) . ") ---\n";
foreach ($passes as $p) {
    echo "  [OK] $p\n";
}

if (!empty($errors)) {
    echo "\n--- ERRORS (" . count($errors) . ") ---\n";
    foreach ($errors as $e) {
        echo "  [FAIL] $e\n";
    }
    exit(1);
} else {
    echo "\nALL INDEPENDENT VERIFICATION CHECKS PASSED!\n";
    exit(0);
}
