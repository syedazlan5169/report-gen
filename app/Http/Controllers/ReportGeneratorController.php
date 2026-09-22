<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateReportRequest;
use App\Models\ActivityLog;
use App\Support\ReportGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportGeneratorController extends Controller
{
    public function index(Request $request): View
    {
        $selectedShiftId = $request->old('shift_id');
        $selectedLeaveIds = array_map('intval', (array) $request->old('leave_staff_ids', []));
        $selectedOvertimeIds = array_map('intval', (array) $request->old('overtime_staff_ids', []));

        return view('generator.index', $this->viewData($request, $selectedShiftId === null ? null : (int) $selectedShiftId, $selectedLeaveIds, $selectedOvertimeIds, null));
    }

    public function generate(GenerateReportRequest $request): View
    {
        $selectedShiftId = (int) $request->input('shift_id');
        $selectedLeaveIds = array_map('intval', (array) $request->input('leave_staff_ids', []));
        $selectedOvertimeIds = array_map('intval', (array) $request->input('overtime_staff_ids', []));
        $reportDate = CarbonImmutable::parse($request->validated()['report_date'], 'Asia/Kuala_Lumpur');

        $viewData = $this->viewData($request, $selectedShiftId, $selectedLeaveIds, $selectedOvertimeIds, $reportDate);

        $shift = $request->user()->shifts()->where('is_active', true)->find($selectedShiftId);
        $baseStaff = $request->user()->staff()->where('is_active', true)->where('is_base_member', true)->orderBy('staff_number')->get();
        $leaveStaff = $request->user()->staff()->where('is_active', true)->where('is_base_member', true)->whereIn('id', $selectedLeaveIds)->orderBy('staff_number')->get();
        $overtimeStaff = $request->user()->staff()->where('is_active', true)->where('is_base_member', false)->whereIn('id', $selectedOvertimeIds)->orderBy('staff_number')->get();
        $templates = $request->user()->reportTemplates()->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get();

        if ($shift !== null && $templates->isNotEmpty()) {
            $generator = new ReportGenerator;
            $viewData['generatedReports'] = $generator->generate($shift, $baseStaff, $leaveStaff, $overtimeStaff, $templates, $reportDate);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => ActivityLog::ACTION_REPORT_GENERATED,
                'description' => "Shift {$shift->code} on {$reportDate->format('Y-m-d')}",
            ]);
        } else {
            $viewData['generatedReports'] = [];
        }

        return view('generator.index', $viewData);
    }

    /**
     * @param  array<int, int>  $selectedLeaveIds
     * @param  array<int, int>  $selectedOvertimeIds
     * @return array<string, mixed>
     */
    private function viewData(Request $request, ?int $selectedShiftId, array $selectedLeaveIds, array $selectedOvertimeIds, ?CarbonImmutable $reportDate): array
    {
        $user = $request->user();

        $baseStaff = $user
            ->staff()
            ->where('is_active', true)
            ->where('is_base_member', true)
            ->orderBy('staff_number')
            ->get();

        $overtimeStaff = $user
            ->staff()
            ->where('is_active', true)
            ->where('is_base_member', false)
            ->get()
            ->sortBy('short_code', SORT_NATURAL)
            ->values();

        $activeShifts = $user
            ->shifts()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('code')
            ->get();

        $enabledTemplates = $user
            ->reportTemplates()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'activeShifts' => $activeShifts,
            'baseStaff' => $baseStaff,
            'overtimeStaff' => $overtimeStaff,
            'enabledTemplates' => $enabledTemplates,
            'selectedShiftId' => $selectedShiftId,
            'selectedLeaveIds' => $selectedLeaveIds,
            'selectedOvertimeIds' => $selectedOvertimeIds,
            'reportDate' => $reportDate?->format('Y-m-d') ?? $request->old('report_date', CarbonImmutable::now('Asia/Kuala_Lumpur')->format('Y-m-d')),
            'generatedReports' => [],
        ];
    }
}
