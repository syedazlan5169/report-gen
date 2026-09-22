<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\ReportTemplate;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_log_page(): void
    {
        $this->get(route('admin.logs.index'))->assertRedirect(route('login', absolute: false));
    }

    public function test_non_admin_is_forbidden_from_admin_log_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.logs.index'))->assertForbidden();
    }

    public function test_admin_can_view_the_log_page(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::factory()->for($admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.logs.index'));

        $response->assertOk();
    }

    public function test_login_is_logged(): void
    {
        $user = User::factory()->create(['username' => 'syedazlan']);

        $this->post('/login', [
            'username' => 'syedazlan',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityLog::ACTION_LOGIN,
        ]);
    }

    public function test_logout_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityLog::ACTION_LOGOUT,
        ]);
    }

    public function test_successful_report_generation_is_logged(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);
        Staff::factory()->for($user)->create(['is_base_member' => true, 'is_active' => true]);
        ReportTemplate::factory()->for($user)->create(['is_enabled' => true]);

        $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-16',
            'shift_id' => $shift->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityLog::ACTION_REPORT_GENERATED,
        ]);
    }

    public function test_report_generation_without_enabled_templates_is_not_logged(): void
    {
        $user = User::factory()->create();
        $shift = Shift::factory()->for($user)->create(['is_active' => true]);

        $this->actingAs($user)->post(route('generator.generate', absolute: false), [
            'report_date' => '2026-09-16',
            'shift_id' => $shift->id,
        ]);

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $user->id,
            'action' => ActivityLog::ACTION_REPORT_GENERATED,
        ]);
    }
}
