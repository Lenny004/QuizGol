<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Section;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'mode' => ['nullable', 'in:quiz,match'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);

        abort_unless($section->user_id === auth()->id(), 403);

        $mode = $data['mode'] ?? 'quiz';

        $room = $mode === 'match'
            ? $this->matchGames->createRoom(auth()->user(), $section)
            : $this->quizRooms->createRoom(auth()->user(), $section);

        $msg = $mode === 'match'
            ? 'Partido creado. Comparte el código; los alumnos eligen equipo al unirse.'
            : 'Sala creada. Comparte el código con tus alumnos.';

        return redirect()
            ->route('rooms.host', $room)
            ->with('success', $msg);
    }

    public function start(Room $room): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeHost($room);

        try {
            $this->quizRooms->start($room);
        } catch (\RuntimeException $e) {
            if (request()->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['room' => $e->getMessage()]);
        }

        if (request()->wantsJson()) {
            return response()->json($this->quizRooms->buildHostState($room->fresh()));
        }

        return back()->with('success', '¡Partido iniciado!');
    }

    public function next(Room $room): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeHost($room);

        try {
            $this->quizRooms->nextQuestion($room);
        } catch (\RuntimeException $e) {
            if (request()->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['room' => $e->getMessage()]);
        }

        if (request()->wantsJson()) {
            return response()->json($this->quizRooms->buildHostState($room->fresh()));
        }

        return back();
    }

    public function finish(Room $room): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorizeHost($room);

        $this->quizRooms->finish($room);

        if (request()->wantsJson()) {
            return response()->json($this->quizRooms->buildHostState($room->fresh()));
        }

        return back()->with('success', 'Partido finalizado.');
    }

    private function authorizeHost(Room $room): void
    {
        abort_unless($room->host_id === auth()->id(), 403);
    }
}
