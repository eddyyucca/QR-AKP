<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alamat yang dikabari saat QR milik sistem ini dibuat/diubah/dihapus. Rahasia penandatangan disimpan terenkripsi.
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('webhook_url', 255)->nullable()->after('allowed_ips');
            $table->text('webhook_secret')->nullable()->after('webhook_url');
            $table->unsignedSmallInteger('webhook_last_status')->nullable()->after('webhook_secret');
            $table->timestamp('webhook_last_at')->nullable()->after('webhook_last_status');
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret', 'webhook_last_status', 'webhook_last_at']);
        });
    }
};
