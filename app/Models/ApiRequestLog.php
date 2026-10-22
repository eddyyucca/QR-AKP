<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu permintaan REST API yang sudah lolos autentikasi token. */
class ApiRequestLog extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $fillable = ['api_client_id', 'method', 'path', 'status', 'ip', 'duration_ms'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
