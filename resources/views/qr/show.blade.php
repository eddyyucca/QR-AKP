@extends('layouts.app')

@section('title', $qr->title)
@section('page-title', 'Detail QR')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('qr.index') }}">QR</a></li>
    <li class="breadcrumb-item active">Detail</li>
@endsection

@push('styles')
<style>
.qr-style{max-width:340px;margin-inline:auto;border:1px solid var(--bs-border-color);border-radius:.5rem;padding:.25rem .85rem}
.qr-style-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.6rem 0}
.qr-style-row + .qr-style-row{border-top:1px solid var(--bs-border-color)}
.qr-style-label{font-size:.85rem;color:var(--bs-secondary-color)}
.qr-style .form-check-input{width:2.4em;height:1.3em;float:none;cursor:pointer}
.swatch{width:26px;height:26px;padding:0;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 1px var(--bs-border-color);cursor:pointer}
.swatch.active{box-shadow:0 0 0 2px #2563eb}
</style>
@endpush

@section('content')
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <img id="qrImg" src="{{ route('qr.image', [$qr, 'png']) }}" alt="QR {{ $qr->title }}" class="img-fluid border rounded bg-white p-2" style="max-width:340px;width:100%">
                    <div class="qr-style mt-3" id="qrStyle" data-png="{{ route('qr.image', [$qr, 'png']) }}" data-svg="{{ route('qr.image', [$qr, 'svg']) }}">
                        <div class="qr-style-row">
                            <span class="qr-style-label">Warna</span>
                            <div class="d-flex align-items-center gap-2">
                                @foreach (\App\Support\QrImage::PRESETS as $key => [$label, $hex])
                                    <button type="button" class="swatch {{ $key === 'akp' ? 'active' : '' }}" data-warna="{{ $key }}" title="{{ $label }}" aria-label="Warna {{ $label }}" style="background:#{{ $hex ?? '0e2a47' }}"></button>
                                @endforeach
                            </div>
                        </div>
                        <div class="qr-style-row">
                            <label class="qr-style-label mb-0" for="qrLogo">Logo AKP</label>
                            <div class="form-check form-switch m-0 p-0 d-flex align-items-center">
                                <input class="form-check-input m-0" type="checkbox" role="switch" id="qrLogo" checked>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <a id="dlPng" href="{{ route('qr.image', [$qr, 'png']) }}?unduh=1" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Unduh PNG</a>
                        <a id="dlSvg" href="{{ route('qr.image', [$qr, 'svg']) }}?unduh=1" class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>Unduh SVG</a>
                    </div>
                    <div class="small text-muted mt-3">PNG untuk dokumen dan layar. SVG untuk cetak ukuran besar (tetap tajam).</div>
                    <hr>
                    <div class="text-start small">
                        <div class="fw-semibold mb-1"><i class="bi bi-check2-circle text-success me-1"></i>Sebelum dicetak</div>
                        <ul class="ps-3 mb-0 text-muted">
                            <li>Uji pindai dengan kamera HP.</li>
                            <li>Cetak minimal 2 x 2 cm dengan latar putih.</li>
                            <li>Pilih warna gelap dengan latar putih; jangan menutup logo.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h2 class="h5 mb-3">{{ $qr->title }}</h2>
                        <div class="d-flex gap-2">
                            <a href="{{ route('qr.edit', $qr) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Ubah</a>
                            <form method="POST" action="{{ route('qr.destroy', $qr) }}" onsubmit="return confirm('Hapus QR ini? QR yang sudah dicetak tetap berfungsi, tetapi datanya hilang dari daftar.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button>
                            </form>
                        </div>
                    </div>

                    <div class="small text-muted mb-1">Alamat tujuan</div>
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <a href="{{ $qr->target_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-break">{{ $qr->target_url }}</a>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0" id="copyUrl" data-url="{{ $qr->target_url }}"><i class="bi bi-clipboard"></i></button>
                    </div>

                    @if ($qr->description)
                        <div class="small text-muted mb-1">Keterangan</div>
                        <p class="mb-0" style="white-space:pre-line">{{ $qr->description }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-person-badge me-2"></i>Detail pembuat</h3></div>
                <div class="card-body p-0">
                    <table class="table mb-0 small">
                        <tbody>
                            <tr><th class="text-muted fw-normal" style="width:38%">Nama</th><td class="fw-semibold">{{ $qr->creator_name }}</td></tr>
                            <tr><th class="text-muted fw-normal">NIK</th><td>{{ $qr->creator_nik ?: '-' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Email</th><td>{{ $qr->creator_email ?: '-' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Level</th><td>{{ $qr->creatorRoleLabel() }}</td></tr>
                            <tr><th class="text-muted fw-normal">Sumber</th><td>{{ $qr->isFromApi() ? 'REST API (sistem: '.($qr->apiClient?->name ?? 'sudah dihapus').')' : 'Web QR Code AKP' }}</td></tr>
                            @if ($qr->external_ref)
                                <tr><th class="text-muted fw-normal">Referensi sistem</th><td><code>{{ $qr->external_ref }}</code></td></tr>
                            @endif
                            <tr><th class="text-muted fw-normal">Dibuat</th><td>{{ $qr->created_at->translatedFormat('l, d F Y H:i') }} WITA</td></tr>
                            @if ($qr->updated_by_name)
                                <tr><th class="text-muted fw-normal">Terakhir diubah</th><td>{{ $qr->updated_at->translatedFormat('d M Y H:i') }} oleh {{ $qr->updated_by_name }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const box = document.getElementById('qrStyle'); if (!box) return;
    let warna = 'akp';
    const q = (extra = '') => '?warna=' + warna + (document.getElementById('qrLogo').checked ? '' : '&logo=0') + extra;
    const apply = () => {
        document.getElementById('qrImg').src = box.dataset.png + q();
        document.getElementById('dlPng').href = box.dataset.png + q('&unduh=1');
        document.getElementById('dlSvg').href = box.dataset.svg + q('&unduh=1');
    };
    box.querySelectorAll('.swatch').forEach(b => b.addEventListener('click', () => {
        warna = b.dataset.warna;
        box.querySelectorAll('.swatch').forEach(x => x.classList.toggle('active', x === b));
        apply();
    }));
    document.getElementById('qrLogo').addEventListener('change', apply);
})();
document.getElementById('copyUrl')?.addEventListener('click', function () {
    const url = this.dataset.url;
    const done = () => { this.innerHTML = '<i class="bi bi-check2"></i>'; setTimeout(() => this.innerHTML = '<i class="bi bi-clipboard"></i>', 1500); };
    (navigator.clipboard?.writeText(url) ?? Promise.reject()).then(done).catch(() => { window.prompt('Salin alamat:', url); });
});
</script>
@endpush
