<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftRequest extends FormRequest
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
        $normalized = [];

        if ($this->has('code')) {
            $normalized['code'] = strtolower(trim((string) $this->input('code')));
        }

        if ($this->has('display_name')) {
            $normalized['display_name'] = trim((string) $this->input('display_name'));
        }

        if ($this->has('start_time')) {
            $normalized['start_time'] = trim((string) $this->input('start_time'));
        }

        if ($this->has('end_time')) {
            $normalized['end_time'] = trim((string) $this->input('end_time'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shiftId = $this->route('shift');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('shifts', 'code')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($shiftId),
            ],
            'display_name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
