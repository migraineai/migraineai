<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StorePeriodLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'month' => ['required', 'date_format:Y-m'],
            'symptoms' => ['required', 'array', 'min:1'],
            'symptoms.*' => ['string', 'max:255'],
            'severity' => ['required', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_period_day' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $symptoms = collect(Arr::wrap($this->input('symptoms')))
            ->map(fn ($symptom) => is_string($symptom) ? trim($symptom) : $symptom)
            ->filter(fn ($symptom) => is_string($symptom) && $symptom !== '')
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'symptoms' => $symptoms,
            'is_period_day' => filter_var($this->input('is_period_day', false), FILTER_VALIDATE_BOOLEAN),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
