<?php

namespace Akp\PortalSso\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Alamat Portal yang dipakai aplikasi ini untuk mengarahkan pengguna kembali ke Portal.
 *
 * Portal menyertakan alamatnya sendiri (alamat yang dipakai pengguna untuk membuka Portal) di token login, jadi
 * aplikasi tidak perlu diisi alamat Portal secara tetap. Urutan pemakaian:
 *   1. PORTAL_URL yang memakai penanda ({host}/{base}): selalu menang, karena memang sengaja dibuat dinamis.
 *   2. Alamat dari token login sesi ini (benar untuk pengguna ini, entah lewat IP atau domain).
 *   3. Alamat terakhir yang dikirim Portal (untuk pengguna yang belum punya sesi).
 *   4. PORTAL_URL biasa, disesuaikan bila masih localhost.
 */
class PortalAddress
{
    private const SESSION_KEY = 'portal.url';

    private const CACHE_KEY = 'portal-sso:portal-url';

    /** Simpan alamat Portal dari klaim token (hanya bila berupa alamat http/https yang valid). */
    public static function remember(mixed $claim): void
    {
        $url = self::clean($claim);

        if ($url === null) {
            return;
        }

        try {
            session()->put(self::SESSION_KEY, $url);
            Cache::forever(self::CACHE_KEY, $url);
        } catch (\Throwable) {
            // Penyimpanan cache/sesi tidak boleh menggagalkan login.
        }
    }

    public static function resolve(): string
    {
        $configured = (string) config('sso.portal_url');

        if (self::placeholderApplies($configured)) {
            return HostUrl::adapt($configured);
        }

        try {
            if ($url = self::clean(session()->get(self::SESSION_KEY))) {
                return $url;
            }
            if ($url = self::clean(Cache::get(self::CACHE_KEY))) {
                return $url;
            }
        } catch (\Throwable) {
            // jatuh ke konfigurasi
        }

        return HostUrl::adapt($configured);
    }

    /** {host} selalu bisa dipakai; {base} hanya bermakna bila pengguna membuka lewat subdomain (bukan IP atau nama pendek). */
    private static function placeholderApplies(string $configured): bool
    {
        if (str_contains($configured, '{base}')) {
            return HostUrl::isSubdomain(request()->getHost());
        }

        return str_contains($configured, '{host}');
    }

    /** Hanya skema://host[:port] dari alamat http/https; selain itu null. */
    public static function clean(mixed $value): ?string
    {
        if (! is_string($value) || ($parts = parse_url(trim($value))) === false || empty($parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
