<?php

use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

// Semua halaman sudah wajib login Portal (config/sso.php, protect_all). Level akses:
//   admin : semua QR dari semua pembuat, menyaring menurut pembuat, menghapus, dan mengelola klien REST API
//   user  : membuat QR dan mengelola QR miliknya sendiri
Route::redirect('/', '/qr')->name('home');

Route::middleware(['portal.role:admin,user', 'throttle:120,1'])->prefix('qr')->name('qr.')->group(function () {
    Route::get('/', [QrCodeController::class, 'home'])->name('home');
    Route::get('/daftar', [QrCodeController::class, 'index'])->name('index');
    Route::get('/ekspor', [QrCodeController::class, 'export'])->middleware('throttle:10,1')->name('export');
    Route::get('/buat', [QrCodeController::class, 'create'])->name('create');
    Route::post('/pratinjau', [QrCodeController::class, 'preview'])->middleware('throttle:60,1')->name('preview');
    Route::post('/', [QrCodeController::class, 'store'])->middleware('throttle:30,1')->name('store');
    Route::get('/{qr}', [QrCodeController::class, 'show'])->whereUuid('qr')->name('show');
    Route::get('/{qr}/ubah', [QrCodeController::class, 'edit'])->whereUuid('qr')->name('edit');
    Route::put('/{qr}', [QrCodeController::class, 'update'])->whereUuid('qr')->name('update');
    Route::delete('/{qr}', [QrCodeController::class, 'destroy'])->whereUuid('qr')->name('destroy');
    Route::get('/{qr}/gambar/{format}', [QrCodeController::class, 'image'])->whereUuid('qr')->whereIn('format', ['png', 'svg'])->name('image');
});

// Integrasi REST API: dokumentasi untuk semua pengguna, pengelolaan token hanya administrator.
Route::middleware(['portal.role:admin,user'])->get('/integrasi/dokumentasi', [ApiClientController::class, 'docs'])->name('api.docs');

Route::middleware(['portal.role:admin', 'throttle:60,1'])->prefix('integrasi/klien')->name('api.')->group(function () {
    Route::get('/', [ApiClientController::class, 'index'])->name('clients');
    Route::get('/log', [ApiClientController::class, 'logs'])->name('logs');
    Route::post('/', [ApiClientController::class, 'store'])->name('clients.store');
    Route::post('/{client}/token', [ApiClientController::class, 'regenerate'])->name('clients.regenerate');
    Route::post('/{client}/webhook', [ApiClientController::class, 'webhook'])->name('clients.webhook');
    Route::post('/{client}/webhook/uji', [ApiClientController::class, 'webhookTest'])->middleware('throttle:10,1')->name('clients.webhook.test');
    Route::post('/{client}/aktif', [ApiClientController::class, 'toggle'])->name('clients.toggle');
    Route::delete('/{client}', [ApiClientController::class, 'destroy'])->name('clients.destroy');
});
