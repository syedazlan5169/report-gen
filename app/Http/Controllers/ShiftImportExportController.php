<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Support\ConfigurationTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ShiftImportExportController extends Controller
{
    public function export(Request $request)
    {
        $items = $request->user()
            ->shifts()
            ->orderBy('start_time')
            ->orderBy('code')
            ->get()
            ->map(fn (Shift $shift): array => [
                'code' => $shift->code,
                'display_name' => $shift->display_name,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
                'is_active' => (bool) $shift->is_active,
            ])
            ->all();

        return ConfigurationTransfer::downloadJson(
            'report-gen-shifts-v1.json',
            ConfigurationTransfer::shiftEnvelope($items)
        );
    }

    public function create(): View
    {
        return view('shifts.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        try {
            $payload = ConfigurationTransfer::decodeJsonUploadedFile($validated['file']);
            $payload = ConfigurationTransfer::validateEnvelope($payload, ConfigurationTransfer::SHIFT_FORMAT);

            $items = $this->validateItems($payload['items']);
            $currentCodes = $request->user()->shifts()->pluck('code')->map(fn (string $code): string => strtolower(trim($code)))->all();

            $toCreate = [];
            $skipped = [];

            foreach ($items as $index => $item) {
                $code = strtolower(trim((string) $item['code']));

                if (in_array($code, $currentCodes, true)) {
                    $skipped[] = ['index' => $index, 'label' => $code, 'reason' => 'code already exists'];

                    continue;
                }

                $toCreate[] = [
                    'code' => $code,
                    'display_name' => trim((string) $item['display_name']),
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'is_active' => (bool) $item['is_active'],
                ];
            }

            DB::transaction(function () use ($request, $toCreate): void {
                foreach ($toCreate as $attributes) {
                    $request->user()->shifts()->create($attributes);
                }
            });

            return redirect()->route('shifts.index')->with('import-summary', [
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
                'code',
                'display_name',
                'start_time',
                'end_time',
                'is_active',
            ], 'Shift');

            if (! is_string($item['code'] ?? null) || ! is_string($item['display_name'] ?? null)) {
                throw new \RuntimeException('Shift item '.($index + 1).' is missing required string values.');
            }

            if (! is_bool($item['is_active'] ?? null) && ! is_int($item['is_active'] ?? null)) {
                throw new \RuntimeException('Shift item '.($index + 1).' has an invalid is_active value.');
            }

            if (! is_string($item['start_time'] ?? null) || ! is_string($item['end_time'] ?? null)) {
                throw new \RuntimeException('Shift item '.($index + 1).' must include start_time and end_time.');
            }

            $code = strtolower(trim((string) $item['code']));
            $displayName = trim((string) $item['display_name']);
            $startTime = trim((string) $item['start_time']);
            $endTime = trim((string) $item['end_time']);

            if ($code === '' || $displayName === '') {
                throw new \RuntimeException('Shift item '.($index + 1).' is missing required values.');
            }

            if (! preg_match('/^[a-z0-9_-]+$/', $code)) {
                throw new \RuntimeException('Shift item '.($index + 1).' has an invalid code value.');
            }

            if (mb_strlen($code) > 20 || mb_strlen($displayName) > 100) {
                throw new \RuntimeException('Shift item '.($index + 1).' exceeds the field length limits.');
            }

            Validator::make([
                'start_time' => $startTime,
                'end_time' => $endTime,
            ], [
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i'],
            ])->validate();

            $normalized[] = [
                'code' => $code,
                'display_name' => $displayName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_active' => (bool) $item['is_active'],
            ];
        }

        $seenCodes = [];

        foreach ($normalized as $item) {
            $code = $item['code'];

            if (isset($seenCodes[$code])) {
                throw new \RuntimeException('The imported Shift file contains duplicate code values: '.$code.'.');
            }

            $seenCodes[$code] = true;
        }

        return $normalized;
    }
}
