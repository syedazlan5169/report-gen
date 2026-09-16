<?php

namespace App\Http\Requests;

use App\Support\ReportTemplatePlaceholders;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $normalized['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('sort_order') && trim((string) $this->input('sort_order')) === '') {
            $normalized['sort_order'] = null;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $templateId = $this->route('report_template');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('report_templates', 'name')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($templateId),
            ],
            'body' => ['required', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! is_string($this->input('body'))) {
                return;
            }

            $inspection = ReportTemplatePlaceholders::inspect($this->input('body'));

            if ($inspection['malformed']) {
                $validator->errors()->add('body', 'The body contains malformed placeholder syntax.');
            }

            if ($inspection['unknown'] !== []) {
                $validator->errors()->add(
                    'body',
                    'Unsupported placeholder(s): '.implode(', ', $inspection['unknown']).'.'
                );
            }
        });
    }
}
