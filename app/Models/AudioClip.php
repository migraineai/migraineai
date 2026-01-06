<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioClip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'storage_path',
        'duration_sec',
        'codec',
        'sample_rate',
        'status',
        'transcript_text',
        'asr_provider',
        'asr_confidence',
        'structured_payload',
        'analysis_error',
        'processed_at',
    ];

    protected $casts = [
        'duration_sec' => 'integer',
        'sample_rate' => 'integer',
        'asr_confidence' => 'float',
        'structured_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }
}
