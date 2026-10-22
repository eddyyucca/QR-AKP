<?php

use Akp\PortalSso\PortalSession;
use Akp\PortalSso\PortalUser;

if (! function_exists('portal_user')) {
    /** Pengguna yang sedang login lewat Portal, atau null jika belum login. */
    function portal_user(): ?PortalUser
    {
        return app(PortalSession::class)->user();
    }
}

if (! function_exists('portal_sso_url')) {
    /** Alamat Portal untuk pengguna ini: dipelajari dari token login Portal, dengan PORTAL_URL sebagai cadangan. */
    function portal_sso_url(): string
    {
        return \Akp\PortalSso\Support\PortalAddress::resolve();
    }
}

if (! function_exists('adapt_host_url')) {
    /** Menyesuaikan alamat tersimpan (localhost atau {host}) dengan alamat yang dipakai pengguna saat ini. */
    function adapt_host_url(?string $url): string
    {
        return \Akp\PortalSso\Support\HostUrl::adapt($url);
    }
}
