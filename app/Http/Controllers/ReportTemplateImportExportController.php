<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use App\Support\ConfigurationTransfer;
use App\Support\ReportTemplatePlaceholders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportTemplateImportExportController extends Controller
{
    public function export(Request $request)
    {
        $items = $request->user()
            ->reportTemplates()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ReportTemplate $template): array => [
                'name' => $template->name,
                'body' => $template->body,
                'is_enabled' => (bool) $template->is_enabled,
                'sort_order' => (int) $template->sort_order,
            ])
            ->all();

        return ConfigurationTransfer::downloadJson(
            'report-gen-templates-v1.json',
            ConfigurationTransfer::templateEnvelope($items)
        );
    }

    public function create(): View
    {
        return view('report-templates.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        try {
            $payload = ConfigurationTransfer::decodeJsonUploadedFile($validated['file']);
            $payload = ConfigurationTransfer::validateEnvelope($payload, ConfigurationTransfer::TEMPLATE_FORMAT);

            $items = $this->validateItems($payload['items']);
            $currentNames = $request->user()->reportTemplates()->pluck('name')->map(fn (string $name): string => trim($name))->all();

            $toCreate = [];
            $skipped = [];

            foreach ($items as $index => $item) {
                $name = trim((string) $item['name']);

                if (in_array($name, $currentNames, true)) {
                    $skipped[] = ['index' => $index, 'label' => $name, 'reason' => 'name already exists'];

                    continue;
                }

                $toCreate[] = [
                    'name' => $name,
                    'body' => $item['body'],
                    'is_enabled' => (bool) $item['is_enabled'],
                    'sort_order' => (int) $item['sort_order'],
                ];
            }

            $orderedItems = $this->orderImportItems($toCreate);
            $maxSortOrder = (int) $request->user()->reportTemplates()->max('sort_order');
            $nextOrder = $maxSortOrder + 1;

            foreach ($orderedItems as $item) {
                $item['sort_order'] = $nextOrder;
                $nextOrder++;
            }

            DB::transaction(function () use ($request, $orderedItems): void {
                foreach ($orderedItems as $attributes) {
                    $request->user()->reportTemplates()->create($attributes);
                }
            });

            return redirect()->route('report-templates.index')->with('import-summary', [
                'imported' => count($orderedItems),
                'skipped' => count($skipped),
                'items' => $skipped,
            ]);
        } catch (\Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function validateItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $index => $item) {
            $item = ConfigurationTransfer::validateAllowedFields($item, [
                'name',
                'body',
                'is_enabled',
                'sort_order',
            ], 'Report template');

            if (! is_string($item['name'] ?? null) || ! is_string($item['body'] ?? null)) {
                throw new \RuntimeException('Template item '.($index + 1).' is missing required string values.');
            }

            if (! is_bool($item['is_enabled'] ?? null) && ! is_int($item['is_enabled'] ?? null)) {
                throw new \RuntimeException('Template item '.($index + 1).' has an invalid is_enabled value.');
            }

            if (! is_int($item['sort_order'] ?? null) && ! is_string($item['sort_order'] ?? null)) {
                throw new \RuntimeException('Template item '.($index + 1).' has an invalid sort_order value.');
            }

            $name = trim((string) $item['name']);
            $body = (string) $item['body'];
            $sortOrder = (int) $item['sort_order'];

            if ($name === '' || $body === '') {
                throw new \RuntimeException('Template item '.($index + 1).' is missing required values.');
            }

            if ($sortOrder < 1) {
                throw new \RuntimeException('Template item '.($index + 1).' has an invalid sort_order value.');
            }

            if (mb_strlen($name) > 150) {
                throw new \RuntimeException('Template item '.($index + 1).' exceeds the name length limit.');
            }

            $inspection = ReportTemplatePlaceholders::inspect($body);

            if ($inspection['malformed']) {
                throw new \RuntimeException('Template item '.($index + 1).' contains malformed placeholder syntax.');
            }

            if ($inspection['unknown'] !== []) {
                throw new \RuntimeException('Template item '.($index + 1).' contains unsupported placeholders: '.implode(', ', $inspection['unknown']).'.');
            }

            $normalized[] = [
                'name' => $name,
                'body' => $body,
                'is_enabled' => (bool) $item['is_enabled'],
                'sort_order' => $sortOrder,
            ];
        }

        $seenNames = [];

        foreach ($normalized as $item) {
            $name = $item['name'];

            if (isset($seenNames[$name])) {
                throw new \RuntimeException('The imported Template file contains duplicate names: '.$name.'.');
            }

            $seenNames[$name] = true;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function orderImportItems(array $items): array
    {
        usort($items, function (array $left, array $right): int {
            $comparison = $left['sort_order'] <=> $right['sort_order'];

            if ($comparison !== 0) {
                return $comparison;
            }

            return 0;
        });

        return $items;
    }
}
