<?php
$projectRoot = dirname(__DIR__, 3);
echo "Project root: $projectRoot\n";

$phpFiles = glob($projectRoot . '/*.php');
foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $basename = basename($file);
    if (strpos($content, '<link') !== false || strpos($content, 'student-sidebar') !== false || strpos($content, 'sidebar') !== false) {
        echo "=== $basename ===\n";
        preg_match_all('/<link[^>]+>/i', $content, $matches);
        foreach ($matches[0] as $link) {
            if (strpos($link, 'stylesheet') !== false) {
                echo "  CSS: $link\n";
            }
        }
        if (strpos($content, 'student-sidebar.php') !== false) {
            echo "  [INCLUDES student-sidebar.php]\n";
        }
        if (strpos($content, 'admin-shell') !== false) {
            echo "  [CONTAINS admin-shell]\n";
        }
    }
}
