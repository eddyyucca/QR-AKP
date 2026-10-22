<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jejak pemakaian REST API per sistem pemanggil. Dibersihkan otomatis (lihat ApiRequestLog::prunable).
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_client_id')->index();
            $table->string('method', 10);
            $table->string('path', 200);
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
