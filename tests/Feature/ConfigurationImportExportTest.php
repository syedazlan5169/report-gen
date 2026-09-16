<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ConfigurationImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_export_and_import_round_trip(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Staff::factory()->for($user)->create([
            'short_code' => 'A1',
            'rank_prefix' => 'S',
            'staff_number' => 1001,
            'name' => 'Alpha Staff',
            'is_base_member' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('staff.export'));

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('report-gen.staff', $payload['format']);
        $this->assertSame(1, $payload['version']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('A1', $payload['items'][0]['short_code']);
        $this->assertArrayNotHasKey('id', $payload['items'][0]);
        $this->assertArrayNotHasKey('user_id', $payload['items'][0]);

        $file = UploadedFile::fake()->createWithContent(
            'staff-import.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->actingAs($otherUser)->post(route('staff.import.store'), [
            'file' => $file,
        ])->assertRedirect(route('staff.index', absolute: false));

        $this->assertDatabaseHas('staff', [
            'user_id' => $otherUser->id,
            'short_code' => 'A1',
            'name' => 'Alpha Staff',
        ]);
        $this->assertSame(1, $otherUser->staff()->count());
    }

    public function test_shift_export_and_import_round_trip_with_overnight_time_and_conflict_skip(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        Shift::factory()->for($user)->create([
            'code' => 'mlm',
            'display_name' => 'Malam',
            'start_time' => '22:00:00',
            'end_time' => '07:00:00',
            'is_active' => false,
        ]);

        Shift::factory()->for($recipient)->create([
            'code' => 'mlm',
            'display_name' => 'Existing',
            'start_time' => '20:00:00',
            'end_time' => '21:00:00',
        ]);

        $payload = json_decode($this->actingAs($user)->get(route('shifts.export'))->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('report-gen.shifts', $payload['format']);
        $this->assertSame('22:00', $payload['items'][0]['start_time']);
        $this->assertSame('07:00', $payload['items'][0]['end_time']);

        $file = UploadedFile::fake()->createWithContent(
            'shifts-import.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $response = $this->actingAs($recipient)->from(route('shifts.import.create'))->post(route('shifts.import.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('shifts.index', absolute: false));
        $response->assertSessionHas('import-summary');
        $this->assertSame(1, $recipient->shifts()->count());
    }

    public function test_invalid_shift_time_aborts_import_without_writes(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'invalid-shifts.json',
            json_encode([
                'format' => 'report-gen.shifts',
                'version' => 1,
                'items' => [
                    [
                        'code' => 'morning',
                        'display_name' => 'Morning',
                        'start_time' => '07:00',
                        'end_time' => '14:00',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'invalid',
                        'display_name' => 'Invalid',
                        'start_time' => '25:00',
                        'end_time' => '07:00',
                        'is_active' => true,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $response = $this->actingAs($user)->from(route('shifts.import.create'))->post(route('shifts.import.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_report_template_export_and_import_round_trip_preserves_body_and_order(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $body = "*Daily Report*\n\n{{date}}\n{{supervisor}}\n\n  keep me  \n";
        ReportTemplate::factory()->for($user)->create([
            'name' => 'Alpha Template',
            'body' => $body,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $payload = json_decode($this->actingAs($user)->get(route('report-templates.export'))->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('report-gen.templates', $payload['format']);
        $this->assertSame($body, $payload['items'][0]['body']);

        $file = UploadedFile::fake()->createWithContent(
            'templates-import.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->actingAs($recipient)->post(route('report-templates.import.store'), [
            'file' => $file,
        ])->assertRedirect(route('report-templates.index', absolute: false));

        $this->assertDatabaseHas('report_templates', [
            'user_id' => $recipient->id,
            'name' => 'Alpha Template',
            'is_enabled' => true,
        ]);

        $this->assertSame($body, $recipient->reportTemplates()->first()->body);
    }

    public function test_invalid_template_payload_aborts_import(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'invalid.json',
            json_encode([
                'format' => 'report-gen.templates',
                'version' => 1,
                'items' => [[
                    'name' => 'Broken Template',
                    'body' => '{{banana}}',
                    'is_enabled' => true,
                    'sort_order' => 1,
                ]],
            ], JSON_THROW_ON_ERROR)
        );

        $response = $this->actingAs($user)->from(route('report-templates.import.create'))->post(route('report-templates.import.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('report_templates', 0);
    }
}
