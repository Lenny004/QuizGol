<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\QuestionScoring;
use Illuminate\Database\Seeder;

/**
 * Sección demo de matemáticas con 4 preguntas de sumas/restas/multiplicación.
 *
 * Solo corre si existen el maestro demo, la materia y el grado 3° Primaria.
 * Si la sección ya tiene preguntas, no las duplica.
 */
class DemoQuizSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->where('email', 'maestro@quizgol.test')->first();
        $mathSubject = Subject::query()->where('slug', 'matematicas')->first();
        $thirdGrade = Grade::query()->where('slug', '3-primaria')->first();

        if (! $teacher || ! $mathSubject || ! $thirdGrade) {
            return;
        }

        $section = Section::updateOrCreate(
            [
                'user_id' => $teacher->id,
                'title' => 'Sumas y restas — Demo',
            ],
            [
                'subject_id' => $mathSubject->id,
                'grade_id' => $thirdGrade->id,
            ]
        );

        // Si ya hay banco, alinea puntos/dificultad para que la demo quede equilibrada.
        if ($section->questions()->exists()) {
            $this->alignExistingDemo($section);

            return;
        }

        $questionBank = [
            [
                'prompt' => '¿Cuánto es 12 + 8?',
                'difficulty' => 'easy',
                'answers' => ['18', '20', '19', '22'],
                'correct' => 1,
            ],
            [
                'prompt' => '¿Cuánto es 25 − 7?',
                'difficulty' => 'easy',
                'answers' => ['16', '17', '18', '19'],
                'correct' => 2,
            ],
            [
                'prompt' => 'Si tienes 3 cajas con 4 manzanas cada una, ¿cuántas manzanas hay en total?',
                'difficulty' => 'medium',
                'answers' => ['7', '12', '9', '16'],
                'correct' => 1,
            ],
            [
                'prompt' => 'Un entrenador reparte 36 balones en 4 equipos iguales. ¿Cuántos recibe cada uno?',
                'difficulty' => 'hard',
                'answers' => ['8', '9', '6', '12'],
                'correct' => 1,
            ],
        ];

        foreach ($questionBank as $index => $item) {
            $question = $section->questions()->create([
                'prompt' => $item['prompt'],
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'difficulty' => $item['difficulty'],
                'time_limit' => 30,
                'points' => QuestionScoring::basePoints($item['difficulty']),
                'sort_order' => $index,
            ]);

            foreach ($item['answers'] as $answerIndex => $answerText) {
                $question->answers()->create([
                    'text' => $answerText,
                    'is_correct' => $answerIndex === $item['correct'],
                    'sort_order' => $answerIndex,
                ]);
            }
        }
    }

    private function alignExistingDemo(Section $section): void
    {
        $questions = $section->questions()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($questions as $question) {
            if (in_array($question->difficulty, ['easy', 'medium', 'hard'], true)) {
                $question->update([
                    'points' => QuestionScoring::basePoints($question->difficulty),
                ]);
            }
        }

        $hasHard = $questions->contains(fn (Question $question) => $question->difficulty === 'hard');

        if (! $hasHard) {
            $candidate = $questions->last();
            if ($candidate) {
                $candidate->update([
                    'difficulty' => 'hard',
                    'points' => QuestionScoring::basePoints('hard'),
                ]);
            }
        }
    }
}
