<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Section;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Acciones del anfitrión sobre una sala: crear, iniciar, avanzar y finalizar.
 *
 * Responde HTML (redirect) o JSON según Accept / X-Requested-With (polling del host).
 */
class RoomController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
    ) {
    }

    /**
     * Crea una sala quiz o partido a partir de una sección del maestro.
     */
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
            ? 'Partido creado. Comparte el código; los alumnos eligen equipo al unirse.'
            : 'Sala creada. Comparte el código con tus alumnos.';

        return redirect()
            ->route('rooms.host', $room)
            ->with('success', $successMessage);
    }

    /**
     * Pasa la sala de lobby a active (primera pregunta).
     */
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

    /**
     * Avanza a la siguiente pregunta (o finaliza si no hay más).
     */
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

    /**
     * Finaliza la sala de inmediato.
     */
    public function finish(Room $room): RedirectResponse|JsonResponse
    {
        $this->authorizeHost($room);

        $this->quizRooms->finish($room);

        return $this->actionSuccessResponse($room, 'Partido finalizado.');
    }

    /**
     * Solo el anfitrión de la sala puede controlarla.
     */
    private function authorizeHost(Room $room): void
    {
        abort_unless($room->host_id === auth()->id(), 403);
    }

    /**
     * Respuesta de error: JSON 422 o redirect con errores.
     */
    private function actionErrorResponse(RuntimeException $exception): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson()) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return back()->withErrors(['room' => $exception->getMessage()]);
    }

    /**
     * Respuesta de éxito: estado host en JSON o redirect con flash.
     */
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
