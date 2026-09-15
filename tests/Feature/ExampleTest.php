<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_the_placeholder_landing_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Report Generator');
        $response->assertSee('Report generation functionality will be added next.');
    }

    public function test_guests_are_redirected_to_login_from_the_landing_page(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
