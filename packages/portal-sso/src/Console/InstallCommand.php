<?php

namespace Akp\PortalSso\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'portal-sso:install
                            {code : Kode aplikasi di Portal (huruf kecil/angka/strip), mis. gs}
                            {--portal=http://localhost:8000 : Alamat Portal}
                            {--secret= : Rahasia SSO dari halaman Modul > Integrasi di Portal}';

    protected $description = 'Isi .env dan .env.example untuk login lewat AKPSatu';

    public function handle(): int
    {
        $code = (string) $this->argument('code');

        if (! preg_match('/^[a-z0-9-]+$/', $code)) {
            $this->error('Kode hanya boleh huruf kecil, angka, dan strip.');

            return self::FAILURE;
        }

        $values = [
            'PORTAL_URL' => $this->option('portal'),
            'PORTAL_APP_CODE' => $code,
            'PORTAL_SSO_SECRET' => $this->option('secret') ?? '',
            // Cookie sesi harus beda per aplikasi: cookie tidak membedakan port di host yang sama (localhost).
            'SESSION_COOKIE' => 'akp_'.str_replace('-', '_', $code).'_session',
        ];

        $this->writeEnv(base_path('.env'), $values, keepExisting: ['PORTAL_SSO_SECRET']);
        $this->writeEnv(base_path('.env.example'), ['PORTAL_SSO_SECRET' => ''] + $values, keepExisting: [], blankSecrets: true);

        $this->info("Selesai. Login Portal untuk '$code' dikonfigurasi di .env.");
        if (blank($this->option('secret'))) {
            $this->warn('PORTAL_SSO_SECRET masih kosong. Salin dari Portal: Modul > (modul ini) > Integrasi, lalu isi di .env.');
        }
        $this->line('Periksa dengan: php artisan portal-sso:check');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<int, string>  $keepExisting  kunci yang tidak ditimpa jika sudah berisi
     */
    private function writeEnv(string $path, array $values, array $keepExisting, bool $blankSecrets = false): void
    {
        if (! is_file($path)) {
            return;
        }

        $env = file_get_contents($path);

        foreach ($values as $key => $value) {
            if ($blankSecrets && $key === 'PORTAL_SSO_SECRET') {
                $value = '';
            }

            if (preg_match("/^{$key}=(.*)$/m", $env, $match)) {
                if (in_array($key, $keepExisting, true) && trim($match[1]) !== '' && $value === '') {
                    continue;
                }
                $env = preg_replace("/^{$key}=.*$/m", addcslashes("{$key}={$value}", '\\$'), $env);
            } else {
                $env = rtrim($env)."\n{$key}={$value}\n";
            }
        }

        file_put_contents($path, $env);
    }
}