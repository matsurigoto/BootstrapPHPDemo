<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Session;

class AuthGuard
{
    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (($u['role'] ?? 'USER') === 'ADMIN');
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }
}
