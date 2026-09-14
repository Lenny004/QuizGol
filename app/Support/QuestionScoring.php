<?php

namespace App\Support;

use App\Models\Question;

/**
 * Puntos de una pregunta según su dificultad y premio al acertar.
 *
 * Un acierto garantiza el 50% de los puntos base.
 * El 50% restante es bonus por rapidez (máximo si responde al instante).
 */
final class QuestionScoring
{
    public const EASY = 500;

    public const MEDIUM = 1000;

    public const HARD = 2000;

    /** @var array<string, int> */
    public const POINTS = [
        'easy' => self::EASY,
        'medium' => self::MEDIUM,
        'hard' => self::HARD,
    ];

    /**
     * @return array<string, int>
     */
    public static function pointsMap(): array
    {
        return self::POINTS;
    }

    public static function basePoints(?string $difficulty): int
    {
        return self::POINTS[$difficulty] ?? self::MEDIUM;
    }

    public static function award(int $basePoints, int $timeLimitSeconds, int $elapsedSeconds): int
    {
        $basePoints = max(0, $basePoints);
        $timeLimitSeconds = max(1, $timeLimitSeconds);
        $elapsedSeconds = max(0, $elapsedSeconds);
        $remainingRatio = max(0, min(1, ($timeLimitSeconds - $elapsedSeconds) / $timeLimitSeconds));

        return (int) round($basePoints * (0.5 + 0.5 * $remainingRatio));
    }

    public static function awardForQuestion(Question $question, int $elapsedSeconds): int
    {
        return self::award(
            (int) ($question->points ?: self::basePoints($question->difficulty)),
            (int) ($question->time_limit ?: 30),
            $elapsedSeconds
        );
    }
}
