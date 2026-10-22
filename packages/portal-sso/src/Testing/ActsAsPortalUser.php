<?php

namespace Akp\PortalSso\Testing;

/**
 * Pembantu tes untuk aplikasi yang memakai akp/portal-sso. Pakai di tests/TestCase.php:
 *
 *   abstract class TestCase extends BaseTestCase
 *   {
 *       use \Akp\PortalSso\Testing\ActsAsPortalUser;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->loginPortal();           // semua test berjalan sebagai pengguna Portal yang sudah login
 *       }
 *   }
 *
 * Tes tamu / level lain:  $this->logoutPortal();  atau  $this->loginPortal(['role' => 'viewer', 'level' => 10]);
 */
trait ActsAsPortalUser
{
    /** Pura-pura sudah login lewat Portal (sesi yang dibentuk callback SSO). */
    protected function loginPortal(array $user = []): static
    {
        return $this->withSession([
            'portal.user' => $user + [
                'id' => '1', 'username' => 'tester', 'employee_no' => '32601949', 'name' => 'Penguji',
                'email' => 'tester@example.test', 'role' => 'admin', 'level' => 100, 'is_admin' => false,
            ],
            'portal.started_at' => time(),
        ]);
    }

    protected function logoutPortal(): static
    {
        return $this->flushSession();
    }
}