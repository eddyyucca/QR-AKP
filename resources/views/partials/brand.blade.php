@if (!empty($compact))
    <div class="brand-pill">
        <img src="{{ asset('assets/images/akp.png') }}" height="30" alt="{{ config('app.name') }}">
    </div>
@else
    <div class="sidebar-brand d-flex align-items-center justify-content-center">
        <img src="{{ asset('assets/images/akp.png') }}" alt="{{ config('app.name') }}" class="sidebar-logo">
    </div>
@endif
