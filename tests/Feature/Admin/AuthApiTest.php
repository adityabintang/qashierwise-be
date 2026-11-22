<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login(): void
    {
        User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => bcrypt('secret123'),
        ]);

        // Same-origin browser request → Sanctum treats it as stateful (session).
        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/admin/auth/login', [
                'email' => User::SUPER_ADMIN_EMAIL,
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'permissions']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/admin/auth/login', [
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_login_to_panel(): void
    {
        User::factory()->create([
            'email' => 'regular@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/admin/auth/login', [
            'email' => 'regular@example.com',
            'password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_me_returns_current_user(): void
    {
        $admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', User::SUPER_ADMIN_EMAIL);
    }
}
