@extends('layouts.app')

@section('title', 'Beranda')
@section('page-title', 'QR Code AKP')
@section('breadcrumb')
    <li class="breadcrumb-item active">Beranda</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('qr.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-list-ul"></i><span class="d-none d-sm-inline">{{ $isAdmin ? 'Semua QR' : 'QR Saya' }}</span>
    </a>
@endpush

@push('styles')
<style>
    .hero { background: linear-gradient(135deg, #0e2a47 0%, #1b4f86 100%); color: #fff; border-radius: 18px; }
    .hero .form-control { border: 0; }
    .step { display: flex; gap: .65rem; align-items: flex-start; }
    .step-no { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%; background: rgba(255,255,255,.18); display: grid; place-items: center; font-weight: 700; font-size: .85rem; }
    .qr-card { border: 1px solid #e5eaf1; border-radius: 14px; background: #fff; transition: box-shadow .15s, transform .15s; height: 100%; }
    .qr-card:hover { box-shadow: 0 8px 22px rgba(15, 42, 71, .12); transform: translateY(-2px); }
    .qr-card img { width: 100%; max-width: 150px; aspect-ratio: 1; }
</style>
@endpush

@section('content')
    {{-- Panduan + pembuatan cepat --}}
    <div class="hero p-4 p-lg-5 mb-4 shadow-sm">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-7">
                <h2 class="h3 fw-bold mb-2">Ubah link jadi QR resmi AKP</h2>
                <p class="mb-4 opacity-75">QR berlogo AKP yang langsung membawa pemindai ke link tujuan asli. Tanpa iklan, tanpa halaman perantara.</p>

                <div class="row g-3 mb-4 small">
                    <div class="col-12 col-md-4 step"><span class="step-no">1</span><div><strong>Tempel link</strong><br><span class="opacity-75">Alamat https:// yang ingin dibagikan.</span></div></div>
                    <div class="col-12 col-md-4 step"><span class="step-no">2</span><div><strong>Lihat pratinjau</strong><br><span class="opacity-75">QR langsung muncul di samping.</span></div></div>
                    <div class="col-12 col-md-4 step"><span class="step-no">3</span><div><strong>Unduh dan cetak</strong><br><span class="opacity-75">PNG untuk dokumen, SVG untuk cetak besar.</span></div></div>
                </div>

                <form method="POST" action="{{ route('qr.store') }}" novalidate>
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-md-5">
                            <label for="title" class="visually-hidden">Judul QR</label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="150" required class="form-control form-control-lg" placeholder="Judul, mis. Formulir FTW">
                        </div>
                        <div class="col-12 col-md-7">
                            <label for="target_url" class="visually-hidden">Alamat tujuan</label>
                            <input type="url" id="target_url" name="target_url" value="{{ old('target_url') }}" maxlength="2048" required class="form-control form-control-lg" placeholder="https://contoh.akp.co.id/halaman">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-light btn-lg fw-semibold"><i class="bi bi-qr-code me-2"></i>Buat QR</button>
                            <a href="{{ route('qr.create') }}" class="btn btn-outline-light btn-lg">Form lengkap</a>
                        </div>
                    </div>
                    <div class="small mt-2 opacity-75">Pembuat tercatat: {{ $portalUser->name }}@if ($portalUser->employee_no) (NIK {{ $portalUser->employee_no }})@endif</div>
                </form>
            </div>

            <div class="col-12 col-lg-5">
                <div id="previewBox" class="preview-box"><i class="bi bi-qr-code"></i></div>
                <div id="previewHint" class="small text-center mt-2 opacity-75">Isi alamat tujuan untuk melihat pratinjau QR.</div>
            </div>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-qr-code"></i></div>
                <div><div class="kpi-label">{{ $isAdmin ? 'Total QR' : 'QR Saya' }}</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['total']) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-calendar-check"></i></div>
                <div><div class="kpi-label">Hari Ini</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['today']) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-calendar-week"></i></div>
                <div><div class="kpi-label">7 Hari Terakhir</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['week']) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info-subtle text-info"><i class="bi {{ $isAdmin ? 'bi-people' : 'bi-person-check' }}"></i></div>
                <div>
                    <div class="kpi-label">{{ $isAdmin ? 'Jumlah Pembuat' : 'Level Anda' }}</div>
                    <div class="kpi-value fs-5 fw-bold">{{ $isAdmin ? number_format($stats['creators']) : 'Pengguna' }}</div>
                </div>
            </div></div>
        </div>
    </div>

    {{-- Grafik 14 hari --}}
    @php($max = max(1, max($daily)))
    <div class="row g-3 mb-4">
        <div class="col-12 {{ $isAdmin ? 'col-lg-8' : '' }}">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h3 class="h6 mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>QR dibuat, 14 hari terakhir <span class="text-muted small">(total {{ array_sum($daily) }})</span></h3>
                <div class="d-flex align-items-end gap-1" style="height:120px" role="img" aria-label="Grafik batang jumlah QR dibuat per hari">
                    @foreach ($daily as $date => $n)
                        <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100" title="{{ \Carbon\Carbon::parse($date)->translatedFormat('d M') }}: {{ $n }} QR">
                            <div class="small text-muted" style="font-size:.7rem">{{ $n ?: '' }}</div>
                            <div class="w-100 rounded-top {{ $n ? 'bg-primary' : 'bg-secondary-subtle' }}" style="height:{{ $n ? max(4, round($n / $max * 90)) : 3 }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex gap-1 mt-1">
                    @foreach ($daily as $date => $n)
                        <div class="flex-fill text-center text-muted" style="font-size:.65rem">{{ \Carbon\Carbon::parse($date)->format('d') }}</div>
                    @endforeach
                </div>
            </div></div>
        </div>
        @if ($isAdmin)
            <div class="col-12 col-lg-4">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <h3 class="h6 mb-3"><i class="bi bi-trophy me-2 text-warning"></i>Pembuat teraktif</h3>
                    @forelse ($topCreators as $i => $c)
                        <div class="d-flex justify-content-between small py-1 {{ $loop->last ? '' : 'border-bottom' }}"><span class="text-truncate me-2">{{ $i + 1 }}. {{ $c->name }}</span><strong>{{ number_format($c->total) }}</strong></div>
                    @empty
                        <div class="text-muted small">Belum ada data.</div>
                    @endforelse
                    <div class="small text-muted mt-3">Dibuat lewat API: <strong>{{ number_format($stats['api']) }}</strong> dari {{ number_format($stats['total']) }} QR</div>
                </div></div>
            </div>
        @endif
    </div>

    {{-- QR terbaru --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h5 mb-0">QR terbaru</h3>
        <a href="{{ route('qr.index') }}" class="small text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>

    @if ($recent->isEmpty())
        <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-qr-code fs-1 d-block mb-2"></i>
            Belum ada QR. Tempel link di kotak biru di atas untuk membuat QR pertama Anda.
        </div></div>
    @else
        <div class="row g-3 mb-4">
            @foreach ($recent as $qr)
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="qr-card p-3 text-center">
                        <a href="{{ route('qr.show', $qr) }}"><img src="{{ route('qr.image', [$qr, 'svg']) }}" alt="QR {{ $qr->title }}" class="border rounded bg-white p-1" loading="lazy"></a>
                        <a href="{{ route('qr.show', $qr) }}" class="d-block fw-semibold text-decoration-none mt-2 text-truncate" title="{{ $qr->title }}">{{ $qr->title }}</a>
                        <div class="small text-muted text-truncate" title="{{ $qr->target_url }}">{{ parse_url($qr->target_url, PHP_URL_HOST) }}</div>
                        <div class="small text-muted mt-1">{{ $qr->creator_name }} &middot; {{ $qr->created_at->diffForHumans() }}</div>
                        @if ($qr->isFromApi())<span class="badge bg-secondary-subtle text-secondary mt-1">API</span>@endif
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a href="{{ route('qr.show', $qr) }}" class="btn btn-outline-primary btn-sm" title="Lihat"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('qr.image', [$qr, 'png']) }}?unduh=1" class="btn btn-outline-success btn-sm" title="Unduh PNG"><i class="bi bi-download"></i></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Tips --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="h6 mb-3"><i class="bi bi-lightbulb me-2 text-warning"></i>Tips supaya QR mudah dipindai</h3>
            <div class="row g-3 small text-muted">
                <div class="col-12 col-md-4">Cetak minimal <strong>2 x 2 cm</strong>. Makin jauh jarak pindai, makin besar ukurannya.</div>
                <div class="col-12 col-md-4">Jaga kontras: biarkan latar <strong>putih</strong> dan jangan menutup atau mengubah warna QR.</div>
                <div class="col-12 col-md-4">Selalu <strong>uji pindai</strong> dengan kamera HP sebelum dicetak banyak. Alamat tujuan tidak bisa diubah setelah QR dibuat.</div>
            </div>
        </div>
    </div>
@endsection

@include('qr._preview')
