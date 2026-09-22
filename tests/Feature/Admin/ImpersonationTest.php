<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_start_or_stop_impersonation(): void
    {
        $user = User::factory()->create();

        $this->post(route('admin.users.impersonate', $user))->assertRedirect(route('login', absolute: false));
        $this->post(route('impersonate.stop'))->assertRedirect(route('login', absolute: false));
    }

    public function test_non_admin_cannot_start_impersonation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('admin.users.impersonate', $other))->assertForbidden();
    }

    public function test_admin_cannot_impersonate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $admin))->assertForbidden();
    }

    public function test_admin_cannot_impersonate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $otherAdmin))->assertForbidden();
    }

    public function test_admin_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $response->assertRedirect(route('generator.index', absolute: false));
        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_impersonated_session_cannot_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_stop_impersonating_and_return_to_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target));

        $response = $this->post(route('impersonate.stop'));

        $response->assertRedirect(route('admin.users.index', absolute: false));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_stopping_impersonation_without_an_active_session_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('impersonate.stop'))->assertForbidden();
    }
}
