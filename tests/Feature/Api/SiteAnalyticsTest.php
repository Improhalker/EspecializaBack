<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['analytics.excluded_ips' => [], 'analytics.enabled' => true, 'analytics.trusted_proxies' => []]);
    }

    private function payload(string $path = '/cursos/mopp'): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'page' => ['id' => (string) Str::uuid(), 'path' => $path, 'active_ms' => 12000, 'scroll_depth' => 75, 'lcp_ms' => 1800, 'inp_ms' => 100, 'cls' => 0.02, 'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'MOPP Formação'],
            'interactions' => [['id' => (string) Str::uuid(), 'kind' => 'whatsapp', 'label' => 'WhatsApp · Conteúdo do curso']],
        ];
    }

    public function test_batches_are_idempotent_and_metrics_only_increase(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/analytics/events', $payload)->assertNoContent();
        $payload['page']['active_ms'] = 8000;
        $payload['page']['scroll_depth'] = 25;
        $this->postJson('/api/analytics/events', $payload)->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 1);
        $this->assertDatabaseCount('analytics_interactions', 1);
        $this->assertDatabaseHas('analytics_page_views', ['id' => $payload['page']['id'], 'active_ms' => 12000, 'scroll_depth' => 75]);
        $stored = DB::table('analytics_page_views')->first();
        $this->assertNotEquals($payload['session_id'], $stored->session_hash);
        $this->assertFalse(property_exists($stored, 'ip'));
        $payload['page']['active_ms'] = 20000;
        $payload['page']['scroll_depth'] = 90;
        $payload['interactions'][] = ['id' => (string) Str::uuid(), 'kind' => 'faq', 'label' => 'Como receber o acesso?'];
        $this->postJson('/api/analytics/events', $payload)->assertNoContent();
        $this->assertDatabaseCount('analytics_interactions', 2);
        $this->assertDatabaseHas('analytics_page_views', ['active_ms' => 20000, 'scroll_depth' => 90]);
    }

    public function test_another_session_cannot_update_a_visit(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/analytics/events', $payload)->assertNoContent();
        $payload['session_id'] = (string) Str::uuid();
        $this->postJson('/api/analytics/events', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('analytics_page_views', 1);
    }

    public function test_private_paths_and_personal_payload_fields_are_rejected(): void
    {
        foreach (['/admin', '/admin/login', '/api/home', '/cursos/mopp?email=test@example.com', '/nao-existe'] as $path) {
            $this->postJson('/api/analytics/events', $this->payload($path))->assertUnprocessable()->assertJsonValidationErrors('page.path');
        }
        $payload = $this->payload();
        $payload['page']['email'] = 'test@example.com';
        $this->postJson('/api/analytics/events', $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['page']['utm_source'] = 'test@example.com';
        $this->postJson('/api/analytics/events', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('analytics_page_views', 0);
    }

    public function test_invalid_metrics_and_large_batches_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['page']['scroll_depth'] = 101;
        $payload['page']['active_ms'] = -1;
        $this->postJson('/api/analytics/events', $payload)->assertUnprocessable()->assertJsonValidationErrors(['page.scroll_depth', 'page.active_ms']);
        $payload = $this->payload();
        $payload['interactions'] = array_fill(0, 26, $payload['interactions'][0]);
        $this->postJson('/api/analytics/events', $payload)->assertUnprocessable()->assertJsonValidationErrors('interactions');
    }

    public function test_own_ip_is_excluded_from_both_collectors(): void
    {
        config(['analytics.excluded_ips' => ['179.98.61.162']]);
        $this->withServerVariables(['REMOTE_ADDR' => '179.98.61.162'])->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->postJson('/api/whatsapp-clicks', ['source_url' => 'https://especializacondutor.com.br/'])->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 0);
        $this->assertDatabaseCount('whatsapp_clicks', 0);
    }

    public function test_untrusted_forwarded_ip_cannot_spoof_exclusion(): void
    {
        config(['analytics.excluded_ips' => ['179.98.61.162']]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])->withHeader('X-Forwarded-For', '179.98.61.162')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 1);
    }

    public function test_explicit_trusted_proxy_can_supply_real_visitor_ip(): void
    {
        config(['analytics.excluded_ips' => ['179.98.61.162'], 'analytics.trusted_proxies' => ['10.0.0.1']]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeader('X-Forwarded-For', '179.98.61.162')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 0);
    }

    public function test_privacy_signals_bots_and_disabled_collection_are_ignored(): void
    {
        $this->withHeader('DNT', '1')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->flushHeaders();
        $this->withHeader('Sec-GPC', '1')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->flushHeaders();
        $this->withHeader('User-Agent', 'Googlebot')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->flushHeaders();
        config(['analytics.enabled' => false]);
        $this->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 0);
    }

    public function test_unrelated_origins_are_ignored_but_public_site_can_collect(): void
    {
        $this->withHeader('Origin', 'https://unrelated.example')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 0);
        $this->withHeader('Origin', 'https://especializacondutor.com.br')->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->assertDatabaseCount('analytics_page_views', 1);
    }

    public function test_reports_require_administrator_and_finished_password_change(): void
    {
        $this->getJson('/api/admin/analytics')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/api/admin/analytics')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => true]));
        $this->getJson('/api/admin/analytics')->assertStatus(423);
    }

    public function test_report_aggregates_sessions_actions_scroll_and_performance(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(15, 0));
        $first = $this->payload();
        $this->postJson('/api/analytics/events', $first)->assertNoContent();
        $second = $this->payload('/cursos');
        $second['session_id'] = $first['session_id'];
        $second['page']['active_ms'] = 8000;
        $second['page']['scroll_depth'] = 25;
        $second['interactions'] = [];
        $this->postJson('/api/analytics/events', $second)->assertNoContent();
        $third = $this->payload('/');
        $third['interactions'] = [];
        $this->postJson('/api/analytics/events', $third)->assertNoContent();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $response = $this->getJson('/api/admin/analytics?start_date=2026-10-01&end_date=2026-10-01')->assertOk();
        $response->assertJsonPath('summary.views', 3)->assertJsonPath('summary.sessions', 2)
            ->assertJsonPath('summary.whatsapp_clicks', 1)->assertJsonPath('summary.contact_rate', 50)
            ->assertJsonPath('summary.active_seconds', 10.7)->assertJsonPath('scroll.2.total', 2)
            ->assertJsonPath('timeline.0.views', 3)->assertJsonPath('performance.lcp_samples', 3)
            ->assertJsonCount(3, 'pages')->assertJsonPath('sources.0.label', 'instagram');
        $this->getJson('/api/admin/analytics?start_date=2026-10-01&end_date=2026-10-01&path=%2Fcursos')
            ->assertOk()->assertJsonPath('summary.views', 1)->assertJsonPath('summary.whatsapp_clicks', 0);
        $this->getJson('/api/admin/analytics?start_date=2026-10-01&end_date=2026-10-01&device=mobile')->assertOk()->assertJsonPath('summary.views', 0);
        $this->getJson('/api/admin/analytics?start_date=2026-10-01&end_date=2026-10-01&utm_source=google')->assertOk()->assertJsonPath('summary.views', 0);
    }

    public function test_sao_paulo_day_boundary_and_previous_period_are_correct(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(1, 30));
        $this->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(4, 0));
        $this->postJson('/api/analytics/events', $this->payload())->assertNoContent();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $this->getJson('/api/admin/analytics?start_date=2026-10-01&end_date=2026-10-01')->assertOk()
            ->assertJsonPath('summary.views', 1)->assertJsonPath('previous.views', 1)->assertJsonPath('timeline.0.views', 1)->assertJsonPath('summary.views_change', 0);
    }

    public function test_empty_report_is_valid_and_period_is_bounded(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
        $this->getJson('/api/admin/analytics')->assertOk()->assertJsonPath('summary.views', 0)->assertJsonPath('summary.contact_rate', 0)
            ->assertJsonPath('summary.views_change', null)->assertJsonCount(30, 'timeline')->assertJsonCount(0, 'pages')->assertJsonCount(5, 'scroll');
        $this->getJson('/api/admin/analytics?start_date=2026-01-01&end_date=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/admin/analytics?start_date=2026-10-03&end_date=2026-10-01')->assertUnprocessable();
    }
}
