<?php

namespace App\Models;

use App\Jobs\SendWebhook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Satu QR. Isi QR = alamat tujuan asli (statis, tanpa pengalihan lewat pihak ketiga), jadi QR tetap berfungsi
 * selama alamat tujuannya hidup. Detail pembuat disimpan bersama QR.
 * Sumber: "web" (dibuat pengguna Portal) atau "api" (dibuat sistem lain lewat REST API).
 */
class QrCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'target_url', 'description',
        'creator_portal_id', 'creator_name', 'creator_nik', 'creator_email', 'creator_role',
        'api_client_id', 'external_ref', 'source',
        'updated_by_name', 'deleted_by_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (QrCode $qr) {
            $qr->uuid ??= (string) Str::uuid();
        });

        // Sistem pemanggil dikabari lewat webhook. "Diubah" hanya bila judul/keterangan berubah (menghapus juga menulis kolom lain).
        static::created(fn (QrCode $qr) => $qr->notifyWebhook('qr.created'));
        static::updated(fn (QrCode $qr) => $qr->wasChanged(['title', 'description']) && $qr->notifyWebhook('qr.updated'));
        static::deleted(fn (QrCode $qr) => $qr->notifyWebhook('qr.deleted'));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /** QR yang boleh dilihat pengguna Portal ini: administrator semuanya, pengguna biasa hanya miliknya. */
    public function scopeVisibleTo(Builder $query, \Akp\PortalSso\PortalUser $user): Builder
    {
        return $user->hasRole('admin') ? $query : $query->where('creator_portal_id', (string) $user->id);
    }

    public function notifyWebhook(string $event): void
    {
        if ($this->api_client_id && $this->apiClient?->webhook_url) {
            SendWebhook::dispatchAfterResponse($this->api_client_id, $event, $this->toApi());
        }
    }

    public function isFromApi(): bool
    {
        return $this->source === 'api';
    }

    public function creatorRoleLabel(): string
    {
        return match ($this->creator_role) {
            'admin' => 'Administrator',
            'api' => 'Sistem (API)',
            default => 'Pengguna',
        };
    }

    /** Nama berkas unduhan yang aman. */
    public function downloadName(string $extension): string
    {
        return 'qr-akp-'.(Str::slug($this->title) ?: 'qr').'.'.$extension;
    }

    /** Bentuk JSON untuk REST API. */
    public function toApi(): array
    {
        $base = url('/api/v1/qr/'.$this->uuid.'/image');

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'target_url' => $this->target_url,
            'description' => $this->description,
            'external_ref' => $this->external_ref,
            'source' => $this->source,
            'creator' => [
                'name' => $this->creator_name,
                'nik' => $this->creator_nik,
                'email' => $this->creator_email,
                'level' => $this->creator_role,
            ],
            'image_urls' => ['png' => $base.'?format=png', 'svg' => $base.'?format=svg'],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
