<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login gagal | {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background:#f5f7fb">
<div class="container py-5" style="max-width:480px">
    <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
        <i class="bi bi-shield-exclamation text-danger" style="font-size:2.5rem"></i>
        <h1 class="h5 mt-2">Tidak bisa masuk ke {{ config('app.name') }}</h1>
        <p class="text-muted mb-4">{{ $message }}</p>
        <a href="{{ $portalUrl }}" class="btn text-white" style="background:#203868">Kembali ke Portal</a>
    </div>
</div>
</body>
</html>