<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_switch_language_for_guest_users()
    {
        // Switch to Indonesian
        $response = $this->get(route('language.switch', 'id'));

        $response->assertRedirect();
        $this->assertEquals('id', session('locale'));
    }

    /** @test */
    public function it_can_switch_language_for_authenticated_users()
    {
        $user = User::factory()->create([
            'language_preference' => 'en',
        ]);

        $this->actingAs($user);

        // Switch to Indonesian
        $response = $this->get(route('language.switch', 'id'));

        $response->assertRedirect();
        $this->assertEquals('id', session('locale'));

        // Verify user preference was updated
        $this->assertEquals('id', $user->fresh()->language_preference);
    }

    /** @test */
    public function it_rejects_unsupported_locales()
    {
        $response = $this->get(route('language.switch', 'fr'));

        $response->assertStatus(400);
    }

    /** @test */
    public function it_persists_locale_in_session()
    {
        // Switch to Indonesian
        $this->get(route('language.switch', 'id'));

        // Make another request
        $response = $this->get('/');

        $response->assertSuccessful();
        $this->assertEquals('id', session('locale'));
    }

    /** @test */
    public function authenticated_user_preference_takes_priority()
    {
        $user = User::factory()->create([
            'language_preference' => 'id',
        ]);

        // Set session to English
        session(['locale' => 'en']);

        $this->actingAs($user);

        // Make a request - user preference should override session
        $response = $this->get('/dashboard');

        $this->assertEquals('id', app()->getLocale());
    }
}
