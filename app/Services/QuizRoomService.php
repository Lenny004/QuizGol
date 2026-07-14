<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lógica principal del quiz en vivo (lobby → preguntas → ranking).
 *
 * El modo partido reutiliza este flujo; MatchGameService solo agrega equipos y goles.
 */
class QuizRoomService
{
    public function __construct(private MatchGameService $matchGames)
    {
    }

    /**
     * Crea una sala en lobby a partir de una sección con al menos 1 pregunta.
     */
    public function createRoom(User $host, Section $section): Room
    {
        if ($section->questions()->count() < 1) {
            throw ValidationException::withMessages([
                'section_id' => 'La sección debe tener al menos una pregunta.',
            ]);
        }

        return Room::query()->create([
            'code' => Room::generateUniqueCode(),
            'mode' => Room::MODE_QUIZ,
            'status' => Room::STATUS_LOBBY,
            'host_id' => $host->id,
            'section_id' => $section->id,
            'current_question_id' => null,
            'question_started_at' => null,
        ]);
    }

    /**
     * Inicia el juego mostrando la primera pregunta (por sort_order).
     */
    public function start(Room $room): void
    {
        if (! $room->isLobby()) {
            throw new RuntimeException('La sala solo se puede iniciar desde el lobby.');
        }

        $firstQuestion = $room->section
            ->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $firstQuestion) {
            throw new RuntimeException('La sección no tiene preguntas.');
        }

        $room->update([
            'status' => Room::STATUS_ACTIVE,
            'current_question_id' => $firstQuestion->id,
            'question_started_at' => now(),
        ]);

