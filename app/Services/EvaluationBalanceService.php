<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Section;
use App\Support\QuestionScoring;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Comprueba si una sección está lista para usarse como evaluación.
 *
 * Una sesión equilibrada necesita al menos 3 preguntas, las tres dificultades
 * (fácil, media y difícil) y que ninguna supere el 50% del banco.
 */
class EvaluationBalanceService
{
    public const MIN_QUESTIONS = 3;

    public const MAX_SHARE = 0.5;

    /**
     * @return array{
     *     balanced: bool,
     *     total: int,
     *     counts: array{easy: int, medium: int, hard: int, unset: int},
     *     shares: array{easy: int, medium: int, hard: int},
     *     max_score: int,
     *     issues: array<int, string>
     * }
     */
    public function analyze(Section $section): array
    {
        $questions = $section->relationLoaded('questions')
            ? $section->questions
            : $section->questions()->get(['id', 'difficulty', 'points']);

        return $this->analyzeQuestions($questions);
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @return array{
     *     balanced: bool,
     *     total: int,
     *     counts: array{easy: int, medium: int, hard: int, unset: int},
     *     shares: array{easy: int, medium: int, hard: int},
     *     max_score: int,
     *     issues: array<int, string>
     * }
     */
    public function analyzeQuestions(Collection $questions): array
    {
        $counts = [
            'easy' => 0,
            'medium' => 0,
            'hard' => 0,
            'unset' => 0,
        ];

        $maxScore = 0;

        foreach ($questions as $question) {
            $difficulty = $question->difficulty;
            if (isset($counts[$difficulty])) {
                $counts[$difficulty]++;
            } else {
                $counts['unset']++;
            }

            $maxScore += (int) ($question->points ?: QuestionScoring::basePoints($difficulty));
        }

        $total = $questions->count();
        $issues = [];

        if ($total < self::MIN_QUESTIONS) {
            $issues[] = 'La evaluación necesita al menos '.self::MIN_QUESTIONS.' preguntas (hoy hay '.$total.').';
        }

        if ($counts['unset'] > 0) {
            $issues[] = 'Todas las preguntas deben tener dificultad (Fácil, Media o Difícil). Faltan '.$counts['unset'].'.';
        }

        foreach (Question::DIFFICULTIES as $value => $label) {
            if ($counts[$value] < 1) {
                $issues[] = 'Incluye al menos una pregunta '.$label.'.';
            }
        }

        $shares = [
            'easy' => 0,
            'medium' => 0,
            'hard' => 0,
        ];

        if ($total > 0) {
            foreach (array_keys($shares) as $level) {
                $shares[$level] = (int) round(($counts[$level] / $total) * 100);

                if (($counts[$level] / $total) > self::MAX_SHARE) {
                    $issues[] = 'Hay demasiadas preguntas '.Question::DIFFICULTIES[$level].' ('.$shares[$level].'%). Ninguna dificultad debe superar el 50%.';
                }
            }
        }

        return [
            'balanced' => $issues === [],
            'total' => $total,
            'counts' => $counts,
            'shares' => $shares,
            'max_score' => $maxScore,
            'issues' => $issues,
        ];
    }

    public function assertReadyToPlay(Section $section): void
    {
        $report = $this->analyze($section);

        if ($report['balanced']) {
            return;
        }

        throw ValidationException::withMessages([
            'section_id' => $report['issues'],
        ]);
    }
}
