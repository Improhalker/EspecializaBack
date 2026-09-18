<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_with_temporary_password_must_change_it_before_using_admin_modules(): void
    {
        $admin = User::factory()->create([
            'username' => 'gabriel-admin',
            'is_admin' => true,
            'must_change_password' => true,
            'password' => Hash::make('Senha-temporaria-123!'),
        ]);

        $loginResponse = $this->postJson('/api/admin/auth/login', [
            'username' => 'gabriel-admin',
            'password' => 'Senha-temporaria-123!',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.must_change_password', true)
            ->assertJsonStructure(['token']);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/admin/courses')
            ->assertStatus(423);

        $this->withToken($token)
            ->putJson('/api/admin/auth/initial-password', [
                'password' => 'Uma-nova-senha-segura-123!',
                'password_confirmation' => 'Uma-nova-senha-segura-123!',
            ])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', false);

        $this->assertFalse($admin->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Uma-nova-senha-segura-123!', $admin->fresh()->password));
    }

    public function test_non_administrators_cannot_access_administrative_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/courses')->assertForbidden();
    }

    public function test_administrative_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/courses')->assertUnauthorized();
    }
}
