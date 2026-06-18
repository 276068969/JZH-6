<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_USER_KEY = 'user';
    private const SESSION_FINGERPRINT_KEY = '_auth_fingerprint';
    private const SESSION_LAST_ACTIVITY_KEY = '_auth_last_activity';
    private const SESSION_LOGIN_TIME_KEY = '_auth_login_time';
    private const SESSION_STATUS_CHECK_KEY = '_auth_status_checked_at';

    private const STATUS_CHECK_INTERVAL = 300;
    private const SESSION_INACTIVITY_TIMEOUT = 7200;

    public static function user(): ?array
    {
        if (!self::isSessionValid()) {
            return null;
        }

        if (!self::verifyUserStatus()) {
            self::forceLogout('账号已被停用');
            return null;
        }

        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        return self::role() === $role;
    }

    public static function hasAnyRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !hash_equals($user['password_hash'], hash('sha256', $password))) {
            return false;
        }

        if ($user['status'] !== 'enabled') {
            return false;
        }

        self::regenerateSession();

        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];

        self::setSessionFingerprint();
        self::updateActivityTimestamps();

        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::redirectToLogin();
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (!self::hasRole($role)) {
            self::forceLogout('权限不足');
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireLogin();
        if (!self::hasAnyRole($roles)) {
            self::forceLogout('权限不足');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAnyRole(['admin', 'dispatcher']);
    }

    public static function logout(): void
    {
        self::clearSession();
        self::destroySessionCookie();
        session_destroy();
    }

    private static function isSessionValid(): bool
    {
        if (!isset($_SESSION[self::SESSION_USER_KEY])) {
            return false;
        }

        if (!self::verifySessionFingerprint()) {
            self::clearSession();
            return false;
        }

        if (self::isSessionExpired()) {
            self::clearSession();
            return false;
        }

        self::updateActivityTimestamps();

        return true;
    }

    private static function verifySessionFingerprint(): bool
    {
        if (!isset($_SESSION[self::SESSION_FINGERPRINT_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_FINGERPRINT_KEY], self::generateFingerprint());
    }

    private static function setSessionFingerprint(): void
    {
        $_SESSION[self::SESSION_FINGERPRINT_KEY] = self::generateFingerprint();
    }

    private static function generateFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $userAgent . self::getClientIp());
    }

    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function isSessionExpired(): bool
    {
        if (!isset($_SESSION[self::SESSION_LAST_ACTIVITY_KEY])) {
            return true;
        }

        $inactiveSeconds = time() - $_SESSION[self::SESSION_LAST_ACTIVITY_KEY];
        return $inactiveSeconds > self::SESSION_INACTIVITY_TIMEOUT;
    }

    private static function updateActivityTimestamps(): void
    {
        $_SESSION[self::SESSION_LAST_ACTIVITY_KEY] = time();
    }

    private static function verifyUserStatus(): bool
    {
        $user = $_SESSION[self::SESSION_USER_KEY] ?? null;
        if (!$user) {
            return false;
        }

        $lastChecked = $_SESSION[self::SESSION_STATUS_CHECK_KEY] ?? 0;
        if ((time() - $lastChecked) < self::STATUS_CHECK_INTERVAL) {
            return true;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT status FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $user['id']]);
            $dbUser = $stmt->fetch();

            $_SESSION[self::SESSION_STATUS_CHECK_KEY] = time();

            if (!$dbUser || $dbUser['status'] !== 'enabled') {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            error_log('验证用户状态失败: ' . $e->getMessage());
            return true;
        }
    }

    private static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function clearSession(): void
    {
        $_SESSION = [];
    }

    private static function destroySessionCookie(): void
    {
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }

    private static function redirectToLogin(): void
    {
        header('Location: /login');
        exit;
    }

    private static function forceLogout(string $reason = ''): void
    {
        self::logout();
        $query = $reason ? '?reason=' . urlencode($reason) : '';
        header('Location: /login' . $query);
        exit;
    }
}
