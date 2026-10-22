@extends('layouts.app')

@section('title', 'Ubah QR')
@section('page-title', 'Ubah QR')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('qr.index') }}">QR</a></li>
    <li class="breadcrumb-item active">Ubah</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="alert alert-info small">
                        Alamat tujuan <strong>tidak bisa diubah</strong> karena QR yang sudah dicetak atau dibagikan tidak ikut berubah.
                        Untuk alamat baru, buat QR baru.
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted">Alamat tujuan</div>
                        <div class="text-break">{{ $qr->target_url }}</div>
                    </div>

                    <form method="POST" action="{{ route('qr.update', $qr) }}" novalidate>
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="title" class="form-label">Judul QR <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title', $qr->title) }}" maxlength="150" required
                                   class="form-control @error('title') is-invalid @enderror">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea id="description" name="description" rows="3" maxlength="500"
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $qr->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('qr.show', $qr) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
