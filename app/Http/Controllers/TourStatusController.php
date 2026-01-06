<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'tour_status' => $user?->tour_status ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'page' => ['required', 'string'],
            'seen' => ['required', 'boolean'],
        ]);

        $status = is_array($user->tour_status) ? $user->tour_status : [];
        $status[$data['page']] = $data['seen'];

        $user->tour_status = $status;
        $user->save();

        return response()->json([
            'tour_status' => $status,
        ]);
    }
}
