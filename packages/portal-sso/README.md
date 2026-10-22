# akp/portal-sso

Penerima login terpusat **AKPSatu** untuk aplikasi Laravel (11/12). Pasang paket ini, isi tiga nilai di `.env`,
dan seluruh halaman aplikasi otomatis memakai login Portal.

Standar lengkap (konvensi, level, keamanan, kontrak token): [`../../STANDAR-APLIKASI.md`](../../STANDAR-APLIKASI.md).

## Pasang

```powershell
composer config repositories.akp-portal-sso path ../packages/portal-sso
composer require akp/portal-sso:@dev
php artisan portal-sso:install <kode-modul> --portal=http://localhost:8000 --secret=<rahasia dari Portal>
php artisan portal-sso:check
```

Atau buat aplikasi baru sekaligus: `.\new-app.ps1 -Code <kode> -Name "<Nama>" -Port <port> -Secret <rahasia>`.

## Yang disediakan

| | |
|---|---|
| Route | `GET /sso/callback` (`portal.callback`), `POST /sso/logout` (`portal.logout`), `GET /sso/health` (`portal.health`) |
| Middleware | `portal.login` (otomatis di grup `web`), `portal.level:50`, `portal.role:admin,hr` |
| Helper | `portal_user()` → `PortalUser` (`name`, `employee_no`, `role`, `level`, `is_admin`, `atLeast()`, `hasRole()`) |
| View | `$portalUser` tersedia di semua view |
| Perintah | `portal-sso:install`, `portal-sso:check` |
| Tes | trait `Akp\PortalSso\Testing\ActsAsPortalUser` (`loginPortal()`, `logoutPortal()`) |

## Konfigurasi (`config/sso.php`, nilai dari `.env`)

| Kunci | `.env` | Keterangan |
|---|---|---|
| `portal_url` | `PORTAL_URL` | Alamat Portal |
| `app_code` | `PORTAL_APP_CODE` | Kode modul di Portal |
| `secret` | `PORTAL_SSO_SECRET` | Rahasia bersama (dari Portal > Modul > Integrasi) |
| `max_session_minutes` | `PORTAL_MAX_SESSION_MINUTES` | Umur mutlak sesi (bawaan 480) |
| `protect_all` | - | `true`: semua halaman `web` wajib login |
| `except` | - | Path yang dikecualikan dari login |
| `home` | - | Tujuan setelah login bila tidak ada halaman yang diingat |

Publikasikan konfigurasi: `php artisan vendor:publish --tag=portal-sso-config`.

## Catatan teknis

- Token JWT HS256 sekali pakai (`jti` disimpan di cache sampai kedaluwarsa); gunakan cache store yang dipakai bersama semua proses
  aplikasi (`database`, `redis`, `file`), bukan `array`, di produksi.
- Middleware dipasang lewat `Illuminate\Contracts\Http\Kernel::appendMiddlewareToGroup` (bukan `Router`), karena Kernel menyinkronkan
  ulang grup middleware ke Router dan penambahan lewat Router bisa tertimpa.
- Kunci sesi: `portal.user`, `portal.started_at`, `portal.intended`.

## Aplikasi dengan tabel user sendiri (event)

Jika data bisnis mereferensikan `users.id` (mis. PTW), pertahankan tabel user sebagai profil lokal dan hubungkan ke Portal lewat event.
Listener di `app/Listeners` ditemukan otomatis oleh Laravel 11+ dari type-hint (jangan didaftarkan lagi secara manual, nanti jalan dua kali).

```php
// app/Listeners/LoginLocalUser.php
use Akp\PortalSso\Events\PortalUserLoggedIn;
use Akp\PortalSso\Exceptions\LoginDenied;

public function handle(PortalUserLoggedIn $event): void
{
    $portal = $event->user;                                  // PortalUser: id, employee_no, name, email, role, level, is_admin
    $local = User::firstOrNew(['portal_user_id' => $portal->id]);

    if ($local->exists && ! $local->is_active) {
        throw new LoginDenied('Akun dinonaktifkan di aplikasi ini.');   // menampilkan halaman 403 dan mengakhiri sesi
    }

    // ... isi/sinkronkan profil dan peran dari $portal ...
    Auth::login($local);
}

// app/Listeners/LogoutLocalUser.php   (event PortalUserLoggingOut)
public function handle(PortalUserLoggingOut $event): void { Auth::logout(); }
```

Aturan yang dianjurkan: Portal berwenang atas **identitas dan peran**; aplikasi berwenang atas data lokalnya. Akun baru tanpa data lokal
wajib fail-closed (tidak melihat data siapa pun) sampai dilengkapi. Contoh lengkap: `ptw/app/Listeners` dan `ptw/app/Support/PortalUserSync.php`.

Route yang hanya meneruskan ke Portal (mis. `/login` lama) harus dikecualikan dari perlindungan: `config(['sso.except' => [..., 'login']])`.

## Aplikasi yang berjalan di Docker

Image dibangun dari folder proyek, jadi paket harus ada **di dalam** proyek, bukan tautan ke folder lain:

```json
"repositories": [{ "type": "path", "url": "packages/portal-sso", "options": { "symlink": false } }],
"require": { "akp/portal-sso": "@dev" }
```

Salin paket dengan `..\sync-package.ps1`, pasang di dalam container (`composer update akp/portal-sso`), lalu `php artisan package:discover`
(manifest paket di `bootstrap/cache` bisa usang). Dari dalam container, `PORTAL_URL` harus bisa dibuka **browser** (mis. `http://localhost:8000`);
Portal tidak dihubungi dari server aplikasi kecuali oleh `portal-sso:check`.

Catatan: `php artisan serve` membuang variabel lingkungan yang diteruskan lewat `docker run -e` (hanya `.env` yang dipakai); untuk uji gunakan
`php -S 0.0.0.0:PORT vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` dari folder `public`.

## Mendeklarasikan level aplikasi

Tulis level yang disediakan aplikasi di `config/sso.php` (kunci `roles`). Portal membacanya otomatis lewat `GET /sso/manifest`
(Admin > Modul > Atur > Deteksi level), jadi admin tidak perlu mengetik ulang atau mengisi angka tingkat:

```php
'roles' => [
    'admin'  => ['Administrator', 'Mengelola semua data dan pengaturan.'],
    'viewer' => ['Hanya lihat', 'Hanya bisa melihat data.'],
],
```

Kode level dipakai untuk otorisasi: `->middleware('portal.role:admin')`. Lihat `STANDAR-APLIKASI.md` bagian 4a.
