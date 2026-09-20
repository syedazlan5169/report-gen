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
            'report_date' => '2026-09-16',
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

    public function test_working_base_pikk_supervisor_beats_lower_numbered_overtime_pikk(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        Staff::factory()->for($user)->create([
            'short_code' => 'B1',
            'rank_prefix' => 'PiKK',
            'staff_number' => 12409,
            'name' => 'BASE SUPERVISOR',
            'is_base_member' => true,
            'is_active' => true,
        ]);
        $overtime = Staff::factory()->for($user)->create([
            'short_code' => 'A1',
            'rank_prefix' => 'PiKK',
            'staff_number' => 10913,
            'name' => 'OVERTIME STAFF',
            'is_base_member' => false,
            'is_active' => true,
        ]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Supervisor Report',
            'body' => '{{supervisor}}',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [$overtime->id],
        ]);

        $response->assertOk();
        $this->assertSame('PiKK 12409 - BASE SUPERVISOR', $response->viewData('generatedReports')[0]['body']);
    }

    public function test_lowest_numbered_working_base_pikk_is_supervisor(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiKK', 12492, 'HIGHER PIKK', true);
        $this->createStaff($user, 'B2', 'PiKK', 12409, 'LOWER PIKK', true);
        $this->createStaff($user, 'B3', 'PiK', 10000, 'LOWEST PIK', true);

        $report = $this->generateSupervisorReport($user, $shift);

        $this->assertSame('PiKK 12409 - LOWER PIKK', $report);
    }

    public function test_base_pikk_on_leave_is_excluded_from_supervisor_priority(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $leaveStaff = $this->createStaff($user, 'B1', 'PiKK', 11000, 'LEAVE PIKK', true);
        $this->createStaff($user, 'B2', 'PiKK', 12500, 'WORKING PIKK', true);

        $report = $this->generateSupervisorReport($user, $shift, [$leaveStaff->id]);

        $this->assertSame('PiKK 12500 - WORKING PIKK', $report);
    }

    public function test_lowest_working_pikk_is_supervisor_when_no_base_pikk_is_working(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiK', 13981, 'BASE ONE', true);
        $this->createStaff($user, 'B2', 'PiK', 16297, 'BASE TWO', true);
        $lowerOvertime = $this->createStaff($user, 'A1', 'PiKK', 10913, 'LOWER OVERTIME', false);
        $higherOvertime = $this->createStaff($user, 'A2', 'PiKK', 11800, 'HIGHER OVERTIME', false);

        $report = $this->generateSupervisorReport($user, $shift, [], [$higherOvertime->id, $lowerOvertime->id]);

        $this->assertSame('PiKK 10913 - LOWER OVERTIME', $report);
    }

    public function test_lowest_working_pik_is_supervisor_when_no_pikk_is_working(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiK', 13981, 'BASE ONE', true);
        $this->createStaff($user, 'B2', 'PiK', 16297, 'BASE TWO', true);
        $overtime = $this->createStaff($user, 'A1', 'PiK', 12000, 'OVERTIME PIK', false);

        $report = $this->generateSupervisorReport($user, $shift, [], [$overtime->id]);

        $this->assertSame('PiK 12000 - OVERTIME PIK', $report);
    }

    public function test_lower_numbered_pik_does_not_beat_working_pikk_for_supervisor(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiK', 10000, 'LOWER PIK', true);
        $this->createStaff($user, 'B2', 'PiKK', 15000, 'HIGHER PIKK', true);

        $report = $this->generateSupervisorReport($user, $shift);

        $this->assertSame('PiKK 15000 - HIGHER PIKK', $report);
    }

    public function test_zero_workers_render_empty_supervisor_and_staff_lists(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);

        $report = $this->generateSupervisorReport(
            $user,
            $shift,
            templateBody: '{{supervisor}}|{{attendance_count}}|{{working_staff_list}}|{{overtime_staff_list}}',
        );

        $this->assertSame('-|0|-|-', $report);
    }

    public function test_supervisor_remains_in_normal_staff_list(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiKK', 12409, 'BASE SUPERVISOR', true);

        $report = $this->generateSupervisorReport(
            $user,
            $shift,
            templateBody: "{{supervisor}}\n{{working_staff_list}}",
        );

        $this->assertSame("PiKK 12409 - BASE SUPERVISOR\n1. PiKK 12409 - BASE SUPERVISOR", $report);
    }

    public function test_working_staff_nosupervisor_list_excludes_base_supervisor_and_renumbers_staff(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiKK', 12409, 'BASE SUPERVISOR', true);
        $this->createStaff($user, 'B2', 'PiK', 13913, 'MOHD BASRUL', true);
        $this->createStaff($user, 'B3', 'PiK', 13981, 'SYED AZLAN', true);

        $report = $this->generateSupervisorReport(
            $user,
            $shift,
            templateBody: "{{working_staff_list}}\n---\n{{working_staff_nosupervisor_list}}",
        );

        $this->assertSame("1. PiKK 12409 - BASE SUPERVISOR\n2. PiK 13913 - MOHD BASRUL\n3. PiK 13981 - SYED AZLAN\n---\n1. PiK 13913 - MOHD BASRUL\n2. PiK 13981 - SYED AZLAN", $report);
    }

    public function test_working_staff_nosupervisor_list_renders_dash_when_only_base_supervisor_remains(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PiKK', 12409, 'BASE SUPERVISOR', true);

        $report = $this->generateSupervisorReport(
            $user,
            $shift,
            templateBody: '{{working_staff_nosupervisor_list}}',
        );

        $this->assertSame('-', $report);
    }

    public function test_lowest_numbered_other_rank_is_fallback_supervisor(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $this->createStaff($user, 'B1', 'PPN', 13000, 'HIGHER PPN', true);
        $this->createStaff($user, 'B2', 'PPN', 12000, 'LOWER PPN', true);

        $report = $this->generateSupervisorReport($user, $shift);

        $this->assertSame('PPN 12000 - LOWER PPN', $report);
    }

    public function test_generator_rejects_other_users_shift_and_staff_ids(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create(['is_active' => true]);
        $otherStaff = Staff::factory()->for($otherUser)->create(['is_base_member' => true, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-17',
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
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$baseOne->id],
            'overtime_staff_ids' => [$nonBase->id],
        ]);

        $response->assertSessionHasErrors(['overtime_staff_ids.0']);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-17',
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
            'report_date' => '2026-09-17',
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
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('17/09/2026');
        $response->assertSee('KHAMIS');
        $response->assertDontSee('16/09/2026');
    }

    public function test_initial_generator_get_defaults_report_date_to_kuala_lumpur_today(): void
    {
        Date::setTestNow('2026-09-16 17:00:00Z');

        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('generator.index', absolute: false));

        $response->assertOk();
        $response->assertSee('name="report_date"', false);
        $response->assertSee('value="2026-09-17"', false);
    }

    public function test_selected_report_date_controls_date_and_day_for_future_date(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => true]);
        ReportTemplate::factory()->for($user)->create([
            'body' => '{{date}} {{day}}',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-18',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('18/09/2026');
        $response->assertSee('JUMAAT');
        $response->assertDontSee('17/09/2026');
    }

    public function test_selected_report_date_controls_past_date(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        ReportTemplate::factory()->for($user)->create([
            'body' => '{{date}} {{day}}',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-15',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('15/09/2026');
        $response->assertSee('SELASA');
    }

    public function test_invalid_report_date_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '18/09/2026',
            'shift_id' => 1,
        ]);

        $response->assertSessionHasErrors('report_date');
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
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [$leaveStaff->id],
            'overtime_staff_ids' => [$overtimeStaff->id],
        ]);

        $response->assertOk();
        $response->assertSee('name="shift_id"', false);
        $response->assertSee('name="report_date"', false);
        $response->assertSee('value="2026-09-17"', false);
        $response->assertSee('value="'.$shift->id.'"', false);
        $response->assertSee('name="leave_staff_ids[]"', false);
        $response->assertSee('value="'.$leaveStaff->id.'"', false);
        $response->assertSee('checked', false);
        $response->assertSee('name="overtime_staff_ids[]"', false);
        $response->assertSee('value="'.$overtimeStaff->id.'"', false);
        $response->assertSee('Petang', false);
    }

    public function test_initial_generator_get_does_not_render_generated_reports_autoscroll(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('generator.index', absolute: false));

        $response->assertOk();
        $response->assertDontSee('id="generated-reports-autoscroll"', false);
        $response->assertDontSee('scrollIntoView', false);
    }

    public function test_initial_generator_get_contains_clear_control(): void
    {
        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('generator.index', absolute: false));

        $response->assertOk();
        $response->assertSee('href="'.route('generator.index').'"', false);
        $response->assertSee('Clear');
    }

    public function test_clear_target_returns_clean_generator_state(): void
    {
        $user = User::factory()->create();
        Shift::factory()->for($user)->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('generator.index'));

        $response->assertOk();
        $response->assertSee('value="'.now('Asia/Kuala_Lumpur')->format('Y-m-d').'"', false);
        $response->assertDontSee('id="generated-reports"', false);
        $response->assertDontSee('scrollIntoView', false);
    }

    public function test_generator_successful_submission_renders_generated_reports_autoscroll(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => true]);

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Anchor Template',
            'body' => '{{date}}',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('id="generated-reports"', false);
        $response->assertSee('id="generated-reports-autoscroll"', false);
        $response->assertSee("document.getElementById('generated-reports')?.scrollIntoView", false);
        $response->assertSee("behavior: 'auto'", false);
    }

    public function test_copy_control_has_visible_fallback_and_safe_report_serialization(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        $body = "*Laporan*\nTarikh : {{date}}";

        ReportTemplate::factory()->for($user)->create([
            'name' => 'Copy Template',
            'body' => $body,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-18',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertOk();
        $response->assertSee('type="button"', false);
        $response->assertSee('x-data="{ copied: false, text:', false);
        $response->assertSee('>Copy</span>', false);
        $response->assertSee('Laporan', false);
        $response->assertSee('navigator.clipboard', false);
        $response->assertSee('18/09/2026', false);
    }

    public function test_generator_validation_errors_do_not_render_generated_reports_autoscroll(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherShift = Shift::factory()->for($otherUser)->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'shift_id' => $otherShift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [],
        ]);

        $response->assertSessionHasErrors(['shift_id']);
        $response->assertDontSee('id="generated-reports"', false);
        $response->assertDontSee('id="generated-reports-autoscroll"', false);
        $response->assertDontSee('scrollIntoView', false);
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
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => [],
            'overtime_staff_ids' => [$lowerShortCodeHigherNumber->id, $higherShortCodeLowerNumber->id],
        ]);

        $response->assertOk();
        $this->assertStringContainsString("1. PiK 120 - LOWER NUMBER\n2. PiK 130 - HIGHER NUMBER", $response->getContent());
    }

    private function createStaff(User $user, string $shortCode, string $rankPrefix, int $staffNumber, string $name, bool $isBaseMember): Staff
    {
        return Staff::factory()->for($user)->create([
            'short_code' => $shortCode,
            'rank_prefix' => $rankPrefix,
            'staff_number' => $staffNumber,
            'name' => $name,
            'is_base_member' => $isBaseMember,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, int>  $leaveStaffIds
     * @param  array<int, int>  $overtimeStaffIds
     */
    private function generateSupervisorReport(User $user, Shift $shift, array $leaveStaffIds = [], array $overtimeStaffIds = [], string $templateBody = '{{supervisor}}'): string
    {
        ReportTemplate::factory()->for($user)->create([
            'name' => 'Supervisor Report',
            'body' => $templateBody,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-17',
            'shift_id' => $shift->id,
            'leave_staff_ids' => $leaveStaffIds,
            'overtime_staff_ids' => $overtimeStaffIds,
        ]);

        $response->assertOk();

        return $response->viewData('generatedReports')[0]['body'];
    }
}
