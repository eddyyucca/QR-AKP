<?php

use App\Http\Controllers\Api\QrApiController;
use App\Http\Controllers\Api\MetaApiController;
use Illuminate\Support\Facades\Route;

// Tanpa token: pemantauan layanan dan spesifikasi OpenAPI (untuk Postman, Swagger UI, generator klien).
Route::get('health', [MetaApiController::class, 'health'])->middleware('throttle:60,1');
Route::get('v1/openapi.json', [MetaApiController::class, 'openapi'])->middleware('throttle:60,1');

// REST API untuk sistem lain. Autentikasi: header "Authorization: Bearer akpq_..." (dibuat admin di menu Integrasi > Klien API).
Route::middleware('api.client')->prefix('v1')->group(function () {
    Route::get('ping', [QrApiController::class, 'ping']);
    Route::get('qr', [QrApiController::class, 'index']);
    Route::post('qr', [QrApiController::class, 'store']);
    Route::post('qr/bulk', [QrApiController::class, 'bulk']);
    Route::get('qr/{uuid}', [QrApiController::class, 'show'])->whereUuid('uuid');
    Route::match(['put', 'patch'], 'qr/{uuid}', [QrApiController::class, 'update'])->whereUuid('uuid');
    Route::delete('qr/{uuid}', [QrApiController::class, 'destroy'])->whereUuid('uuid');
    Route::get('qr/{uuid}/image', [QrApiController::class, 'image'])->whereUuid('uuid');
});
