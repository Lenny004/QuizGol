<?php

namespace App\Services;

use App\Models\PlayerAnswer;
use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Reportes pedagógicos post-partida.
 */
class RoomResultsService
{
    /**
     * Resumen completo de una sala finalizada (o en curso).
     *
     * @return array{
     *   room: array{id: int, code: string, mode: string, status: string},
     *   section: array{title: string, subject: ?string, grade: ?string},
     *   players: array<int, array{nickname: string, score: int, team: ?string}>,
     *   questions: array<int, array{
     *     id: int,
     *     prompt: string,
     *     answered: int,
     *     correct: int,
     *     percent_correct: float,
     *     answers: array<int, array{id: int, text: string, is_correct: bool, count: int, percent: float}>
     *   }>,
     *   match: ?array
     * }
     */
    public function build(Room $room): array
    {
        $room->loadMissing([
            'section.subject',
            'section.grade',
            'section.questions.answers',
            'players.team',
            'teams',
            'matchGame',
        ]);

        $playerIds = $room->players->pluck('id');
        $answersByQuestion = PlayerAnswer::query()
            ->whereIn('room_player_id', $playerIds)
            ->get()
            ->groupBy('question_id');

        $questions = $room->section->questions
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(function ($question) use ($answersByQuestion, $playerIds) {
                /** @var Collection<int, PlayerAnswer> $playerAnswers */
                $playerAnswers = $answersByQuestion->get($question->id, collect());
                $answered = $playerAnswers->count();
                $correct = $playerAnswers->where('is_correct', true)->count();
                $percentCorrect = $answered > 0
                    ? round(($correct / $answered) * 100, 1)
                    : 0.0;

                $countsByAnswerId = $playerAnswers->groupBy('answer_id')->map->count();

                $answerStats = $question->answers
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values()
                    ->map(function ($answer) use ($countsByAnswerId, $answered) {
                        $count = (int) ($countsByAnswerId->get($answer->id) ?? 0);
                        $percent = $answered > 0
                            ? round(($count / $answered) * 100, 1)
                            : 0.0;

                        return [
                            'id' => $answer->id,
                            'text' => $answer->text,
                            'is_correct' => (bool) $answer->is_correct,
                            'count' => $count,
                            'percent' => $percent,
                        ];
                    })
                    ->all();

                return [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'answered' => $answered,
                    'correct' => $correct,
                    'percent_correct' => $percentCorrect,
                    'answers' => $answerStats,
                ];
            })
            ->all();

        $players = $room->players
            ->sortByDesc('score')
            ->values()
            ->map(fn ($player) => [
                'nickname' => $player->nickname,
                'score' => (int) $player->score,
                'team' => $player->team?->name,
            ])
            ->all();

        $match = null;
        if ($room->isMatchMode()) {
            $match = app(MatchGameService::class)->buildMatchPayload($room);
        }

        return [
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'mode' => $room->mode,
                'status' => $room->status,
            ],
            'section' => [
                'title' => $room->section->title,
                'subject' => $room->section->subject?->name,
                'grade' => $room->section->gradeLabel(),
            ],
            'players' => $players,
            'questions' => $questions,
            'match' => $match,
            'players_count' => $playerIds->count(),
        ];
    }
}
