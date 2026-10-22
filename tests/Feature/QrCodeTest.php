<?php

namespace Tests\Feature;

use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'qr-akp', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
    }

    private function asUser(string $id = '2', string $name = 'Budi Santoso', string $nik = '32600002'): static
    {
        return $this->loginPortal(['id' => $id, 'name' => $name, 'employee_no' => $nik, 'email' => "u$id@example.test", 'role' => 'user', 'level' => 10]);
    }

    private function asAdmin(): static
    {
        return $this->loginPortal(['id' => '1', 'name' => 'Admin AKP', 'employee_no' => '32600001', 'role' => 'admin', 'level' => 100]);
    }

    private function makeQr(string $creatorId, string $name = 'Pembuat', string $title = 'Judul', string $url = 'https://akp.co.id/a'): QrCode
    {
        return QrCode::create([
            'title' => $title, 'target_url' => $url,
            'creator_portal_id' => $creatorId, 'creator_name' => $name, 'creator_nik' => '999', 'creator_role' => 'user',
        ]);
    }

    public function test_guests_are_sent_to_the_portal_and_health_check_is_open(): void
    {
        foreach (['/', '/qr', '/qr/buat'] as $path) {
            $res = $this->get($path);
            $res->assertRedirect();
            $this->assertStringStartsWith('http://portal.test/login', $res->headers->get('Location'), $path);
        }

        $this->get('/up')->assertOk();
    }

    public function test_levels_without_access_are_refused(): void
    {
        $this->loginPortal(['role' => 'tamu', 'level' => 1])->get('/qr')->assertForbidden();
    }

    public function test_creating_a_qr_records_who_made_it(): void
    {
        $this->asUser()->post('/qr', ['title' => 'Formulir FTW', 'target_url' => ' https://ftw.akp.co.id/form ', 'description' => 'Poster kantin'])
            ->assertRedirect();

        $qr = QrCode::firstOrFail();
        $this->assertSame('https://ftw.akp.co.id/form', $qr->target_url);
        $this->assertSame('Formulir FTW', $qr->title);
        $this->assertSame('2', $qr->creator_portal_id);
        $this->assertSame('Budi Santoso', $qr->creator_name);
        $this->assertSame('32600002', $qr->creator_nik);
        $this->assertSame('u2@example.test', $qr->creator_email);
        $this->assertSame('user', $qr->creator_role);
        $this->assertNotEmpty($qr->uuid);

        $this->asAdmin()->post('/qr', ['title' => 'Admin QR', 'target_url' => 'https://akp.co.id']);
        $this->assertSame('admin', QrCode::where('title', 'Admin QR')->value('creator_role'));
    }

    /** @dataProvider badUrls */
    public function test_dangerous_or_malformed_urls_are_rejected(string $url): void
    {
        $this->asUser()->post('/qr', ['title' => 'X', 'target_url' => $url])->assertSessionHasErrors('target_url');
        $this->assertSame(0, QrCode::count());
    }

    public static function badUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'file' => ['file:///etc/passwd'],
            'tanpa skema' => ['akp.co.id/halaman'],
            'ftp' => ['ftp://akp.co.id/x'],
            'ada spasi' => ['https://akp.co.id/a b'],
            'akun tersisip' => ['https://akp.co.id@evil.example/'],
            'host tanpa titik' => ['http://intranet/halaman'],
            'terlalu panjang' => ['https://akp.co.id/'.str_repeat('a', 2100)],
        ];
    }

    /** @dataProvider goodUrls */
    public function test_normal_urls_are_accepted(string $url): void
    {
        $this->asUser()->post('/qr', ['title' => 'OK', 'target_url' => $url])->assertSessionHasNoErrors();
        $this->assertSame(1, QrCode::count());
    }

    public static function goodUrls(): array
    {
        return [
            'https' => ['https://hris.lameruru.web.id/login?a=1&b=2#bagian'],
            'http' => ['http://akp.co.id/halaman'],
            'ip dan port' => ['http://172.100.1.113:8101/x'],
        ];
    }

    public function test_title_is_required(): void
    {
        $this->asUser()->post('/qr', ['title' => '', 'target_url' => 'https://akp.co.id'])->assertSessionHasErrors('title');
    }

    public function test_users_only_see_their_own_qr_and_admin_sees_all(): void
    {
        $mine = $this->makeQr('2', 'Budi', 'Punya Budi');
        $other = $this->makeQr('3', 'Sari', 'Punya Sari');

        $this->asUser()->get('/qr/daftar')->assertOk()->assertSee('Punya Budi')->assertDontSee('Punya Sari');
        $this->asUser()->get(route('qr.show', $mine))->assertOk();
        $this->asUser()->get(route('qr.show', $other))->assertNotFound();
        $this->asUser()->get(route('qr.image', [$other, 'png']))->assertNotFound();
        $this->asUser()->get(route('qr.edit', $other))->assertNotFound();
        $this->asUser()->put(route('qr.update', $other), ['title' => 'Dibajak'])->assertNotFound();
        $this->asUser()->delete(route('qr.destroy', $other))->assertNotFound();

        $this->asAdmin()->get('/qr/daftar')->assertOk()->assertSee('Punya Budi')->assertSee('Punya Sari');
        $this->asAdmin()->get(route('qr.show', $other))->assertOk()->assertSee('Sari')->assertSee('NIK');
    }

    public function test_only_admin_can_filter_by_creator(): void
    {
        $this->makeQr('2', 'Budi', 'Punya Budi');
        $this->makeQr('3', 'Sari', 'Punya Sari');

        $this->asAdmin()->get('/qr/daftar?creator=3')->assertOk()->assertSee('Punya Sari')->assertDontSee('Punya Budi');
        // Pengguna biasa tidak bisa melihat milik orang lain lewat parameter creator.
        $this->asUser()->get('/qr/daftar?creator=3')->assertOk()->assertDontSee('Punya Sari');
    }

    public function test_search_works_on_title_url_and_creator(): void
    {
        $this->makeQr('2', 'Budi', 'Poster Kantin', 'https://kantin.akp.co.id');
        $this->makeQr('3', 'Sari', 'Lain', 'https://lain.akp.co.id');

        $this->asAdmin()->get('/qr/daftar?q=Kantin')->assertSee('Poster Kantin')->assertDontSee('lain.akp.co.id');
        $this->asAdmin()->get('/qr/daftar?q=Sari')->assertSee('lain.akp.co.id')->assertDontSee('Poster Kantin');
        $this->asAdmin()->get('/qr/daftar?q=%25')->assertOk()->assertDontSee('Poster Kantin');
    }

    public function test_images_are_real_png_and_svg_and_can_be_downloaded(): void
    {
        $qr = $this->makeQr('2', 'Budi', 'Poster Kantin');

        $png = $this->asUser()->get(route('qr.image', [$qr, 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($png->getContent(), 0, 4));

        $svg = $this->asUser()->get(route('qr.image', [$qr, 'svg']));
        $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svg->getContent());

        $this->asUser()->get(route('qr.image', [$qr, 'png']).'?unduh=1')
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-akp-poster-kantin.png"');

        $this->asUser()->get('/qr/'.$qr->uuid.'/gambar/gif')->assertNotFound();
    }

    public function test_different_urls_make_different_images(): void
    {
        $a = $this->makeQr('2', 'Budi', 'A', 'https://akp.co.id/a');
        $b = $this->makeQr('2', 'Budi', 'B', 'https://akp.co.id/b');

        $this->assertNotSame(
            $this->asUser()->get(route('qr.image', [$a, 'svg']))->getContent(),
            $this->asUser()->get(route('qr.image', [$b, 'svg']))->getContent(),
        );
    }

    public function test_update_changes_only_title_and_description_and_records_who(): void
    {
        $qr = $this->makeQr('2', 'Budi', 'Lama', 'https://akp.co.id/asli');

        $this->asUser()->put(route('qr.update', $qr), ['title' => 'Baru', 'description' => 'Ket', 'target_url' => 'https://evil.example/'])
            ->assertRedirect(route('qr.show', $qr));

        $qr->refresh();
        $this->assertSame('Baru', $qr->title);
        $this->assertSame('Ket', $qr->description);
        $this->assertSame('https://akp.co.id/asli', $qr->target_url, 'alamat tujuan terkunci');
        $this->assertSame('Budi Santoso', $qr->updated_by_name);
        $this->assertSame('Budi', $qr->creator_name, 'pembuat asli tidak berubah');
    }

    public function test_delete_is_soft_and_records_who(): void
    {
        $qr = $this->makeQr('2', 'Budi', 'QR Yang Dihapus');

        $this->asAdmin()->delete(route('qr.destroy', $qr))->assertRedirect(route('qr.index'));

        $this->assertSoftDeleted('qr_codes', ['id' => $qr->id, 'deleted_by_name' => 'Admin AKP']);
        $this->asAdmin()->get('/qr/daftar')->assertDontSee('QR Yang Dihapus');
    }

    public function test_titles_are_escaped_in_pages(): void
    {
        $this->makeQr('2', 'Budi', '<script>alert(1)</script>');

        $this->asUser()->get('/qr/daftar')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
