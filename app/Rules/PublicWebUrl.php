<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Alamat tujuan QR: hanya http/https dengan nama host yang jelas.
 * Menolak javascript:, data:, file:, alamat tanpa host, alamat yang memuat spasi/karakter kontrol,
 * dan alamat yang menyisipkan akun (user:password@host) karena sering dipakai untuk menyamarkan tujuan.
 */
class PublicWebUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = is_string($value) ? trim($value) : '';

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url)) {
            $fail('Alamat tujuan tidak valid (kosong, terlalu panjang, atau memuat spasi).');

            return;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Alamat tujuan harus diawali http:// atau https://.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('Alamat tujuan tidak boleh memuat nama pengguna atau kata sandi.');

            return;
        }

        $isIp = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
        if ($host === '' || (! $isIp && ! str_contains($host, '.'))) {
            $fail('Nama host alamat tujuan tidak valid.');
        }
    }
}
