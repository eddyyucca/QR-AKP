<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Endpoint tanpa token: pemantauan layanan dan spesifikasi OpenAPI. */
class MetaApiController extends Controller
{
    /** Untuk uptime monitor / load balancer. 503 bila database tidak terjangkau. */
    public function health(): JsonResponse
    {
        try {
            DB::select('select 1');
            $db = true;
        } catch (\Throwable) {
            $db = false;
        }

        return response()->json(
            ['status' => $db ? 'ok' : 'degraded', 'database' => $db, 'time' => now()->toIso8601String()],
            $db ? 200 : 503,
        );
    }

    public function openapi(): JsonResponse
    {
        $uuid = ['name' => 'uuid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']];
        $ref = fn (string $name) => ['$ref' => '#/components/schemas/'.$name];
        $json = fn (array $schema) => ['content' => ['application/json' => ['schema' => $schema]]];
        $err = fn (string $d) => ['description' => $d, 'content' => ['application/json' => ['schema' => $ref('Error')]]];
        $one = ['description' => 'OK', ...$json(['type' => 'object', 'properties' => ['data' => $ref('Qr')]])];

        $createBody = [
            'type' => 'object',
            'required' => ['title', 'target_url'],
            'properties' => [
                'title' => ['type' => 'string', 'maxLength' => 150],
                'target_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'Alamat web publik (http/https).'],
                'description' => ['type' => 'string', 'maxLength' => 500, 'nullable' => true],
                'external_ref' => ['type' => 'string', 'maxLength' => 120, 'description' => 'ID di sistem Anda. Mengirim ulang referensi yang sama tidak membuat QR ganda.'],
                'created_by' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'], 'nik' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email'],
                ]],
            ],
        ];

        $style = [
            ['name' => 'format', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['png', 'svg'], 'default' => 'png']],
            ['name' => 'size', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 200, 'maximum' => 2000]],
            ['name' => 'fg', 'in' => 'query', 'description' => 'Warna kode, hex 6 digit tanpa #.', 'schema' => ['type' => 'string', 'example' => '0e2a47']],
            ['name' => 'bg', 'in' => 'query', 'description' => 'Warna latar, hex 6 digit tanpa #.', 'schema' => ['type' => 'string', 'example' => 'ffffff']],
            ['name' => 'margin', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'default' => 16]],
            ['name' => 'logo', 'in' => 'query', 'description' => '0 untuk menyembunyikan logo AKP.', 'schema' => ['type' => 'boolean', 'default' => true]],
            ['name' => 'download', 'in' => 'query', 'schema' => ['type' => 'boolean']],
        ];

        return response()->json([
            'openapi' => '3.0.3',
            'info' => ['title' => 'QR Code AKP API', 'version' => '1.0.0', 'description' => 'Membuat dan mengelola QR dari sistem lain. Token dibuat administrator di menu Integrasi > Klien API.'],
            'servers' => [['url' => rtrim(url('/'), '/').'/api/v1']],
            'security' => [['bearerAuth' => []]],
            'paths' => [
                '/ping' => ['get' => ['summary' => 'Uji token', 'responses' => ['200' => ['description' => 'OK'], '401' => $err('Token tidak valid')]]],
                '/qr' => [
                    'get' => [
                        'summary' => 'Daftar QR milik token ini',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'external_ref', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25]],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => ['200' => ['description' => 'OK', ...$json(['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref('Qr')], 'meta' => ['type' => 'object']]])]],
                    ],
                    'post' => [
                        'summary' => 'Buat QR (idempoten bila external_ref dikirim)',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $createBody]]],
                        'responses' => ['201' => ['description' => 'Dibuat'] + $one, '200' => ['description' => 'Sudah ada (existing: true)'], '409' => $err('external_ref dipakai untuk alamat lain'), '422' => $err('Isian tidak valid')],
                    ],
                ],
                '/qr/bulk' => ['post' => [
                    'summary' => 'Buat sampai 50 QR sekaligus',
                    'description' => 'Tiap item diproses sendiri; hasil per item ada di results dengan urutan sama seperti kiriman.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['items'], 'properties' => ['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => $createBody]]]]]],
                    'responses' => ['200' => ['description' => 'results[] dan summary'], '422' => $err('items tidak valid')],
                ]],
                '/qr/{uuid}' => [
                    'parameters' => [$uuid],
                    'get' => ['summary' => 'Detail QR', 'responses' => ['200' => $one, '404' => $err('Tidak ditemukan')]],
                    'patch' => [
                        'summary' => 'Ubah judul / keterangan (alamat tujuan terkunci)',
                        'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string', 'nullable' => true]]]]]],
                        'responses' => ['200' => $one, '422' => $err('Tidak valid / target_locked')],
                    ],
                    'delete' => ['summary' => 'Hapus QR', 'responses' => ['204' => ['description' => 'Terhapus'], '404' => $err('Tidak ditemukan')]],
                ],
                '/qr/{uuid}/image' => ['get' => [
                    'summary' => 'Gambar QR',
                    'parameters' => [$uuid, ...$style],
                    'responses' => ['200' => ['description' => 'Gambar', 'content' => ['image/png' => ['schema' => ['type' => 'string', 'format' => 'binary']], 'image/svg+xml' => ['schema' => ['type' => 'string']]]]],
                ]],
            ],
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Token berawalan akpq_']],
                'schemas' => [
                    'Error' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string']]]]],
                    'Qr' => ['type' => 'object', 'properties' => [
                        'uuid' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'], 'target_url' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true], 'external_ref' => ['type' => 'string', 'nullable' => true],
                        'source' => ['type' => 'string'], 'creator' => ['type' => 'object'], 'image_urls' => ['type' => 'object'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'], 'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    ]],
                ],
            ],
        ]);
    }
}
