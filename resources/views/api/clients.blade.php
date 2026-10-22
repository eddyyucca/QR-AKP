@extends('layouts.app')

@section('title', 'Klien API')
@section('page-title', 'Klien API')
@section('breadcrumb')
    <li class="breadcrumb-item">Integrasi</li>
    <li class="breadcrumb-item active">Klien API</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('api.docs') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-braces"></i><span class="d-none d-sm-inline">Dokumentasi</span>
    </a>
@endpush

@section('content')
    @if ($newToken)
        <div class="alert alert-warning border-warning shadow-sm" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Token untuk {{ $newTokenName }}: salin sekarang</div>
            <div class="small mb-2">Token ini <strong>hanya tampil sekali</strong>. Simpan di pengelola kata sandi atau langsung di pengaturan sistem pemanggil. Jangan dikirim lewat chat atau email.</div>
            <div class="input-group">
                <input type="text" id="newToken" class="form-control font-monospace" value="{{ $newToken }}" readonly onfocus="this.select()">
                <button type="button" class="btn btn-dark" id="copyToken"><i class="bi bi-clipboard me-1"></i>Salin</button>
            </div>
        </div>
    @endif

    @if ($newWebhookSecret)
        <div class="alert alert-warning border-warning shadow-sm" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Rahasia webhook untuk {{ $newTokenName }}: salin sekarang</div>
            <div class="small mb-2">Dipakai sistem pemanggil untuk memeriksa tanda tangan <code>X-QR-Signature</code>. <strong>Hanya tampil sekali.</strong></div>
            <input type="text" class="form-control font-monospace" value="{{ $newWebhookSecret }}" readonly onfocus="this.select()">
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-plus-circle me-2"></i>Tambah sistem</h2></div>
                <div class="card-body">
                    <p class="small text-muted">Satu sistem (misalnya P2H) memakai satu token. Token hanya melihat QR yang dibuat oleh tokennya sendiri.</p>
                    <form method="POST" action="{{ route('api.clients.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama sistem <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="100" required class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: P2H AKP">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Keterangan</label>
                            <input type="text" id="description" name="description" value="{{ old('description') }}" maxlength="255" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="rate_per_minute" class="form-label">Batas permintaan per menit</label>
                            <input type="number" id="rate_per_minute" name="rate_per_minute" value="{{ old('rate_per_minute', 60) }}" min="1" max="1000" class="form-control @error('rate_per_minute') is-invalid @enderror">
                            @error('rate_per_minute')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="allowed_ips" class="form-label">IP yang diizinkan</label>
                            <input type="text" id="allowed_ips" name="allowed_ips" value="{{ old('allowed_ips') }}" maxlength="255" class="form-control @error('allowed_ips') is-invalid @enderror" placeholder="Kosong = semua. Contoh: 172.100.1.113, 10.0.0.0/24">
                            @error('allowed_ips')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Disarankan diisi dengan IP server sistem pemanggil.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-1"></i>Buat token</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-plug me-2"></i>Sistem terhubung</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead><tr><th>Sistem</th><th>Token</th><th class="text-center">QR</th><th>Terakhir dipakai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($clients as $c)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $c->name }}</div>
                                            <div class="small text-muted">{{ $c->description ?: '-' }}</div>
                                            <div class="small text-muted">{{ $c->rate_per_minute }}/menit &middot; IP: {{ $c->allowed_ips ?: 'semua' }}</div>
                                            <form method="POST" action="{{ route('api.clients.webhook', $c) }}" class="d-flex gap-1 mt-2">@csrf
                                                <input type="text" name="webhook_url" value="{{ $c->webhook_url }}" maxlength="255" class="form-control form-control-sm" placeholder="Webhook: https://sistem-anda/hook (kosong = mati)" aria-label="Alamat webhook {{ $c->name }}">
                                                <button class="btn btn-outline-primary btn-sm" title="Simpan webhook"><i class="bi bi-check2"></i></button>
                                            </form>
                                            @if ($c->webhook_url)
                                                <div class="small mt-1 d-flex align-items-center gap-2">
                                                    @if ($c->webhook_last_at)
                                                        <span class="badge {{ $c->webhook_last_status >= 200 && $c->webhook_last_status < 300 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $c->webhook_last_status ?: 'gagal' }}</span>
                                                        <span class="text-muted">{{ $c->webhook_last_at->diffForHumans() }}</span>
                                                    @else
                                                        <span class="text-muted">belum pernah terkirim</span>
                                                    @endif
                                                    <button form="wh-test-{{ $c->id }}" class="btn btn-link btn-sm p-0">Kirim uji</button>
                                                </div>
                                                <form id="wh-test-{{ $c->id }}" method="POST" action="{{ route('api.clients.webhook.test', $c) }}">@csrf</form>
                                            @endif
                                        </td>
                                        <td><code>{{ $c->token_prefix }}…</code></td>
                                        <td class="text-center">{{ number_format($c->qr_codes_count) }}</td>
                                        <td class="small">{{ $c->last_used_at?->diffForHumans() ?? 'belum pernah' }}</td>
                                        <td>
                                            <span class="badge {{ $c->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <form method="POST" action="{{ route('api.clients.toggle', $c) }}" class="d-inline">@csrf
                                                <button class="btn btn-outline-secondary btn-sm" title="{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="bi {{ $c->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('api.clients.regenerate', $c) }}" class="d-inline" onsubmit="return confirm('Buat token baru? Token lama langsung tidak berlaku dan sistem pemanggil harus diperbarui.');">@csrf
                                                <button class="btn btn-outline-warning btn-sm" title="Token baru"><i class="bi bi-arrow-repeat"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('api.clients.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Hapus sistem ini? Tokennya langsung tidak berlaku. QR yang pernah dibuatnya tetap ada.');">@csrf @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" title="Hapus"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-plug fs-1 d-block mb-2"></i>Belum ada sistem yang terhubung. Tambah sistem di sebelah kiri untuk mendapat token.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('copyToken')?.addEventListener('click', function () {
    const el = document.getElementById('newToken');
    el.select();
    (navigator.clipboard?.writeText(el.value) ?? Promise.reject()).then(() => { this.innerHTML = '<i class="bi bi-check2 me-1"></i>Tersalin'; }).catch(() => document.execCommand('copy'));
});
</script>
@endpush
