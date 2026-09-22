<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$db = database();
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/', '/');
$parts = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === 'api/state' && $method === 'GET') {
    $tasks = array_map(fn($row) => json_decode($row['payload'], true), $db->query('SELECT payload FROM tasks')->fetchAll(PDO::FETCH_ASSOC));
    $settingsRow = $db->query('SELECT payload FROM settings WHERE id = 1')->fetchColumn();
    $courses = $db->query('SELECT name FROM courses ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $resources = array_map(fn($row) => json_decode($row['payload'], true), $db->query('SELECT payload FROM resources')->fetchAll(PDO::FETCH_ASSOC));
    respond(['tasks' => $tasks, 'settings' => $settingsRow ? json_decode($settingsRow, true) : null, 'courses' => $courses, 'resources' => $resources]);
}
if ($path === 'api/tasks' && $method === 'POST') {
    $body = jsonBody(); if (!isset($body['id'])) respond(['error' => 'id requis'], 422);
    $stmt = $db->prepare('INSERT OR REPLACE INTO tasks (id, payload) VALUES (?, ?)'); $stmt->execute([(string)$body['id'], json_encode($body)]);
    respond($body, 201);
}
if ($path === 'api/settings' && $method === 'PUT') {
    $body = jsonBody(); $stmt = $db->prepare('INSERT OR REPLACE INTO settings (id, payload) VALUES (1, ?)'); $stmt->execute([json_encode($body)]); respond($body);
}
if ($path === 'api/proposals' && $method === 'GET') {
    respond($db->query("SELECT id, course, title, resource_type, url, created_at FROM proposals WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC));
}
if ($path === 'api/proposals' && $method === 'POST') {
    $body = jsonBody();
    foreach (['course', 'title', 'type', 'url'] as $field) if (!isset($body[$field]) || trim((string)$body[$field]) === '') respond(['error' => "$field requis"], 422);
    if (!filter_var($body['url'], FILTER_VALIDATE_URL) || !in_array(parse_url($body['url'], PHP_URL_SCHEME), ['http', 'https'], true)) respond(['error' => 'URL invalide'], 422);
    $stmt = $db->prepare('INSERT INTO proposals (course, title, resource_type, url, created_at) VALUES (?, ?, ?, ?, datetime("now"))');
    $stmt->execute([trim($body['course']), trim($body['title']), trim($body['type']), trim($body['url'])]);
    respond(['ok' => true], 201);
}
if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'proposals' && $method === 'POST' && in_array($parts[3], ['approve', 'reject'], true)) {
    $id = filter_var($parts[2], FILTER_VALIDATE_INT); if (!$id) respond(['error' => 'id invalide'], 422);
    $status = $parts[3] === 'approve' ? 'approved' : 'rejected';
    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE proposals SET status = ? WHERE id = ?'); $stmt->execute([$status, $id]);
    if ($status === 'approved') {
        $proposal = $db->prepare('SELECT course, title, resource_type, url FROM proposals WHERE id = ?'); $proposal->execute([$id]); $item = $proposal->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            $db->prepare('INSERT OR IGNORE INTO courses (name) VALUES (?)')->execute([$item['course']]);
            $resource = ['id' => (string)time() . $id, 'title' => $item['title'], 'course' => $item['course'], 'type' => $item['resource_type'], 'url' => $item['url']];
            $db->prepare('INSERT OR REPLACE INTO resources (id, payload) VALUES (?, ?)')->execute([$resource['id'], json_encode($resource)]);
        }
    }
    $db->commit(); respond(['ok' => true]);
}
respond(['error' => 'Route inconnue'], 404);
