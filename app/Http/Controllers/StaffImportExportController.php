<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Support\ConfigurationTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffImportExportController extends Controller
{
    public function export(Request $request)
    {
        $items = $request->user()
            ->staff()
            ->orderBy('short_code')
            ->get()
            ->map(fn (Staff $staff): array => [
                'short_code' => $staff->short_code,
                'rank_prefix' => $staff->rank_prefix,
                'staff_number' => $staff->staff_number,
                'name' => $staff->name,
                'is_base_member' => (bool) $staff->is_base_member,
                'is_active' => (bool) $staff->is_active,
            ])
            ->all();

        return ConfigurationTransfer::downloadJson(
            'report-gen-staff-v1.json',
            ConfigurationTransfer::staffEnvelope($items)
        );
    }

    public function create(): View
    {
        return view('staff.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        try {
            $payload = ConfigurationTransfer::decodeJsonUploadedFile($validated['file']);
            $payload = ConfigurationTransfer::validateEnvelope($payload, ConfigurationTransfer::STAFF_FORMAT);

            $items = $this->validateItems($payload['items']);
            $existing = $request->user()->staff()->get();
            $currentShortCodes = $existing->pluck('short_code')->map(fn (string $code): string => strtoupper(trim($code)))->all();
            $currentNumbers = $existing->pluck('staff_number')->map(fn ($value): int => (int) $value)->all();

            $toCreate = [];
            $skipped = [];

            foreach ($items as $index => $item) {
                $shortCode = strtoupper(trim((string) $item['short_code']));
                $staffNumber = (int) $item['staff_number'];

                if (in_array($shortCode, $currentShortCodes, true) || in_array($staffNumber, $currentNumbers, true)) {
                    $reason = in_array($shortCode, $currentShortCodes, true)
                        ? 'short code already exists'
                        : 'staff number already exists';

                    $skipped[] = ['index' => $index, 'label' => $shortCode, 'reason' => $reason];

                    continue;
                }

                $toCreate[] = [
                    'short_code' => $shortCode,
                    'rank_prefix' => trim((string) $item['rank_prefix']),
                    'staff_number' => $staffNumber,
                    'name' => trim((string) $item['name']),
                    'is_base_member' => (bool) $item['is_base_member'],
                    'is_active' => (bool) $item['is_active'],
                ];
            }

            DB::transaction(function () use ($request, $toCreate): void {
                foreach ($toCreate as $attributes) {
                    $request->user()->staff()->create($attributes);
                }
            });

            return redirect()->route('staff.index')->with('import-summary', [
                'imported' => count($toCreate),
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
                'short_code',
                'rank_prefix',
                'staff_number',
                'name',
                'is_base_member',
                'is_active',
            ], 'Staff');

            if (! is_string($item['short_code'] ?? null) || ! is_string($item['rank_prefix'] ?? null) || ! is_string($item['name'] ?? null)) {
                throw new \RuntimeException('Staff item '.($index + 1).' is missing required string values.');
            }

            if (! is_bool($item['is_base_member'] ?? null) && ! is_int($item['is_base_member'] ?? null)) {
                throw new \RuntimeException('Staff item '.($index + 1).' has an invalid is_base_member value.');
            }

            if (! is_bool($item['is_active'] ?? null) && ! is_int($item['is_active'] ?? null)) {
                throw new \RuntimeException('Staff item '.($index + 1).' has an invalid is_active value.');
            }

            if (! is_int($item['staff_number'] ?? null) && ! is_string($item['staff_number'] ?? null)) {
                throw new \RuntimeException('Staff item '.($index + 1).' has an invalid staff_number value.');
            }

            $shortCode = strtoupper(trim((string) $item['short_code']));
            $rankPrefix = trim((string) $item['rank_prefix']);
            $name = trim((string) $item['name']);
            $staffNumber = (int) $item['staff_number'];

            if ($shortCode === '' || $rankPrefix === '' || $name === '') {
                throw new \RuntimeException('Staff item '.($index + 1).' is missing required values.');
            }

            if ($staffNumber < 1) {
                throw new \RuntimeException('Staff item '.($index + 1).' has an invalid staff_number value.');
            }

            if (mb_strlen($shortCode) > 20 || mb_strlen($rankPrefix) > 50 || mb_strlen($name) > 255) {
                throw new \RuntimeException('Staff item '.($index + 1).' exceeds the field length limits.');
            }

            $normalized[] = [
                'short_code' => $shortCode,
                'rank_prefix' => $rankPrefix,
                'staff_number' => $staffNumber,
                'name' => $name,
                'is_base_member' => (bool) $item['is_base_member'],
                'is_active' => (bool) $item['is_active'],
            ];
        }

        $seenShortCodes = [];
        $seenStaffNumbers = [];

        foreach ($normalized as $item) {
            $shortCode = $item['short_code'];
            $staffNumber = $item['staff_number'];

            if (isset($seenShortCodes[$shortCode])) {
                throw new \RuntimeException('The imported Staff file contains duplicate short_code values: '.$shortCode.'.');
            }

            if (isset($seenStaffNumbers[$staffNumber])) {
                throw new \RuntimeException('The imported Staff file contains duplicate staff_number values: '.$staffNumber.'.');
            }

            $seenShortCodes[$shortCode] = true;
            $seenStaffNumbers[$staffNumber] = true;
        }

        return $normalized;
    }
}
