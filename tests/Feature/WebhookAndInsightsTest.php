<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Webhook ke sistem pemanggil, templat warna QR di web, dan grafik beranda. */
class WebhookAndInsightsTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'qr-akp', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);

        $this->client = new ApiClient(['name' => 'P2H AKP', 'rate_per_minute' => 60, 'is_active' => true]);
        $this->token = $this->client->issueToken();
        $this->client->forceFill(['webhook_url' => 'https://p2h.akp.co.id/hook', 'webhook_secret' => 'whsec_rahasia'])->save();
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

    public function test_webhook_is_signed_and_sent_on_create_and_update(): void
    {
        Http::fake(['p2h.akp.co.id/*' => Http::response('ok', 200)]);

        $uuid = $this->api()->postJson('/api/v1/qr', ['title' => 'A', 'target_url' => 'https://akp.co.id/a'])->assertCreated()->json('data.uuid');
        $this->api()->patchJson('/api/v1/qr/'.$uuid, ['title' => 'B'])->assertOk();

        // Catatan: dalam tes, aplikasi dipakai ulang antar-permintaan sehingga jumlah kiriman tidak bisa dihitung persis.
        foreach (['qr.created', 'qr.updated'] as $event) {
            Http::assertSent(function (Request $r) use ($event) {
                return $r->header('X-QR-Event') === [$event]
                    && $r->url() === 'https://p2h.akp.co.id/hook'
                    && $r->header('X-QR-Signature') === ['sha256='.hash_hmac('sha256', $r->body(), 'whsec_rahasia')]
                    && ($r->data()['data']['uuid'] ?? null) === $this->uuidOf($r);
            });
        }
        $this->assertSame(200, $this->client->fresh()->webhook_last_status);
    }

    private function uuidOf(Request $r): ?string
    {
        return $r->data()['data']['uuid'] ?? null;
    }

    public function test_deleting_sends_exactly_one_deleted_event_and_no_updated(): void
    {
        Http::fake();
        $qr = QrCode::create(['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'creator_portal_id' => 'api:1', 'creator_name' => 'P2H', 'creator_role' => 'api', 'source' => 'api']);
        QrCode::withoutEvents(fn () => $qr->forceFill(['api_client_id' => $this->client->id])->save());

        $this->api()->deleteJson('/api/v1/qr/'.$qr->uuid)->assertNoContent();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->header('X-QR-Event') === ['qr.deleted']);
    }

    public function test_deleting_in_the_web_also_notifies_the_system(): void
    {
        Http::fake();
        $qr = QrCode::create(['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'creator_portal_id' => 'api:1', 'creator_name' => 'P2H', 'creator_role' => 'api', 'api_client_id' => $this->client->id, 'source' => 'api']);
        Http::assertNothingSent(); // dikirim sesudah jawaban, bukan saat model dibuat di luar permintaan

        $this->asAdmin()->delete('/qr/'.$qr->uuid)->assertRedirect();
        Http::assertSent(fn (Request $r) => $r->header('X-QR-Event') === ['qr.deleted']);
    }

    public function test_no_webhook_means_nothing_is_sent_and_web_qr_never_trigger_it(): void
    {
        Http::fake();
        $this->client->forceFill(['webhook_url' => null, 'webhook_secret' => null])->save();
        $this->api()->postJson('/api/v1/qr', ['title' => 'A', 'target_url' => 'https://akp.co.id/a'])->assertCreated();
        $this->asUser()->post('/qr', ['title' => 'Web', 'target_url' => 'https://akp.co.id/w'])->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_unreachable_webhook_never_breaks_the_api_call_and_is_recorded(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->api()->postJson('/api/v1/qr', ['title' => 'A', 'target_url' => 'https://akp.co.id/a'])->assertCreated();
        $this->assertSame(500, $this->client->fresh()->webhook_last_status);
    }

    public function test_admin_sets_webhook_sees_secret_once_and_can_send_a_test(): void
    {
        Http::fake(['*' => Http::response('ok', 204)]);
        $fresh = new ApiClient(['name' => 'HRIS', 'rate_per_minute' => 60, 'is_active' => true]);
        $fresh->issueToken();
        $fresh->save();

        $this->asAdmin()->post("/integrasi/klien/{$fresh->id}/webhook", ['webhook_url' => 'http://localhost/x'])->assertSessionHasErrors('webhook_url');

        $this->asAdmin()->post("/integrasi/klien/{$fresh->id}/webhook", ['webhook_url' => 'https://hris.akp.co.id/hook'])
            ->assertRedirect('/integrasi/klien')->assertSessionHas('new_webhook_secret');
        $secret = $fresh->fresh()->webhook_secret;
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertDatabaseMissing('api_clients', ['webhook_secret' => $secret]); // tersimpan terenkripsi

        // Menyimpan ulang tidak membuat rahasia baru.
        $this->asAdmin()->post("/integrasi/klien/{$fresh->id}/webhook", ['webhook_url' => 'https://hris.akp.co.id/hook2'])->assertSessionMissing('new_webhook_secret');
        $this->assertSame($secret, $fresh->fresh()->webhook_secret);

        $this->asAdmin()->post("/integrasi/klien/{$fresh->id}/webhook/uji")->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => $r->header('X-QR-Event') === ['webhook.test']);

        $this->asUser()->post("/integrasi/klien/{$fresh->id}/webhook", ['webhook_url' => 'https://x.akp.co.id/h'])->assertForbidden();

        $this->asAdmin()->post("/integrasi/klien/{$fresh->id}/webhook", ['webhook_url' => ''])->assertSessionHas('success');
        $this->assertNull($fresh->fresh()->webhook_url);
        $this->assertNull($fresh->fresh()->webhook_secret);
    }

    public function test_web_image_supports_color_presets_and_logo_toggle(): void
    {
        $qr = QrCode::create(['title' => 'A', 'target_url' => 'https://akp.co.id/a', 'creator_portal_id' => '2', 'creator_name' => 'Budi', 'creator_role' => 'user']);

        $default = $this->asUser()->get("/qr/{$qr->uuid}/gambar/svg")->assertOk()->getContent();
        $red = $this->asUser()->get("/qr/{$qr->uuid}/gambar/svg?warna=merah&logo=0")->assertOk()->getContent();
        $this->assertStringContainsString('#991b1b', strtolower($red));
        $this->assertNotSame($default, $red);

        // Warna di luar templat diabaikan (kembali ke warna AKP), bukan diteruskan mentah.
        $unknown = $this->asUser()->get("/qr/{$qr->uuid}/gambar/svg?warna=ffffff")->assertOk()->getContent();
        $this->assertSame($default, $unknown);

        $this->asUser()->get('/qr/'.$qr->uuid)->assertOk()->assertSee('Hijau tua')->assertSee('id="qrLogo"', false);
    }

    public function test_home_chart_counts_per_day_and_respects_visibility(): void
    {
        $mk = fn ($t, $owner, $at) => QrCode::create(['title' => $t, 'target_url' => 'https://akp.co.id/'.$t, 'creator_portal_id' => $owner, 'creator_name' => 'Pembuat '.$owner, 'creator_role' => 'user'])
            ->forceFill(['created_at' => $at])->save();
        $mk('a', '2', now());
        $mk('b', '2', now()->subDays(3));
        $mk('c', '3', now());
        $mk('lama', '2', now()->subDays(30));

        $this->asUser()->get('/qr')->assertOk()->assertSee('14 hari terakhir')->assertSee('(total 2)', false)->assertDontSee('Pembuat teraktif');
        $this->asAdmin()->get('/qr')->assertOk()->assertSee('(total 3)', false)->assertSee('Pembuat teraktif')->assertSee('Pembuat 2');
    }
}
