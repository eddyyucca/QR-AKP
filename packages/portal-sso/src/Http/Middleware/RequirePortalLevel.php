<?php

namespace Akp\PortalSso\Http\Middleware;

use Akp\PortalSso\PortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi route berdasarkan tingkat level dari Portal:  ->middleware('portal.level:50')
 * Super admin Portal selalu lolos. Berlaku setelah login (grup 'web' sudah mewajibkannya).
 */
class RequirePortalLevel
{
    public function __construct(private PortalSession $portal)
    {
    }

    public function handle(Request $request, Closure $next, int|string $level): Response
    {
        abort_unless($this->portal->user()?->atLeast((int) $level), 403, 'Level akses Anda tidak cukup untuk membuka halaman ini.');

        return $next($request);
    }
}