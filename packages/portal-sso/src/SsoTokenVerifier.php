<?php

namespace Akp\PortalSso;

use Akp\PortalSso\Exceptions\InvalidSsoToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Memeriksa token SSO yang diterbitkan Portal. */
class SsoTokenVerifier
{
    /**
     * @return array<string, mixed> klaim token
     *
     * @throws InvalidSsoToken
     */
    public function verify(string $token): array
    {
        $secret = config('sso.secret');
        if (blank($secret) || blank(config('sso.app_code'))) {
            throw new InvalidSsoToken('Aplikasi ini belum dikonfigurasi untuk login Portal (PORTAL_APP_CODE / PORTAL_SSO_SECRET di .env).');
        }

        try {
            JWT::$leeway = config('sso.leeway');
            $claims = (array) JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable $e) {
            // Pesan pustaka JWT tidak ditampilkan mentah ke pengguna.
            throw new InvalidSsoToken(str_contains($e->getMessage(), 'Expired') ? 'Token login kedaluwarsa. Silakan masuk lagi dari Portal.' : 'Token login tidak valid.', 0, $e);
        }

        if (($claims['aud'] ?? null) !== config('sso.app_code')) {
            throw new InvalidSsoToken('Token ini bukan untuk aplikasi ini.');
        }

        foreach (['sub', 'jti', 'name', 'role', 'exp'] as $required) {
            if (blank($claims[$required] ?? null)) {
                throw new InvalidSsoToken('Token login tidak lengkap.');
            }
        }

        // Sekali pakai: jti yang sama ditolak sampai token pasti sudah kedaluwarsa.
        $ttl = max(1, ((int) $claims['exp']) - time() + config('sso.leeway') + 5);
        if (! Cache::add('sso:jti:'.$claims['jti'], 1, $ttl)) {
            throw new InvalidSsoToken('Token login sudah pernah dipakai. Silakan masuk lagi dari Portal.');
        }

        return $claims;
    }
}