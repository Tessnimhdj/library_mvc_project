<?php

namespace Services\Auth;

/**
 * Stores the staff session. This service does not render pages.
 */
class AuthService
{
    private const IDLE_TIMEOUT = 1800;
    private const MAX_LIFETIME = 28800;

    /**
     * Starts the staff session once, with a fixed cookie name and flags.
     */
    public static function startSession(): void
    {
        // لا تعِد تشغيل جلسة قائمة
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

    /**
     * Stores the logged-in staff member and rotates the session id.
     */
    public static function login(int $userId, string $username, string $role): void
    {
        // تثبيت الجلسة ثم حفظ بيانات الدخول
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['login_at'] = time();
        $_SESSION['last_activity'] = time();
    }

    /**
     * Returns true when the session is present and still inside both time limits.
     */
    public static function check(): bool
    {
        // رفض الجلسة المنتهية وتحديث النشاط عند النجاح
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

    /**
     * Returns the logged-in staff member, or null when the session is not valid.
     *
     * @return array{id: int, username: string, role: string}|null
     */
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

    /**
     * Clears the staff session and expires its cookie.
     */
    public static function logout(): void
    {
        // مسح بيانات الجلسة وإنهاء ملف تعريف الارتباط
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

    /**
     * Sends guests to the login page. The target is fixed and never read from the request.
     */
    public static function requireLogin(): void
    {
        // إعادة الزائر إلى صفحة الدخول، ومنع تخزين الصفحات المحمية
        if (!self::check()) {
            header('Location: ' . \Router::url('/auth'), true, 302);
            exit;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    /**
     * Uses the application base path as the session cookie path.
     */
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
