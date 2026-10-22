<?php

use Akp\PortalSso\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('sso/callback', [SsoController::class, 'callback'])->name('portal.callback');
    Route::post('sso/logout', [SsoController::class, 'logout'])->name('portal.logout');

    // Tanpa login dan tanpa rahasia: dipakai Portal untuk "Tes koneksi" saat modul didaftarkan.
    Route::get('sso/health', [SsoController::class, 'health'])->name('portal.health');

    // Daftar level aplikasi ini, dibaca Portal (terbukti lewat HMAC rahasia SSO). Tanpa login karena hanya berisi nama level.
    Route::get('sso/manifest', [SsoController::class, 'manifest'])->name('portal.manifest');
});