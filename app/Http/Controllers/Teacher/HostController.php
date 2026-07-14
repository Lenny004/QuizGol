<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\QuizRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HostController extends Controller
{
    public function __construct(private QuizRoomService $quizRooms)
    {
    }

    public function show(Room $room): View
    {
        $this->authorizeHost($room);

        $room->load(['section.subject', 'section.grade']);

        return view('host.show', compact('room'));
    }

    public function state(Room $room): JsonResponse
    {
        $this->authorizeHost($room);

        return response()->json($this->quizRooms->buildHostState($room));
    }

    private function authorizeHost(Room $room): void
    {
        abort_unless($room->host_id === auth()->id(), 403);
    }
}
