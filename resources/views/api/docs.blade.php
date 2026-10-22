@extends('layouts.app')

@section('title', 'Dokumentasi API')
@section('page-title', 'Dokumentasi API')
@section('breadcrumb')
    <li class="breadcrumb-item">Integrasi</li>
    <li class="breadcrumb-item active">Dokumentasi API</li>
@endsection

@push('styles')
<style>
    .doc pre { background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 1rem; font-size: .8rem; overflow-x: auto; margin-bottom: 0; }
    .doc .method { display: inline-block; min-width: 58px; text-align: center; font-weight: 700; font-size: .72rem; padding: .15rem .4rem; border-radius: 6px; color: #fff; }
    .m-get { background: #0d9488; } .m-post { background: #2563eb; } .m-patch { background: #d97706; } .m-delete { background: #dc2626; }
</style>
@endpush

@section('content')
<div class="doc">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Untuk apa REST API ini?</h2>
            <p class="mb-2">Sistem AKP lain (misalnya P2H) bisa membuat, melihat, dan mengunduh QR resmi AKP lewat API, tanpa lewat layar web. QR tetap berlogo AKP dan langsung mengarah ke alamat tujuan asli.</p>
            <ul class="mb-0 small">
                <li>Alamat dasar: <code>{{ $baseUrl }}/api/v1</code></li>
                <li>Autentikasi: header <code>Authorization: Bearer akpq_...</code>. Token dibuat administrator di <strong>Integrasi &gt; Klien API</strong>.</li>
                <li>Setiap token hanya melihat QR yang dibuat oleh tokennya sendiri. Batas permintaan per menit diatur per token.</li>
                <li>Semua jawaban berbentuk JSON, kecuali gambar. Galat: <code>{"error": {"code": "...", "message": "..."}}</code> atau, untuk validasi, <code>{"message": "...", "errors": {...}}</code> (HTTP 422).</li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-get">GET</span> <code>/api/v1/ping</code> <span class="text-muted small ms-2">Cek token dan koneksi</span></div>
        <div class="card-body"><pre>curl {{ $baseUrl }}/api/v1/ping -H "Authorization: Bearer $QR_TOKEN"

{"ok": true, "client": "P2H AKP", "time": "2026-10-19T09:00:00+08:00"}</pre></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-post">POST</span> <code>/api/v1/qr</code> <span class="text-muted small ms-2">Membuat QR</span></div>
        <div class="card-body">
            <table class="table table-sm small">
                <thead><tr><th>Isian</th><th>Wajib</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr><td><code>title</code></td><td>ya</td><td>Judul QR (maks. 150 karakter)</td></tr>
                    <tr><td><code>target_url</code></td><td>ya</td><td>Alamat tujuan, <code>http://</code> atau <code>https://</code>, maks. 2048 karakter. Tidak bisa diubah setelah dibuat.</td></tr>
                    <tr><td><code>description</code></td><td>tidak</td><td>Keterangan (maks. 500 karakter)</td></tr>
                    <tr><td><code>external_ref</code></td><td>tidak</td><td>Kode unik dari sistem Anda (huruf, angka, <code>. _ : - /</code>). Bila dikirim ulang dengan alamat yang sama, QR yang sudah ada dikembalikan (HTTP 200), tidak dibuat ganda. Dengan alamat berbeda: HTTP 409.</td></tr>
                    <tr><td><code>created_by.name / nik / email</code></td><td>tidak</td><td>Siapa pelakunya di sistem Anda. Tersimpan sebagai detail pembuat. Bila kosong, pembuat tercatat sebagai nama sistem.</td></tr>
                </tbody>
            </table>
            <pre>curl -X POST {{ $baseUrl }}/api/v1/qr \
  -H "Authorization: Bearer $QR_TOKEN" -H "Content-Type: application/json" \
  -d '{
    "title": "Unit LV-12",
    "target_url": "https://p2h.lameruru.web.id/p2h/isi?unit=LV-12",
    "external_ref": "p2h-unit-LV-12",
    "created_by": {"name": "Eddy Adha Saputra", "nik": "32601949"}
  }'

HTTP 201
{"data": {"uuid": "3f2c...", "title": "Unit LV-12", "target_url": "https://p2h...", "external_ref": "p2h-unit-LV-12",
          "source": "api", "creator": {"name": "Eddy Adha Saputra", "nik": "32601949", "email": null, "level": "api"},
          "image_urls": {"png": "{{ $baseUrl }}/api/v1/qr/3f2c.../image?format=png", "svg": "..."},
          "created_at": "2026-10-19T09:00:00+08:00"}, "existing": false}</pre>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-post">POST</span> <code>/api/v1/qr/bulk</code> <span class="text-muted small ms-2">Buat banyak QR (maks. 50)</span></div>
        <div class="card-body small">
            Isi <code>items</code> memakai bentuk yang sama dengan pembuatan satu QR. Tiap item diproses sendiri: yang gagal tidak membatalkan yang lain. Jawaban HTTP 200 berisi <code>results</code> (per item, urutan sama) dan <code>summary</code>.
            <pre class="mt-2">curl -X POST {{ $baseUrl }}/api/v1/qr/bulk -H "Authorization: Bearer $QR_TOKEN" -H "Content-Type: application/json" \
  -d '{"items": [
        {"title": "Unit LV-12", "target_url": "https://p2h.example/u/LV-12", "external_ref": "p2h-unit-LV-12"},
        {"title": "Unit LV-13", "target_url": "https://p2h.example/u/LV-13", "external_ref": "p2h-unit-LV-13"}
      ]}'

