<?php

namespace App\Support;

use App\Models\ReportTemplate;
use App\Models\Shift;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportGenerator
{
    /**
     * @param  Collection<int, Staff>  $baseStaff
     * @param  Collection<int, Staff>  $leaveStaff
     * @param  Collection<int, Staff>  $overtimeStaff
     * @param  Collection<int, ReportTemplate>  $templates
     * @return array<int, array{name: string, body: string}>
     */
    public function generate(Shift $shift, Collection $baseStaff, Collection $leaveStaff, Collection $overtimeStaff, Collection $templates, CarbonImmutable $reportDate): array
    {
        $workingBase = $baseStaff
            ->reject(fn (Staff $staff): bool => $leaveStaff->contains('id', $staff->id))
            ->sortBy('staff_number')
            ->values();

        $workingStaff = $workingBase->merge($overtimeStaff)->sortBy('staff_number')->values();
        $supervisor = $this->selectSupervisor($workingBase, $workingStaff);
        $workingBaseWithoutSupervisor = $this->excludeSupervisor($workingBase, $supervisor);
        $attendanceCount = $workingBase->count() + $overtimeStaff->count();
        $placeholders = [
            '{{date}}' => $reportDate->format('d/m/Y'),
            '{{day}}' => $this->reportDay($reportDate),
            '{{shift_start}}' => $this->formatShiftTime($shift->start_time),
            '{{shift_end}}' => $this->formatShiftTime($shift->end_time),
            '{{shift_time_range}}' => $this->formatShiftRange($shift),
            '{{supervisor}}' => $this->formatSupervisor($supervisor),
            '{{working_staff_list}}' => $this->formatStaffList($workingBase),
            '{{working_staff_nosupervisor_list}}' => $this->formatStaffList($workingBaseWithoutSupervisor),
            '{{leave_staff_list}}' => $this->formatStaffList($leaveStaff),
            '{{overtime_staff_list}}' => $this->formatStaffList($overtimeStaff),
            '{{attendance_count}}' => (string) $attendanceCount,
        ];

        $results = [];

        foreach ($templates as $template) {
            $results[] = [
                'name' => $template->name,
                'body' => strtr($template->body, $placeholders),
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<int, Staff>  $workingBase
     * @param  Collection<int, Staff>  $workingStaff
     */
    private function selectSupervisor(Collection $workingBase, Collection $workingStaff): ?Staff
    {
        return $workingBase->where('rank_prefix', 'PiKK')->first()
            ?? $workingStaff->where('rank_prefix', 'PiKK')->first()
            ?? $workingStaff->where('rank_prefix', 'PiK')->first()
            ?? $workingStaff->first();
    }

    /**
     * @param  Collection<int, Staff>  $workingBase
     * @return Collection<int, Staff>
     */
    private function excludeSupervisor(Collection $workingBase, ?Staff $supervisor): Collection
    {
        if ($supervisor === null || ! $workingBase->contains('id', $supervisor->id)) {
            return $workingBase;
        }

        return $workingBase
            ->reject(fn (Staff $staff): bool => $staff->id === $supervisor->id)
            ->values();
    }

    private function reportDay(CarbonImmutable $reportDate): string
    {
        $dayNames = [
            'AHAD',
            'ISNIN',
            'SELASA',
            'RABU',
            'KHAMIS',
            'JUMAAT',
            'SABTU',
        ];

        return $dayNames[$reportDate->dayOfWeek];
    }

    private function formatShiftTime(?string $time): string
    {
        if ($time === null) {
            return '';
        }

        $cleaned = substr($time, 0, 5);

        return str_replace(':', '', $cleaned).'HRS';
    }

    private function formatShiftRange(Shift $shift): string
    {
        return $this->formatShiftTime($shift->start_time).' ~ '.$this->formatShiftTime($shift->end_time);
    }

    private function formatSupervisor(?Staff $supervisor): string
    {
        return $supervisor === null ? '-' : $this->formatStaffLabel($supervisor);
    }

    /**
     * @param  Collection<int, Staff>  $staff
     */
    private function formatStaffList(Collection $staff): string
    {
        if ($staff->isEmpty()) {
            return '-';
        }

        $lines = [];

        foreach ($staff->sortBy('staff_number')->values() as $index => $member) {
            $lines[] = ($index + 1).'. '.$this->formatStaffLabel($member);
        }

        return implode("\n", $lines);
    }

    private function formatStaffLabel(Staff $staff): string
    {
        return trim($staff->rank_prefix.' '.$staff->staff_number.' - '.$staff->name);
    }
}
