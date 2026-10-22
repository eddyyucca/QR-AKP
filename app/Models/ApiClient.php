<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/** Sistem pemanggil REST API. Token asli hanya terlihat sekali saat dibuat; yang tersimpan hanya hash-nya. */
class ApiClient extends Model
{
    public const TOKEN_PREFIX = 'akpq_';

    protected $fillable = ['name', 'description', 'rate_per_minute', 'allowed_ips', 'is_active', 'created_by_name'];

    protected $hidden = ['token_hash', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'webhook_secret' => 'encrypted',
            'webhook_last_at' => 'datetime',
        ];
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /** Membuat token baru dan mengembalikan token aslinya (hanya kali ini terlihat). */
    public function issueToken(): string
    {
        $token = self::TOKEN_PREFIX.Str::random(40);
        $this->token_hash = hash('sha256', $token);
        $this->token_prefix = substr($token, 0, 12);

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token || ! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))->first();
    }

    /** @return list<string> */
    public function allowedIpList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
    }

    public function allowsIp(?string $ip): bool
    {
        $list = $this->allowedIpList();

        return $list === [] || ($ip !== null && IpUtils::checkIp($ip, $list));
    }
}
