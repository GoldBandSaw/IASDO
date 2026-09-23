<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$db = database();
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/', '/');
$parts = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === 'api/auth/me' && $method === 'GET') {
    $user = currentUser();
    if (!$user) respond(['authenticated' => false], 401);
    respond(['authenticated' => true, 'user' => $user]);
}
if ($path === 'api/auth/login' && $method === 'POST') {
    $body = jsonBody();
    $username = strtolower(trim((string)($body['username'] ?? '')));
    $password = (string)($body['password'] ?? '');
    $stmt = $db->prepare('SELECT username, display_name, role, password_hash, setup_used FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$user['setup_used'] || !password_verify($password, $user['password_hash'])) respond(['error' => 'Identifiant ou mot de passe incorrect.'], 401);
    startSession();
    session_regenerate_id(true);
    $_SESSION['username'] = $user['username'];
    $_SESSION['authenticated_at'] = time();
    respond(['authenticated' => true, 'user' => ['username' => $user['username'], 'display_name' => $user['display_name'], 'role' => $user['role']]]);
}
if ($path === 'api/auth/setup' && $method === 'POST') {
    $body = jsonBody();
    $username = strtolower(trim((string)($body['username'] ?? '')));
    $token = trim((string)($body['token'] ?? ''));
    $password = (string)($body['password'] ?? '');
    if (!passwordIsValid($password)) respond(['error' => 'Le mot de passe doit contenir au moins 10 caractères, une lettre et un chiffre.'], 422);
    $update = $db->prepare('UPDATE users SET password_hash = ?, setup_token_hash = \'\', setup_used = TRUE WHERE username = ? AND setup_used = FALSE AND setup_token_hash = ?');
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $username, hash('sha256', $token)]);
    if ($update->rowCount() !== 1) respond(['error' => 'Lien de première connexion invalide ou déjà utilisé.'], 401);
    respond(['ok' => true]);
}
if ($path === 'api/auth/password' && $method === 'PUT') {
    $user = requireUser();
    $body = jsonBody();
    $password = (string)($body['password'] ?? '');
    if (!passwordIsValid($password)) respond(['error' => 'Le mot de passe doit contenir au moins 10 caractères, une lettre et un chiffre.'], 422);
    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['username']]);
    respond(['ok' => true]);
}
if ($path === 'api/auth/profile' && $method === 'PUT') {
    $user = requireUser();
    $name = trim((string)(jsonBody()['displayName'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) respond(['error' => 'Nom invalide.'], 422);
    $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE username = ?');
    $stmt->execute([$name, $user['username']]);
    respond(['ok' => true, 'display_name' => $name]);
}
if ($path === 'api/auth/logout' && $method === 'POST') {
    startSession();
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
}
requireUser();

if ($path === 'api/timetable' && $method === 'GET') {
    $url = getenv('COMMON_CALENDAR_URL') ?: 'https://aderead.univ-orleans.fr/jsp/custom/modules/plannings/anonymous_cal.jsp?data=4cd3f88e35ea1920bb2fb34fc8572ff515958a020261fadcd06b8f9f1ea94c625cb13e04815b0b9371306a590364622aba7aca821742c72906697de27ff79d4cf1f995a532c8174ffc4cd3c5822313a409547008355ac6ff85c3d0ba2e9efbe6,1';
    $context = stream_context_create(['http' => [
        'timeout' => 15,
        'ignore_errors' => true,
        'header' => "User-Agent: CampusFlow/1.0\r\nAccept: text/calendar,text/plain\r\n"
    ]]);
    $ics = @file_get_contents($url, false, $context);
    $status = $http_response_header[0] ?? '';
    if ($ics === false || !preg_match('/\s2\d\d\s/', $status)) {
        respond(['error' => 'Le calendrier universitaire est momentanément indisponible.'], 502);
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo $ics;
    exit;
}

if ($path === 'api/state' && $method === 'GET') {
    $tasks = array_map(fn($row) => json_decode($row['payload'], true), $db->query('SELECT payload FROM tasks')->fetchAll(PDO::FETCH_ASSOC));
    $settingsRow = $db->query('SELECT payload FROM settings WHERE id = 1')->fetchColumn();
    $courses = $db->query('SELECT name FROM courses ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $resources = array_map(fn($row) => json_decode($row['payload'], true), $db->query('SELECT payload FROM resources')->fetchAll(PDO::FETCH_ASSOC));
    respond(['tasks' => $tasks, 'settings' => $settingsRow ? json_decode($settingsRow, true) : null, 'courses' => $courses, 'resources' => $resources]);
}
if ($path === 'api/tasks' && $method === 'POST') {
    $body = jsonBody(); if (!isset($body['id'])) respond(['error' => 'id requis'], 422);
    $stmt = $db->prepare('INSERT INTO tasks (id, payload) VALUES (?, ?::jsonb) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload'); $stmt->execute([(string)$body['id'], json_encode($body)]);
    respond($body, 201);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'tasks' && $method === 'PUT') {
    $body = jsonBody();
    if (!isset($body['id']) || (string)$body['id'] !== $parts[2]) respond(['error' => 'id invalide'], 422);
    $stmt = $db->prepare('UPDATE tasks SET payload = ?::jsonb WHERE id = ?');
    $stmt->execute([json_encode($body), $parts[2]]);
    respond($body);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'tasks' && $method === 'DELETE') {
    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ?');
    $stmt->execute([$parts[2]]);
    respond(['ok' => true]);
}
if ($path === 'api/settings' && $method === 'PUT') {
    $body = jsonBody(); $stmt = $db->prepare('INSERT INTO settings (id, payload) VALUES (1, ?::jsonb) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload'); $stmt->execute([json_encode($body)]); respond($body);
}
if ($path === 'api/courses' && $method === 'POST') {
    $name = trim((string)(jsonBody()['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) respond(['error' => 'Matière invalide'], 422);
    $db->prepare('INSERT INTO courses (name) VALUES (?) ON CONFLICT (name) DO NOTHING')->execute([$name]);
    respond(['name' => $name], 201);
}
if ($path === 'api/resources' && $method === 'POST') {
    $body = jsonBody();
    foreach (['id', 'title', 'course', 'type', 'url'] as $field) {
        if (!isset($body[$field]) || trim((string)$body[$field]) === '') respond(['error' => "$field requis"], 422);
    }
    if (!filter_var($body['url'], FILTER_VALIDATE_URL) || !in_array(parse_url($body['url'], PHP_URL_SCHEME), ['http', 'https'], true)) respond(['error' => 'URL invalide'], 422);
    $resource = ['id' => (string)$body['id'], 'title' => trim((string)$body['title']), 'course' => trim((string)$body['course']), 'type' => trim((string)$body['type']), 'url' => trim((string)$body['url'])];
    $db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload')->execute([$resource['id'], json_encode($resource)]);
    respond($resource, 201);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'resources' && $method === 'DELETE') {
    $db->prepare('DELETE FROM resources WHERE id = ?')->execute([$parts[2]]);
    respond(['ok' => true]);
}
if ($path === 'api/proposals' && $method === 'GET') {
    requireAdmin();
    respond($db->query("SELECT id, course, title, resource_type, url, created_at FROM proposals WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC));
}
if ($path === 'api/proposals' && $method === 'POST') {
    $body = jsonBody();
    foreach (['course', 'title', 'type', 'url'] as $field) if (!isset($body[$field]) || trim((string)$body[$field]) === '') respond(['error' => "$field requis"], 422);
    if (!filter_var($body['url'], FILTER_VALIDATE_URL) || !in_array(parse_url($body['url'], PHP_URL_SCHEME), ['http', 'https'], true)) respond(['error' => 'URL invalide'], 422);
    $stmt = $db->prepare('INSERT INTO proposals (course, title, resource_type, url) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($body['course']), trim($body['title']), trim($body['type']), trim($body['url'])]);
    respond(['ok' => true], 201);
}
if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'proposals' && $method === 'POST' && in_array($parts[3], ['approve', 'reject'], true)) {
    requireAdmin();
    $id = filter_var($parts[2], FILTER_VALIDATE_INT); if (!$id) respond(['error' => 'id invalide'], 422);
    $status = $parts[3] === 'approve' ? 'approved' : 'rejected';
    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE proposals SET status = ? WHERE id = ?'); $stmt->execute([$status, $id]);
    if ($status === 'approved') {
        $proposal = $db->prepare('SELECT course, title, resource_type, url FROM proposals WHERE id = ?'); $proposal->execute([$id]); $item = $proposal->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            $db->prepare('INSERT INTO courses (name) VALUES (?) ON CONFLICT (name) DO NOTHING')->execute([$item['course']]);
            $resource = ['id' => (string)time() . $id, 'title' => $item['title'], 'course' => $item['course'], 'type' => $item['resource_type'], 'url' => $item['url']];
            $db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload')->execute([$resource['id'], json_encode($resource)]);
        }
    }
    $db->commit(); respond(['ok' => true]);
}
respond(['error' => 'Route inconnue'], 404);
