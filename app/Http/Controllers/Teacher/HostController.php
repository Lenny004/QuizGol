<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\QuizRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Pantalla del anfitrión (proyector) y endpoint de estado para polling.
 */
class HostController extends Controller
{
    public function __construct(private QuizRoomService $quizRooms)
    {
    }

    /**
     * Vista del host: código de sala, lista de jugadores y controles.
     */
    public function show(Room $room): View
    {
        $this->authorizeHost($room);

        $room->load(['section.subject', 'section.grade']);

        return view('host.show', compact('room'));
    }

    /**
     * JSON con el estado actual de la sala (lo consulta host.js cada 1.5s).
     */
    public function state(Room $room): JsonResponse
    {
        $this->authorizeHost($room);

        return response()->json($this->quizRooms->buildHostState($room));
    }

    /**
     * Solo el anfitrión puede ver y controlar esta sala.
     */
    private function authorizeHost(Room $room): void
    {
        abort_unless($room->host_id === auth()->id(), 403);
    }
}
