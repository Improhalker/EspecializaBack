<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saved_global_faqs_are_exposed_on_the_public_home_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'global_faq' => [
                    ['question' => 'Como faço o curso?', 'answer' => 'Fazendo.'],
                ],
            ],
        ])->assertOk()->assertJsonPath('settings.global_faq.0.question', 'Como faço o curso?');

        $this->getJson('/api/home')->assertOk()
            ->assertJsonCount(1, 'faqs')
            ->assertJsonPath('faqs.0.question', 'Como faço o curso?')
            ->assertJsonPath('faqs.0.answer', 'Fazendo.');
    }

    public function test_home_endpoint_falls_back_to_default_faqs_when_none_are_configured(): void
    {
        $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('faqs.0.question', 'Como faço a matrícula?');
    }
}
