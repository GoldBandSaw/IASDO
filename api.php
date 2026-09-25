<?php
declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

function sanitizeUrl($url) {
    if (!$url) return '';
    $parsed = parse_url($url);
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'])) return '';
    return $url;
}

$db = database();
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/', '/');
$parts = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') requireSameOrigin();

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
if ($path === 'api/admin/login' && $method === 'POST') {
    $body = jsonBody();
    $username = strtolower(trim((string)($body['username'] ?? '')));
    $password = (string)($body['password'] ?? '');
    $stmt = $db->prepare("SELECT username, display_name, role, password_hash FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !password_verify($password, $admin['password_hash'])) respond(['error' => 'Identifiants administrateur incorrects.'], 401);
    startSession('CAMPUSFLOW_ADMIN_SESSION');
    session_regenerate_id(true);
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['authenticated_at'] = time();
    respond(['ok' => true]);
}
if ($path === 'api/admin/logout' && $method === 'POST') {
    startSession('CAMPUSFLOW_ADMIN_SESSION');
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
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
    $body = jsonBody();
    $name = trim((string)($body['displayName'] ?? ''));
    $pic = trim((string)($body['profilePicture'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80) respond(['error' => 'Nom invalide.'], 422);
    // Accept: empty string (clear), https URL, or data URI (base64 image uploaded from client)
    if ($pic !== '') {
        $isDataUri = str_starts_with($pic, 'data:image/');
        $isUrl = filter_var($pic, FILTER_VALIDATE_URL) !== false;
        if (!$isDataUri && !$isUrl) respond(['error' => 'Format de photo invalide.'], 422);
        // Limit base64 payload to ~5MB (base64 of 5MB ≈ 6.7MB string)
        if (strlen($pic) > 7_000_000) respond(['error' => 'Image trop volumineuse (max 5 Mo).'], 413);
    }
    $stmt = $db->prepare('UPDATE users SET display_name = ?, profile_picture = ? WHERE username = ?');
    $stmt->execute([$name, $pic, $user['username']]);
    respond(['ok' => true, 'display_name' => $name, 'profile_picture' => $pic]);
}
if ($path === 'api/auth/logout' && $method === 'POST') {
    startSession();
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
}
if ($path === 'api/public/state' && $method === 'GET') {
    $resources = array_map(function ($row) {
        $resource = json_decode($row['payload'], true);
        if (!is_array($resource)) return null;
        return array_intersect_key($resource, array_flip(['id', 'title', 'course', 'type', 'url', 'created_at']));
    }, $db->query('SELECT payload FROM resources ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC));
    respond(['courses' => $db->query('SELECT name FROM courses ORDER BY name')->fetchAll(PDO::FETCH_COLUMN), 'resources' => array_values(array_filter($resources, 'is_array'))]);
}
$adminRoute = ($path === 'api/admin/resources') || (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'admin' && $parts[2] === 'resources') || ($path === 'api/admin/reports') || ($path === 'api/proposals' && $method === 'GET') || (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'proposals');
$downloadRoute = count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'resources' && $parts[3] === 'download';
if ($downloadRoute) {
    $authenticatedUser = currentUser() ?: currentAdmin();
    if (!$authenticatedUser) {
        respond(['error' => 'Authentification requise'], 401);
    }
} else {
    $authenticatedUser = $adminRoute ? requireAdmin() : requireUser();
}
$username = (string)$authenticatedUser['username'];

if ($path === 'api/timetable' && $method === 'GET') {
    $url = getenv('COMMON_CALENDAR_URL');
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
    $stmt = $db->prepare("SELECT payload FROM tasks WHERE payload->>'owner' = ? OR payload->>'shared' = 'true'");
    $stmt->execute([$username]);
    $taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tasks = array_map(fn($row) => json_decode($row['payload'], true), $taskRows);
    $settingsStmt = $db->prepare('SELECT payload FROM user_settings WHERE username = ?');
    $settingsStmt->execute([$username]);
    $settingsRow = $settingsStmt->fetchColumn();
    $settings = $settingsRow ? json_decode($settingsRow, true) : null;
    $courses = $db->query('SELECT name FROM courses ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    // Resources loaded separately via /api/resources for performance — only send recent 5 here
    $resStmt = $db->prepare("SELECT payload FROM resources ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT 5");
    $resStmt->execute();
    $resources = array_values(array_filter(array_map(fn($row) => json_decode($row['payload'], true), $resStmt->fetchAll(PDO::FETCH_ASSOC)), 'is_array'));
    respond(['tasks' => $tasks, 'settings' => $settings, 'courses' => $courses, 'resources' => $resources]);
}
// GET /api/resources — paginated resource listing
if ($path === 'api/resources' && $method === 'GET') {
    // Create index on first access if not exists (idempotent, fast if already exists)
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_resources_created_at ON resources ((payload->>'created_at') DESC NULLS LAST)"); } catch (Throwable) {}
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 50)));
    $before = isset($_GET['before']) ? (string)$_GET['before'] : null;
    $course = isset($_GET['course']) ? trim((string)$_GET['course']) : null;
    $conditions = [];
    $params = [];
    if ($before) {
        $conditions[] = "payload->>'created_at' < ?";
        $params[] = $before;
    }
    if ($course !== null && $course !== '') {
        $conditions[] = "payload->>'course' = ?";
        $params[] = $course;
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $stmt = $db->prepare("SELECT payload FROM resources $where ORDER BY (payload->>'created_at') DESC NULLS LAST LIMIT ?");
    $params[] = $limit;
    $stmt->execute($params);
    $resources = array_values(array_filter(array_map(fn($row) => json_decode($row['payload'], true), $stmt->fetchAll(PDO::FETCH_ASSOC)), 'is_array'));
    respond(['resources' => $resources, 'total' => count($resources)]);
}
if ($path === 'api/tasks' && $method === 'POST') {
    $body = jsonBody();
    $body['id'] = bin2hex(random_bytes(8));
    $body['owner'] = $username;
    $stmt = $db->prepare('INSERT INTO tasks (id, payload) VALUES (?, ?::jsonb)'); 
    $stmt->execute([(string)$body['id'], json_encode($body)]);
    respond($body, 201);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'tasks' && $method === 'PUT') {
    $body = jsonBody();
    if (!isset($body['id']) || (string)$body['id'] !== $parts[2]) respond(['error' => 'id invalide'], 422);
    $body['owner'] = $username;
    $stmt = $db->prepare("UPDATE tasks SET payload = ?::jsonb WHERE id = ? AND payload->>'owner' = ?");
    $stmt->execute([json_encode($body), $parts[2], $username]);
    if ($stmt->rowCount() !== 1) respond(['error' => 'Tâche introuvable'], 404);
    respond($body);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'tasks' && $method === 'DELETE') {
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND payload->>'owner' = ?");
    $stmt->execute([$parts[2], $username]);
    respond(['ok' => true]);
}
if ($path === 'api/settings' && $method === 'PUT') {
    $body = jsonBody();
    $stmt = $db->prepare('INSERT INTO user_settings (username, payload) VALUES (?, ?::jsonb) ON CONFLICT (username) DO UPDATE SET payload = EXCLUDED.payload');
    $stmt->execute([$username, json_encode($body)]);
    respond($body);
}
if ($path === 'api/courses' && $method === 'POST') {
    $name = trim((string)(jsonBody()['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) respond(['error' => 'Matière invalide'], 422);
    $db->prepare('INSERT INTO courses (name) VALUES (?) ON CONFLICT (name) DO NOTHING')->execute([$name]);
    respond(['name' => $name], 201);
}
if ($path === 'api/resources' && $method === 'POST') {
    $body = jsonBody();
    if (empty($body['course']) || empty($body['url'])) respond(['error' => 'Champs requis'], 422);
    $url = sanitizeUrl($body['url']);
    if (!$url) respond(['error' => 'URL invalide'], 422);
    
    $title = trim((string)($body['title'] ?? ''));
    if ($title === '') {
        $context = stream_context_create(['http' => ['timeout' => 3, 'user_agent' => 'CampusFlowBot/1.0']]);
        $html = @file_get_contents($url, false, $context, 0, 8192);
        if ($html && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        } else {
            $title = parse_url($url, PHP_URL_HOST) ?? 'Lien externe';
        }
    }
    
    $type = trim((string)($body['type'] ?? ''));
    if ($type === '') {
        if (str_contains($url, 'notion.so') || str_contains($url, 'notion.site')) $type = 'notion';
        else $type = 'other';
    }

    $resourceId = bin2hex(random_bytes(8));
    $resource = ['id' => $resourceId, 'title' => $title, 'course' => trim((string)$body['course']), 'type' => $type, 'url' => $url, 'owner' => $username, 'created_at' => date('c')];
    $db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb)')->execute([$resourceId, json_encode($resource)]);
    respond($resource, 201);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'resources' && $method === 'PUT') {
    $body = jsonBody();
    if (!isset($body['id']) || (string)$body['id'] !== $parts[2]) respond(['error' => 'id invalide'], 422);
    foreach (['title', 'course', 'type', 'url'] as $field) {
        if (!isset($body[$field]) || trim((string)$body[$field]) === '') respond(['error' => "$field requis"], 422);
    }
    $url = sanitizeUrl($body['url']);
    if (!$url) respond(['error' => 'URL invalide'], 422);
    $resource = ['id' => $parts[2], 'title' => trim((string)$body['title']), 'course' => trim((string)$body['course']), 'type' => trim((string)$body['type']), 'url' => $url, 'owner' => $username];
    $stmt = $db->prepare("UPDATE resources SET payload = ?::jsonb WHERE id = ? AND payload->>'owner' = ?");
    $stmt->execute([json_encode($resource), $parts[2], $username]);
    if ($stmt->rowCount() !== 1) respond(['error' => 'Ressource introuvable'], 404);
    respond($resource);
}
if ($path === 'api/resources/upload' && $method === 'POST') {
    $course = trim((string)($_POST['course'] ?? ''));
    $title  = trim((string)($_POST['title']  ?? ''));
    $file   = $_FILES['file'] ?? null;

    // Accepted MIME types → resource type label
    $allowedMime = [
        'application/pdf'  => 'pdf',
        'text/markdown'    => 'markdown',
        'text/plain'       => 'markdown',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/msword' => 'docx',                    // legacy .doc
        'application/vnd.ms-excel'       => 'xlsx',        // legacy .xls
        'application/vnd.ms-powerpoint'  => 'pptx',        // legacy .ppt
        'application/octet-stream'       => null,           // fallback – check extension below
        'application/zip'                => null,           // .docx/.pptx/.xlsx are ZIP-based
    ];
    // Extension-based type map for common Office and document formats (covers misdetected MIMEs)
    $allowedExt = [
        'pdf'  => 'pdf',
        'md'   => 'markdown', 'markdown' => 'markdown', 'txt' => 'markdown',
        'docx' => 'docx', 'doc' => 'docx',
        'pptx' => 'pptx', 'ppt' => 'pptx',
        'xlsx' => 'xlsx', 'xls' => 'xlsx',
        'png'  => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'webp' => 'image',
    ];

    if ($course === '' || !$file || !isset($file['tmp_name']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        respond(['error' => 'Fichier invalide ou matière manquante.'], 422);
    }
    if ((int)$file['size'] > 50 * 1024 * 1024) {
        respond(['error' => 'Le fichier ne doit pas dépasser 50 Mo.'], 413);
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($file['tmp_name']);

    // Determine resource type: prefer MIME, fall back to extension
    $type = null;
    $isImage = str_starts_with($mime, 'image/') && isset($allowedExt[$extension]) && $allowedExt[$extension] === 'image';
    if ($isImage) {
        $type = 'image';
    } elseif (isset($allowedMime[$mime]) && $allowedMime[$mime] !== null) {
        $type = $allowedMime[$mime];
    } elseif (isset($allowedExt[$extension])) {
        $type = $allowedExt[$extension];
        // Override MIME to something reasonable for storage (avoid sending 'application/octet-stream' for .docx)
        if ($mime === 'application/octet-stream' || $mime === 'application/zip') {
            $officeMimes = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc'  => 'application/msword',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            $mime = $officeMimes[$extension] ?? $mime;
        }
    }

    if ($type === null) {
        respond(['error' => "Format non autorisé (.$extension). Formats acceptés : PDF, Word (.doc/.docx), PowerPoint, Excel, Markdown, images."], 415);
    }
    $resourceId = bin2hex(random_bytes(12));
    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename((string)$file['name'])) ?: 'fichier';
    $objectPath = $username . '/' . $resourceId . '-' . $safeName;
    [$storageUrl, $storageKey, $bucket] = storageConfig();
    $content = file_get_contents($file['tmp_name']);
    [$status] = storageRequest('POST', '/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($objectPath)), $content, ['Content-Type: ' . $mime, 'x-upsert: false']);
    if ($status < 200 || $status >= 300) respond(['error' => 'Le fichier n’a pas pu être stocké.'], 502);
    $resource = ['id' => $resourceId, 'title' => $title !== '' ? $title : pathinfo((string)$file['name'], PATHINFO_FILENAME), 'course' => $course, 'type' => $type, 'file_name' => (string)$file['name'], 'file_path' => $objectPath, 'owner' => $username, 'created_at' => date('c')];
    $db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb)')->execute([$resourceId, json_encode($resource)]);
    respond($resource, 201);
}
if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'resources' && $parts[3] === 'download' && $method === 'GET') {
    $stmt = $db->prepare('SELECT payload FROM resources WHERE id = ?');
    $stmt->execute([$parts[2]]);
    $resource = json_decode((string)$stmt->fetchColumn(), true);
    if (!is_array($resource) || empty($resource['file_path'])) respond(['error' => 'Ressource introuvable'], 404);
    [$storageUrl, $storageKey, $bucket] = storageConfig();
    [$status, $body] = storageRequest('POST', '/object/sign/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($resource['file_path'])), json_encode(['expiresIn' => 300]), ['Content-Type: application/json']);
    $signed = json_decode($body, true);
    if ($status < 200 || $status >= 300 || empty($signed['signedURL'])) respond(['error' => 'Téléchargement indisponible.'], 502);
    header('Location: ' . $storageUrl . '/storage/v1' . $signed['signedURL']);
    exit;
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'resources' && $parts[2] !== '' && $method === 'POST') {
    $body = jsonBody();
    if (empty($body['report'])) respond(['error' => 'Requête invalide.'], 422);
    $reason = trim((string)($body['reason'] ?? ''));
    if ($reason === '' || mb_strlen($reason) > 500) respond(['error' => 'Motif invalide.'], 422);
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$parts[2]]);
    if (!$check->fetchColumn()) respond(['error' => 'Ressource introuvable.'], 404);
    $stmt = $db->prepare('INSERT INTO resource_reports (resource_id, reporter, reason) VALUES (?, ?, ?) ON CONFLICT (resource_id, reporter) DO UPDATE SET reason = EXCLUDED.reason, status = \'open\', created_at = NOW()');
    $stmt->execute([$parts[2], $username, $reason]);
    respond(['ok' => true], 201);
}
if (count($parts) === 3 && $parts[0] === 'api' && $parts[1] === 'resources' && $method === 'DELETE') {
    $find = $db->prepare("SELECT payload FROM resources WHERE id = ? AND (payload->>'owner' = ? OR ? = 'admin')");
    $find->execute([$parts[2], $username, $authenticatedUser['role'] ?? 'student']);
    $payloadStr = $find->fetchColumn();
    if (!$payloadStr) respond(['error' => 'Ressource introuvable ou accès refusé'], 404);
    
    $resource = json_decode((string)$payloadStr, true);
    if (is_array($resource) && !empty($resource['file_path'])) {
        try { [$storageUrl, $storageKey, $bucket] = storageConfig(); storageRequest('DELETE', '/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($resource['file_path']))); } catch (Throwable $error) { error_log($error->getMessage()); }
    }
    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$parts[2]]);
    respond(['ok' => true]);
}
if ($path === 'api/admin/resources' && $method === 'GET') {
    $resources = array_map(fn($row) => json_decode($row['payload'], true), $db->query('SELECT payload FROM resources ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC));
    respond(array_values(array_filter($resources, 'is_array')));
}
if ($path === 'api/admin/reports' && $method === 'GET') {
    $reports = $db->query("SELECT r.id, r.resource_id, r.reporter, r.reason, r.status, r.created_at, res.payload FROM resource_reports r LEFT JOIN resources res ON res.id = r.resource_id WHERE r.status = 'open' ORDER BY r.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    respond(array_map(function (array $row): array {
        $resource = json_decode((string)$row['payload'], true);
        unset($row['payload']);
        $row['resource'] = is_array($resource) ? $resource : null;
        return $row;
    }, $reports));
}
if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'admin' && $parts[2] === 'reports' && $method === 'POST') {
    $stmt = $db->prepare("UPDATE resource_reports SET status = 'closed' WHERE id = ?");
    $stmt->execute([(int)$parts[3]]);
    respond(['ok' => true]);
}
if (count($parts) === 4 && $parts[0] === 'api' && $parts[1] === 'admin' && $parts[2] === 'resources' && $method === 'DELETE') {
    $find = $db->prepare('SELECT payload FROM resources WHERE id = ?');
    $find->execute([$parts[3]]);
    $resource = json_decode((string)$find->fetchColumn(), true);
    if (is_array($resource) && !empty($resource['file_path'])) {
        [$storageUrl, $storageKey, $bucket] = storageConfig();
        storageRequest('DELETE', '/object/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($resource['file_path'])));
    }
    $db->prepare('DELETE FROM resources WHERE id = ?')->execute([$parts[3]]);
    respond(['ok' => true]);
}
if ($path === 'api/proposals' && $method === 'GET') {
    respond($db->query("SELECT id, course, title, resource_type, url, created_at, status FROM proposals ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC));
}
if ($path === 'api/proposals' && $method === 'POST') {
    $body = jsonBody();
    foreach (['course', 'title', 'type', 'url'] as $field) if (!isset($body[$field]) || trim((string)$body[$field]) === '') respond(['error' => "$field requis"], 422);
    $url = sanitizeUrl($body['url']);
    if (!$url) respond(['error' => 'URL invalide'], 422);
    $stmt = $db->prepare('INSERT INTO proposals (course, title, resource_type, url) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($body['course']), trim($body['title']), trim($body['type']), $url]);
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
            $db->prepare('INSERT INTO courses (name) VALUES (?) ON CONFLICT (name) DO NOTHING')->execute([$item['course']]);
            $resource = ['id' => (string)time() . $id, 'title' => $item['title'], 'course' => $item['course'], 'type' => $item['resource_type'], 'url' => $item['url']];
            $db->prepare('INSERT INTO resources (id, payload) VALUES (?, ?::jsonb) ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload')->execute([$resource['id'], json_encode($resource)]);
        }
    }
    $db->commit(); respond(['ok' => true]);
}
// GET /api/chat?after=ID — Get chat messages
if ($method === 'GET' && $path === 'api/chat') {
    $after = isset($_GET['after']) ? (int)$_GET['after'] : 0;
    $stmt = $db->prepare('SELECT m.id, m.username, m.content, m.created_at, u.profile_picture,
        COALESCE(u.display_name, m.username) as display_name
        FROM messages m LEFT JOIN users u ON m.username = u.username
        WHERE m.id > ? ORDER BY m.id ASC LIMIT 100');
    $stmt->execute([$after]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respond(['messages' => $messages]);
}

// POST /api/chat — Send a message
if ($method === 'POST' && $path === 'api/chat') {
    $body = jsonBody();
    $content = trim($body['content'] ?? '');
    if (!$content || strlen($content) > 1000) {
        respond(['error' => 'Message vide ou trop long (max 1000 caractères)'], 400);
    }
    $stmt = $db->prepare('INSERT INTO messages (username, content) VALUES (?, ?) RETURNING id, username, content, created_at');
    $stmt->execute([$username, $content]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    $message['display_name'] = $authenticatedUser['display_name'] ?? $username;
    respond(['ok' => true, 'message' => $message]);
}

respond(['error' => 'Route inconnue'], 404);
