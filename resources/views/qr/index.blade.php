@extends('layouts.app')

@section('title', $isAdmin ? 'Semua QR' : 'QR Saya')
@section('page-title', $isAdmin ? 'Semua QR' : 'QR Saya')
@section('breadcrumb')
    <li class="breadcrumb-item active">{{ $isAdmin ? 'Semua QR' : 'QR Saya' }}</li>
@endsection

@push('topbar-actions')
    <a href="{{ route('qr.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">Buat QR Baru</span>
    </a>
@endpush

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-qr-code"></i></div>
                <div><div class="kpi-label">{{ $isAdmin ? 'Total QR' : 'QR Saya' }}</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['total']) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-calendar-check"></i></div>
                <div><div class="kpi-label">Dibuat Hari Ini</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['today']) }}</div></div>
            </div></div>
        </div>
        @if ($isAdmin)
            <div class="col-6 col-md-3">
                <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
                    <div><div class="kpi-label">Jumlah Pembuat</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['creators']) }}</div></div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-card h-100"><div class="erp-card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-person-check"></i></div>
                    <div><div class="kpi-label">Buatan Saya</div><div class="kpi-value fs-5 fw-bold">{{ number_format($stats['mine']) }}</div></div>
                </div></div>
            </div>
        @endif
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('qr.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-{{ $isAdmin ? 5 : 8 }}">
                    <label for="q" class="form-label small text-muted mb-1">Cari</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] }}" class="form-control"
                           placeholder="{{ $isAdmin ? 'Judul, alamat tujuan, nama atau NIK pembuat' : 'Judul atau alamat tujuan' }}">
                </div>
                @if ($isAdmin)
                    <div class="col-12 col-md-4">
                        <label for="creator" class="form-label small text-muted mb-1">Pembuat</label>
                        <select id="creator" name="creator" class="form-select">
                            <option value="">Semua pembuat</option>
                            @foreach ($creators as $c)
                                <option value="{{ $c->creator_portal_id }}" @selected($filters['creator'] === (string) $c->creator_portal_id)>{{ $c->creator_name }} ({{ $c->total }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-1">
                    <label for="per_page" class="form-label small text-muted mb-1">Baris</label>
                    <select id="per_page" name="per_page" class="form-select">
                        @foreach ([15, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($filters['per_page'] === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Cari</button>
                    <a href="{{ route('qr.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <a href="{{ route('qr.export', array_filter(['q' => $filters['q'], 'creator' => $filters['creator']])) }}" class="btn btn-outline-success" title="Ekspor CSV (sesuai saringan)"><i class="bi bi-filetype-csv"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:72px">QR</th>
                            <th>Judul dan alamat tujuan</th>
                            <th>Pembuat</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $qr)
                            <tr>
                                <td>
                                    <a href="{{ route('qr.show', $qr) }}">
                                        <img src="{{ route('qr.image', [$qr, 'svg']) }}" alt="QR {{ $qr->title }}" width="56" height="56" loading="lazy" class="border rounded bg-white">
                                    </a>
                                </td>
                                <td style="max-width:360px">
                                    <a href="{{ route('qr.show', $qr) }}" class="fw-semibold text-decoration-none">{{ $qr->title }}</a>
                                    <div class="small text-muted text-truncate" title="{{ $qr->target_url }}">{{ $qr->target_url }}</div>
                                    @if ($qr->external_ref)<div class="small text-muted">Ref: <code>{{ $qr->external_ref }}</code></div>@endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $qr->creator_name }}</div>
                                    <div class="small text-muted">
                                        NIK {{ $qr->creator_nik ?: '-' }}
                                        &middot; <span class="badge {{ match ($qr->creator_role) { 'admin' => 'bg-primary-subtle text-primary', 'api' => 'bg-warning-subtle text-warning', default => 'bg-secondary-subtle text-secondary' } }}">{{ $qr->creatorRoleLabel() }}</span>
                                    </div>
                                </td>
                                <td class="small text-nowrap">{{ $qr->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('qr.show', $qr) }}" class="btn btn-outline-primary btn-sm" title="Lihat dan unduh"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('qr.image', [$qr, 'png']) }}?unduh=1" class="btn btn-outline-success btn-sm" title="Unduh PNG"><i class="bi bi-download"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-qr-code fs-1 d-block mb-2"></i>
                                    Belum ada QR. <a href="{{ route('qr.create') }}">Buat QR pertama</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
