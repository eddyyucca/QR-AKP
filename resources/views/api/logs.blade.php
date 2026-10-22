@extends('layouts.app')

@section('title', 'Log API')
@section('page-title', 'Log API')
@section('breadcrumb')
    <li class="breadcrumb-item">Integrasi</li>
    <li class="breadcrumb-item active">Log API</li>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Permintaan hari ini</div><div class="h4 mb-0">{{ number_format($summary['today']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">7 hari terakhir</div><div class="h4 mb-0">{{ number_format($summary['week']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Galat (4xx/5xx) 7 hari</div><div class="h4 mb-0 {{ $summary['errors'] ? 'text-danger' : '' }}">{{ number_format($summary['errors']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Rata-rata waktu respons</div><div class="h4 mb-0">{{ $summary['avg_ms'] }} ms</div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-activity me-2"></i>Permintaan terbaru <span class="text-muted small">(disimpan 90 hari)</span></h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('api.logs') }}" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-4">
                    <label for="client" class="form-label small text-muted mb-1">Sistem</label>
                    <select id="client" name="client" class="form-select">
                        <option value="">Semua sistem</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected($filters['client'] === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="result" class="form-label small text-muted mb-1">Hasil</label>
                    <select id="result" name="result" class="form-select">
                        <option value="">Semua</option>
                        <option value="ok" @selected($filters['result'] === 'ok')>Berhasil (2xx)</option>
                        <option value="error" @selected($filters['result'] === 'error')>Galat (4xx/5xx)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Saring</button>
                    <a href="{{ route('api.logs') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th>Waktu</th><th>Sistem</th><th>Permintaan</th><th>Status</th><th>IP</th><th class="text-end">Durasi</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="small text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->apiClient?->name ?? '(dihapus)' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $log->method }}</span> <code>{{ $log->path }}</code></td>
                                <td><span class="badge {{ $log->status < 300 ? 'bg-success-subtle text-success' : ($log->status < 500 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger') }}">{{ $log->status }}</span></td>
                                <td class="small">{{ $log->ip }}</td>
                                <td class="text-end small">{{ $log->duration_ms }} ms</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-activity fs-1 d-block mb-2"></i>Belum ada permintaan API.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $logs->links() }}</div>
        </div>
    </div>
@endsection
