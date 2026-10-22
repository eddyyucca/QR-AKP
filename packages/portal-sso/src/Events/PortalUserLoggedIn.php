<?php

namespace Akp\PortalSso\Events;

use Akp\PortalSso\PortalUser;
use Illuminate\Http\Request;

/**
 * Dikirim setelah token SSO valid dan sesi Portal terbentuk, SEBELUM pengguna diteruskan ke halaman tujuan.
 *
 * Untuk aplikasi yang punya tabel user sendiri (mis. karena data bisnis mereferensikan users.id): dengarkan event ini,
 * cari/buat user lokal dari $user->employee_no atau $user->id, lalu Auth::login($local).
 * Untuk menolak login, lempar Akp\PortalSso\Exceptions\LoginDenied.
 */
class PortalUserLoggedIn
{
    public function __construct(public readonly PortalUser $user, public readonly Request $request)
    {
    }
}