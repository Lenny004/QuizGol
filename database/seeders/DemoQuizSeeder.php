<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoQuizSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->where('email', 'maestro@quizgol.test')->first();
        $math = Subject::query()->where('slug', 'matematicas')->first();
        $grade = Grade::query()->where('slug', '3-primaria')->first();

        if (! $teacher || ! $math || ! $grade) {
            return;
        }

        $section = Section::updateOrCreate(
            [
                'user_id' => $teacher->id,
                'title' => 'Sumas y restas — Demo',
            ],
            [
                'subject_id' => $math->id,
                'grade_id' => $grade->id,
            ]
        );

        if ($section->questions()->exists()) {
            return;
        }

        $bank = [
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
                'prompt' => '¿Cuál es el resultado de 9 × 3?',
                'difficulty' => 'medium',
                'answers' => ['27', '21', '24', '30'],
                'correct' => 0,
            ],
        ];

        foreach ($bank as $index => $item) {
            $question = $section->questions()->create([
                'prompt' => $item['prompt'],
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'difficulty' => $item['difficulty'],
                'time_limit' => 30,
                'points' => 1000,
                'sort_order' => $index,
            ]);

            foreach ($item['answers'] as $answerIndex => $text) {
                $question->answers()->create([
                    'text' => $text,
                    'is_correct' => $answerIndex === $item['correct'],
                    'sort_order' => $answerIndex,
                ]);
            }
        }
    }
}
