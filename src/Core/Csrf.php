<?php
declare(strict_types=1);

namespace App\Core;

class Csrf
{
    public const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function field(string $name = '_csrf'): string
    {
        $t = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . $name . '" value="' . $t . '">';
    }
}