        if ($room->isMatchMode()) {
            $this->matchGames->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Avanza a la siguiente pregunta o finaliza si no hay más.
     */
    public function nextQuestion(Room $room): void
    {
        if (! $room->isActive()) {
            throw new RuntimeException('La sala no está activa.');
        }

        $orderedQuestions = $room->section
            ->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentIndex = $orderedQuestions->search(
            fn (Question $question) => (int) $question->id === (int) $room->current_question_id
        );

        $nextQuestion = $currentIndex === false
            ? null
            : $orderedQuestions->get($currentIndex + 1);

        if (! $nextQuestion) {
            $this->finish($room);

            return;
        }

        $room->update([
            'current_question_id' => $nextQuestion->id,
            'question_started_at' => now(),
        ]);
    }

    /**
     * Marca la sala como finalizada y limpia la pregunta actual.
     */
    public function finish(Room $room): void
    {
        $room->update([
            'status' => Room::STATUS_FINISHED,
            'current_question_id' => null,
            'question_started_at' => null,
        ]);

        if ($room->isMatchMode()) {
            $this->matchGames->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Registra la respuesta de un jugador y calcula puntos (MVP).
     *
     * Fórmula si acierta:
     *   base   = question.points (default 1000)
     *   elapsed = segundos desde question_started_at
     *   bonus  = max(0, (time_limit - elapsed) / time_limit) * 500
     *   points = (int)(base * 0.5 + bonus)
     *
     * Si falla: 0 puntos.
     * Solo se permite una respuesta por jugador por pregunta.
     */
    public function submitAnswer(RoomPlayer $player, Room $room, int $answerId): PlayerAnswer
    {
        if (! $room->isActive() || ! $room->current_question_id) {
            throw ValidationException::withMessages([
                'answer_id' => 'No hay una pregunta activa.',
            ]);
        }

        if ((int) $player->room_id !== (int) $room->id) {
            throw ValidationException::withMessages([
                'answer_id' => 'El jugador no pertenece a esta sala.',
            ]);
        }

        $alreadyAnswered = PlayerAnswer::query()
            ->where('room_player_id', $player->id)
            ->where('question_id', $room->current_question_id)
            ->exists();

        if ($alreadyAnswered) {
            throw ValidationException::withMessages([
                'answer_id' => 'Ya respondiste esta pregunta.',
            ]);
        }

        $selectedAnswer = Answer::query()->find($answerId);

        if (! $selectedAnswer || (int) $selectedAnswer->question_id !== (int) $room->current_question_id) {
            throw ValidationException::withMessages([
                'answer_id' => 'La respuesta no corresponde a la pregunta actual.',
            ]);
        }

        $currentQuestion = $room->currentQuestion;
        $isCorrect = (bool) $selectedAnswer->is_correct;
        $pointsAwarded = 0;

        if ($isCorrect) {
            $basePoints = (int) ($currentQuestion->points ?: 1000);
            $timeLimitSeconds = max(1, (int) ($currentQuestion->time_limit ?: 30));
            // Segundos desde que arrancó la pregunta (siempre >= 0).
            $elapsedSeconds = max(0, (int) $room->question_started_at->diffInSeconds(now()));
            $speedBonus = max(0, ($timeLimitSeconds - $elapsedSeconds) / $timeLimitSeconds) * 500;
            $pointsAwarded = (int) ($basePoints * 0.5 + $speedBonus);
        }

        $playerAnswer = PlayerAnswer::query()->create([
            'room_player_id' => $player->id,
            'question_id' => $currentQuestion->id,
            'answer_id' => $selectedAnswer->id,
            'is_correct' => $isCorrect,
            'points_awarded' => $pointsAwarded,
            'answered_at' => now(),
        ]);

        if ($pointsAwarded > 0) {
            $player->increment('score', $pointsAwarded);
        }

        // Modo partido: cada acierto suma 1 gol al equipo del jugador.
        if ($room->isMatchMode() && $isCorrect) {
            $this->matchGames->awardGoal($player);
        }

        return $playerAnswer;
    }

    /**
     * Estado JSON para el jugador (polling).
     * No incluye is_correct en las opciones hasta después de responder.
     *
     * @return array<string, mixed>
     */
    public function buildPlayerState(Room $room, ?RoomPlayer $player): array
    {
        $room->loadMissing([
            'players.team',
            'currentQuestion.answers' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $scoreboard = $this->buildScoreboard($room);
        $myAnswer = null;
        $phase = $this->resolvePhase($room, $player, $myAnswer);
        $shouldReveal = $phase === 'reveal';

        $questionPayload = null;
        if ($room->isActive() && $room->currentQuestion) {
            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'answers' => $room->currentQuestion->answers->map(fn (Answer $answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                ])->values()->all(),
            ];
        }

        $state = [
            'status' => $room->status,
            'mode' => $room->mode,
            'players_count' => $room->players->count(),
            'players' => $room->players
                ->sortByDesc('score')
                ->values()
                ->map(fn (RoomPlayer $roomPlayer) => [
                    'nickname' => $roomPlayer->nickname,
                    'score' => $roomPlayer->score,
                    'team_side' => $roomPlayer->team?->side,
                ])
                ->all(),
            'question' => $questionPayload,
            'phase' => $phase,
            'my_answer' => $myAnswer,
            'scoreboard' => $scoreboard,
            'reveal' => $shouldReveal,
            'nickname' => $player?->nickname,
            'my_score' => $player?->score ?? 0,
        ];

        if ($room->isMatchMode()) {
            $state['match'] = $this->matchGames->buildMatchPayload($room);
            $state['my_team'] = $this->matchGames->playerTeamInfo($player);
        }

        return $state;
    }

    /**
     * Estado JSON para el anfitrión (incluye is_correct para proyectar).
     *
     * @return array<string, mixed>
     */
    public function buildHostState(Room $room): array
    {
        $room->loadMissing([
            'section.subject',
            'section.grade',
            'players.team',
            'currentQuestion.answers' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $answeredCount = 0;
        if ($room->current_question_id) {
            $answeredCount = PlayerAnswer::query()
                ->where('question_id', $room->current_question_id)
                ->whereIn('room_player_id', $room->players->pluck('id'))
                ->count();
        }

        $phase = match ($room->status) {
            Room::STATUS_LOBBY => 'lobby',
            Room::STATUS_FINISHED => 'finished',
            default => 'question',
        };

        // El host siempre ve la respuesta correcta (útil para proyectar).
        $showCorrectAnswers = $room->isActive();

        $questionPayload = null;
        if ($room->isActive() && $room->currentQuestion) {
            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'difficulty' => $room->currentQuestion->difficulty,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'answers' => $room->currentQuestion->answers->map(fn (Answer $answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'is_correct' => $showCorrectAnswers ? (bool) $answer->is_correct : null,
                ])->values()->all(),
            ];
        }

        $totalQuestions = $room->section->questions()->count();
        $currentQuestionNumber = null;

        if ($room->current_question_id) {
            $orderedQuestionIds = $room->section
                ->questions()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');

            $foundIndex = $orderedQuestionIds->search(
                fn ($questionId) => (int) $questionId === (int) $room->current_question_id
            );

            $currentQuestionNumber = $foundIndex === false ? null : $foundIndex + 1;
        }

        $state = [
            'status' => $room->status,
            'mode' => $room->mode,
            'code' => $room->code,
            'section' => [
                'title' => $room->section->title,
                'subject' => $room->section->subject?->name,
                'grade' => $room->section->gradeLabel(),
            ],
            'players_count' => $room->players->count(),
            'players' => $room->players
                ->sortByDesc('score')
                ->values()
                ->map(fn (RoomPlayer $roomPlayer) => [
                    'id' => $roomPlayer->id,
                    'nickname' => $roomPlayer->nickname,
                    'score' => $roomPlayer->score,
                    'team_side' => $roomPlayer->team?->side,
                    'team_name' => $roomPlayer->team?->name,
                ])
                ->all(),
            'question' => $questionPayload,
            'phase' => $phase,
            'answered_count' => $answeredCount,
            'scoreboard' => $this->buildScoreboard($room),
            'reveal' => $showCorrectAnswers,
            'question_index' => $currentQuestionNumber,
            'total_questions' => $totalQuestions,
        ];

        if ($room->isMatchMode()) {
            $state['match'] = $this->matchGames->buildMatchPayload($room);
        }

        return $state;
    }

    /**
     * Crea un jugador en modo quiz (sin equipo).
     */
    public function createPlayer(Room $room, string $nickname): RoomPlayer
    {
        if (! in_array($room->status, [Room::STATUS_LOBBY, Room::STATUS_ACTIVE], true)) {
            throw ValidationException::withMessages([
                'code' => 'Esta sala ya terminó.',
            ]);
        }

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
            'session_token' => Str::random(40),
        ]);
    }

    /**
     * Ranking de jugadores ordenado por puntaje (mayor primero).
     *
     * @return array<int, array{nickname: string, score: int}>
     */
    private function buildScoreboard(Room $room): array
    {
        return $room->players
            ->sortByDesc('score')
            ->values()
            ->map(fn (RoomPlayer $roomPlayer) => [
                'nickname' => $roomPlayer->nickname,
                'score' => $roomPlayer->score,
            ])
            ->all();
    }

    /**
     * Decide la fase de UI del jugador: lobby | question | reveal | finished.
     *
     * Si ya respondió la pregunta actual, rellena $myAnswer y devuelve "reveal".
     *
     * @param  array{answer_id: int, is_correct: bool, points_awarded: int}|null  $myAnswer
     */
    private function resolvePhase(Room $room, ?RoomPlayer $player, ?array &$myAnswer): string
    {
        if ($room->isLobby()) {
            return 'lobby';
        }

        if ($room->isFinished()) {
            return 'finished';
        }

        // Sala activa: si el jugador ya contestó, mostramos resultado.
        if ($player && $room->current_question_id) {
            $existingAnswer = PlayerAnswer::query()
                ->where('room_player_id', $player->id)
                ->where('question_id', $room->current_question_id)
                ->first();

            if ($existingAnswer) {
                $myAnswer = [
                    'answer_id' => $existingAnswer->answer_id,
                    'is_correct' => (bool) $existingAnswer->is_correct,
                    'points_awarded' => $existingAnswer->points_awarded,
                ];

                return 'reveal';
            }
        }

        return 'question';
    }
}
