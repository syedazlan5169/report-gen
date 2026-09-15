<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_shift_pages(): void
    {
        $shift = Shift::factory()->create();

        $this->get(route('shifts.index'))->assertRedirect(route('login', absolute: false));
        $this->get(route('shifts.create'))->assertRedirect(route('login', absolute: false));
        $this->post(route('shifts.store'), $this->validShiftData())->assertRedirect(route('login', absolute: false));
        $this->get(route('shifts.edit', $shift))->assertRedirect(route('login', absolute: false));
        $this->patch(route('shifts.update', $shift), $this->validShiftData())->assertRedirect(route('login', absolute: false));
        $this->delete(route('shifts.destroy', $shift))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_view_their_shift_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response->assertOk();
    }

    public function test_user_can_create_shift(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'display_name' => ' Petang ',
        ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('shifts.index', absolute: false));

        $shift = Shift::query()->firstOrFail();

        $this->assertSame($user->id, $shift->user_id);
        $this->assertSame('ptg', $shift->code);
        $this->assertSame('Petang', $shift->display_name);
        $this->assertSame('14:00', $shift->startTimeForInput());
        $this->assertSame('23:00', $shift->endTimeForInput());
        $this->assertTrue($shift->is_active);
    }

    public function test_code_is_trimmed_and_lowercased(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'code' => ' PG ',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'user_id' => $user->id,
            'code' => 'pg',
        ]);
    }

    public function test_code_must_be_unique_within_one_users_shifts(): void
    {
        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['code' => 'ptg']);

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'code' => ' PTG ',
        ]));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_another_user_may_use_the_same_code(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Shift::factory()->for($otherUser)->create(['code' => 'ptg']);

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'code' => 'ptg',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'user_id' => $user->id,
            'code' => 'ptg',
        ]);
    }

    public function test_index_shows_only_current_users_shifts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Shift::factory()->for($user)->create(['code' => 'ptg', 'display_name' => 'Petang']);
        Shift::factory()->for($otherUser)->create(['code' => 'mlm', 'display_name' => 'Malam']);

        $response = $this->actingAs($user)->get(route('shifts.index'));

        $response
            ->assertSee('PTG')
            ->assertSee('Petang')
            ->assertDontSee('MLM')
            ->assertDontSee('Malam');
    }

    public function test_user_cannot_edit_another_users_shift(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('shifts.edit', $otherShift));

        $response->assertNotFound();
    }

    public function test_user_cannot_update_another_users_shift(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create([
            'code' => 'mlm',
            'display_name' => 'Malam',
        ]);

        $response = $this->actingAs($user)->patch(route('shifts.update', $otherShift), $this->validShiftData([
            'code' => 'pg',
            'display_name' => 'Pagi',
        ]));

        $response->assertNotFound();
        $this->assertDatabaseHas('shifts', [
            'id' => $otherShift->id,
            'code' => 'mlm',
            'display_name' => 'Malam',
        ]);
    }

    public function test_user_cannot_delete_another_users_shift(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->delete(route('shifts.destroy', $otherShift));

        $response->assertNotFound();
        $this->assertModelExists($otherShift);
    }

    public function test_start_time_and_end_time_save_correctly(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'start_time' => '07:00',
            'end_time' => '15:00',
        ]));

        $response->assertSessionHasNoErrors();

        $shift = Shift::query()->firstOrFail();
        $this->assertSame('07:00', $shift->startTimeForInput());
        $this->assertSame('15:00', $shift->endTimeForInput());
    }

    public function test_overnight_shift_is_accepted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'code' => 'mlm',
            'display_name' => 'Malam',
            'start_time' => '22:00',
            'end_time' => '07:00',
        ]));

        $response->assertSessionHasNoErrors();

        $shift = Shift::query()->firstOrFail();
        $this->assertSame('22:00', $shift->startTimeForInput());
        $this->assertSame('07:00', $shift->endTimeForInput());
    }

    public function test_active_defaults_correctly_on_create(): void
    {
        $user = User::factory()->create();
        $shiftData = $this->validShiftData();
        unset($shiftData['is_active']);

        $response = $this->actingAs($user)->post(route('shifts.store'), $shiftData);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Shift::query()->firstOrFail()->is_active);
    }

    public function test_active_state_persists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), $this->validShiftData([
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertFalse(Shift::query()->firstOrFail()->is_active);
    }

    public function test_active_state_may_be_cleared_during_update(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->patch(route('shifts.update', $shift), $this->validShiftData([
            'code' => $shift->code,
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();
        $shift->refresh();
        $this->assertFalse($shift->is_active);
    }

    public function test_required_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'code' => '',
            'display_name' => '',
            'start_time' => '',
            'end_time' => '',
        ]);

        $response->assertSessionHasErrors([
            'code',
            'display_name',
            'start_time',
            'end_time',
        ]);
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('shifts.store'), [
            'code' => 'not valid',
            'display_name' => 'Petang',
            'start_time' => '25:00',
            'end_time' => '07:99',
            'is_active' => 'not-boolean',
        ]);

        $response->assertSessionHasErrors([
            'code',
            'start_time',
            'end_time',
            'is_active',
        ]);
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_user_may_update_shift_without_changing_its_own_code(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['code' => 'ptg']);

        $response = $this->actingAs($user)->patch(route('shifts.update', $shift), $this->validShiftData([
            'code' => ' PTG ',
            'display_name' => 'Petang Updated',
        ]));

        $response->assertSessionHasNoErrors();
        $shift->refresh();
        $this->assertSame('ptg', $shift->code);
        $this->assertSame('Petang Updated', $shift->display_name);
    }

    public function test_user_cannot_update_shift_to_another_existing_code_in_their_account(): void
    {
        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['code' => 'pg']);
        $shift = Shift::factory()->for($user)->create(['code' => 'ptg']);

        $response = $this->actingAs($user)->patch(route('shifts.update', $shift), $this->validShiftData([
            'code' => ' PG ',
        ]));

        $response->assertSessionHasErrors('code');
        $shift->refresh();
        $this->assertSame('ptg', $shift->code);
    }

    public function test_time_values_populate_edit_form_without_seconds(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create([
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('shifts.edit', $shift));

        $response
            ->assertSee('value="07:00"', false)
            ->assertSee('value="15:00"', false)
            ->assertDontSee('value="07:00:00"', false)
            ->assertDontSee('value="15:00:00"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validShiftData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'ptg',
            'display_name' => 'Petang',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'is_active' => '1',
        ], $overrides);
    }
}
