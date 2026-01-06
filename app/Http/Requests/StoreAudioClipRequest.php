<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioClipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/webm,audio/ogg,audio/wav,audio/x-wav,audio/mpeg,video/webm',
                'max:20480',
            ],
            'duration_sec' => ['required', 'integer', 'min:1', 'max:600'],
            'sample_rate' => ['nullable', 'integer', 'min:8000', 'max:192000'],
            'codec' => ['nullable', 'string', 'max:64'],
        ];
    }
}
