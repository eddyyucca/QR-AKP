<?php

/*
 * Pengaturan login terpusat lewat AKPSatu.
 * Nilai diisi dari .env; jalankan `php artisan portal-sso:install <kode-app>` untuk membuat kuncinya.
 */
return [
    // Alamat Portal. Pengguna yang belum login dialihkan ke {portal_url}/login?app={app_code}
    'portal_url' => env('PORTAL_URL', 'http://localhost:8000'),

    // Kode aplikasi ini di Portal (sama persis dengan yang didaftarkan di halaman Modul Portal).
    'app_code' => env('PORTAL_APP_CODE'),

    // Rahasia bersama dengan Portal (halaman Modul > Integrasi). Jangan dimasukkan ke kode atau git.
    'secret' => env('PORTAL_SSO_SECRET'),

    // Toleransi selisih jam server (detik) saat memeriksa exp/nbf token.
    'leeway' => 5,

    // Umur mutlak sesi aplikasi. Setelah ini pengguna kembali ke Portal yang memeriksa ulang aksesnya.
    'max_session_minutes' => (int) env('PORTAL_MAX_SESSION_MINUTES', 480),

    // true = SEMUA halaman grup 'web' wajib login Portal (disarankan; modul baru otomatis aman).
    // false = pasang sendiri middleware 'portal.login' pada route yang perlu.
    'protect_all' => true,

    // Path yang dikecualikan dari kewajiban login (tanpa awalan "/"). Route paket (sso/*) selalu dikecualikan.
    'except' => ['up'],

    // Level (peran) yang disediakan aplikasi ini. Portal MEMBACA daftar ini otomatis (Modul > Atur > Deteksi level),
    // jadi admin tinggal memilih dari daftar saat memberi akses. Kode (kiri) dipakai di kode aplikasi: portal.role:admin.
    // Bentuk: 'kode' => ['Nama', 'Keterangan singkat'], urutan = dari yang paling berwenang.
    'roles' => [
        'admin' => ['Administrator', 'Mengelola semua data dan pengaturan aplikasi ini.'],
        'user' => ['Pengguna', 'Memakai fitur aplikasi sehari-hari.'],
    ],

    // Tujuan setelah login jika tidak ada halaman yang diingat. Path atau URL.
    'home' => '/',
];