<?php

namespace App\Http\Controllers;

use App\Mail\ClinicianReportMail;
use App\Services\ClinicianReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ClinicianReportController extends Controller
{
    public function download(Request $request, ClinicianReportService $service)
    {
        $data = $request->validate([
            'clinician_name' => ['nullable', 'string', 'max:255'],
            'clinician_email' => ['nullable', 'email', 'max:255'],
            'period' => ['nullable', 'string', 'max:64'],
        ]);

        $pdf = $service->create($request->user(), [
            'clinician_name' => $data['clinician_name'] ?? null,
            'clinician_email' => $data['clinician_email'] ?? null,
            'requested_by' => $request->user()->name,
            'period' => $data['period'] ?? 'All time',
        ]);

        return $pdf->download('migraineai-clinician-report.pdf');
    }

    public function send(Request $request, ClinicianReportService $service)
    {
        $data = $request->validate([
            'clinician_name' => ['nullable', 'string', 'max:255'],
            'clinician_email' => ['required', 'email', 'max:255'],
            'requested_by' => ['nullable', 'string', 'max:255'],
        ]);

        $pdf = $service->create($request->user(), [
            'clinician_name' => $data['clinician_name'] ?? null,
            'clinician_email' => $data['clinician_email'],
            'requested_by' => $data['requested_by'] ?? $request->user()->name,
        ]);

        Mail::to($data['clinician_email'])->send(
            new ClinicianReportMail(
                $request->user(),
                $data['clinician_name'] ?? 'Clinician',
                $data['requested_by'] ?? $request->user()->name,
                $pdf->output()
            )
        );

        return response()->json(['message' => 'Report submitted to your clinician.']);
    }
}
