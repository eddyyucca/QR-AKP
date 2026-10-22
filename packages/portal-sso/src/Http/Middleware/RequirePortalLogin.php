<?php

namespace Akp\PortalSso\Http\Middleware;

use Akp\PortalSso\PortalSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wajib login lewat Portal. Dipasang otomatis di grup 'web' (config sso.protect_all),
 * atau pakai alias 'portal.login' pada route tertentu.
 */
class RequirePortalLogin
{
    public function __construct(private PortalSession $portal)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExcepted($request)) {
            return $next($request);
        }

        $user = $this->portal->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(401, 'Belum login.');
            }

            // Ingat halaman tujuan (GET saja) agar setelah login kembali ke sini.
            if ($request->isMethod('GET')) {
                $request->session()->put('portal.intended', $request->fullUrl());
            }

            return redirect()->away(portal_sso_url().'/login?'.http_build_query(['app' => config('sso.app_code')]));
        }

        // Tersedia di semua view sebagai $portalUser (objek PortalUser, bisa dibaca juga sebagai array).
        View::share('portalUser', $user);

        return $next($request);
    }

    private function isExcepted(Request $request): bool
    {
        return $request->routeIs('portal.*') || $request->is(...array_map(fn ($path) => ltrim($path, '/'), config('sso.except', [])));
    }
}