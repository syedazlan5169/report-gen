<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportTemplateRequest;
use App\Models\ReportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = $request->user()
            ->reportTemplates()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('report-templates.index', ['templates' => $templates]);
    }

    public function create(): View
    {
        return view('report-templates.create', [
            'template' => new ReportTemplate(['is_enabled' => true]),
        ]);
    }

    public function store(ReportTemplateRequest $request): RedirectResponse
    {
        $attributes = $this->templateAttributes($request, true);

        if ($attributes['sort_order'] === null) {
            $attributes['sort_order'] = ((int) $request->user()->reportTemplates()->max('sort_order')) + 1;
        }

        $request->user()->reportTemplates()->create($attributes);

        return redirect()->route('report-templates.index')->with('status', 'report-template-created');
    }

    public function edit(Request $request, string $report_template): View
    {
        $template = $request->user()->reportTemplates()->findOrFail($report_template);

        return view('report-templates.edit', ['template' => $template]);
    }

    public function update(ReportTemplateRequest $request, string $report_template): RedirectResponse
    {
        $template = $request->user()->reportTemplates()->findOrFail($report_template);
        $attributes = $this->templateAttributes($request, false);

        if ($attributes['sort_order'] === null) {
            $attributes['sort_order'] = $template->sort_order;
        }

        $template->update($attributes);

        return redirect()->route('report-templates.edit', $template)->with('status', 'report-template-updated');
    }

    public function destroy(Request $request, string $report_template): RedirectResponse
    {
        $template = $request->user()->reportTemplates()->findOrFail($report_template);

        $template->delete();

        return redirect()->route('report-templates.index')->with('status', 'report-template-deleted');
    }

    /**
     * @return array{name: string, body: string, is_enabled: bool, sort_order: ?int}
     */
    private function templateAttributes(ReportTemplateRequest $request, bool $creating): array
    {
        $attributes = $request->safe()->only(['name', 'body', 'sort_order']);
        $attributes['sort_order'] ??= null;
        $attributes['is_enabled'] = $creating && ! $request->has('is_enabled')
            ? true
            : $request->boolean('is_enabled');

        return $attributes;
    }
}
