<?php

namespace Akp\PortalSso\Exceptions;

use RuntimeException;

/** Token SSO dari Portal tidak sah (tanda tangan salah, kedaluwarsa, bukan untuk aplikasi ini, atau sudah dipakai). */
class InvalidSsoToken extends RuntimeException
{
}