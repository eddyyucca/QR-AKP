<?php

namespace App\Jobs;

use App\Models\ApiClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mengabari sistem pemanggil lewat HTTP POST JSON. Isi ditandatangani HMAC-SHA256 dengan rahasia webhook sistem itu
 * (header X-QR-Signature: sha256=...). Dikirim sesudah jawaban ke pengguna terkirim, sekali coba, tanpa mengikuti pengalihan;
 * hasil terakhir dicatat di klien API supaya terlihat di menu Klien API.
 */
class SendWebhook
{
    use Dispatchable;

    public function __construct(public int $clientId, public string $event, public array $data) {}

    public function handle(): ?int
    {
        $client = ApiClient::find($this->clientId);
        if (! $client || ! $client->webhook_url || ! $client->webhook_secret) {
            return null;
        }

        $body = json_encode(['event' => $this->event, 'delivered_at' => now()->toIso8601String(), 'data' => $this->data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $status = Http::withHeaders([
                'X-QR-Event' => $this->event,
                'X-QR-Delivery' => (string) Str::uuid(),
                'X-QR-Signature' => 'sha256='.hash_hmac('sha256', $body, $client->webhook_secret),
                'User-Agent' => 'QR-AKP-Webhook/1.0',
            ])->timeout(5)->connectTimeout(3)->withoutRedirecting()->withBody($body, 'application/json')->post($client->webhook_url)->status();
        } catch (ConnectionException) {
            $status = 0;
        }

        $client->forceFill(['webhook_last_status' => $status, 'webhook_last_at' => now()])->saveQuietly();

        return $status;
    }
}
