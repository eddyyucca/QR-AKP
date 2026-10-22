{{--
    Sidebar mengikuti pola HRIS: Dashboard tunggal di atas, lalu grup yang bisa dilipat.
    Grup yang berisi halaman aktif terbuka; grup lain tertutup.
--}}
@php
    $isAdmin = (bool) portal_user()?->hasRole('admin');

    $item = fn (string $title, string $icon, string $route, array $match) => [
        'title' => $title, 'icon' => $icon, 'href' => route($route), 'active' => request()->routeIs(...$match),
    ];

    $menus = [
        $item('Beranda', 'bi bi-grid-1x2-fill', 'qr.home', ['qr.home']),
        [
            'title' => 'Kode QR',
            'children' => [
                $item('Buat QR Baru', 'bi bi-plus-square', 'qr.create', ['qr.create']),
                $item($isAdmin ? 'Semua QR' : 'QR Saya', 'bi bi-qr-code', 'qr.index', ['qr.index', 'qr.show', 'qr.edit']),
            ],
        ],
        [
            'title' => 'Integrasi',
            'children' => array_values(array_filter([
                $item('Dokumentasi API', 'bi bi-braces', 'api.docs', ['api.docs']),
                $isAdmin ? $item('Klien API', 'bi bi-key', 'api.clients', ['api.clients']) : null,
                $isAdmin ? $item('Log API', 'bi bi-activity', 'api.logs', ['api.logs']) : null,
            ])),
        ],
    ];

    foreach ($menus as &$menu) {
        if (isset($menu['children'])) {
            $menu['active'] = collect($menu['children'])->contains('active', true);
        }
    }
    unset($menu);
@endphp

<div class="sidebar-section">
    @foreach ($menus as $menu)
        @if (isset($menu['children']))
            <div class="sidebar-group mt-2">
                <div class="sidebar-group-toggle {{ $menu['active'] ? 'has-active' : 'collapsed' }}" data-sidebar-toggle>
                    <span>{{ $menu['title'] }}</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </div>
                <div class="sidebar-collapse-content {{ $menu['active'] ? '' : 'closed' }}">
                    <div class="sidebar-collapse-inner">
                        @foreach ($menu['children'] as $child)
                            @include('partials.sidebar-link', ['item' => $child])
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @include('partials.sidebar-link', ['item' => $menu])
        @endif
    @endforeach
</div>
