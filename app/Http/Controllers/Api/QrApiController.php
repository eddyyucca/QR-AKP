<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\QrCode;
use App\Rules\PublicWebUrl;
use App\Support\QrImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * REST API QR Code AKP untuk sistem lain. Setiap token hanya melihat QR yang dibuat oleh tokennya sendiri.
 * Siapa pelakunya di sistem pemanggil boleh dikirim lewat "created_by" (nama, NIK, email) dan ikut tersimpan sebagai detail pembuat.
 */
class QrApiController extends Controller
{
    private function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    private function find(Request $request, string $uuid): QrCode
    {
        return QrCode::where('api_client_id', $this->client($request)->id)->where('uuid', $uuid)->firstOrFail();
    }

    public function ping(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'client' => $this->client($request)->name, 'time' => now()->toIso8601String()]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = QrCode::where('api_client_id', $this->client($request)->id)->latest('id');

        if (filled($ref = $request->query('external_ref'))) {
            $query->where('external_ref', (string) $ref);
        }
        if (filled($q = trim((string) $request->query('q')))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('target_url', 'like', $like));
        }

        $page = $query->paginate((int) $request->query('per_page', 25))->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map->toApi()->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    private const CREATE_RULES = [
        'title' => ['required', 'string', 'max:150'],
        'target_url' => ['required', 'string'],
        'description' => ['nullable', 'string', 'max:500'],
        'external_ref' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\-\/]+$/'],
        'created_by' => ['nullable', 'array'],
        'created_by.name' => ['nullable', 'string', 'max:150'],
        'created_by.nik' => ['nullable', 'string', 'max:40'],
        'created_by.email' => ['nullable', 'email', 'max:150'],
    ];

    private function createRules(): array
    {
        $rules = self::CREATE_RULES;
        $rules['target_url'][] = new PublicWebUrl();

        return $rules;
    }

    /**
     * Membuat satu QR dari data yang sudah divalidasi. Dengan external_ref, pengulangan tidak membuat QR ganda (idempoten).
     *
     * @return array{0: int, 1: array} [status HTTP, isi jawaban]
     */
    private function createFor(ApiClient $client, array $data): array
    {
        $target = trim($data['target_url']);

        if (filled($data['external_ref'] ?? null)) {
            $existing = QrCode::where('api_client_id', $client->id)->where('external_ref', $data['external_ref'])->first();
            if ($existing) {
                if ($existing->target_url !== $target) {
                    return [409, ['error' => [
                        'code' => 'external_ref_conflict',
                        'message' => 'external_ref ini sudah dipakai untuk alamat tujuan lain. Alamat tujuan QR tidak bisa diubah; hapus QR lama atau pakai external_ref baru.',
                    ]]];
                }

                return [200, ['data' => $existing->toApi(), 'existing' => true]];
            }
        }

        $by = $data['created_by'] ?? [];

        $qr = QrCode::create([
            'title' => trim($data['title']),
            'target_url' => $target,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'external_ref' => $data['external_ref'] ?? null,
            'creator_portal_id' => 'api:'.$client->id,
            'creator_name' => filled($by['name'] ?? null) ? trim($by['name']) : $client->name,
            'creator_nik' => $by['nik'] ?? null,
            'creator_email' => $by['email'] ?? null,
            'creator_role' => 'api',
            'api_client_id' => $client->id,
            'source' => 'api',
        ]);

        return [201, ['data' => $qr->toApi(), 'existing' => false]];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->createRules());

        [$status, $body] = $this->createFor($this->client($request), $data);

        return response()->json($body, $status);
    }

    /**
     * Membuat banyak QR sekaligus (maks. 50). Tiap item diproses sendiri-sendiri: satu item gagal tidak membatalkan yang lain.
     * Jawaban selalu 200 dengan hasil per item, urutannya sama dengan kiriman.
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate(['items' => ['required', 'array', 'min:1', 'max:50']]);

        $rules = $this->createRules();
        $client = $this->client($request);

        $results = [];
        foreach (array_values($request->input('items')) as $index => $item) {
            $validator = Validator::make(is_array($item) ? $item : [], $rules);
            if ($validator->fails()) {
                $results[] = ['index' => $index, 'status' => 422, 'error' => ['code' => 'validation_failed', 'message' => 'Isian tidak valid.', 'errors' => $validator->errors()->toArray()]];

                continue;
            }
            [$status, $body] = $this->createFor($client, $validator->validated());
            $results[] = ['index' => $index, 'status' => $status] + $body;
        }

        $ok = collect($results)->where('status', '<', 300);

        return response()->json([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'created' => $ok->where('existing', false)->count(),
                'existing' => $ok->where('existing', true)->count(),
                'failed' => count($results) - $ok->count(),
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json(['data' => $this->find($request, $uuid)->toApi()]);
    }

    /** Hanya judul dan keterangan yang boleh diubah (alamat tujuan terkunci). */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $qr = $this->find($request, $uuid);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($request->has('target_url')) {
            return response()->json(['error' => ['code' => 'target_locked', 'message' => 'Alamat tujuan tidak bisa diubah setelah QR dibuat.']], 422);
        }

        $qr->update($data + ['updated_by_name' => $this->client($request)->name]);

        return response()->json(['data' => $qr->fresh()->toApi()]);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        $qr = $this->find($request, $uuid);
        $qr->update(['deleted_by_name' => $this->client($request)->name, 'external_ref' => null]);
        $qr->delete();

        return response()->noContent();
    }

    /** Gambar QR: ?format=png|svg (bawaan png), ?size=200..2000, ?fg= dan ?bg= (hex 6 digit), ?logo=0, ?margin=0..100, ?download=1. */
    public function image(Request $request, string $uuid): Response
    {
        $qr = $this->find($request, $uuid);

        $params = $request->validate([
            'format' => ['nullable', 'in:png,svg'],
            'size' => ['nullable', 'integer', 'min:200', 'max:2000'],
            'fg' => ['nullable', 'regex:/^[0-9a-fA-F]{6}$/'],
            'bg' => ['nullable', 'regex:/^[0-9a-fA-F]{6}$/'],
            'margin' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $format = $params['format'] ?? 'png';

        [$content, $mime] = QrImage::render($qr, $format, (int) ($params['size'] ?? ($format === 'png' ? 900 : 600)), [
            'fg' => $params['fg'] ?? null,
            'bg' => $params['bg'] ?? null,
            'margin' => $params['margin'] ?? 16,
            'logo' => $request->boolean('logo', true),
        ]);

        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$qr->downloadName($format).'"';
        }

        return response($content, 200, $headers);
    }
}
