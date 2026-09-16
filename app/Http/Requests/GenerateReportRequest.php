<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'leave_staff_ids' => ['sometimes', 'nullable', 'array'],
            'leave_staff_ids.*' => ['integer', 'distinct'],
            'overtime_staff_ids' => ['sometimes', 'nullable', 'array'],
            'overtime_staff_ids.*' => ['integer', 'distinct'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $this->validateShiftOwnership($validator, $user);
            $this->validateStaffMembership($validator, $user, 'leave_staff_ids', true, true);
            $this->validateStaffMembership($validator, $user, 'overtime_staff_ids', false, true);
            $this->validateNoOverlap($validator);
        });
    }

    private function validateShiftOwnership($validator, $user): void
    {
        $shiftId = $this->input('shift_id');

        if ($shiftId === null || $shiftId === '') {
            return;
        }

        $shift = $user->shifts()->where('id', $shiftId)->where('is_active', true)->first();

        if ($shift === null) {
            $validator->errors()->add('shift_id', 'The selected shift is invalid.');
        }
    }

    private function validateStaffMembership($validator, $user, string $field, bool $mustBeBase, bool $mustBeActive): void
    {
        $ids = $this->input($field, []);

        if (! is_array($ids) || $ids === []) {
            return;
        }

        $validIds = $user->staff()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->when($mustBeBase, fn ($query) => $query->where('is_base_member', true), fn ($query) => $query->where('is_base_member', false))
            ->pluck('id')
            ->all();

        $invalidIds = array_diff(array_map('intval', $ids), array_map('intval', $validIds));

        foreach ($invalidIds as $invalidId) {
            $validator->errors()->add($field.'.'.array_search($invalidId, array_map('intval', $ids), true), 'The selected staff member is invalid.');
        }
    }

    private function validateNoOverlap($validator): void
    {
        $leaveIds = array_map('intval', (array) $this->input('leave_staff_ids', []));
        $overtimeIds = array_map('intval', (array) $this->input('overtime_staff_ids', []));

        $overlap = array_values(array_intersect($leaveIds, $overtimeIds));

        foreach ($overlap as $index => $overlapId) {
            $validator->errors()->add('overtime_staff_ids.'.(string) $index, 'Leave and overtime selections cannot overlap.');
        }
    }
}
