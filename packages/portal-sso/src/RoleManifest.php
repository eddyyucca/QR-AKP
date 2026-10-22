<?php

namespace Akp\PortalSso;

/**
 * Daftar level (peran) yang DIDEKLARASIKAN aplikasi ini, dibaca Portal secara otomatis lewat GET /sso/manifest.
 * Aplikasi menuliskannya sekali di config/sso.php:
 *
 *   'roles' => [
 *       'admin'  => ['Administrator', 'Mengelola semua data dan pengaturan.'],
 *       'staff'  => ['Staf', 'Mengisi data sehari-hari.'],
 *       'viewer' => 'Hanya lihat',                                    // boleh hanya nama
 *   ],
 *
 * Urutan penulisan = urutan tampil di Portal (yang pertama dianggap paling berwenang).
 * Kode (kiri) dipakai aplikasi untuk otorisasi: ->middleware('portal.role:admin,staff'). Jangan diubah setelah dipakai.
 */
final class RoleManifest
{
    /** @return list<array{code: string, name: string, description: ?string}> */
    public static function fromConfig(): array
    {
        $roles = [];

        foreach ((array) config('sso.roles', []) as $code => $definition) {
            $code = (string) $code;

            // Kode yang tidak valid dilewati agar satu salah ketik tidak merusak seluruh daftar.
            if (! preg_match('/^[a-z0-9_-]{1,30}$/', $code)) {
                continue;
            }

            $definition = is_array($definition) ? array_values($definition) : [$definition];
            $name = trim((string) ($definition[0] ?? ''));

            $roles[] = [
                'code' => $code,
                'name' => $name !== '' ? mb_substr($name, 0, 100) : ucfirst(str_replace(['_', '-'], ' ', $code)),
                'description' => isset($definition[1]) && trim((string) $definition[1]) !== '' ? mb_substr(trim((string) $definition[1]), 0, 255) : null,
            ];
        }

        return $roles;
    }
}
