<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'audio_clip_id',
        'start_time',
        'end_time',
        'intensity',
        'pain_location',
        'aura',
        'symptoms',
        'triggers',
        'what_you_tried',
        'notes',
        'transcript_text',
        'extraction_confidences',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'intensity' => 'integer',
        'aura' => 'boolean',
        'symptoms' => 'array',
        'triggers' => 'array',
        'extraction_confidences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audioClip(): BelongsTo
    {
        return $this->belongsTo(AudioClip::class);
    }
}
