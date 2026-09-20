<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\User;
use App\Support\ReportTemplatePlaceholders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_report_template_pages(): void
    {
        $template = ReportTemplate::factory()->create();

        $this->get(route('report-templates.index'))->assertRedirect(route('login', absolute: false));
        $this->get(route('report-templates.create'))->assertRedirect(route('login', absolute: false));
        $this->post(route('report-templates.store'), $this->validTemplateData())->assertRedirect(route('login', absolute: false));
        $this->get(route('report-templates.edit', $template))->assertRedirect(route('login', absolute: false));
        $this->patch(route('report-templates.update', $template), $this->validTemplateData())->assertRedirect(route('login', absolute: false));
        $this->delete(route('report-templates.destroy', $template))->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_view_their_report_template_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('report-templates.index'))->assertOk();
    }

    public function test_user_can_create_template_with_enabled_default_and_exact_body(): void
    {
        $user = User::factory()->create();
        $body = "*Laporan Kehadiran Harian*\n\nTarikh : {{date}}\nMasa : {{shift_time_range}}\n\n_~copy~_";
        $data = $this->validTemplateData(['body' => $body]);
        unset($data['is_enabled'], $data['sort_order']);

        $response = $this->actingAs($user)->post(route('report-templates.store'), $data);

        $response->assertSessionHasNoErrors()->assertRedirect(route('report-templates.index', absolute: false));
        $template = ReportTemplate::query()->firstOrFail();

        $this->assertSame($user->id, $template->user_id);
        $this->assertSame('Daily Report', $template->name);
        $this->assertSame($body, $template->body);
        $this->assertTrue($template->is_enabled);
        $this->assertSame(1, $template->sort_order);
    }

    public function test_enabled_can_be_set_to_false(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'is_enabled' => '0',
        ]))->assertSessionHasNoErrors();

        $this->assertFalse(ReportTemplate::query()->firstOrFail()->is_enabled);
    }

    public function test_enabled_state_can_be_toggled_during_update(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create(['is_enabled' => true]);

        $this->actingAs($user)->patch(route('report-templates.update', $template), $this->validTemplateData([
            'name' => $template->name,
            'is_enabled' => '0',
        ]))->assertSessionHasNoErrors();
        $this->assertFalse($template->refresh()->is_enabled);

        $this->actingAs($user)->patch(route('report-templates.update', $template), $this->validTemplateData([
            'name' => $template->name,
            'is_enabled' => '1',
        ]))->assertSessionHasNoErrors();
        $this->assertTrue($template->refresh()->is_enabled);
    }

    public function test_template_body_preserves_leading_and_trailing_whitespace_through_http(): void
    {
        $user = User::factory()->create();
        $body = "\n  first line\nsecond line  \n\n";

        $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'body' => $body,
        ]))->assertSessionHasNoErrors();

        $template = ReportTemplate::query()->firstOrFail();
        $this->assertSame($body, $template->body);

        $updatedBody = "\n  updated first line\nupdated second line  \n\n";
        $this->actingAs($user)->patch(route('report-templates.update', $template), $this->validTemplateData([
            'name' => $template->name,
            'body' => $updatedBody,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($updatedBody, $template->refresh()->body);
    }

    public function test_valid_supported_placeholders_are_accepted(): void
    {
        $user = User::factory()->create();
        $body = implode(' ', array_map(fn (string $placeholder): string => '{{'.$placeholder.'}}', [
            'date', 'day', 'shift_start', 'shift_end', 'shift_time_range', 'supervisor',
            'working_staff_list', 'working_staff_nosupervisor_list', 'leave_staff_list', 'overtime_staff_list', 'attendance_count',
        ]));

        $response = $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'body' => $body,
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_unknown_placeholders_are_rejected_together(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'body' => '{{banana}} {{orange}} {{banana}}',
        ]));

        $response->assertSessionHasErrors('body');
        $messages = implode(' ', $response->getSession()->get('errors')->get('body'));
        $this->assertStringContainsString('{{banana}}', $messages);
        $this->assertStringContainsString('{{orange}}', $messages);
        $this->assertDatabaseCount('report_templates', 0);
    }

    public function test_malformed_placeholders_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['{{ shift_start }}', '{{date', '{{date + 1}}', '{{{date}}}'] as $body) {
            $response = $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
                'name' => 'Template '.str_replace(['{', '}', ' '], '', $body),
                'body' => $body,
            ]));

            $response->assertSessionHasErrors('body');
        }

        $this->assertDatabaseCount('report_templates', 0);
    }

    public function test_preview_escapes_html_and_displays_placeholders_literally(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create([
            'body' => '<script>alert(1)</script> {{date}} *literal*',
        ]);

        $response = $this->actingAs($user)->get(route('report-templates.index'));

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('{{date}}', false)
            ->assertSee('*literal*', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_template_names_are_unique_per_user_but_reusable_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        ReportTemplate::factory()->for($user)->create(['name' => 'Daily Report']);

        $duplicate = $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'name' => ' Daily Report ',
        ]));
        $duplicate->assertSessionHasErrors('name');

        $other = $this->actingAs($otherUser)->post(route('report-templates.store'), $this->validTemplateData([
            'name' => 'Daily Report',
        ]));
        $other->assertSessionHasNoErrors();
    }

    public function test_current_name_is_allowed_but_duplicate_name_update_is_rejected(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create(['name' => 'Daily Report']);
        $otherTemplate = ReportTemplate::factory()->for($user)->create(['name' => 'Other Report', 'sort_order' => 2]);

        $this->actingAs($user)->patch(route('report-templates.update', $template), $this->validTemplateData([
            'name' => ' Daily Report ',
        ]))->assertSessionHasNoErrors();

        $response = $this->actingAs($user)->patch(route('report-templates.update', $template), $this->validTemplateData([
            'name' => $otherTemplate->name,
        ]));
        $response->assertSessionHasErrors('name');
    }

    public function test_user_sees_only_their_templates_and_cannot_access_another_users_template(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create(['name' => 'Own Template']);
        $otherTemplate = ReportTemplate::factory()->for($otherUser)->create(['name' => 'Private Template']);

        $this->actingAs($user)->get(route('report-templates.index'))
            ->assertSee('Own Template')
            ->assertDontSee('Private Template');
        $this->actingAs($user)->get(route('report-templates.edit', $otherTemplate))->assertNotFound();
        $this->actingAs($user)->patch(route('report-templates.update', $otherTemplate), $this->validTemplateData())->assertNotFound();
        $this->actingAs($user)->delete(route('report-templates.destroy', $otherTemplate))->assertNotFound();
        $this->assertModelExists($otherTemplate);

        $template->refresh();
        $this->assertSame('Own Template', $template->name);
    }

    public function test_sort_order_is_assigned_respected_and_preserved_when_omitted(): void
    {
        $user = User::factory()->create();
        $first = ReportTemplate::factory()->for($user)->create(['sort_order' => 1]);

        $secondData = $this->validTemplateData(['name' => 'Second Template']);
        unset($secondData['sort_order']);
        $this->actingAs($user)->post(route('report-templates.store'), $secondData)->assertSessionHasNoErrors();
        $second = ReportTemplate::query()->where('name', 'Second Template')->firstOrFail();
        $this->assertSame(2, $second->sort_order);

        $this->actingAs($user)->post(route('report-templates.store'), $this->validTemplateData([
            'name' => 'Explicit Template',
            'sort_order' => 1,
        ]))->assertSessionHasNoErrors();
        $explicit = ReportTemplate::query()->where('name', 'Explicit Template')->firstOrFail();
        $this->assertSame(1, $explicit->sort_order);

        $updateData = $this->validTemplateData(['name' => $first->name]);
        unset($updateData['sort_order']);
        $this->actingAs($user)->patch(route('report-templates.update', $first), $updateData)->assertSessionHasNoErrors();
        $this->assertSame(1, $first->refresh()->sort_order);

        $orderedIds = $this->actingAs($user)->get(route('report-templates.index'))
            ->viewData('templates')
            ->pluck('id')
            ->all();
        $this->assertSame([$first->id, $explicit->id, $second->id], $orderedIds);
    }

    public function test_automatic_sort_order_is_scoped_to_each_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        ReportTemplate::factory()->for($user)->create(['sort_order' => 1]);
        ReportTemplate::factory()->for($user)->create(['sort_order' => 2]);
        $data = $this->validTemplateData(['name' => 'Other User Template']);
        unset($data['sort_order']);

        $this->actingAs($otherUser)->post(route('report-templates.store'), $data)->assertSessionHasNoErrors();

        $template = ReportTemplate::query()->where('name', 'Other User Template')->firstOrFail();
        $this->assertSame($otherUser->id, $template->user_id);
        $this->assertSame(1, $template->sort_order);
    }

    public function test_hard_delete_removes_template(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('report-templates.destroy', $template))
            ->assertRedirect(route('report-templates.index', absolute: false));

        $this->assertModelMissing($template);
    }

    public function test_template_forms_render_tappable_placeholder_insertion_controls(): void
    {
        $user = User::factory()->create();
        $template = ReportTemplate::factory()->for($user)->create();

        $this->assertTemplateFormHasPlaceholderInsertionControls(
            $this->actingAs($user)->get(route('report-templates.create', absolute: false))
        );
        $this->assertTemplateFormHasPlaceholderInsertionControls(
            $this->actingAs($user)->get(route('report-templates.edit', $template, false))
        );
    }

    /**
     * @return array<string, string>
     */
    private function validTemplateData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Daily Report',
            'body' => "*Report*\n{{date}}",
            'is_enabled' => '1',
            'sort_order' => '1',
        ], $overrides);
    }

    private function assertTemplateFormHasPlaceholderInsertionControls(TestResponse $response): void
    {
        $response->assertSee('<textarea', false);
        $response->assertSee('name="body"', false);
        $response->assertSee('x-ref="body"', false);
        $response->assertSee('Tap a placeholder to insert it at the cursor.', false);
        $response->assertSee('setRangeText(placeholder, start, end,', false);
        $response->assertSee("dispatchEvent(new Event('input'", false);
        $response->assertSee('textarea.focus()', false);

        foreach (ReportTemplatePlaceholders::supported() as $placeholder) {
            $response->assertSee('<button type="button"', false);
            $response->assertSee('{{'.$placeholder.'}}', false);
        }
    }
}
