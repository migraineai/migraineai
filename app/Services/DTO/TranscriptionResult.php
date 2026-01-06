<?php

namespace App\Services\DTO;

class TranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?float $confidence,
        public readonly string $provider = 'whisper-1',
    ) {
    }
}
