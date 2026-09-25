<?php
declare(strict_types=1);

function initializeSchema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS tasks (id TEXT PRIMARY KEY, payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK (id = 1), payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS user_settings (username TEXT PRIMARY KEY, payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS resource_reports (id BIGSERIAL PRIMARY KEY, resource_id TEXT NOT NULL, reporter TEXT NOT NULL, reason TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'open\', created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), UNIQUE (resource_id, reporter))');
    $db->exec('CREATE TABLE IF NOT EXISTS courses (name TEXT PRIMARY KEY)');
    $db->exec('CREATE TABLE IF NOT EXISTS resources (id TEXT PRIMARY KEY, payload JSONB NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS proposals (id BIGSERIAL PRIMARY KEY, course TEXT NOT NULL, title TEXT NOT NULL, resource_type TEXT NOT NULL, url TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\', created_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
    $db->exec('CREATE TABLE IF NOT EXISTS users (username TEXT PRIMARY KEY, display_name TEXT NOT NULL, password_hash TEXT NOT NULL DEFAULT \'\', setup_token_hash TEXT NOT NULL DEFAULT \'\', setup_used BOOLEAN NOT NULL DEFAULT FALSE, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role TEXT NOT NULL DEFAULT 'student'");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture TEXT NOT NULL DEFAULT ''");
    $db->exec('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_fixed_username');
    $db->exec("ALTER TABLE users ADD CONSTRAINT users_fixed_username CHECK (username IN ('admin', 'antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric'))");
    $db->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, data TEXT NOT NULL, last_activity BIGINT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS messages (id BIGSERIAL PRIMARY KEY, username TEXT NOT NULL, content TEXT NOT NULL, created_at TIMESTAMPTZ DEFAULT NOW())');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_messages_id ON messages (id)');
    
    $users = ['antonin', 'lucas', 'aymen', 'youssef', 'maelle', 'jason', 'nolann', 'leon', 'roman', 'cedric'];
    $initialPassword = getenv('INITIAL_PASSWORD') ?: 'CampusFlow2026!';
    $initialHash = password_hash($initialPassword, PASSWORD_DEFAULT);
    $insert = $db->prepare('
        INSERT INTO users (username, display_name, password_hash, setup_used)
        VALUES (?, ?, ?, TRUE)
        ON CONFLICT (username) DO UPDATE SET
            password_hash = CASE WHEN users.password_hash = \'\' THEN EXCLUDED.password_hash ELSE users.password_hash END,
            setup_used = CASE WHEN users.password_hash = \'\' THEN TRUE ELSE users.setup_used END
    ');
    foreach ($users as $username) {
        $insert->execute([$username, $username, $initialHash]);
    }
    $adminPassword = getenv('ADMIN_PASSWORD');
    if ($adminPassword) {
        $admin = $db->prepare('
            INSERT INTO users (username, display_name, password_hash, setup_used, role)
            VALUES (\'admin\', \'Administrateur\', ?, TRUE, \'admin\')
            ON CONFLICT (username) DO UPDATE SET
                role = \'admin\',
                password_hash = CASE WHEN users.password_hash = \'\' THEN EXCLUDED.password_hash ELSE users.password_hash END
        ');
        $admin->execute([password_hash($adminPassword, PASSWORD_DEFAULT)]);
    }
}

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
    
    static $initialized = false;
    if (!$initialized) {
        initializeSchema($db);
        $initialized = true;
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

function storageConfig(): array {
    $url = rtrim((string)getenv('SUPABASE_URL'), '/');
    $key = (string)getenv('SUPABASE_SERVICE_ROLE_KEY');
    $bucket = (string)(getenv('SUPABASE_STORAGE_BUCKET') ?: 'campusflow-resources');
    if ($url === '' || $key === '') throw new RuntimeException('Configuration Supabase Storage manquante.');
    return [$url, $key, $bucket];
}

function storageRequest(string $method, string $path, ?string $body = null, array $headers = []): array {
    [$url, $key] = storageConfig();
    $handle = curl_init($url . '/storage/v1' . $path);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array_merge(['Authorization: Bearer ' . $key, 'apikey: ' . $key], $headers),
    ]);
    if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    return [$status, (string)$response];
}
