<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QR Code AKP') | {{ config('app.name', 'QR Code AKP') }}</title>
    <link rel="icon" href="{{ asset('assets/images/akp.png') }}" type="image/png">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('styles')

    {{-- Restore sidebar collapsed state before paint --}}
    <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed-init');
        }
    </script>
</head>

<body style="background:var(--app-bg);">
<div class="app-shell" id="appShell">

    {{-- Sidebar desktop --}}
    <aside class="sidebar-desktop" id="desktopSidebar">
        @include('partials.brand')
        @include('partials.sidebar')
    </aside>

    {{-- Sidebar mobile (offcanvas) --}}
    <div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            @include('partials.brand', ['compact' => true])
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            @include('partials.sidebar')
        </div>
    </div>

    <div class="main-shell">
        @include('partials.header')

        <main class="content-shell">
            @include('partials.alerts')
            @yield('content')
        </main>

        @include('partials.footer')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Collapse sidebar desktop (state tersimpan di localStorage persis seperti HRIS)
    const appShell = document.getElementById('appShell');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (localStorage.getItem('sidebarCollapsed') === 'true') appShell.classList.add('sidebar-collapsed');
    document.documentElement.classList.remove('sidebar-collapsed-init');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            appShell.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', appShell.classList.contains('sidebar-collapsed'));
        });
    }

    // Grup menu sidebar yang bisa dilipat
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('[data-sidebar-toggle]');
        if (!toggle) return;
        const content = toggle.nextElementSibling;
        if (!content || !content.classList.contains('sidebar-collapse-content')) return;

        const closing = !content.classList.contains('closed');
        content.classList.toggle('closed', closing);
        toggle.classList.toggle('collapsed', closing);
    });
});
</script>
@stack('scripts')
</body>
</html>
