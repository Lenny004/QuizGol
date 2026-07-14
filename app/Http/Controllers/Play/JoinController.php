<?php

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\MatchGameService;
use App\Services\QuizRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Unirse a una sala pública (sin login) con código + apodo.
 *
 * Guarda session_token en cookie httponly "quizgol_player".
 */
class JoinController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
    ) {
    }

    /**
     * Formulario: código de sala, apodo y (si es partido) equipo.
     */
    public function show(): View
    {
        return view('play.join');
    }

    /**
     * Crea el RoomPlayer y redirige a /play/{code} con la cookie de sesión.
     */
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

        // Cookie de 24h: identifica al jugador en las siguientes peticiones.
        $playerCookie = cookie(
            'quizgol_player',
            $player->session_token,
            60 * 24,
            '/',
            null,
            false,
            true
        );

        return redirect()
            ->route('play.game', ['code' => $room->code])
            ->withCookie($playerCookie)
            ->with('success', '¡Bienvenido al partido!');
    }
}
