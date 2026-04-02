<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class AuthCookieManager
{
    public const ACCESS_COOKIE = 'kermesse_access';
    public const REFRESH_COOKIE = 'kermesse_refresh';

    /**
     * @param array{accessToken:string,refreshToken:string,accessExpiresAt:\DateTimeImmutable,refreshExpiresAt:\DateTimeImmutable} $tokenPair
     *
     * @return array{access:Cookie,refresh:Cookie}
     */
    public function createCookies(Request $request, array $tokenPair): array
    {
        $secure = $request->isSecure();

        return [
            'access' => Cookie::create(self::ACCESS_COOKIE)
                ->withValue($tokenPair['accessToken'])
                ->withHttpOnly(true)
                ->withSecure($secure)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/')
                ->withExpires($tokenPair['accessExpiresAt']),
            'refresh' => Cookie::create(self::REFRESH_COOKIE)
                ->withValue($tokenPair['refreshToken'])
                ->withHttpOnly(true)
                ->withSecure($secure)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/')
                ->withExpires($tokenPair['refreshExpiresAt']),
        ];
    }

    /**
     * @return array{access:Cookie,refresh:Cookie}
     */
    public function clearCookies(Request $request): array
    {
        $secure = $request->isSecure();

        return [
            'access' => Cookie::create(self::ACCESS_COOKIE)
                ->withValue('')
                ->withHttpOnly(true)
                ->withSecure($secure)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/')
                ->withExpires(1),
            'refresh' => Cookie::create(self::REFRESH_COOKIE)
                ->withValue('')
                ->withHttpOnly(true)
                ->withSecure($secure)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/')
                ->withExpires(1),
        ];
    }
}
