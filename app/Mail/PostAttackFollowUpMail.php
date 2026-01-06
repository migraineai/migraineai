<?php

namespace App\Mail;

use App\Models\Episode;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostAttackFollowUpMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        private readonly User $user,
        private readonly Reminder $reminder,
        private readonly ?Episode $episode = null
    ) {
    }

    public function build(): self
    {
        return $this->subject('Gentle check-in after your migraine')
            ->view('emails.post-attack-follow-up')
            ->with([
                'user' => $this->user,
                'reminder' => $this->reminder,
                'episode' => $this->episode,
                'firstName' => $this->determineFirstName(),
                'ctaUrl' => $this->determineDashboardUrl(),
                'timeZone' => $this->user->time_zone ?: config('app.timezone', 'UTC'),
            ]);
    }

    private function determineFirstName(): string
    {
        $name = trim((string)$this->user->name);

        if ($name === '') {
            return 'there';
        }

        $parts = preg_split('/\s+/', $name);

        return $parts[0] ?? $name;
    }

    private function determineDashboardUrl(): string
    {
        $base = config('app.frontend_url') ?? config('app.url') ?? '';
        $base = rtrim($base, '/');

        if ($base !== '') {
            return $base . '/dashboard';
        }

        return url('/dashboard');
    }
}
