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

class JoinController extends Controller
{
    public function __construct(
        private QuizRoomService $quizRooms,
        private MatchGameService $matchGames,
    ) {
    }

    public function show(): View
    {
        return view('play.join');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8'],
            'nickname' => ['required', 'string', 'max:40'],
            'team' => ['nullable', 'string', 'in:home,away'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $code = strtoupper(trim($data['code']));

        $room = Room::query()->where('code', $code)->first();

        if (! $room) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'No existe una sala con ese código.']);
        }

        if (! in_array($room->status, ['lobby', 'active'], true)) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'Esta sala ya terminó.']);
        }

        try {
            if ($room->mode === 'match') {
                $player = $this->matchGames->createPlayer(
                    $room,
                    $data['nickname'],
                    $data['team'] ?? null,
                    isset($data['team_id']) ? (int) $data['team_id'] : null,
                );
            } else {
                $player = $this->quizRooms->createPlayer($room, $data['nickname']);
            }
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $cookie = cookie(
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
            ->withCookie($cookie)
            ->with('success', '¡Bienvenido al partido!');
    }
}
