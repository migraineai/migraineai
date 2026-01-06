<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClinicianReportMail extends Mailable
{
    use SerializesModels;

    private User $patient;
    private string $clinicianName;
    private string $requestedBy;
    private string $pdfData;

    public function __construct(User $patient, string $clinicianName, string $requestedBy, string $pdfData)
    {
        $this->patient = $patient;
        $this->clinicianName = $clinicianName;
        $this->requestedBy = $requestedBy;
        $this->pdfData = $pdfData;
    }

    public function build()
    {
        return $this->subject("MigraineAI Report for {$this->patient->name}")
            ->view('emails.clinician-report')
            ->with([
                'clinicianName' => $this->clinicianName,
                'requestedBy' => $this->requestedBy,
            ])
            ->attachData($this->pdfData, 'migraineai-clinician-report.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