{"results": [{"index": 0, "status": 201, "data": {...}, "existing": false}, {"index": 1, "status": 422, "error": {...}}],
 "summary": {"total": 2, "created": 1, "existing": 0, "failed": 1}}</pre>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-get">GET</span> <code>/api/v1/qr</code> <span class="text-muted small ms-2">Daftar QR milik token ini</span></div>
        <div class="card-body small">
            Parameter: <code>q</code> (cari judul atau alamat), <code>external_ref</code>, <code>per_page</code> (1-100, bawaan 25), <code>page</code>.
            <pre class="mt-2">curl "{{ $baseUrl }}/api/v1/qr?external_ref=p2h-unit-LV-12" -H "Authorization: Bearer $QR_TOKEN"

{"data": [ {...}, ... ], "meta": {"page": 1, "last_page": 1, "per_page": 25, "total": 1}}</pre>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-get">GET</span> <code>/api/v1/qr/{uuid}</code> <span class="text-muted small ms-2">Detail satu QR</span></div>
        <div class="card-body small">Jawaban sama seperti objek <code>data</code> di atas. QR milik token lain dijawab 404.</div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-get">GET</span> <code>/api/v1/qr/{uuid}/image</code> <span class="text-muted small ms-2">Gambar QR</span></div>
        <div class="card-body small">
            Parameter: <code>format</code> = <code>png</code> (bawaan) atau <code>svg</code>; <code>size</code> = 200-2000 piksel; <code>fg</code> dan <code>bg</code> = warna heksadesimal 6 digit tanpa <code>#</code> (pastikan kontras cukup agar terbaca); <code>logo=0</code> menyembunyikan logo AKP; <code>margin</code> = 0-100; <code>download=1</code> memicu unduhan berkas.
            <pre class="mt-2">curl "{{ $baseUrl }}/api/v1/qr/$UUID/image?format=png&amp;size=800" -H "Authorization: Bearer $QR_TOKEN" -o qr.png</pre>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-patch">PATCH</span> <code>/api/v1/qr/{uuid}</code> <span class="text-muted small ms-2">Ubah judul / keterangan</span></div>
        <div class="card-body small">Hanya <code>title</code> dan <code>description</code>. Mengirim <code>target_url</code> dijawab 422 (<code>target_locked</code>), karena QR yang sudah dicetak tidak bisa ikut berubah.</div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-delete">DELETE</span> <code>/api/v1/qr/{uuid}</code> <span class="text-muted small ms-2">Hapus</span></div>
        <div class="card-body small">HTTP 204. Setelah dihapus, <code>external_ref</code> bebas dipakai lagi. QR yang sudah dicetak tetap berfungsi karena isinya adalah alamat tujuan itu sendiri.</div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><span class="method m-get">GET</span> <code>/api/v1/openapi.json</code> <span class="text-muted small ms-2">Spesifikasi OpenAPI 3 (tanpa token)</span></div>
        <div class="card-body small">
            Impor ke Postman, Insomnia, atau Swagger UI, atau pakai untuk membuat pustaka klien otomatis. Untuk pemantauan layanan tersedia <code>GET /api/health</code> (tanpa token; 503 bila database bermasalah).
            <div class="mt-2"><a href="{{ $baseUrl }}/api/v1/openapi.json" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Buka openapi.json</a></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-broadcast me-2"></i>Webhook</h2></div>
        <div class="card-body small">
            Administrator dapat mengisi alamat webhook per sistem di menu Klien API. Saat QR milik sistem itu <strong>dibuat</strong> (<code>qr.created</code>), <strong>judul/keterangannya diubah</strong> (<code>qr.updated</code>), atau <strong>dihapus</strong> (<code>qr.deleted</code>), kami mengirim <code>POST</code> JSON ke alamat itu, termasuk bila perubahannya dilakukan lewat web. Pengiriman sekali coba (batas 5 detik, tanpa mengikuti pengalihan); jawab dengan HTTP 2xx.
            <pre class="mt-2">POST https://sistem-anda/hook
X-QR-Event: qr.created
X-QR-Delivery: 7d1c...            (ID unik tiap kiriman)
X-QR-Signature: sha256=9f2a...    (HMAC-SHA256 dari body mentah dengan rahasia webhook)

{"event": "qr.created", "delivered_at": "2026-10-22T09:00:00+08:00", "data": { ...objek QR seperti di atas... }}</pre>
            Verifikasi tanda tangan (PHP): <code>hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $_SERVER['HTTP_X_QR_SIGNATURE'])</code>. Rahasia webhook hanya tampil sekali saat alamat pertama kali disimpan.
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Kode status</h2></div>
        <div class="card-body small">
            <code>200</code> berhasil / sudah ada &middot; <code>201</code> dibuat &middot; <code>204</code> terhapus &middot; <code>401</code> token tidak valid &middot; <code>403</code> sistem nonaktif atau IP tidak diizinkan &middot;
            <code>404</code> tidak ditemukan &middot; <code>409</code> <code>external_ref</code> bentrok &middot; <code>422</code> isian tidak valid &middot; <code>429</code> melebihi batas permintaan (lihat header <code>Retry-After</code>).
        </div>
    </div>
</div>
@endsection
