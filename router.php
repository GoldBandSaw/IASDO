<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/api/')) {
    require __DIR__ . DIRECTORY_SEPARATOR . 'api.php';
    exit;
}
if ($path !== '/login.html' && $path !== '/setup.html' && $path !== '/auth.js' && $path !== '/setup.js' && $path !== '/auth.css' && $path !== '/styles.css' && $path !== '/modern.css' && $path !== '/page-shell.css' && $path !== '/shared-pages.css') {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
    if (!currentUser()) {
        header('Location: /login.html');
        exit;
    }
}
return false;
