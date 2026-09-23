<?php
declare(strict_types=1);

function database(): PDO {
    static $db;
    if ($db instanceof PDO) return $db;
    $url = getenv('DATABASE_URL') ?: '';
    if ($url === '') throw new RuntimeException('DATABASE_URL manquante.');
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || empty($parts['user']) || !isset($parts['pass'])) {
        throw new RuntimeException('DATABASE_URL invalide.');
    }
    $database = ltrim($parts['path'] ?? '', '/');
    $dsn = 'pgsql:host=' . $parts['host'] . ';port=' . ($parts['port'] ?? 5432) . ';dbname=' . $database . ';sslmode=require';
    $db = new PDO($dsn, urldecode($parts['user']), urldecode($parts['pass']));
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS tasks (id TEXT PRIMARY KEY, payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK (id = 1), payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS courses (name TEXT PRIMARY KEY)');
    $db->exec('CREATE TABLE IF NOT EXISTS resources (id TEXT PRIMARY KEY, payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS proposals (id BIGSERIAL PRIMARY KEY, course TEXT NOT NULL, title TEXT NOT NULL, resource_type TEXT NOT NULL, url TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\', created_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
    $db->exec('CREATE TABLE IF NOT EXISTS users (username TEXT PRIMARY KEY, display_name TEXT NOT NULL, password_hash TEXT NOT NULL DEFAULT \'\', setup_token_hash TEXT NOT NULL DEFAULT \'\', setup_used BOOLEAN NOT NULL DEFAULT FALSE, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
    $db->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, data TEXT NOT NULL, last_activity BIGINT NOT NULL)');
    $users = ['antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric'];
    $insert = $db->prepare('INSERT INTO users (username, display_name, setup_token_hash) VALUES (?, ?, ?) ON CONFLICT (username) DO NOTHING');
    foreach ($users as $username) {
        $insert->execute([$username, $username, hash('sha256', bin2hex(random_bytes(32)))]);
    }
    return $db;
}

function jsonBody(): array {
    $data = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($data) ? $data : [];
}

function respond(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
