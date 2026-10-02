<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WhatsappClick;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_history_requires_administrator(): void
    {
        $this->getJson('/api/admin/attendances')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/api/admin/attendances')->assertForbidden();
    }

    public function test_empty_history_always_contains_pagination_meta(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson('/api/admin/attendances')->assertOk()->assertJsonPath('clicks.meta.last_page', 1)->assertJsonPath('clicks.meta.total', 0)->assertJsonPath('clicks.data', []);
    }

    public function test_history_pagination_is_compatible_and_filtered_on_server(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        for ($index = 0; $index < 3; $index++) {
            WhatsappClick::query()->create(['source_url' => 'https://especializacondutor.com.br/cursos/mopp', 'utm_source' => 'instagram']);
        }
        WhatsappClick::query()->create(['utm_source' => 'google']);
        $this->getJson('/api/admin/attendances?utm_source=instagram&per_page=2&page=2')->assertOk()
            ->assertJsonPath('summary.total_clicks', 3)->assertJsonPath('clicks.meta.last_page', 2)
            ->assertJsonPath('clicks.last_page', 2)->assertJsonPath('clicks.meta.current_page', 2)->assertJsonCount(1, 'clicks.data');
    }

    public function test_history_uses_sao_paulo_dates_and_an_equal_previous_period(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(1, 30));
        WhatsappClick::query()->create(['utm_source' => 'instagram']);
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(4, 0));
        WhatsappClick::query()->create(['utm_source' => 'instagram']);
        $this->getJson('/api/admin/attendances?start_date=2026-10-01&end_date=2026-10-01')
            ->assertOk()->assertJsonPath('summary.total_clicks', 1)->assertJsonPath('summary.previous_period_clicks', 1);
    }
}
