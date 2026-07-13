<?php

namespace App\Services;

use App\Models\MatchGame;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Section;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Reglas del modo partido (2 equipos).
 * Reutiliza el flujo de preguntas de QuizRoomService; aquí solo equipos y goles.
 */
class MatchGameService
{
    public function __construct(private QuizRoomService $quizRooms)
    {
    }

    /**
     * Crea sala mode=match + 2 equipos (Local / Visitante) + fila MatchGame.
     */
    public function createRoom(User $host, Section $section): Room
    {
        if ($section->questions()->count() < 1) {
            throw ValidationException::withMessages([
                'section_id' => 'La sección debe tener al menos una pregunta.',
            ]);
        }

        return DB::transaction(function () use ($host, $section) {
            $room = Room::query()->create([
                'code' => $this->quizRooms->generateUniqueCode(),
                'mode' => 'match',
                'status' => 'lobby',
                'host_id' => $host->id,
                'section_id' => $section->id,
                'current_question_id' => null,
                'question_started_at' => null,
            ]);

            $home = Team::query()->create([
                'room_id' => $room->id,
                'name' => 'Local',
                'side' => 'home',
                'goals' => 0,
            ]);

            $away = Team::query()->create([
                'room_id' => $room->id,
                'name' => 'Visitante',
                'side' => 'away',
                'goals' => 0,
            ]);

            MatchGame::query()->create([
                'room_id' => $room->id,
                'home_team_id' => $home->id,
                'away_team_id' => $away->id,
                'turn_team_id' => null,
                'status' => 'lobby',
            ]);

            return $room;
        });
    }

    /**
     * Une un jugador a un equipo (home|away o team_id).
     */
    public function createPlayer(Room $room, string $nickname, ?string $teamSide = null, ?int $teamId = null): RoomPlayer
    {
        if ($room->mode !== 'match') {
            throw new RuntimeException('createPlayer de MatchGameService solo aplica a mode=match.');
        }

        if (! in_array($room->status, ['lobby', 'active'], true)) {
            throw ValidationException::withMessages([
                'code' => 'Esta sala ya terminó.',
            ]);
        }

        $team = $this->resolveTeam($room, $teamSide, $teamId);

        $nickname = trim($nickname);

        $exists = $room->players()->where('nickname', $nickname)->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'nickname' => 'Ese apodo ya está en uso en esta sala.',
            ]);
        }

        return $room->players()->create([
            'nickname' => $nickname,
            'score' => 0,
            'team_id' => $team->id,
            'session_token' => Str::random(40),
        ]);
    }

    /**
     * +1 gol al equipo del jugador (respuesta correcta).
     */
    public function awardGoal(RoomPlayer $player): void
    {
        if (! $player->team_id) {
            return;
        }

        Team::query()->whereKey($player->team_id)->increment('goals');
    }

    /**
     * Sincroniza el status de MatchGame con el de la Room.
     */
    public function syncMatchStatus(Room $room): void
    {
        if ($room->mode !== 'match') {
            return;
        }

        $match = $room->matchGame;
        if (! $match) {
            return;
        }

        $match->update(['status' => $room->status]);
    }

    /**
     * Bloque "match" para el JSON de host/jugador.
     *
     * @return array{home: array{name: string, goals: int}, away: array{name: string, goals: int}, winner: string|null}
     */
    public function buildMatchPayload(Room $room): array
    {
        $room->loadMissing(['teams', 'matchGame']);

        $home = $room->teams->firstWhere('side', 'home');
        $away = $room->teams->firstWhere('side', 'away');

        $homeGoals = (int) ($home?->goals ?? 0);
        $awayGoals = (int) ($away?->goals ?? 0);

        $winner = null;
        if ($room->status === 'finished') {
            if ($homeGoals > $awayGoals) {
                $winner = 'home';
            } elseif ($awayGoals > $homeGoals) {
                $winner = 'away';
            } else {
                $winner = 'draw';
            }
        }

        return [
            'home' => [
                'name' => $home?->name ?? 'Local',
                'goals' => $homeGoals,
            ],
            'away' => [
                'name' => $away?->name ?? 'Visitante',
                'goals' => $awayGoals,
            ],
            'winner' => $winner,
        ];
    }

    /**
     * Datos del equipo del jugador (para UI).
     *
     * @return array{id: int, name: string, side: string}|null
     */
    public function playerTeamInfo(?RoomPlayer $player): ?array
    {
        if (! $player?->team_id) {
            return null;
        }

        $player->loadMissing('team');

        if (! $player->team) {
            return null;
        }

        return [
            'id' => $player->team->id,
            'name' => $player->team->name,
            'side' => $player->team->side,
        ];
    }

    private function resolveTeam(Room $room, ?string $teamSide, ?int $teamId): Team
    {
        $room->loadMissing('teams');

        if ($teamId) {
            $team = $room->teams->firstWhere('id', $teamId);
            if (! $team) {
                throw ValidationException::withMessages([
                    'team' => 'El equipo no pertenece a esta sala.',
                ]);
            }

            return $team;
        }

        $side = strtolower(trim((string) $teamSide));
        if (! in_array($side, ['home', 'away'], true)) {
            throw ValidationException::withMessages([
                'team' => 'En modo partido debes elegir equipo: Local (home) o Visitante (away).',
            ]);
        }

        $team = $room->teams->firstWhere('side', $side);
        if (! $team) {
            throw ValidationException::withMessages([
                'team' => 'No se encontraron los equipos de esta sala.',
            ]);
        }

        return $team;
    }
}
