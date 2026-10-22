<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'qr-akp', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
    }

    private function asUser(): static
    {
        return $this->loginPortal(['id' => '2', 'name' => 'Budi Santoso', 'employee_no' => '32600002', 'role' => 'user', 'level' => 10]);
    }

    private function asAdmin(): static
    {
        return $this->loginPortal(['id' => '1', 'name' => 'Admin AKP', 'employee_no' => '32600001', 'role' => 'admin', 'level' => 100]);
    }

    public function test_home_page_shows_guide_quick_form_and_recent_qr_for_each_level(): void
    {
        QrCode::create(['title' => 'Punya Budi', 'target_url' => 'https://akp.co.id/a', 'creator_portal_id' => '2', 'creator_name' => 'Budi Santoso', 'creator_role' => 'user']);
        QrCode::create(['title' => 'Punya Sari', 'target_url' => 'https://akp.co.id/b', 'creator_portal_id' => '3', 'creator_name' => 'Sari', 'creator_role' => 'user']);

        $this->asUser()->get('/qr')->assertOk()->assertSee('Ubah link jadi QR resmi AKP')->assertSee('Punya Budi')->assertDontSee('Punya Sari')->assertSee('Budi Santoso');
        $this->asAdmin()->get('/qr')->assertOk()->assertSee('Punya Budi')->assertSee('Punya Sari')->assertSee('Jumlah Pembuat');
        $this->asUser()->get('/qr/buat')->assertOk()->assertSee('Pratinjau');
    }

    public function test_home_is_empty_friendly(): void
    {
        $this->asUser()->get('/qr')->assertOk()->assertSee('Belum ada QR');
    }

    public function test_preview_returns_an_image_without_saving_anything(): void
    {
        $res = $this->asUser()->postJson('/qr/pratinjau', ['target_url' => 'https://akp.co.id/halaman'])->assertOk();

        $this->assertStringStartsWith('data:image/png;base64,', $res->json('image'));
        $this->assertSame(0, QrCode::count());
    }

    public function test_preview_rejects_dangerous_or_missing_urls(): void
    {
        $this->asUser()->postJson('/qr/pratinjau', ['target_url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('target_url');
        $this->asUser()->postJson('/qr/pratinjau', [])->assertStatus(422);
    }

    public function test_guests_cannot_use_preview(): void
    {
        $this->postJson('/qr/pratinjau', ['target_url' => 'https://akp.co.id'])->assertStatus(401);
    }

    public function test_api_docs_are_open_to_both_levels_and_show_the_real_base_url(): void
    {
        config(['app.url' => 'https://qr.lameruru.web.id']);

        $this->asUser()->get('/integrasi/dokumentasi')->assertOk()->assertSee('https://qr.lameruru.web.id/api/v1', false)->assertSee('Authorization: Bearer');
        $this->asAdmin()->get('/integrasi/dokumentasi')->assertOk();
    }

    public function test_only_admin_can_manage_api_clients(): void
    {
        $this->asUser()->get('/integrasi/klien')->assertForbidden();
        $this->asUser()->post('/integrasi/klien', ['name' => 'X', 'rate_per_minute' => 60])->assertForbidden();
        $this->asAdmin()->get('/integrasi/klien')->assertOk()->assertSee('Belum ada sistem');
        $this->assertSame(0, ApiClient::count());
    }

    public function test_admin_creates_a_client_and_sees_the_token_exactly_once(): void
    {
        $res = $this->asAdmin()->post('/integrasi/klien', ['name' => 'P2H AKP', 'rate_per_minute' => 30, 'allowed_ips' => '172.100.1.113'])->assertRedirect(route('api.clients'));

        $client = ApiClient::firstOrFail();
        $this->assertSame('P2H AKP', $client->name);
        $this->assertSame(30, $client->rate_per_minute);
        $this->assertSame('Admin AKP', $client->created_by_name);

        $page = $this->asAdmin()->followingRedirects()->post('/integrasi/klien', ['name' => 'Sistem B', 'rate_per_minute' => 60]);
        $page->assertSee('akpq_')->assertSee('hanya tampil sekali');

        // Muat ulang: token asli tidak tampil lagi, hanya awalannya.
        $second = ApiClient::where('name', 'Sistem B')->firstOrFail();
        $this->asAdmin()->get('/integrasi/klien')->assertOk()->assertSee($second->token_prefix)->assertDontSee('hanya tampil sekali');
    }

    public function test_token_regeneration_invalidates_the_old_token(): void
    {
        $client = new ApiClient(['name' => 'P2H', 'rate_per_minute' => 60, 'is_active' => true]);
        $old = $client->issueToken();
        $client->save();

        $this->withToken($old)->getJson('/api/v1/ping')->assertOk();

        $this->asAdmin()->post('/integrasi/klien/'.$client->id.'/token')->assertRedirect();

        $this->withToken($old)->getJson('/api/v1/ping')->assertStatus(401);
    }

    public function test_toggle_and_delete_client(): void
    {
        $client = new ApiClient(['name' => 'P2H', 'rate_per_minute' => 60, 'is_active' => true]);
        $token = $client->issueToken();
        $client->save();

        $this->asAdmin()->post('/integrasi/klien/'.$client->id.'/aktif')->assertRedirect();
        $this->assertFalse($client->fresh()->is_active);
        $this->withToken($token)->getJson('/api/v1/ping')->assertStatus(403);

        $this->asAdmin()->delete('/integrasi/klien/'.$client->id)->assertRedirect();
        $this->assertSame(0, ApiClient::count());
        $this->withToken($token)->getJson('/api/v1/ping')->assertStatus(401);
    }

    public function test_qr_made_by_api_show_up_for_admin_with_system_label_and_stay_hidden_from_users(): void
    {
        $client = new ApiClient(['name' => 'P2H AKP', 'rate_per_minute' => 60, 'is_active' => true]);
        $token = $client->issueToken();
        $client->save();

        $this->withToken($token)->postJson('/api/v1/qr', ['title' => 'QR Unit LV-12', 'target_url' => 'https://akp.co.id/u', 'external_ref' => 'unit-LV-12'])->assertCreated();
        $uuid = QrCode::firstOrFail()->uuid;

        $this->asAdmin()->get('/qr/daftar')->assertSee('QR Unit LV-12')->assertSee('Sistem (API)');
        $this->asAdmin()->get('/qr/'.$uuid)->assertOk()->assertSee('REST API')->assertSee('P2H AKP')->assertSee('unit-LV-12');
        $this->asUser()->get('/qr/daftar')->assertDontSee('QR Unit LV-12');
        $this->asUser()->get('/qr/'.$uuid)->assertNotFound();
    }

    public function test_deleting_in_the_web_frees_the_external_ref_for_the_api(): void
    {
        $client = new ApiClient(['name' => 'P2H AKP', 'rate_per_minute' => 60, 'is_active' => true]);
        $token = $client->issueToken();
        $client->save();

        $payload = ['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'external_ref' => 'ref-1'];
        $uuid = $this->withToken($token)->postJson('/api/v1/qr', $payload)->json('data.uuid');

        $this->asAdmin()->delete('/qr/'.$uuid)->assertRedirect();
        $this->withToken($token)->postJson('/api/v1/qr', $payload)->assertCreated();
    }
}
