<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 150);
            $table->string('target_url', 2048);
            $table->string('description', 500)->nullable();

            // Detail pembuat (disalin dari token Portal saat QR dibuat; tetap tersimpan walau akun Portal berubah).
            $table->string('creator_portal_id', 40)->index();
            $table->string('creator_name', 150);
            $table->string('creator_nik', 40)->nullable();
            $table->string('creator_email', 150)->nullable();
            $table->string('creator_role', 30);

            $table->string('updated_by_name', 150)->nullable();
            $table->string('deleted_by_name', 150)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
