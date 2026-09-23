<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
$db = database();
$base = rtrim((string)($argv[1] ?? 'http://localhost:8000'), '/');
$update = $db->prepare('UPDATE users SET setup_token_hash = ?, setup_used = 0, password_hash = "" WHERE username = ?');
$users = $db->query('SELECT username FROM users ORDER BY username')->fetchAll(PDO::FETCH_COLUMN);
echo "Liens de première connexion (à transmettre individuellement)".PHP_EOL;
foreach ($users as $username) {
    $token = bin2hex(random_bytes(32));
    $update->execute([hash('sha256', $token), $username]);
    echo $username . ': ' . $base . '/setup.html?username=' . rawurlencode($username) . '&token=' . $token . PHP_EOL;
}
