<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_staff_pages(): void
    {
        $staff = Staff::factory()->create();

        $this->get(route('staff.index'))->assertRedirect(route('login', absolute: false));
        $this->get(route('staff.create'))->assertRedirect(route('login', absolute: false));
        $this->post(route('staff.store'), $this->validStaffData())->assertRedirect(route('login', absolute: false));
        $this->get(route('staff.edit', $staff))->assertRedirect(route('login', absolute: false));
        $this->patch(route('staff.update', $staff), $this->validStaffData())->assertRedirect(route('login', absolute: false));
        $this->delete(route('staff.destroy', $staff))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_view_their_staff_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('staff.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_staff(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'is_base_member' => '0',
        ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.index', absolute: false));

        $staff = Staff::query()->firstOrFail();

        $this->assertSame($user->id, $staff->user_id);
        $this->assertSame('B1', $staff->short_code);
        $this->assertSame('PiK', $staff->rank_prefix);
        $this->assertSame(13913, $staff->staff_number);
        $this->assertSame('MOHD BASRUL', $staff->name);
        $this->assertFalse($staff->is_base_member);
        $this->assertTrue($staff->is_active);
    }

    public function test_short_code_is_trimmed_and_converted_to_uppercase(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'short_code' => ' b2 ',
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('staff', [
            'user_id' => $user->id,
            'short_code' => 'B2',
        ]);
    }

    public function test_short_code_must_be_unique_within_one_users_roster(): void
    {
        $user = User::factory()->create();
        Staff::factory()->for($user)->create(['short_code' => 'B1']);

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'short_code' => ' b1 ',
            'staff_number' => 16099,
        ]));

        $response->assertSessionHasErrors('short_code');
        $this->assertDatabaseCount('staff', 1);
    }

    public function test_another_user_may_use_the_same_short_code(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Staff::factory()->for($otherUser)->create(['short_code' => 'B1']);

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'short_code' => 'b1',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', [
            'user_id' => $user->id,
            'short_code' => 'B1',
        ]);
    }

    public function test_staff_number_must_be_unique_within_one_users_roster(): void
    {
        $user = User::factory()->create();
        Staff::factory()->for($user)->create(['staff_number' => 13913]);

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'short_code' => 'B2',
            'staff_number' => 13913,
        ]));

        $response->assertSessionHasErrors('staff_number');
        $this->assertDatabaseCount('staff', 1);
    }

    public function test_another_user_may_use_the_same_staff_number(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Staff::factory()->for($otherUser)->create(['staff_number' => 13913]);

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'staff_number' => 13913,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', [
            'user_id' => $user->id,
            'staff_number' => 13913,
        ]);
    }

    public function test_user_sees_only_their_own_staff_on_index(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Staff::factory()->for($user)->create(['short_code' => 'B1', 'name' => 'MOHD BASRUL']);
        Staff::factory()->for($otherUser)->create(['short_code' => 'A1', 'name' => 'SITI SAKINAH']);

        $response = $this->actingAs($user)->get(route('staff.index'));

        $response
            ->assertSee('B1')
            ->assertSee('MOHD BASRUL')
            ->assertDontSee('A1')
            ->assertDontSee('SITI SAKINAH');
    }

    public function test_user_cannot_edit_another_users_staff(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherStaff = Staff::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('staff.edit', $otherStaff));

        $response->assertNotFound();
    }

    public function test_user_cannot_update_another_users_staff(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherStaff = Staff::factory()->for($otherUser)->create([
            'short_code' => 'A1',
            'name' => 'SITI SAKINAH',
        ]);

        $response = $this->actingAs($user)->patch(route('staff.update', $otherStaff), $this->validStaffData([
            'short_code' => 'B2',
            'staff_number' => 16099,
            'name' => 'SYED HAFIZ',
        ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('staff', [
            'id' => $otherStaff->id,
            'short_code' => 'A1',
            'name' => 'SITI SAKINAH',
        ]);
    }

    public function test_user_cannot_delete_another_users_staff(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherStaff = Staff::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->delete(route('staff.destroy', $otherStaff));

        $response->assertNotFound();
        $this->assertModelExists($otherStaff);
    }

    public function test_base_member_state_persists_correctly(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'is_base_member' => '1',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Staff::query()->firstOrFail()->is_base_member);
    }

    public function test_active_state_persists_correctly(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('staff.store'), $this->validStaffData([
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertFalse(Staff::query()->firstOrFail()->is_active);
    }

    public function test_boolean_states_can_be_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $staff = Staff::factory()->for($user)->create([
            'is_base_member' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('staff.update', $staff), $this->validStaffData([
            'short_code' => $staff->short_code,
            'staff_number' => $staff->staff_number,
            'is_base_member' => '0',
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();
        $staff->refresh();
        $this->assertFalse($staff->is_base_member);
        $this->assertFalse($staff->is_active);
    }

    public function test_required_and_invalid_fields_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('staff.store'), [
            'short_code' => '',
            'rank_prefix' => '',
            'staff_number' => 0,
            'name' => '',
            'is_base_member' => 'not-boolean',
            'is_active' => 'not-boolean',
        ]);

        $response->assertSessionHasErrors([
            'short_code',
            'rank_prefix',
            'staff_number',
            'name',
            'is_base_member',
            'is_active',
        ]);
        $this->assertDatabaseCount('staff', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStaffData(array $overrides = []): array
    {
        return array_merge([
            'short_code' => 'B1',
            'rank_prefix' => 'PiK',
            'staff_number' => 13913,
            'name' => 'MOHD BASRUL',
            'is_base_member' => '0',
            'is_active' => '1',
        ], $overrides);
    }
}
