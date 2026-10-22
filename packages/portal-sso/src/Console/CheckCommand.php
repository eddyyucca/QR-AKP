<?php

namespace Akp\PortalSso\Console;

use Akp\PortalSso\RoleManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'portal-sso:check';

    protected $description = 'Periksa apakah aplikasi ini siap login lewat AKPSatu';

    public function handle(): int
    {
        $portal = rtrim((string) config('sso.portal_url'), '/');
        $secret = (string) config('sso.secret');
        $cookie = (string) config('session.cookie');

        $roles = RoleManifest::fromConfig();

        // [label, lolos?, keterangan, kritis?]
        $checks = [
            ['PORTAL_APP_CODE terisi', filled(config('sso.app_code')), (string) config('sso.app_code') ?: 'isi dengan kode modul dari Portal', true],
            ['PORTAL_SSO_SECRET terisi (min. 32 karakter)', strlen($secret) >= 32, strlen($secret) >= 32 ? strlen($secret).' karakter' : 'salin dari Portal: Modul > Integrasi', true],
            ['Route /sso/callback terdaftar', Route::has('portal.callback'), '', true],
            ['SESSION_COOKIE khusus aplikasi ini', $cookie !== 'laravel_session', "$cookie (harus unik per aplikasi: cookie tidak membedakan port di localhost)", true],
            ['Portal dapat dijangkau', $this->reachable($portal), $portal, true],
            ['Level yang dideklarasikan (config/sso.php > roles)', $roles !== [], $roles !== [] ? count($roles).' level: '.implode(', ', array_column($roles, 'code')) : 'isi agar Portal bisa mendeteksi level otomatis', false],
            ['Semua halaman web wajib login', (bool) config('sso.protect_all'), config('sso.protect_all') ? '' : 'protect_all=false: pasang middleware portal.login sendiri di route', false],
        ];

        $failed = 0;
        foreach ($checks as [$label, $ok, $detail, $critical]) {
            $mark = $ok ? '<info>OK   </info>' : ($critical ? '<error>GAGAL</error>' : '<comment>CEK  </comment>');
            $this->line(" $mark $label".($detail !== '' ? "  ($detail)" : ''));
            $failed += (! $ok && $critical) ? 1 : 0;
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("$failed pemeriksaan penting gagal.");

            return self::FAILURE;
        }

        $this->info('Siap. Pastikan modul ini terdaftar di Portal dengan URL aplikasi ini, lalu coba login dari Portal.');

        return self::SUCCESS;
    }

    private function reachable(string $portal): bool
    {
        try {
            return Http::timeout(4)->get($portal.'/up')->successful();
        } catch (Throwable) {
            return false;
        }
    }
}