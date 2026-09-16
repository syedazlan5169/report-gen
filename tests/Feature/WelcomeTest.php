<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_welcome_login_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Report Generator');
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('action="'.route('login').'"', false);
    }

    public function test_authenticated_users_are_redirected_to_the_generator(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('generator.index', absolute: false));
    }

    public function test_users_can_authenticate_from_the_welcome_page(): void
    {
        $user = User::factory()->create(['username' => 'reportuser']);

        $response = $this->from('/')->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard', absolute: false))
            ->assertRedirect(route('generator.index', absolute: false));
    }

    public function test_invalid_login_from_the_welcome_page_returns_normal_errors(): void
    {
        $response = $this->followingRedirects()->from('/')->post(route('login'), [
            'username' => 'unknown-user',
            'password' => 'incorrect-password',
        ]);

        $this->assertGuest();
        $response->assertOk();
        $response
            ->assertSee('unknown-user')
            ->assertSee(trans('auth.failed'));
    }
}
