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
 * Reglas del modo partido (2 equipos: Local y Visitante).
 *
 * Reutiliza el flujo de preguntas de QuizRoomService;
 * aquí solo se crean equipos, se asignan jugadores y se suman goles.
 */
class MatchGameService
{
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
                'code' => Room::generateUniqueCode(),
                'mode' => Room::MODE_MATCH,
                'status' => Room::STATUS_LOBBY,
                'host_id' => $host->id,
                'section_id' => $section->id,
                'current_question_id' => null,
                'question_started_at' => null,
            ]);

            $homeTeam = Team::query()->create([
                'room_id' => $room->id,
                'name' => 'Local',
                'side' => Team::SIDE_HOME,
                'goals' => 0,
            ]);

            $awayTeam = Team::query()->create([
                'room_id' => $room->id,
                'name' => 'Visitante',
                'side' => Team::SIDE_AWAY,
                'goals' => 0,
            ]);

            MatchGame::query()->create([
                'room_id' => $room->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'turn_team_id' => null,
                'status' => Room::STATUS_LOBBY,
            ]);

            return $room;
        });
    }

    /**
     * Une un jugador a un equipo (por side home|away o por team_id).
     */
    public function createPlayer(
        Room $room,
        string $nickname,
        ?string $teamSide = null,
        ?int $teamId = null,
    ): RoomPlayer {
        if (! $room->isMatchMode()) {
            throw new RuntimeException('createPlayer de MatchGameService solo aplica a mode=match.');
        }

        if (! in_array($room->status, [Room::STATUS_LOBBY, Room::STATUS_ACTIVE], true)) {
            throw ValidationException::withMessages([
                'code' => 'Esta sala ya terminó.',
            ]);
        }

        $team = $this->resolveTeam($room, $teamSide, $teamId);
        $nickname = trim($nickname);

        $nicknameAlreadyTaken = $room->players()->where('nickname', $nickname)->exists();
        if ($nicknameAlreadyTaken) {
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
     * Suma 1 gol al equipo del jugador (cuando acierta una pregunta).
     */
    public function awardGoal(RoomPlayer $player): void
    {
        if (! $player->team_id) {
            return;
        }

        Team::query()->whereKey($player->team_id)->increment('goals');
    }

    /**
     * Copia el status de la Room al MatchGame (lobby/active/finished).
     */
    public function syncMatchStatus(Room $room): void
    {
        if (! $room->isMatchMode()) {
            return;
        }

        $matchGame = $room->matchGame;
        if (! $matchGame) {
            return;
        }

        $matchGame->update(['status' => $room->status]);
    }

    /**
     * Bloque "match" para el JSON de host/jugador (marcador y ganador).
     *
     * @return array{home: array{name: string, goals: int}, away: array{name: string, goals: int}, winner: string|null}
     */
    public function buildMatchPayload(Room $room): array
    {
        $room->loadMissing(['teams', 'matchGame']);

        $homeTeam = $room->teams->firstWhere('side', Team::SIDE_HOME);
        $awayTeam = $room->teams->firstWhere('side', Team::SIDE_AWAY);

        $homeGoals = (int) ($homeTeam?->goals ?? 0);
        $awayGoals = (int) ($awayTeam?->goals ?? 0);

        $winner = null;
        if ($room->isFinished()) {
            if ($homeGoals > $awayGoals) {
                $winner = Team::SIDE_HOME;
            } elseif ($awayGoals > $homeGoals) {
                $winner = Team::SIDE_AWAY;
            } else {
                $winner = 'draw';
            }
        }

        return [
            'home' => [
                'name' => $homeTeam?->name ?? 'Local',
                'goals' => $homeGoals,
            ],
            'away' => [
                'name' => $awayTeam?->name ?? 'Visitante',
                'goals' => $awayGoals,
            ],
            'winner' => $winner,
        ];
    }

    /**
     * Datos del equipo del jugador para la UI.
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

    /**
     * Resuelve el equipo por id o por side (home/away).
     */
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

        $normalizedSide = strtolower(trim((string) $teamSide));
        if (! in_array($normalizedSide, [Team::SIDE_HOME, Team::SIDE_AWAY], true)) {
            throw ValidationException::withMessages([
                'team' => 'En modo partido debes elegir equipo: Local (home) o Visitante (away).',
            ]);
        }

        $team = $room->teams->firstWhere('side', $normalizedSide);
        if (! $team) {
            throw ValidationException::withMessages([
                'team' => 'No se encontraron los equipos de esta sala.',
            ]);
        }

        return $team;
    }
}
