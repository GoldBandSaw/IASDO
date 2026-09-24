<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Blocklist for sensitive files
if (preg_match('/\.(env|git.*|sql|yaml|conf)$/i', $path) || 
    preg_match('/(Dockerfile|proxy-server\.js|package.*\.json|provision\.php)$/i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// Never serve internal PHP/db files directly
if ($path === '/db.php' || $path === '/auth.php' || str_starts_with($path, '/partials/')) {
    http_response_code(403);
    exit('Forbidden');
}

if (str_starts_with($path, '/api/')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api.php';
    exit;
}

$whitelist = [
    '/login.php', '/setup.php', '/admin-login.php', '/guest.php',
    '/auth.js', '/setup.js', '/app.js',
    '/auth.css', '/styles.css', '/modern.css', '/page-shell.css', '/shared-pages.css', '/courses.css', '/chat.css'
];

if (!in_array($path, $whitelist)) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
    if (!currentUser()) {
        header('Location: /login.php');
        exit;
    }
}

if (str_ends_with($path, '.php')) {
    $file = __DIR__ . $path;
    if (file_exists($file) && is_file($file)) {
        require $file;
        exit;
    }
}

return false;
