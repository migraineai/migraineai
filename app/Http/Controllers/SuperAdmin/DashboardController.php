<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalUsers = User::count();
        $newUsersThisWeek = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $activeUsers = Episode::where('created_at', '>=', Carbon::now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');
        $totalEpisodes = Episode::count();

        $userTrend = collect(range(5, 0))
            ->map(function (int $offset) {
                $monthStart = Carbon::now()->subMonths($offset)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                return [
                    'label' => $monthStart->format('M Y'),
                    'count' => User::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                ];
            })
            ->values();

        $users = User::query()
            ->withCount('episodes')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => optional($user->created_at)?->toIso8601String(),
                'episodes_count' => $user->episodes_count,
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'new_users' => $newUsersThisWeek,
                'active_users' => $activeUsers,
                'total_episodes' => $totalEpisodes,
            ],
            'user_trend' => $userTrend,
            'users' => $users,
            'profile' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }
}
