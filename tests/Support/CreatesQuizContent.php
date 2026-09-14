<?php

namespace Tests\Support;

use App\Models\Answer;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;

trait CreatesQuizContent
{
    protected function createTeacherWithSection(int $questionCount = 2, int $timeLimit = 30): array
    {
        $teacher = User::factory()->teacher()->create();

        $subject = Subject::query()->create([
            'name' => 'Matemáticas',
            'slug' => 'matematicas-'.uniqid(),
            'is_active' => true,
        ]);

        $grade = Grade::query()->create([
            'name' => '3° Primaria',
            'slug' => '3-primaria-'.uniqid(),
            'level_order' => 3,
            'is_active' => true,
        ]);

        $subject->grades()->attach($grade->id);

        $section = Section::query()->create([
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'grade_id' => $grade->id,
            'title' => 'Sección test',
        ]);

        for ($i = 0; $i < $questionCount; $i++) {
            $question = Question::query()->create([
                'section_id' => $section->id,
                'prompt' => "Pregunta {$i}",
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'difficulty' => 'easy',
                'time_limit' => $timeLimit,
                'points' => 1000,
                'sort_order' => $i,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $index => $letter) {
                Answer::query()->create([
                    'question_id' => $question->id,
                    'text' => "Opción {$letter}",
                    'is_correct' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        return compact('teacher', 'subject', 'grade', 'section');
    }
}
