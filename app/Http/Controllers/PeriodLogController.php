<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePeriodLogRequest;
use App\Models\PeriodLog;
use Illuminate\Http\RedirectResponse;

class PeriodLogController extends Controller
{
    public function store(StorePeriodLogRequest $request): RedirectResponse
    {
        $user = $request->user();

        $date = $request->date('date')->toDateString();

        PeriodLog::updateOrCreate(
            [
                'user_id' => $user->id,
                'logged_on' => $date,
            ],
            [
                'symptoms' => $request->input('symptoms'),
                'severity' => $request->integer('severity'),
                'notes' => $request->input('notes'),
                'is_period_day' => $request->boolean('is_period_day'),
            ]
        );

        return redirect()
            ->route('period-tracking', [
                'month' => $request->input('month'),
                'date' => $date,
            ])
            ->with('success', 'Symptoms logged successfully.');
    }
}
