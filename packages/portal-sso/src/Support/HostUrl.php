<?php

namespace Akp\PortalSso\Support;

use Illuminate\Http\Request;

/**
 * Menyesuaikan alamat yang tersimpan di konfigurasi dengan alamat yang sedang dipakai pengguna.
 *
 * Alamat bawaan seperti http://localhost:8000 hanya benar di laptop pengembang. Begitu aplikasi berjalan di server
 * (mis. http://172.100.1.113:8101), tautan ke Portal harus memakai host yang sama dengan yang dibuka pengguna.
 * Aturannya: bila host yang tersimpan adalah localhost/127.0.0.1/::1 DAN pengguna tidak membuka lewat alamat itu,
 * host diganti dengan host yang dipakai pengguna; skema mengikuti permintaan, port tetap. Alamat yang sudah
 * berupa IP/domain sungguhan tidak diubah, jadi pengaturan eksplisit (mis. domain HTTPS) selalu menang.
 *
 * Penanda {host} juga didukung: http://{host}:8100 selalu memakai host yang sedang dipakai pengguna (IP, nama
 * komputer, atau domain), jadi satu pengaturan benar dari mana pun aplikasi dibuka.
 *
 * Penanda {base} = domain induk host yang dipakai pengguna (buang label paling depan). Untuk Cloudflare Tunnel dengan
 * satu subdomain per aplikasi: dari hris.akp.co.id, https://portal.{base} menjadi https://portal.akp.co.id.
 * Hanya untuk aplikasi yang berada di subdomain (portal.…, hris.…). Bila host berupa IP atau hanya dua label,
 * {base} sama dengan host itu sendiri. Domain utama seperti akp.co.id tidak bisa dibedakan dari subdomain tanpa daftar
 * akhiran domain publik, jadi untuk aplikasi yang berada di domain utama isi alamat lengkap (tanpa penanda).
 */
class HostUrl
{
    private const LOOPBACK = ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'];

    public static function adapt(?string $url, ?Request $request = null): string
    {
        $url = rtrim((string) $url, '/');
        $request ??= request();

        if (str_contains($url, '{host}') || str_contains($url, '{base}')) {
            $host = $request?->getHost() ?: 'localhost';

            return str_replace(['{host}', '{base}'], [$host, self::baseDomain($host)], $url);
        }

        $parts = parse_url($url);

        if (! $request || ! $parts || empty($parts['host']) || ! self::isLoopback($parts['host'])) {
            return $url;
        }

        $current = $request->getHost();

        if ($current === '' || self::isLoopback($current)) {
            return $url;
        }

        return $request->getScheme().'://'.$current
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');
    }

    /** Domain induk: hris.akp.co.id -> akp.co.id. IP dan host dengan dua label atau kurang dikembalikan apa adanya. */
    public static function baseDomain(string $host): string
    {
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $labels = explode('.', $host);

        return count($labels) > 2 ? implode('.', array_slice($labels, 1)) : $host;
    }

    /** Berupa nama domain dengan sedikitnya tiga label (portal.akp.co.id), bukan IP atau nama pendek. */
    public static function isSubdomain(string $host): bool
    {
        return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false && count(explode('.', $host)) > 2;
    }

    public static function isLoopback(string $host): bool
    {
        return in_array(strtolower($host), self::LOOPBACK, true);
    }
}
