<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/index.php';
if (!currentUser()) {
    header('Location: /login.php');
    exit;
}
$file = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($path, '/'));
if (!$file || !str_starts_with($file, realpath(__DIR__)) || !is_file($file)) {
    http_response_code(404);
    exit('Page introuvable');
}
header('Content-Type: text/html; charset=utf-8');
readfile($file);
