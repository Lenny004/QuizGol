<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Section;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use App\Services\RoomResultsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class RoomController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
        private RoomResultsService $results,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'mode' => ['nullable', 'in:quiz,match'],
        ]);

        $section = Section::query()->findOrFail($validatedData['section_id']);

        abort_unless($section->user_id === auth()->id(), 403);

        $mode = $validatedData['mode'] ?? Room::MODE_QUIZ;

        $room = $mode === Room::MODE_MATCH
            ? $this->matchGames->createRoom(auth()->user(), $section)
            : $this->quizRooms->createRoom(auth()->user(), $section);

        $successMessage = $mode === Room::MODE_MATCH
            ? 'Partido creado. Comparte el código o el QR; los alumnos eligen equipo al unirse.'
            : 'Sala creada. Comparte el código o el QR con tus alumnos.';

        return redirect()
            ->route('rooms.host', $room)
            ->with('success', $successMessage);
    }

    public function start(Room $room): RedirectResponse|JsonResponse
    {
        $this->authorizeHost($room);

        try {
            $this->quizRooms->start($room);
        } catch (RuntimeException $exception) {
            return $this->actionErrorResponse($exception);
        }

        return $this->actionSuccessResponse($room, '¡Partido iniciado!');
    }

    public function reveal(Room $room): RedirectResponse|JsonResponse
    {
        $this->authorizeHost($room);

        try {
            $this->quizRooms->revealQuestion($room);
        } catch (RuntimeException $exception) {
            return $this->actionErrorResponse($exception);
        }

        return $this->actionSuccessResponse($room);
    }

    public function next(Room $room): RedirectResponse|JsonResponse
    {
        $this->authorizeHost($room);

        try {
            $this->quizRooms->nextQuestion($room);
        } catch (RuntimeException $exception) {
            return $this->actionErrorResponse($exception);
        }

        return $this->actionSuccessResponse($room);
    }

    public function finish(Room $room): RedirectResponse|JsonResponse
    {
        $this->authorizeHost($room);

        $this->quizRooms->finish($room);

        return $this->actionSuccessResponse($room, 'Partido finalizado.');
    }

    public function results(Room $room): View
    {
        $this->authorizeHost($room);

        $report = $this->results->build($room);

        return view('teacher.rooms.results', [
            'room' => $room,
            'report' => $report,
        ]);
    }

    private function authorizeHost(Room $room): void
    {
        abort_unless($room->host_id === auth()->id(), 403);
    }

    private function actionErrorResponse(RuntimeException $exception): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return back()->withErrors(['room' => $exception->getMessage()]);
    }

    private function actionSuccessResponse(Room $room, ?string $flashMessage = null): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json($this->quizRooms->buildHostState($room->fresh()));
        }

        if ($flashMessage) {
            return back()->with('success', $flashMessage);
        }

        return back();
    }
}
