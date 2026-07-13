<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $sectionsCount = $user->sections()->count();
        $recentSections = $user->sections()
            ->with('subject')
            ->latest()
            ->take(5)
            ->get();

        $activeRooms = $user->hostedRooms()
            ->with('section')
            ->whereIn('status', ['lobby', 'active'])
            ->latest()
            ->get();

        return view('teacher.dashboard', [
            'sectionsCount' => $sectionsCount,
            'recentSections' => $recentSections,
            'activeRooms' => $activeRooms,
        ]);
    }
}
