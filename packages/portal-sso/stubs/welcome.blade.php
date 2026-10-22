<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background:#f5f7fb">
<div class="container py-5" style="max-width:640px">
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="d-flex align-items-center gap-2 text-success mb-2"><i class="bi bi-check-circle-fill fs-4"></i><strong>Login lewat AKPSatu berhasil</strong></div>
        <h1 class="h4">{{ config('app.name') }}</h1>

        {{-- portal_user() / $portalUser: identitas dari Portal --}}
        <dl class="row mb-3">
            <dt class="col-4 text-muted fw-normal">Nama</dt><dd class="col-8">{{ $portalUser->name }}</dd>
            <dt class="col-4 text-muted fw-normal">NIK</dt><dd class="col-8">{{ $portalUser->employee_no ?? '-' }}</dd>
            <dt class="col-4 text-muted fw-normal">Level</dt><dd class="col-8">{{ $portalUser->role }} (tingkat {{ $portalUser->level }}){{ $portalUser->is_admin ? ' · super admin Portal' : '' }}</dd>
        </dl>

        <p class="small text-muted">Ini halaman contoh. Ganti <code>resources/views/welcome.blade.php</code> dengan aplikasi Anda.
            Batasi fitur dengan <code>->middleware('portal.level:50')</code> atau <code>->middleware('portal.role:admin,hr')</code>.</p>

        <div class="d-flex gap-2">
            <a href="{{ portal_sso_url() }}" class="btn btn-outline-secondary"><i class="bi bi-grid-3x3-gap me-1"></i>AKPSatu</a>
            <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button></form>
        </div>
    </div>
</div>
</body>
</html>