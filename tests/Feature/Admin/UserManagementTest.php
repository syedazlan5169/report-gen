<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_user_pages(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('login', absolute: false));
        $this->get(route('admin.users.edit', $user))->assertRedirect(route('login', absolute: false));
    }

    public function test_non_admin_user_is_forbidden_from_admin_user_pages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), $this->validUserData())->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.edit', $other))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.users.update', $other), $this->validUserData())->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $other))->assertForbidden();
    }

    public function test_admin_can_view_the_user_index(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $this->validUserData([
            'username' => 'newuser',
        ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index', absolute: false));

        $newUser = User::where('username', 'newuser')->firstOrFail();
        $this->assertFalse($newUser->is_admin);
    }

    public function test_admin_can_promote_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $other), $this->validUserData([
            'username' => $other->username,
            'is_admin' => '1',
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->is_admin);
    }

    public function test_admin_can_demote_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $otherAdmin), $this->validUserData([
            'username' => $otherAdmin->username,
            'is_admin' => '0',
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertFalse($otherAdmin->fresh()->is_admin);
    }

    public function test_admin_cannot_remove_their_own_admin_access(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $admin), $this->validUserData([
            'username' => $admin->username,
            'is_admin' => '0',
        ]));

        $response->assertRedirect(route('admin.users.edit', $admin, absolute: false));

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $other));

        $response->assertRedirect(route('admin.users.index', absolute: false));

        $this->assertNull($other->fresh());
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index', absolute: false));

        $this->assertNotNull($admin->fresh());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUserData(array $overrides = []): array
    {
        return array_merge([
            'username' => 'someuser',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => '0',
        ], $overrides);
    }
}
