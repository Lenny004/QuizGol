<?php

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Services\QuizRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla del jugador y endpoints de estado / respuesta (polling).
 *
 * El jugador se identifica con la cookie quizgol_player (= session_token).
 */
class PlayController extends Controller
{
    public function __construct(private QuizRoomService $quizRooms)
    {
    }

    /**
     * Vista del juego; exige cookie de jugador válida para esa sala.
     */
    public function show(string $code): View
    {
        $room = $this->findRoom($code);
        $player = $this->resolvePlayer($code);

        abort_unless($player, 403, 'Debes unirte a la sala primero.');

        return view('play.game', [
            'room' => $room,
            'player' => $player,
        ]);
    }

    /**
     * JSON de estado para play.js (cada 1.5s).
     */
    public function state(string $code): JsonResponse
    {
        $room = $this->findRoom($code);
        $player = $this->resolvePlayer($code);

        return response()->json(
            $this->quizRooms->buildPlayerState($room, $player)
        );
    }

    /**
     * Envía la respuesta del jugador a la pregunta actual.
     */
    public function answer(Request $request, string $code): JsonResponse
    {
        $room = $this->findRoom($code);
        $player = $this->resolvePlayer($code);

        if (! $player) {
            return response()->json(['message' => 'Jugador no identificado.'], 403);
        }

        $validatedData = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        $playerAnswer = $this->quizRooms->submitAnswer(
            $player,
            $room,
            (int) $validatedData['answer_id']
        );

        return response()->json([
            'ok' => true,
            'my_answer' => [
                'answer_id' => $playerAnswer->answer_id,
                'is_correct' => (bool) $playerAnswer->is_correct,
                'points_awarded' => $playerAnswer->points_awarded,
            ],
            'state' => $this->quizRooms->buildPlayerState($room->fresh(), $player->fresh()),
        ]);
    }

    /**
     * Busca al jugador por cookie quizgol_player en la sala del código dado.
     */
    private function resolvePlayer(string $code): ?RoomPlayer
    {
        $sessionToken = request()->cookie('quizgol_player');

        if (! $sessionToken) {
            return null;
        }

        $room = Room::query()->where('code', strtoupper($code))->first();

        if (! $room) {
            return null;
        }

        return RoomPlayer::query()
            ->where('room_id', $room->id)
            ->where('session_token', $sessionToken)
            ->first();
    }

    /**
     * Busca la sala por código (404 si no existe).
     */
    private function findRoom(string $code): Room
    {
        return Room::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();
    }
}
