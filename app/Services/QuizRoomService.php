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
 * Lógica principal del quiz en vivo.
 *
 * Flujo de pregunta (estilo Kahoot):
 *   asking → (timeout / todos respondieron / host revela) → reveal → (auto o host) → siguiente
 */
class QuizRoomService
{
    public function __construct(private MatchGameService $matchGames)
    {
    }

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
            'question_phase' => null,
            'question_started_at' => null,
            'reveal_started_at' => null,
        ]);
    }

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

        $this->beginQuestion($room, $firstQuestion);

        if ($room->isMatchMode()) {
            $this->matchGames->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Pasa de asking → reveal (mostrar correctas + desglose).
     */
    public function revealQuestion(Room $room): void
    {
        if (! $room->isActive() || ! $room->isAsking()) {
            throw new RuntimeException('Solo se puede revelar durante una pregunta activa.');
        }

        $room->update([
            'question_phase' => Room::PHASE_REVEAL,
            'reveal_started_at' => now(),
        ]);
    }

    /**
     * Avanza a la siguiente pregunta (solo desde reveal) o finaliza.
     */
    public function nextQuestion(Room $room): void
    {
        if (! $room->isActive()) {
            throw new RuntimeException('La sala no está activa.');
        }

        if ($room->isAsking()) {
            throw new RuntimeException('Primero revela las respuestas (o espera al tiempo).');
        }

        if (! $room->isRevealing()) {
            throw new RuntimeException('Espera a revelar la respuesta antes de avanzar.');
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

        $this->beginQuestion($room, $nextQuestion);
    }

    public function finish(Room $room): void
    {
        $room->update([
            'status' => Room::STATUS_FINISHED,
            'current_question_id' => null,
            'question_phase' => null,
            'question_started_at' => null,
            'reveal_started_at' => null,
        ]);

        if ($room->isMatchMode()) {
            $this->matchGames->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Auto reveal / auto next según tiempo y respuestas.
     * Se llama al leer estado (polling) para no depender del host.
     */
    public function syncQuestionLifecycle(Room $room): Room
    {
        if (! $room->isActive() || ! $room->current_question_id) {
            return $room;
        }

        $room->loadMissing('currentQuestion', 'players');

        if ($room->isAsking()) {
            $answeredCount = $this->answeredCountForCurrentQuestion($room);
            $allAnswered = $room->players->count() > 0
                && $answeredCount >= $room->players->count();

            if ($room->isQuestionTimedOut() || $allAnswered) {
                $this->revealQuestion($room);
                $room->refresh();
            }
        }

        if ($room->isRevealing() && $room->reveal_started_at) {
            $revealElapsed = max(0, (int) $room->reveal_started_at->diffInSeconds(now()));
            if ($revealElapsed >= Room::REVEAL_DURATION_SECONDS) {
                $this->nextQuestion($room->fresh());
                $room->refresh();
            }
        }

        return $room->fresh() ?? $room;
    }

    /**
     * Registra la respuesta. Rechaza si ya reveló o se acabó el tiempo.
     */
    public function submitAnswer(RoomPlayer $player, Room $room, int $answerId): PlayerAnswer
    {
        $room = $this->syncQuestionLifecycle($room);

        if (! $room->isActive() || ! $room->current_question_id) {
            throw ValidationException::withMessages([
                'answer_id' => 'No hay una pregunta activa.',
            ]);
        }

        if (! $room->isAsking()) {
            throw ValidationException::withMessages([
                'answer_id' => 'El tiempo terminó. Espera la revelación.',
            ]);
        }

        if ($room->isQuestionTimedOut()) {
            throw ValidationException::withMessages([
                'answer_id' => 'Se acabó el tiempo para esta pregunta.',
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
            $elapsedSeconds = $room->questionElapsedSeconds();
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

        if ($room->isMatchMode() && $isCorrect) {
            $this->matchGames->awardGoal($player);
        }

        // Si todos ya respondieron, revelar de inmediato.
        $room->load('players');
        if ($this->answeredCountForCurrentQuestion($room) >= $room->players->count()
            && $room->players->count() > 0
        ) {
            $this->revealQuestion($room->fresh());
        }

        return $playerAnswer;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPlayerState(Room $room, ?RoomPlayer $player): array
    {
        $room = $this->syncQuestionLifecycle($room);

        $room->loadMissing([
            'players.team',
            'currentQuestion.answers' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $scoreboard = $this->buildScoreboard($room);
        $myAnswer = null;
        $phase = $this->resolvePhase($room, $player, $myAnswer);

        $questionPayload = null;
        if ($room->isActive() && $room->currentQuestion) {
            $showCorrect = $room->isRevealing();
            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'phase' => $room->question_phase,
                'answers' => $room->currentQuestion->answers->map(fn (Answer $answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'is_correct' => $showCorrect ? (bool) $answer->is_correct : null,
                ])->values()->all(),
            ];
        }

        $state = [
            'status' => $room->status,
            'mode' => $room->mode,
            'question_phase' => $room->question_phase,
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
            'reveal' => $room->isRevealing(),
            'nickname' => $player?->nickname,
            'my_score' => $player?->score ?? 0,
            'results_ready' => $room->isFinished(),
        ];

        if ($room->isMatchMode()) {
            $state['match'] = $this->matchGames->buildMatchPayload($room);
            $state['my_team'] = $this->matchGames->playerTeamInfo($player);
        }

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildHostState(Room $room): array
    {
        $room = $this->syncQuestionLifecycle($room);

        $room->loadMissing([
            'section.subject',
            'section.grade',
            'players.team',
            'currentQuestion.answers' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $answeredCount = $this->answeredCountForCurrentQuestion($room);
        $showCorrectAnswers = $room->isRevealing();

        $phase = match ($room->status) {
            Room::STATUS_LOBBY => 'lobby',
            Room::STATUS_FINISHED => 'finished',
            default => $room->isRevealing() ? 'reveal' : 'question',
        };

        $questionPayload = null;
        if ($room->isActive() && $room->currentQuestion) {
            $answerStats = $showCorrectAnswers
                ? $this->answerStatsForCurrentQuestion($room)
                : null;

            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'difficulty' => $room->currentQuestion->difficulty,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'reveal_started_at' => optional($room->reveal_started_at)?->toIso8601String(),
                'phase' => $room->question_phase,
                'answers' => $room->currentQuestion->answers->map(fn (Answer $answer) => [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'is_correct' => $showCorrectAnswers ? (bool) $answer->is_correct : null,
                    'count' => $answerStats[$answer->id]['count'] ?? null,
                    'percent' => $answerStats[$answer->id]['percent'] ?? null,
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

        $joinUrl = url('/join?code='.$room->code);

        $state = [
            'status' => $room->status,
            'mode' => $room->mode,
            'code' => $room->code,
            'join_url' => $joinUrl,
            'question_phase' => $room->question_phase,
            'reveal_seconds' => Room::REVEAL_DURATION_SECONDS,
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
            'session_token' => Str::random(64),
        ]);
    }

    private function beginQuestion(Room $room, Question $question): void
    {
        $room->update([
            'status' => Room::STATUS_ACTIVE,
            'current_question_id' => $question->id,
            'question_phase' => Room::PHASE_ASKING,
            'question_started_at' => now(),
            'reveal_started_at' => null,
        ]);
    }

    private function answeredCountForCurrentQuestion(Room $room): int
    {
        if (! $room->current_question_id) {
            return 0;
        }

        return PlayerAnswer::query()
            ->where('question_id', $room->current_question_id)
            ->whereIn('room_player_id', $room->players->pluck('id'))
            ->count();
    }

    /**
     * @return array<int, array{count: int, percent: float}>
     */
    private function answerStatsForCurrentQuestion(Room $room): array
    {
        if (! $room->current_question_id) {
            return [];
        }

        $playerAnswers = PlayerAnswer::query()
            ->where('question_id', $room->current_question_id)
            ->whereIn('room_player_id', $room->players->pluck('id'))
            ->get();

        $total = max(1, $playerAnswers->count());
        $counts = $playerAnswers->groupBy('answer_id')->map->count();

        $stats = [];
        foreach ($counts as $answerId => $count) {
            $stats[(int) $answerId] = [
                'count' => (int) $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        }

        return $stats;
    }

    /**
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
            }
        }

        if ($room->isRevealing()) {
            return 'reveal';
        }

        if ($myAnswer !== null) {
            return 'waiting';
        }

        return 'question';
    }
}
