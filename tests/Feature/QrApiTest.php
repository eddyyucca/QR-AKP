<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class QrApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private ApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ApiClient(['name' => 'P2H AKP', 'rate_per_minute' => 60, 'is_active' => true]);
        $this->token = $this->client->issueToken();
        $this->client->save();
    }

    private function api(?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->withHeaders(['Accept' => 'application/json']);
    }

    private function newClient(string $name): string
    {
        $c = new ApiClient(['name' => $name, 'rate_per_minute' => 60, 'is_active' => true]);
        $t = $c->issueToken();
        $c->save();

        return $t;
    }

    public function test_requests_without_a_valid_token_are_refused_as_json(): void
    {
        $this->getJson('/api/v1/ping')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->api('akpq_salah')->getJson('/api/v1/qr')->assertStatus(401);
        $this->api('bukan-token')->postJson('/api/v1/qr', [])->assertStatus(401);
    }

    public function test_the_token_is_stored_only_as_a_hash(): void
    {
        $this->assertStringStartsWith('akpq_', $this->token);
        $this->assertDatabaseMissing('api_clients', ['token_hash' => $this->token]);
        $this->assertSame(hash('sha256', $this->token), $this->client->fresh()->token_hash);
    }

    public function test_ping_identifies_the_client(): void
    {
        $this->api()->getJson('/api/v1/ping')->assertOk()->assertJson(['ok' => true, 'client' => 'P2H AKP']);
    }

    public function test_inactive_clients_and_disallowed_ips_are_refused(): void
    {
        $this->client->update(['is_active' => false]);
        $this->api()->getJson('/api/v1/ping')->assertStatus(403)->assertJsonPath('error.code', 'client_disabled');

        $this->client->update(['is_active' => true, 'allowed_ips' => '10.9.9.9']);
        $this->api()->getJson('/api/v1/ping')->assertStatus(403)->assertJsonPath('error.code', 'ip_not_allowed');

        $this->client->update(['allowed_ips' => '127.0.0.1']);
        $this->api()->getJson('/api/v1/ping')->assertOk();
    }

    public function test_rate_limit_is_per_token(): void
    {
        RateLimiter::clear('qr-api:'.$this->client->id);
        $this->client->update(['rate_per_minute' => 2]);

        $this->api()->getJson('/api/v1/ping')->assertOk();
        $this->api()->getJson('/api/v1/ping')->assertOk();
        $this->api()->getJson('/api/v1/ping')->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
    }

    public function test_create_stores_creator_details_from_the_calling_system(): void
    {
        $res = $this->api()->postJson('/api/v1/qr', [
            'title' => 'Unit LV-12',
            'target_url' => 'https://p2h.lameruru.web.id/p2h/isi?unit=LV-12',
            'external_ref' => 'p2h-unit-LV-12',
            'created_by' => ['name' => 'Eddy Adha Saputra', 'nik' => '32601949', 'email' => 'eddy@example.test'],
        ]);

        $res->assertCreated()->assertJsonPath('existing', false)
            ->assertJsonPath('data.title', 'Unit LV-12')
            ->assertJsonPath('data.creator.name', 'Eddy Adha Saputra')
            ->assertJsonPath('data.creator.nik', '32601949')
            ->assertJsonPath('data.creator.level', 'api')
            ->assertJsonPath('data.source', 'api');

        $qr = QrCode::firstOrFail();
        $this->assertSame($this->client->id, $qr->api_client_id);
        $this->assertSame('api', $qr->source);
        $this->assertSame('api:'.$this->client->id, $qr->creator_portal_id);
        $this->assertStringContainsString('/api/v1/qr/'.$qr->uuid.'/image', $res->json('data.image_urls.png'));
    }

    public function test_creator_defaults_to_the_system_name(): void
    {
        $this->api()->postJson('/api/v1/qr', ['title' => 'X', 'target_url' => 'https://akp.co.id'])
            ->assertCreated()->assertJsonPath('data.creator.name', 'P2H AKP');
    }

    public function test_repeating_a_request_with_the_same_external_ref_is_idempotent(): void
    {
        $payload = ['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'external_ref' => 'ref-1'];

        $first = $this->api()->postJson('/api/v1/qr', $payload)->assertCreated();
        $second = $this->api()->postJson('/api/v1/qr', $payload)->assertOk()->assertJsonPath('existing', true);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertSame(1, QrCode::count());

        $this->api()->postJson('/api/v1/qr', ['target_url' => 'https://akp.co.id/lain'] + $payload)
            ->assertStatus(409)->assertJsonPath('error.code', 'external_ref_conflict');
    }

    public function test_the_same_external_ref_may_be_used_by_different_systems(): void
    {
        $other = $this->newClient('Sistem Lain');
        $payload = ['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'external_ref' => 'ref-1'];

        $this->api()->postJson('/api/v1/qr', $payload)->assertCreated();
        $this->api($other)->postJson('/api/v1/qr', $payload)->assertCreated();

        $this->assertSame(2, QrCode::count());
    }

    /** @dataProvider badPayloads */
    public function test_invalid_input_is_rejected_with_422(array $payload, string $field): void
    {
        $this->api()->postJson('/api/v1/qr', $payload)->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(0, QrCode::count());
    }

    public static function badPayloads(): array
    {
        return [
            'tanpa judul' => [['target_url' => 'https://akp.co.id'], 'title'],
            'javascript' => [['title' => 'X', 'target_url' => 'javascript:alert(1)'], 'target_url'],
            'tanpa alamat' => [['title' => 'X'], 'target_url'],
            'ref aneh' => [['title' => 'X', 'target_url' => 'https://akp.co.id', 'external_ref' => 'a b<>'], 'external_ref'],
            'email salah' => [['title' => 'X', 'target_url' => 'https://akp.co.id', 'created_by' => ['email' => 'bukan-email']], 'created_by.email'],
        ];
    }

    public function test_each_token_only_sees_its_own_qr(): void
    {
        $other = $this->newClient('Sistem Lain');
        $mine = $this->api()->postJson('/api/v1/qr', ['title' => 'Milik P2H', 'target_url' => 'https://akp.co.id/p2h'])->json('data.uuid');
        $theirs = $this->api($other)->postJson('/api/v1/qr', ['title' => 'Milik Lain', 'target_url' => 'https://akp.co.id/lain'])->json('data.uuid');

        $this->api()->getJson('/api/v1/qr')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $mine);
        $this->api()->getJson('/api/v1/qr/'.$mine)->assertOk();
        $this->api()->getJson('/api/v1/qr/'.$theirs)->assertNotFound();
        $this->api()->getJson('/api/v1/qr/'.$theirs.'/image')->assertNotFound();
        $this->api()->deleteJson('/api/v1/qr/'.$theirs)->assertNotFound();
        $this->api()->patchJson('/api/v1/qr/'.$theirs, ['title' => 'Dibajak'])->assertNotFound();
        $this->assertSame('Milik Lain', QrCode::where('uuid', $theirs)->value('title'));
    }

    public function test_list_supports_search_and_external_ref_filter_and_paging(): void
    {
        foreach (range(1, 3) as $i) {
            $this->api()->postJson('/api/v1/qr', ['title' => "Unit $i", 'target_url' => "https://akp.co.id/u$i", 'external_ref' => "u-$i"])->assertCreated();
        }

        $this->api()->getJson('/api/v1/qr?external_ref=u-2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Unit 2');
        $this->api()->getJson('/api/v1/qr?q=u3')->assertOk()->assertJsonCount(1, 'data');
        $this->api()->getJson('/api/v1/qr?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
        $this->api()->getJson('/api/v1/qr?per_page=500')->assertStatus(422);
    }

    public function test_image_endpoint_returns_png_and_svg(): void
    {
        $uuid = $this->api()->postJson('/api/v1/qr', ['title' => 'Poster Kantin', 'target_url' => 'https://akp.co.id/k'])->json('data.uuid');

        $png = $this->api()->get('/api/v1/qr/'.$uuid.'/image');
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($png->getContent(), 0, 4));

        $svg = $this->api()->get('/api/v1/qr/'.$uuid.'/image?format=svg&size=400');
        $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svg->getContent());

        $this->api()->get('/api/v1/qr/'.$uuid.'/image?download=1')->assertHeader('Content-Disposition', 'attachment; filename="qr-akp-poster-kantin.png"');
        $this->api()->getJson('/api/v1/qr/'.$uuid.'/image?format=gif')->assertStatus(422);
        $this->api()->getJson('/api/v1/qr/'.$uuid.'/image?size=50')->assertStatus(422);
    }

    public function test_update_changes_title_and_description_but_never_the_target(): void
    {
        $uuid = $this->api()->postJson('/api/v1/qr', ['title' => 'Lama', 'target_url' => 'https://akp.co.id/asli'])->json('data.uuid');

        $this->api()->patchJson('/api/v1/qr/'.$uuid, ['title' => 'Baru', 'description' => 'Ket'])
            ->assertOk()->assertJsonPath('data.title', 'Baru')->assertJsonPath('data.target_url', 'https://akp.co.id/asli');

        $this->api()->patchJson('/api/v1/qr/'.$uuid, ['target_url' => 'https://evil.example/'])
            ->assertStatus(422)->assertJsonPath('error.code', 'target_locked');

        $this->assertSame('https://akp.co.id/asli', QrCode::where('uuid', $uuid)->value('target_url'));
    }

    public function test_delete_frees_the_external_ref(): void
    {
        $payload = ['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'external_ref' => 'ref-9'];
        $uuid = $this->api()->postJson('/api/v1/qr', $payload)->json('data.uuid');

        $this->api()->deleteJson('/api/v1/qr/'.$uuid)->assertNoContent();
        $this->assertSoftDeleted('qr_codes', ['uuid' => $uuid, 'deleted_by_name' => 'P2H AKP']);
        $this->api()->getJson('/api/v1/qr/'.$uuid)->assertNotFound();

        $this->api()->postJson('/api/v1/qr', $payload)->assertCreated();
    }

    public function test_unknown_api_routes_answer_with_json(): void
    {
        $this->api()->getJson('/api/v1/tidak-ada')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_is_reachable_without_a_portal_login(): void
    {
        // Sistem lain tidak punya sesi Portal; autentikasinya hanya token.
        $this->api()->getJson('/api/v1/ping')->assertOk();
    }
}
