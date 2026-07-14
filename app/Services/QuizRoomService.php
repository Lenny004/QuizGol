<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\PlayerAnswer;
use App\Models\Room;
use App\Models\RoomPlayer;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class QuizRoomService
{
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
            'code' => $this->generateUniqueCode(),
            'mode' => 'quiz',
            'status' => 'lobby',
            'host_id' => $host->id,
            'section_id' => $section->id,
            'current_question_id' => null,
            'question_started_at' => null,
        ]);
    }

    /**
     * Inicia el juego: primera pregunta por sort_order.
     */
    public function start(Room $room): void
    {
        if ($room->status !== 'lobby') {
            throw new RuntimeException('La sala solo se puede iniciar desde el lobby.');
        }

        $first = $room->section
            ->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $first) {
            throw new RuntimeException('La sección no tiene preguntas.');
        }

        $room->update([
            'status' => 'active',
            'current_question_id' => $first->id,
            'question_started_at' => now(),
        ]);

        if ($room->mode === 'match') {
            app(MatchGameService::class)->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Avanza a la siguiente pregunta o finaliza si no hay más.
     */
    public function nextQuestion(Room $room): void
    {
        if ($room->status !== 'active') {
            throw new RuntimeException('La sala no está activa.');
        }

        $questions = $room->section
            ->questions()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentIndex = $questions->search(
            fn ($q) => (int) $q->id === (int) $room->current_question_id
        );

        $next = $currentIndex === false ? null : $questions->get($currentIndex + 1);

        if (! $next) {
            $this->finish($room);

            return;
        }

        $room->update([
            'current_question_id' => $next->id,
            'question_started_at' => now(),
        ]);
    }

    public function finish(Room $room): void
    {
        $room->update([
            'status' => 'finished',
            'current_question_id' => null,
            'question_started_at' => null,
        ]);

        if ($room->mode === 'match') {
            app(MatchGameService::class)->syncMatchStatus($room->fresh());
        }
    }

    /**
     * Scoring (MVP):
     *   base   = question.points (default 1000)
     *   elapsed = now - question_started_at (seconds)
     *   bonus  = max(0, (time_limit - elapsed) / time_limit) * 500
     *   points = is_correct ? (int)(base * 0.5 + bonus) : 0
     *
     * Solo una respuesta por jugador por pregunta.
     */
    public function submitAnswer(RoomPlayer $player, Room $room, int $answerId): PlayerAnswer
    {
        if ($room->status !== 'active' || ! $room->current_question_id) {
            throw ValidationException::withMessages([
                'answer_id' => 'No hay una pregunta activa.',
            ]);
        }

        if ((int) $player->room_id !== (int) $room->id) {
            throw ValidationException::withMessages([
                'answer_id' => 'El jugador no pertenece a esta sala.',
            ]);
        }

        $already = PlayerAnswer::query()
            ->where('room_player_id', $player->id)
            ->where('question_id', $room->current_question_id)
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'answer_id' => 'Ya respondiste esta pregunta.',
            ]);
        }

        $answer = Answer::query()->find($answerId);

        if (! $answer || (int) $answer->question_id !== (int) $room->current_question_id) {
            throw ValidationException::withMessages([
                'answer_id' => 'La respuesta no corresponde a la pregunta actual.',
            ]);
        }

        $question = $room->currentQuestion;
        $isCorrect = (bool) $answer->is_correct;
        $points = 0;

        if ($isCorrect) {
            $base = (int) ($question->points ?: 1000);
            $timeLimit = max(1, (int) ($question->time_limit ?: 30));
            // Segundos desde que arrancó la pregunta (siempre >= 0).
            $elapsed = max(0, (int) $room->question_started_at->diffInSeconds(now()));
            $bonus = max(0, ($timeLimit - $elapsed) / $timeLimit) * 500;
            $points = (int) ($base * 0.5 + $bonus);
        }

        $playerAnswer = PlayerAnswer::query()->create([
            'room_player_id' => $player->id,
            'question_id' => $question->id,
            'answer_id' => $answer->id,
            'is_correct' => $isCorrect,
            'points_awarded' => $points,
            'answered_at' => now(),
        ]);

        if ($points > 0) {
            $player->increment('score', $points);
        }

        // Modo partido: cada acierto suma 1 gol al equipo del jugador.
        if ($room->mode === 'match' && $isCorrect) {
            app(MatchGameService::class)->awardGoal($player);
        }

        return $playerAnswer;
    }

    /**
     * Estado JSON para el jugador (polling).
     * No incluye is_correct en las opciones de respuesta.
     */
    public function buildPlayerState(Room $room, ?RoomPlayer $player): array
    {
        $room->loadMissing([
            'players.team',
            'currentQuestion.answers' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $scoreboard = $this->scoreboard($room);
        $myAnswer = null;
        $phase = $this->resolvePhase($room, $player, $myAnswer);
        $reveal = $phase === 'reveal';

        $questionPayload = null;
        if ($room->status === 'active' && $room->currentQuestion) {
            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'answers' => $room->currentQuestion->answers->map(fn (Answer $a) => [
                    'id' => $a->id,
                    'text' => $a->text,
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
                ->map(fn (RoomPlayer $p) => [
                    'nickname' => $p->nickname,
                    'score' => $p->score,
                    'team_side' => $p->team?->side,
                ])
                ->all(),
            'question' => $questionPayload,
            'phase' => $phase,
            'my_answer' => $myAnswer,
            'scoreboard' => $scoreboard,
            'reveal' => $reveal,
            'nickname' => $player?->nickname,
            'my_score' => $player?->score ?? 0,
        ];

        if ($room->mode === 'match') {
            $matchService = app(MatchGameService::class);
            $state['match'] = $matchService->buildMatchPayload($room);
            $state['my_team'] = $matchService->playerTeamInfo($player);
        }

        return $state;
    }

    /**
     * Estado JSON para el anfitrión (incluye is_correct al revelar / siempre en host).
     */
    public function buildHostState(Room $room): array
    {
        $room->loadMissing([
            'section.subject',
            'section.grade',
            'players.team',
            'currentQuestion.answers' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $answeredCount = 0;
        if ($room->current_question_id) {
            $answeredCount = PlayerAnswer::query()
                ->where('question_id', $room->current_question_id)
                ->whereIn('room_player_id', $room->players->pluck('id'))
                ->count();
        }

        $phase = match ($room->status) {
            'lobby' => 'lobby',
            'finished' => 'finished',
            default => 'question',
        };

        // El host siempre ve la respuesta correcta (útil para proyectar al final de la pregunta).
        $showCorrect = $room->status === 'active';

        $questionPayload = null;
        if ($room->status === 'active' && $room->currentQuestion) {
            $questionPayload = [
                'id' => $room->currentQuestion->id,
                'prompt' => $room->currentQuestion->prompt,
                'time_limit' => $room->currentQuestion->time_limit,
                'difficulty' => $room->currentQuestion->difficulty,
                'started_at' => optional($room->question_started_at)?->toIso8601String(),
                'answers' => $room->currentQuestion->answers->map(fn (Answer $a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                    'is_correct' => $showCorrect ? (bool) $a->is_correct : null,
                ])->values()->all(),
            ];
        }

        $totalQuestions = $room->section->questions()->count();
        $currentIndex = null;
        if ($room->current_question_id) {
            $orderedIds = $room->section
                ->questions()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id');
            $idx = $orderedIds->search(fn ($id) => (int) $id === (int) $room->current_question_id);
            $currentIndex = $idx === false ? null : $idx + 1;
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
                ->map(fn (RoomPlayer $p) => [
                    'id' => $p->id,
                    'nickname' => $p->nickname,
                    'score' => $p->score,
                    'team_side' => $p->team?->side,
                    'team_name' => $p->team?->name,
                ])
                ->all(),
            'question' => $questionPayload,
            'phase' => $phase,
            'answered_count' => $answeredCount,
            'scoreboard' => $this->scoreboard($room),
            'reveal' => $showCorrect,
            'question_index' => $currentIndex,
            'total_questions' => $totalQuestions,
        ];

        if ($room->mode === 'match') {
            $state['match'] = app(MatchGameService::class)->buildMatchPayload($room);
        }

        return $state;
    }

    public function generateUniqueCode(int $length = 4): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (Room::query()->where('code', $code)->exists());

        return $code;
    }

    public function createPlayer(Room $room, string $nickname): RoomPlayer
    {
        if (! in_array($room->status, ['lobby', 'active'], true)) {
            throw ValidationException::withMessages([
                'code' => 'Esta sala ya terminó.',
            ]);
        }

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
            'session_token' => Str::random(40),
        ]);
    }

    private function scoreboard(Room $room): array
    {
        return $room->players
            ->sortByDesc('score')
            ->values()
            ->map(fn (RoomPlayer $p) => [
                'nickname' => $p->nickname,
                'score' => $p->score,
            ])
            ->all();
    }

    private function resolvePhase(Room $room, ?RoomPlayer $player, ?array &$myAnswer): string
    {
        if ($room->status === 'lobby') {
            return 'lobby';
        }

        if ($room->status === 'finished') {
            return 'finished';
        }

        // active
        if ($player && $room->current_question_id) {
            $pa = PlayerAnswer::query()
                ->where('room_player_id', $player->id)
                ->where('question_id', $room->current_question_id)
                ->first();

            if ($pa) {
                $myAnswer = [
                    'answer_id' => $pa->answer_id,
                    'is_correct' => (bool) $pa->is_correct,
                    'points_awarded' => $pa->points_awarded,
                ];

                return 'reveal';
            }
        }

        return 'question';
    }
}
