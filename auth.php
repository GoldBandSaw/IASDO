<?php
declare(strict_types=1);

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
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
