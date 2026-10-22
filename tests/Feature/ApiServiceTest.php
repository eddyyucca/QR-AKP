<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bulk create, gaya gambar, OpenAPI/health, log API, dan ekspor CSV. */
class ApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private ApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'qr-akp', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);

        $this->client = new ApiClient(['name' => 'P2H AKP', 'rate_per_minute' => 60, 'is_active' => true]);
        $this->token = $this->client->issueToken();
        $this->client->save();
    }

    private function api()
    {
        return $this->withToken($this->token)->withHeaders(['Accept' => 'application/json']);
    }

    private function asAdmin(): static
    {
        return $this->loginPortal(['id' => '1', 'name' => 'Admin AKP', 'employee_no' => '32600001', 'role' => 'admin', 'level' => 100]);
    }

    private function asUser(): static
    {
        return $this->loginPortal(['id' => '2', 'name' => 'Budi Santoso', 'employee_no' => '32600002', 'role' => 'user', 'level' => 10]);
    }

    public function test_bulk_creates_each_item_independently_and_is_idempotent(): void
    {
        $items = [
            ['title' => 'Unit 1', 'target_url' => 'https://akp.co.id/u/1', 'external_ref' => 'u-1'],
            ['title' => '', 'target_url' => 'https://akp.co.id/u/2'],
            ['title' => 'Unit 3', 'target_url' => 'https://akp.co.id/u/3', 'external_ref' => 'u-3'],
        ];

        $res = $this->api()->postJson('/api/v1/qr/bulk', ['items' => $items])->assertOk();
        $res->assertJsonPath('summary', ['total' => 3, 'created' => 2, 'existing' => 0, 'failed' => 1])
            ->assertJsonPath('results.0.status', 201)
            ->assertJsonPath('results.1.status', 422)
            ->assertJsonPath('results.2.status', 201);
        $this->assertSame(2, QrCode::count());

        $this->api()->postJson('/api/v1/qr/bulk', ['items' => [$items[0]]])
            ->assertJsonPath('summary.existing', 1)->assertJsonPath('summary.created', 0);
        $this->assertSame(2, QrCode::count());
    }

    public function test_bulk_rejects_empty_and_oversized_batches_and_bad_targets(): void
    {
        $this->api()->postJson('/api/v1/qr/bulk', ['items' => []])->assertStatus(422);
        $this->api()->postJson('/api/v1/qr/bulk', ['items' => array_fill(0, 51, ['title' => 'x', 'target_url' => 'https://akp.co.id'])])->assertStatus(422);
        $this->api()->postJson('/api/v1/qr/bulk', ['items' => [['title' => 'x', 'target_url' => 'http://localhost/admin']]])
            ->assertOk()->assertJsonPath('summary.failed', 1);
        $this->assertSame(0, QrCode::count());
    }

    public function test_bulk_requires_a_token(): void
    {
        $this->postJson('/api/v1/qr/bulk', ['items' => []])->assertStatus(401);
    }

    public function test_image_accepts_style_options_and_rejects_bad_colors(): void
    {
        $uuid = $this->api()->postJson('/api/v1/qr', ['title' => 'A', 'target_url' => 'https://akp.co.id/a'])->json('data.uuid');

        $svg = $this->api()->get("/api/v1/qr/$uuid/image?format=svg&fg=ff0000&bg=ffff00&logo=0&margin=4")->assertOk();
        $this->assertStringContainsString('image/svg+xml', $svg->headers->get('Content-Type'));
        $this->assertStringContainsString('#ff0000', strtolower($svg->getContent()));

        $this->api()->getJson("/api/v1/qr/$uuid/image?fg=zzzzzz")->assertStatus(422);
        $this->api()->getJson("/api/v1/qr/$uuid/image?margin=999")->assertStatus(422);
    }

    public function test_health_and_openapi_are_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok', 'database' => true]);

        $spec = $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.0.3');
        $paths = array_keys($spec->json('paths'));
        $this->assertContains('/qr/bulk', $paths);
        $this->assertContains('/qr/{uuid}/image', $paths);
    }

    public function test_authenticated_requests_are_logged_but_rejected_tokens_are_not(): void
    {
        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer akpq_salah'])->assertStatus(401);
        $this->assertSame(0, ApiRequestLog::count());

        $this->api()->getJson('/api/v1/ping')->assertOk();
        $this->api()->getJson('/api/v1/qr/'.fake()->uuid())->assertNotFound();

        $this->assertSame(2, ApiRequestLog::count());
        $this->assertDatabaseHas('api_request_logs', ['api_client_id' => $this->client->id, 'method' => 'GET', 'path' => '/api/v1/ping', 'status' => 200]);
        $this->assertDatabaseHas('api_request_logs', ['status' => 404]);
    }

    public function test_log_page_is_admin_only_and_filters_errors(): void
    {
        ApiRequestLog::create(['api_client_id' => $this->client->id, 'method' => 'GET', 'path' => '/api/v1/ping', 'status' => 200, 'duration_ms' => 5]);
        ApiRequestLog::create(['api_client_id' => $this->client->id, 'method' => 'GET', 'path' => '/api/v1/qr/xyz', 'status' => 404, 'duration_ms' => 7]);

        $this->asAdmin()->get('/integrasi/klien/log')->assertOk()->assertSee('P2H AKP')->assertSee('/api/v1/ping')->assertSee('/api/v1/qr/xyz');
        $this->asAdmin()->get('/integrasi/klien/log?result=error')->assertOk()->assertSee('/api/v1/qr/xyz')->assertDontSee('/api/v1/ping');
        $this->asUser()->get('/integrasi/klien/log')->assertForbidden();
    }

    public function test_old_logs_are_prunable(): void
    {
        $old = ApiRequestLog::create(['api_client_id' => 1, 'method' => 'GET', 'path' => '/a', 'status' => 200]);
        $old->forceFill(['created_at' => now()->subDays(100)])->save();
        ApiRequestLog::create(['api_client_id' => 1, 'method' => 'GET', 'path' => '/b', 'status' => 200]);

        $this->assertSame(1, (new ApiRequestLog)->prunable()->count());
    }

    public function test_csv_export_respects_visibility_filters_and_neutralizes_formulas(): void
    {
        QrCode::create(['title' => '=HYPERLINK("http://x")', 'target_url' => 'https://akp.co.id/a', 'creator_portal_id' => '2', 'creator_name' => 'Budi Santoso', 'creator_role' => 'user']);
        QrCode::create(['title' => 'Punya Sari', 'target_url' => 'https://akp.co.id/b', 'creator_portal_id' => '3', 'creator_name' => 'Sari', 'creator_role' => 'user']);

        $res = $this->asUser()->get('/qr/ekspor')->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString('Punya Sari', $body);

        $all = $this->asAdmin()->get('/qr/ekspor')->streamedContent();
        $this->assertStringContainsString('Punya Sari', $all);

        $filtered = $this->asAdmin()->get('/qr/ekspor?q=Sari')->streamedContent();
        $this->assertStringContainsString('Punya Sari', $filtered);
        $this->assertStringNotContainsString('HYPERLINK', $filtered);
    }

    public function test_menu_shows_api_log_only_to_admins(): void
    {
        $this->asAdmin()->get('/qr')->assertSee('Log API');
        $this->asUser()->get('/qr')->assertDontSee('Log API');
    }
}
