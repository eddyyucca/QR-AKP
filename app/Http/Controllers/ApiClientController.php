<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Jobs\SendWebhook;
use App\Rules\PublicWebUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Kelola sistem pemanggil REST API (khusus administrator). Token asli hanya tampil sekali setelah dibuat. */
class ApiClientController extends Controller
{
    public function index()
    {
        return view('api.clients', [
            'clients' => ApiClient::withCount('qrCodes')->latest('id')->get(),
            'newToken' => session('new_token'),
            'newTokenName' => session('new_token_name'),
            'newWebhookSecret' => session('new_webhook_secret'),
        ]);
    }

    /** Jejak pemakaian REST API: ringkasan 7 hari dan daftar permintaan terbaru. */
    public function logs(Request $request)
    {
        $filters = ['client' => (string) $request->query('client', ''), 'result' => (string) $request->query('result', '')];

        $query = ApiRequestLog::with('apiClient:id,name')->latest('id');
        if ($filters['client'] !== '') {
            $query->where('api_client_id', (int) $filters['client']);
        }
        if ($filters['result'] === 'ok') {
            $query->where('status', '<', 400);
        } elseif ($filters['result'] === 'error') {
            $query->where('status', '>=', 400);
        }

        $week = ApiRequestLog::where('created_at', '>=', now()->subDays(7));

        return view('api.logs', [
            'logs' => $query->paginate(25)->withQueryString(),
            'clients' => ApiClient::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'summary' => [
                'today' => ApiRequestLog::where('created_at', '>=', today())->count(),
                'week' => (clone $week)->count(),
                'errors' => (clone $week)->where('status', '>=', 400)->count(),
                'avg_ms' => (int) round((clone $week)->avg('duration_ms') ?? 0),
            ],
        ]);
    }

    public function docs()
    {
        return view('api.docs', ['baseUrl' => rtrim(config('app.url'), '/')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'rate_per_minute' => ['required', 'integer', 'min:1', 'max:1000'],
            'allowed_ips' => ['nullable', 'string', 'max:255', 'regex:/^[0-9a-fA-F:.\/,\s]*$/'],
        ], [
            'name.required' => 'Nama sistem wajib diisi.',
            'allowed_ips.regex' => 'Daftar IP hanya boleh berisi alamat IP atau CIDR yang dipisah koma.',
        ]);

        $client = new ApiClient([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'rate_per_minute' => $data['rate_per_minute'],
            'allowed_ips' => filled($data['allowed_ips'] ?? null) ? trim($data['allowed_ips']) : null,
            'is_active' => true,
            'created_by_name' => portal_user()->name,
        ]);
        $token = $client->issueToken();
        $client->save();

        return redirect()->route('api.clients')->with('new_token', $token)->with('new_token_name', $client->name)
            ->with('success', 'Sistem ditambahkan. Salin token sekarang: token tidak akan ditampilkan lagi.');
    }

    /** Atur alamat webhook. Rahasia penandatangan dibuat sekali dan hanya tampil sekali; mengosongkan alamat menghapusnya. */
    public function webhook(Request $request, ApiClient $client)
    {
        $data = $request->validate(['webhook_url' => ['nullable', 'string', new PublicWebUrl()]]);
        $url = trim((string) ($data['webhook_url'] ?? ''));

        if ($url === '') {
            $client->forceFill(['webhook_url' => null, 'webhook_secret' => null, 'webhook_last_status' => null, 'webhook_last_at' => null])->save();

            return redirect()->route('api.clients')->with('success', 'Webhook '.$client->name.' dimatikan.');
        }

        $redirect = redirect()->route('api.clients');
        if (! $client->webhook_secret) {
            $secret = 'whsec_'.Str::random(32);
            $client->webhook_secret = $secret;
            $redirect->with('new_webhook_secret', $secret)->with('new_token_name', $client->name);
        }
        $client->webhook_url = $url;
        $client->save();

        return $redirect->with('success', 'Webhook disimpan.');
    }

    /** Kirim peristiwa uji supaya admin bisa memastikan alamat dan tanda tangannya diterima benar. */
    public function webhookTest(ApiClient $client)
    {
        $status = (new SendWebhook($client->id, 'webhook.test', ['message' => 'Uji webhook QR Code AKP']))->handle();

        if ($status === null) {
            return redirect()->route('api.clients')->with('error', 'Webhook belum diatur untuk sistem ini.');
        }

        return redirect()->route('api.clients')->with($status >= 200 && $status < 300 ? 'success' : 'error',
            $status === 0 ? 'Webhook tidak terjangkau (waktu habis atau koneksi ditolak).' : 'Webhook dijawab HTTP '.$status.'.');
    }

    /** Token baru menggantikan yang lama (yang lama langsung tidak berlaku). */
    public function regenerate(ApiClient $client)
    {
        $token = $client->issueToken();
        $client->save();

        return redirect()->route('api.clients')->with('new_token', $token)->with('new_token_name', $client->name)
            ->with('success', 'Token baru dibuat. Token lama tidak berlaku lagi.');
    }

    public function toggle(ApiClient $client)
    {
        $client->update(['is_active' => ! $client->is_active]);

        return redirect()->route('api.clients')->with('success', $client->is_active ? 'Sistem diaktifkan.' : 'Sistem dinonaktifkan.');
    }

    public function destroy(ApiClient $client)
    {
        $client->delete();

        return redirect()->route('api.clients')->with('success', 'Sistem dihapus. QR yang pernah dibuatnya tetap ada.');
    }
}
