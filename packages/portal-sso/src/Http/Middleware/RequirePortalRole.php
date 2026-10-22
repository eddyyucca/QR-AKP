<?php

namespace Akp\PortalSso\Http\Middleware;

use Akp\PortalSso\PortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi route berdasarkan kode level dari Portal:  ->middleware('portal.role:admin,hr')
 * Super admin Portal selalu lolos. Berlaku setelah login (grup 'web' sudah mewajibkannya).
 */
class RequirePortalRole
{
    public function __construct(private PortalSession $portal)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($this->portal->user()?->hasRole(...$roles), 403, 'Level akses Anda tidak cukup untuk membuka halaman ini.');

        return $next($request);
    }
}