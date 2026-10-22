@extends('layouts.app')

@section('title', 'Buat QR Baru')
@section('page-title', 'Buat QR Baru')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('qr.index') }}">QR</a></li>
    <li class="breadcrumb-item active">Buat</li>
@endsection

@section('content')
    <div class="row g-4 justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-pencil-square me-2"></i>Data QR</h2></div>
                <div class="card-body p-4">
                    <p class="text-muted small">
                        QR bergaya AKP (logo AKP di tengah) yang <strong>langsung mengarah ke alamat tujuan asli</strong>,
                        tanpa halaman perantara dan tanpa iklan.
                    </p>

                    <form method="POST" action="{{ route('qr.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="title" class="form-label">Judul QR <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="150" required
                                   class="form-control @error('title') is-invalid @enderror" placeholder="Contoh: Formulir Fit To Work">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Nama untuk Anda mengenali QR ini di daftar. Tidak tampil di gambar QR.</div>
                        </div>

                        <div class="mb-3">
                            <label for="target_url" class="form-label">Alamat tujuan (link) <span class="text-danger">*</span></label>
                            <input type="url" id="target_url" name="target_url" value="{{ old('target_url') }}" maxlength="2048" required
                                   class="form-control @error('target_url') is-invalid @enderror" placeholder="https://contoh.akp.co.id/halaman">
                            @error('target_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Harus diawali <code>http://</code> atau <code>https://</code>. <strong>Tidak bisa diubah</strong> setelah QR dibuat, karena QR yang sudah dicetak tidak ikut berubah.</div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea id="description" name="description" rows="3" maxlength="500"
                                      class="form-control @error('description') is-invalid @enderror" placeholder="Opsional: untuk apa QR ini dipakai, di mana akan ditempel">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-qr-code me-1"></i>Buat QR</button>
                            <a href="{{ route('qr.index') }}" class="btn btn-outline-secondary">Batal</a>
                            <span class="small text-muted ms-auto">
                                Pembuat tercatat: <strong>{{ $portalUser->name }}</strong>@if ($portalUser->employee_no) (NIK {{ $portalUser->employee_no }})@endif
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-eye me-2"></i>Pratinjau</h2></div>
                <div class="card-body p-4 text-center">
                    <div id="previewBox" class="preview-box"><i class="bi bi-qr-code"></i></div>
                    <div id="previewHint" class="small text-muted mt-3">Isi alamat tujuan untuk melihat pratinjau QR.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@include('qr._preview')
