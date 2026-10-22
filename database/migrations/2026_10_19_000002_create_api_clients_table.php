<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sistem lain (P2H, HRIS, dan seterusnya) yang boleh membuat QR lewat REST API. Token disimpan sebagai hash.
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->unsignedSmallInteger('rate_per_minute')->default(60);
            $table->string('allowed_ips', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('created_by_name', 150)->nullable();
            $table->timestamps();
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('api_client_id')->nullable()->after('creator_role')->index();
            $table->string('external_ref', 120)->nullable()->after('api_client_id');
            $table->string('source', 10)->default('web')->after('external_ref');
            $table->unique(['api_client_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropUnique(['api_client_id', 'external_ref']);
            $table->dropColumn(['api_client_id', 'external_ref', 'source']);
        });

        Schema::dropIfExists('api_clients');
    }
};
