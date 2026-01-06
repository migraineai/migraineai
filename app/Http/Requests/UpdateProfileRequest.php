<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'age_range' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', 'in:male,female,non_binary,prefer_not_to_say'],
            'time_zone' => ['nullable', 'string', 'max:64'],
            'cycle_tracking_enabled' => ['boolean'],
            'cycle_length_days' => ['nullable', 'integer', 'min:20', 'max:60'],
            'period_length_days' => ['nullable', 'integer', 'min:1', 'max:15'],
            'last_period_start_date' => ['nullable', 'date'],
            'daily_reminder_enabled' => ['boolean'],
            'daily_reminder_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'post_attack_follow_up_hours' => ['nullable', 'integer', 'min:1', 'max:48'],
            'onboarding_answers' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cycle_tracking_enabled' => $this->boolean('cycle_tracking_enabled'),
            'daily_reminder_enabled' => $this->boolean('daily_reminder_enabled', true),
        ]);
    }
}
