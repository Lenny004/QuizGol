<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie httponly del jugador (session_token de RoomPlayer).
 */
class PlayerSessionCookie
{
    public const NAME = 'quizgol_player';

    /** Duración: 7 días (rejoin en la misma sesión escolar). */
    public const MINUTES = 60 * 24 * 7;

    public static function make(string $sessionToken): Cookie
    {
        $secure = self::shouldUseSecure();

        return cookie(
            self::NAME,
            $sessionToken,
            self::MINUTES,
            '/',
            null,
            $secure,
            true, // httpOnly
            false,
            'lax' // sameSite
        );
    }

    public static function token(?Request $request = null): ?string
    {
        $request ??= request();

        $token = $request->cookie(self::NAME);

        return is_string($token) && $token !== '' ? $token : null;
    }

    private static function shouldUseSecure(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        $appUrl = (string) config('app.url', '');

        return str_starts_with($appUrl, 'https://') || request()->secure();
    }
}
