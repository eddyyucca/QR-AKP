<?php

namespace Akp\PortalSso\Events;

use Akp\PortalSso\PortalUser;

/**
 * Dikirim saat pengguna menekan Keluar (POST /sso/logout), sebelum sesi aplikasi diakhiri.
 * Dengarkan untuk membersihkan sesi lokal (Auth::logout()) dan mencatat audit.
 * $user null bila sesi sudah habis.
 */
class PortalUserLoggingOut
{
    public function __construct(public readonly ?PortalUser $user)
    {
    }
}