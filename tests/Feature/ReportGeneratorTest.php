<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_generator_routes(): void
    {
        $this->get(route('generator.index', absolute: false))->assertRedirect(route('login', absolute: false));
        $this->post(route('generator.generate', absolute: false), [
            'shift_id' => 1,
        ])->assertRedirect(route('login', absolute: false));
    }

    public function test_dashboard_redirects_authenticated_users_to_generator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', absolute: false))
            ->assertRedirect(route('generator.index', absolute: false));
    }

    public function test_generator_derives_working_leave_and_overtime_rules(): void
    {
        Date::setTestNow('2026-09-16 08:00:00');

        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create([
            'code' => 'ptg',
            'display_name' => 'Petang',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'is_active' => true,
        ]);

        $baseOne = Staff::factory()->for($user)->create([
            'short_code' => 'B1',
            'rank_prefix' => 'PiK',
            'staff_number' => 13913,
            'name' => 'MOHD BASRUL',
            'is_base_member' => true,
            'is_active' => true,
        ]);
        $baseTwo = Staff::factory()->for($user)->create([
            'short_code' => 'B2',
            'rank_prefix' => 'PiK',
            'staff_number' => 13981,
            'name' => 'SYED AZLAN',
            'is_base_member' => true,
            'is_active' => true,
        ]);
        $baseThree = Staff::factory()->for($user)->create([
            'short_code' => 'B3',
            'rank_prefix' => 'PiK',
            'staff_number' => 16297,
            'name' => 'ZIKRY HAKIM',
            'is_base_member' => true,
            'is_active' => true,
        ]);
        $overtime = Staff::factory()->for($user)->create([
            'short_code' => 'A1',
            'rank_prefix' => 'PiKK',
            'staff_number' => 12928,
            'name' => 'SITI SAKINAH',
            'is_base_member' => false,
            'is_active' => true,
        ]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Daily Report',
            'body' => "*Laporan Kehadiran Harian*\n\nTarikh : {{date}} {{day}}\nMasa : {{shift_time_range}}\n\n{{supervisor}}\n\n{{working_staff_list}}\n\n{{leave_staff_list}}\n\n{{overtime_staff_list}}\n\n{{attendance_count}}",
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$baseTwo->id],
            'overtime_staff_ids' => [$overtime->id],
        ]);

        $response->assertOk();
        $response->assertSee('16/09/2026');
        $response->assertSee('RABU');
        $response->assertSee('1400HRS ~ 2300HRS');
        $response->assertSee('PiKK 12928 - SITI SAKINAH');
        $response->assertSee('1. PiK 13913 - MOHD BASRUL');
        $response->assertSee('1. PiK 13981 - SYED AZLAN');
        $response->assertSee('5');
    }

    public function test_generator_rejects_other_users_shift_and_staff_ids(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create(['is_active' => true]);
        $otherStaff = Staff::factory()->for($otherUser)->create(['is_base_member' => true, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $otherShift->id,
            'leave_staff_ids' => [$otherStaff->id],
            'overtime_staff_ids' => [],
        ]);

        $response->assertSessionHasErrors(['shift_id', 'leave_staff_ids.0']);
    }

    public function test_generator_ignores_inactive_staff_and_shifts_and_zero_workers_case(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $baseOne = Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => true]);
        $baseTwo = Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => false]);
        $nonBase = Staff::factory()->for($user)->create(['is_base_member' => false, 'is_active' => false]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Daily Report',
            'body' => '{{supervisor}}|{{attendance_count}}|{{working_staff_list}}|{{overtime_staff_list}}',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$baseOne->id],
            'overtime_staff_ids' => [$nonBase->id],
        ]);

        $response->assertSessionHasErrors(['overtime_staff_ids.0']);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$baseOne->id],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('-');
    }

    public function test_generator_skips_disabled_templates_and_handles_empty_template_state(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $base = Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => true]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Enabled Template',
            'body' => 'Enabled {{date}}',
            'sort_order' => 2,
            'is_enabled' => true,
        ]);
        ReportTemplate::factory()->for($user)->create([
            'name' => 'Disabled Template',
            'body' => 'Disabled {{date}}',
            'sort_order' => 1,
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('Enabled Template');
        $response->assertDontSee('Disabled Template');

        $user->reportTemplates()->delete();

        $response = $this->actingAs($user)->get(route('generator.index', absolute: false));

        $response->assertOk();
        $response->assertSee('No enabled report templates');
    }

    public function test_generator_uses_kuala_lumpur_date_and_weekday_at_utc_boundary(): void
    {
        Date::setTestNow('2026-09-16 17:00:00Z');

        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create([
            'code' => 'ptg',
            'display_name' => 'Petang',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'is_active' => true,
        ]);

        Staff::factory()->for($user)->create([
            'short_code' => 'B1',
            'rank_prefix' => 'PiK',
            'staff_number' => 13913,
            'name' => 'MOHD BASRUL',
            'is_base_member' => true,
            'is_active' => true,
        ]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Boundary Template',
            'body' => '{{date}} {{day}}',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('17/09/2026');
        $response->assertSee('KHAMIS');
        $response->assertDontSee('16/09/2026');
    }

    public function test_generator_preserves_submitted_form_state_after_generation(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create([
            'code' => 'ptg',
            'display_name' => 'Petang',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'is_active' => true,
        ]);

        $leaveStaff = Staff::factory()->for($user)->create([
            'short_code' => 'B2',
            'rank_prefix' => 'PiK',
            'staff_number' => 13981,
            'name' => 'SYED AZLAN',
            'is_base_member' => true,
            'is_active' => true,
        ]);
        $overtimeStaff = Staff::factory()->for($user)->create([
            'short_code' => 'A1',
            'rank_prefix' => 'PiKK',
            'staff_number' => 12928,
            'name' => 'SITI SAKINAH',
            'is_base_member' => false,
            'is_active' => true,
        ]);
        $baseStaff = Staff::factory()->for($user)->create([
            'short_code' => 'B1',
            'rank_prefix' => 'PiK',
            'staff_number' => 13913,
            'name' => 'MOHD BASRUL',
            'is_base_member' => true,
            'is_active' => true,
        ]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'State Template',
            'body' => '{{date}}\n{{working_staff_list}}',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$leaveStaff->id],
            'overtime_staff_ids' => [$overtimeStaff->id],
        ]);

        $response->assertOk();
        $response->assertSee('name="shift_id"', false);
        $response->assertSee('value="'.$shift->id.'"', false);
        $response->assertSee('name="leave_staff_ids[]"', false);
        $response->assertSee('value="'.$leaveStaff->id.'"', false);
        $response->assertSee('checked', false);
        $response->assertSee('name="overtime_staff_ids[]"', false);
        $response->assertSee('value="'.$overtimeStaff->id.'"', false);
        $response->assertSee('Petang', false);
    }

    public function test_generator_overtime_choices_are_presented_in_natural_short_code_order(): void
    {
        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['is_active' => true]);

        Staff::factory()->for($user)->create(['short_code' => 'A10', 'staff_number' => 1010, 'is_base_member' => false, 'is_active' => true]);
        Staff::factory()->for($user)->create(['short_code' => 'C1', 'staff_number' => 1001, 'is_base_member' => false, 'is_active' => true]);
        Staff::factory()->for($user)->create(['short_code' => 'A2', 'staff_number' => 1002, 'is_base_member' => false, 'is_active' => true]);
        Staff::factory()->for($user)->create(['short_code' => 'A1', 'staff_number' => 1003, 'is_base_member' => false, 'is_active' => true]);

        $orderedShortCodes = $this->actingAs($user)
            ->get(route('generator.index', absolute: false))
            ->viewData('overtimeStaff')
            ->pluck('short_code')
            ->all();

        $this->assertSame(['A1', 'A2', 'A10', 'C1'], $orderedShortCodes);
    }

    public function test_generated_overtime_report_list_remains_numeric_staff_number_order(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        Staff::factory()->for($user)->create(['staff_number' => 9000, 'is_base_member' => true, 'is_active' => true]);
        $higherShortCodeLowerNumber = Staff::factory()->for($user)->create([
            'short_code' => 'A10',
            'rank_prefix' => 'PiK',
            'staff_number' => 120,
            'name' => 'LOWER NUMBER',
            'is_base_member' => false,
            'is_active' => true,
        ]);
        $lowerShortCodeHigherNumber = Staff::factory()->for($user)->create([
            'short_code' => 'A2',
            'rank_prefix' => 'PiK',
            'staff_number' => 130,
            'name' => 'HIGHER NUMBER',
            'is_base_member' => false,
            'is_active' => true,
        ]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Overtime Report',
            'body' => '{{overtime_staff_list}}',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [$lowerShortCodeHigherNumber->id, $higherShortCodeLowerNumber->id],
        ]);

        $response->assertOk();
        $this->assertStringContainsString("1. PiK 120 - LOWER NUMBER\n2. PiK 130 - HIGHER NUMBER", $response->getContent());
    }
}
