<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEpisodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'audio_clip_id' => ['nullable', 'exists:audio_clips,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'intensity' => ['nullable', 'integer', 'min:0', 'max:10'],
            'pain_location' => ['nullable', 'string', 'max:255'],
            'aura' => ['nullable', 'boolean'],
            'symptoms' => ['nullable', 'array'],
            'symptoms.*' => ['string', 'max:255'],
            'triggers' => ['nullable', 'array'],
            'triggers.*' => ['string', 'max:255'],
            'what_you_tried' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'transcript_text' => ['nullable', 'string'],
            'extraction_confidences' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $toMerge = [];
        
        // Only normalize and merge fields that are actually present in the request
        if ($this->has('symptoms')) {
            $toMerge['symptoms'] = $this->normalizeArray($this->input('symptoms'));
        }
        
        if ($this->has('triggers')) {
            $toMerge['triggers'] = $this->normalizeArray($this->input('triggers'));
        }
        
        if ($this->has('pain_location')) {
            $toMerge['pain_location'] = $this->normalizePainLocation($this->input('pain_location'));
        }
        
        if ($this->has('intensity')) {
            $toMerge['intensity'] = $this->normalizeIntensity($this->input('intensity'));
        }
        
        if (!empty($toMerge)) {
            $this->merge($toMerge);
        }
    }

    private function normalizeArray(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $normalized = array_values(
            array_filter(
                array_map(
                    static fn ($item) => is_string($item) ? trim($item) : null,
                    $value
                ),
                static fn ($item) => $item !== null && $item !== ''
            )
        );

        return $normalized === [] ? null : $normalized;
    }

    private function normalizePainLocation(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        
        // Return the trimmed value as-is, accepting any string
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeIntensity(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $intensity = (int)round((float)$value);
            return ($intensity >= 0 && $intensity <= 10) ? $intensity : null;
        }

        return null;
    }
}
