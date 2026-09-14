<?php

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use App\Support\PlayerSessionCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Unirse a una sala pública (sin login) con código + apodo.
 */
class JoinController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
    ) {
    }

    public function show(Request $request): View
    {
        $prefillCode = strtoupper(trim((string) $request->query('code', '')));

        return view('play.join', [
            'prefillCode' => $prefillCode,
        ]);
    }

    /**
     * Devuelve modo/estado de una sala para adaptar el formulario (equipo solo en match).
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code', '')));

        if ($code === '') {
            return response()->json(['found' => false], 404);
        }

        $room = Room::query()->with('section')->where('code', $code)->first();

        if (! $room) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'code' => $room->code,
            'mode' => $room->mode,
            'status' => $room->status,
            'is_match' => $room->isMatchMode(),
            'can_join' => in_array($room->status, [Room::STATUS_LOBBY, Room::STATUS_ACTIVE], true),
            'section_title' => $room->section?->title,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'code' => ['required', 'string', 'max:8'],
            'nickname' => ['required', 'string', 'max:40'],
            'team' => ['nullable', 'string', 'in:home,away'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $roomCode = strtoupper(trim($validatedData['code']));
        $room = Room::query()->where('code', $roomCode)->first();

        if (! $room) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'No existe una sala con ese código.']);
        }

        if (! in_array($room->status, [Room::STATUS_LOBBY, Room::STATUS_ACTIVE], true)) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'Esta sala ya terminó.']);
        }

        // Rejoin: si la cookie ya pertenece a un jugador de esta sala, reentrar.
        $existingToken = PlayerSessionCookie::token($request);
        if ($existingToken) {
            $existingPlayer = RoomPlayer::query()
                ->where('room_id', $room->id)
                ->where('session_token', $existingToken)
                ->first();

            if ($existingPlayer) {
                return redirect()
                    ->route('play.game', ['code' => $room->code])
                    ->withCookie(PlayerSessionCookie::make($existingPlayer->session_token))
                    ->with('success', '¡Bienvenido de nuevo, '.$existingPlayer->nickname.'!');
            }
        }

        try {
            if ($room->isMatchMode()) {
                $player = $this->matchGames->createPlayer(
                    $room,
                    $validatedData['nickname'],
                    $validatedData['team'] ?? null,
                    isset($validatedData['team_id']) ? (int) $validatedData['team_id'] : null,
                );
            } else {
                $player = $this->quizRooms->createPlayer($room, $validatedData['nickname']);
            }
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('play.game', ['code' => $room->code])
            ->withCookie(PlayerSessionCookie::make($player->session_token))
            ->with('success', '¡Bienvenido al partido!');
    }
}
