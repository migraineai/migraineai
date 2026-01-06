<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Illuminate\Auth\MustVerifyEmail;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'gender',
        'age',
        'age_range',
        'time_zone',
        'onboarding_answers',
        'tour_status',
        'cycle_tracking_enabled',
        'cycle_length_days',
        'period_length_days',
        'last_period_start_date',
        'daily_reminder_enabled',
        'daily_reminder_hour',
        'post_attack_follow_up_hours',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
            'cycle_tracking_enabled' => 'boolean',
            'cycle_length_days' => 'integer',
            'period_length_days' => 'integer',
            'last_period_start_date' => 'date',
            'daily_reminder_enabled' => 'boolean',
            'daily_reminder_hour' => 'integer',
            'post_attack_follow_up_hours' => 'integer',
            'onboarding_answers' => 'array',
            'tour_status' => 'array',
        ];
    }

    public function audioClips(): HasMany
    {
        return $this->hasMany(AudioClip::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    public function dataExports(): HasMany
    {
        return $this->hasMany(UserDataExport::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(UserDeletionRequest::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function telemetryEvents(): HasMany
    {
        return $this->hasMany(TelemetryEvent::class);
    }

    public function periodLogs(): HasMany
    {
        return $this->hasMany(PeriodLog::class);
    }
}
