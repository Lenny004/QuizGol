<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\View\View;

/**
 * Panel principal del maestro: resumen de secciones y salas activas/recientes.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $sectionsCount = $user->sections()->count();
        $recentSections = $user->sections()
            ->with(['subject', 'grade'])
            ->latest()
            ->take(5)
            ->get();

        $activeRooms = $user->hostedRooms()
            ->with(['section.subject', 'section.grade'])
            ->whereIn('status', [Room::STATUS_LOBBY, Room::STATUS_ACTIVE])
            ->latest()
            ->get();

        $recentFinishedRooms = $user->hostedRooms()
            ->with(['section.subject', 'section.grade'])
            ->where('status', Room::STATUS_FINISHED)
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', [
            'sectionsCount' => $sectionsCount,
            'recentSections' => $recentSections,
            'activeRooms' => $activeRooms,
            'recentFinishedRooms' => $recentFinishedRooms,
        ]);
    }
}
