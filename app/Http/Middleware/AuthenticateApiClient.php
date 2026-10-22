<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/** Autentikasi sistem pemanggil dengan header "Authorization: Bearer akpq_...". */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = ApiClient::findByToken($request->bearerToken());

        if (! $client) {
            return $this->error('unauthenticated', 'Token API tidak valid.', 401);
        }
        if (! $client->is_active) {
            return $this->error('client_disabled', 'Sistem ini dinonaktifkan di QR Code AKP.', 403);
        }
        if (! $client->allowsIp($request->ip())) {
            return $this->error('ip_not_allowed', 'IP '.$request->ip().' tidak diizinkan untuk token ini.', 403);
        }

        // Batas permintaan per token (bukan per IP), sesuai pengaturan sistem pemanggil.
        $key = 'qr-api:'.$client->id;
        if (RateLimiter::tooManyAttempts($key, $client->rate_per_minute)) {
            return $this->error('rate_limited', 'Terlalu banyak permintaan. Coba lagi sebentar.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        // Cukup diperbarui sekali per menit agar tidak menulis ke database di setiap request.
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_started_at', hrtime(true));

        return $next($request);
    }

    /** Mencatat permintaan setelah jawaban terkirim, sehingga tidak memperlambat sistem pemanggil. */
    public function terminate(Request $request, Response $response): void
    {
        $client = $request->attributes->get('api_client');
        if (! $client) {
            return;
        }

        ApiRequestLog::create([
            'api_client_id' => $client->id,
            'method' => $request->method(),
            'path' => substr('/'.ltrim($request->path(), '/'), 0, 200),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'duration_ms' => (int) ((hrtime(true) - $request->attributes->get('api_started_at', hrtime(true))) / 1e6),
        ]);
    }

    private function error(string $code, string $message, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
