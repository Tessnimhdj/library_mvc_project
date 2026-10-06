<?php

namespace Services\Auth;

class AuthService
{
    private const IDLE_TIMEOUT = 1800;
    private const MAX_LIFETIME = 28800;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('LIBSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::cookiePath(),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function login(int $userId, string $username, string $role): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['login_at'] = time();
        $_SESSION['last_activity'] = time();
    }

    public static function check(): bool
    {
        self::startSession();

        $userId = $_SESSION['user_id'] ?? null;
        $lastActivity = $_SESSION['last_activity'] ?? null;
        $loginAt = $_SESSION['login_at'] ?? null;

        if (!is_numeric($userId) || (int) $userId < 1 || !is_numeric($lastActivity) || !is_numeric($loginAt)) {
            return false;
        }

        $now = time();
        if (($now - (int) $lastActivity) >= self::IDLE_TIMEOUT || ($now - (int) $loginAt) >= self::MAX_LIFETIME) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = $now;
        return true;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => (int) $_SESSION['user_id'],
            'username' => (string) $_SESSION['username'],
            'role' => (string) $_SESSION['role'],
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => ($params['path'] ?? '') !== '' ? $params['path'] : '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => ($params['samesite'] ?? '') !== '' ? $params['samesite'] : 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . \Router::url('/auth'), true, 302);
            exit;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    private static function cookiePath(): string
    {
        if (class_exists('Router', false)) {
            $base = \Router::basePath();
            if (is_string($base) && $base !== '') {
                return $base;
            }
        }

        return '/';
    }
}
