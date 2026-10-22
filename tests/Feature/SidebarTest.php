<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->loginPortal(['id' => '1', 'name' => 'Admin', 'role' => 'admin', 'level' => 100]);
    }

    private function user(): static
    {
        return $this->loginPortal(['id' => '2', 'name' => 'Budi', 'role' => 'user', 'level' => 10]);
    }

    /** Teks sidebar saja (bagian desktop), tanpa halaman lain. */
    private function sidebar(string $html): string
    {
        preg_match('/<aside class="sidebar-desktop".*?<\/aside>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_sidebar_follows_the_hris_pattern(): void
    {
        $html = $this->sidebar($this->admin()->get('/qr')->getContent());

        $this->assertStringContainsString('sidebar-brand', $html);
        $this->assertStringNotContainsString('sidebar-title', $html, 'logo saja, seperti HRIS');
        $this->assertStringNotContainsString('Menu Utama', $html);
        $this->assertStringNotContainsString('Sistem &amp; Ekosistem', $html);
        $this->assertStringContainsString('Beranda', $html);
        $this->assertStringContainsString('Kode QR', $html);
        $this->assertStringContainsString('Integrasi', $html);
        $this->assertStringNotContainsString('text-info', $html, 'ikon tidak berwarna-warni');
    }

    public function test_the_group_with_the_active_page_is_open_and_the_others_are_collapsed(): void
    {
        $home = $this->sidebar($this->admin()->get('/qr')->getContent());
        $this->assertSame(2, substr_count($home, 'sidebar-group-toggle collapsed'), 'di Beranda kedua grup tertutup');
        $this->assertSame(0, substr_count($home, 'has-active'));
        $this->assertMatchesRegularExpression('/sidebar-link active[^>]*>\s*<span class="sidebar-link-icon"><i class="bi bi-grid-1x2-fill"/', $home);

        $create = $this->sidebar($this->admin()->get('/qr/buat')->getContent());
        $this->assertSame(1, substr_count($create, 'has-active'), 'grup Kode QR terbuka di halaman Buat QR');
        $this->assertSame(1, substr_count($create, 'sidebar-group-toggle collapsed'));
        $this->assertSame(1, substr_count($create, 'sidebar-collapse-content closed'));

        $docs = $this->sidebar($this->admin()->get('/integrasi/dokumentasi')->getContent());
        $this->assertSame(1, substr_count($docs, 'has-active'), 'grup Integrasi terbuka di halaman Dokumentasi');
    }

    public function test_menu_items_depend_on_the_level(): void
    {
        $admin = $this->sidebar($this->admin()->get('/qr')->getContent());
        $this->assertStringContainsString('Semua QR', $admin);
        $this->assertStringContainsString('Klien API', $admin);

        $user = $this->sidebar($this->user()->get('/qr')->getContent());
        $this->assertStringContainsString('QR Saya', $user);
        $this->assertStringNotContainsString('Klien API', $user);
        $this->assertStringContainsString('Dokumentasi API', $user);
    }
}
