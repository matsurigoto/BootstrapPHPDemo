<?php
declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start(array $cfg): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name($cfg['name'] ?? 'PHPSESSID');
        session_set_cookie_params([
            'lifetime' => (int)($cfg['lifetime'] ?? 7200),
            'path'     => '/',
            'secure'   => (bool)($cfg['secure'] ?? false),
            'httponly' => (bool)($cfg['httponly'] ?? true),
            'samesite' => $cfg['samesite'] ?? 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, $value = null)
    {
        if ($value === null) {
            $v = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $v;
        }
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
