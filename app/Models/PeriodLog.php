<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'logged_on',
        'is_period_day',
        'severity',
        'symptoms',
        'notes',
    ];

    protected $casts = [
        'logged_on' => 'date',
        'is_period_day' => 'boolean',
        'severity' => 'integer',
        'symptoms' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
