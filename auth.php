<?php
declare(strict_types=1);

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $handler = new class implements SessionHandlerInterface {
            public function open(string $path, string $name): bool { return true; }
            public function close(): bool { return true; }
            public function read(string $id): string {
                $stmt = database()->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity >= ?');
                $stmt->execute([$id, time() - 7 * 24 * 60 * 60]);
                return (string)($stmt->fetchColumn() ?: '');
            }
            public function write(string $id, string $data): bool {
                $stmt = database()->prepare('INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity');
                return $stmt->execute([$id, $data, time()]);
            }
            public function destroy(string $id): bool {
                $stmt = database()->prepare('DELETE FROM sessions WHERE id = ?');
                return $stmt->execute([$id]);
            }
            public function gc(int $max_lifetime): int|false {
                $stmt = database()->prepare('DELETE FROM sessions WHERE last_activity < ?');
                $stmt->execute([time() - 7 * 24 * 60 * 60]);
                return $stmt->rowCount();
            }
        };
        session_set_save_handler($handler, true);
        session_set_cookie_params([
            'lifetime' => 7 * 24 * 60 * 60,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        ]);
        session_start();
    }
}

function currentUser(): ?array {
    startSession();
    if (empty($_SESSION['username']) || empty($_SESSION['authenticated_at'])) return null;
    if (time() - (int)$_SESSION['authenticated_at'] > 7 * 24 * 60 * 60) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $stmt = database()->prepare('SELECT username, display_name FROM users WHERE username = ?');
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function requireUser(): array {
    $user = currentUser();
    if (!$user) respond(['error' => 'Authentification requise'], 401);
    return $user;
}

function passwordIsValid(string $password): bool {
    return strlen($password) >= 10 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}
