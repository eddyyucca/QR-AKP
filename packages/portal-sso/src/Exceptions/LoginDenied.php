<?php

namespace Akp\PortalSso\Exceptions;

use RuntimeException;

/** Dilempar listener PortalUserLoggedIn untuk menolak login (mis. akun dinonaktifkan di aplikasi). Pesannya ditampilkan ke pengguna. */
class LoginDenied extends RuntimeException
{
}