<?php

namespace Akp\PortalSso;

use Illuminate\Contracts\Session\Session;

/** Sesi login aplikasi yang dibentuk dari token SSO Portal. */
class PortalSession
{
    public function __construct(private Session $session)
    {
    }

    /** Mulai sesi dari klaim token. ID sesi diganti untuk mencegah session fixation. */
    public function start(array $claims): void
    {
        $intended = $this->session->get('portal.intended');

        $this->session->regenerate();
        $this->session->put('portal.user', PortalUser::fromClaims($claims)->toArray());
        $this->session->put('portal.started_at', time());

        if ($intended) {
            $this->session->put('portal.intended', $intended);
        }
    }

    public function user(): ?PortalUser
    {
        if (! $this->session->has('portal.user')) {
            return null;
        }

        $age = time() - (int) $this->session->get('portal.started_at', 0);
        if ($age > config('sso.max_session_minutes') * 60) {
            $this->end();

            return null;
        }

        return PortalUser::fromArray($this->session->get('portal.user'));
    }

    public function end(): void
    {
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}