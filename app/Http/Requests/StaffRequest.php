<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('short_code')) {
            $this->merge([
                'short_code' => strtoupper(trim((string) $this->input('short_code'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staffId = $this->route('staff');

        return [
            'short_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('staff', 'short_code')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($staffId),
            ],
            'rank_prefix' => ['required', 'string', 'max:50'],
            'staff_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('staff', 'staff_number')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($staffId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_base_member' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
