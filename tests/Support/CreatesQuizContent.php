<?php

namespace Tests\Support;

use App\Models\Answer;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\QuestionScoring;

trait CreatesQuizContent
{
    /**
     * @param  array<int, string>|null  $difficulties
     * @return array{teacher: User, subject: Subject, grade: Grade, section: Section}
     */
    protected function createTeacherWithSection(int $questionCount = 3, int $timeLimit = 30, ?array $difficulties = null): array
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

        $this->addQuestionsToSection($section, $questionCount, $timeLimit, $difficulties);

        return compact('teacher', 'subject', 'grade', 'section');
    }

    /**
     * @param  array<int, string>|null  $difficulties
     */
    protected function addQuestionsToSection(Section $section, int $questionCount, int $timeLimit = 30, ?array $difficulties = null): void
    {
        $levels = $difficulties ?? ['easy', 'medium', 'hard'];
        $startingOrder = (int) $section->questions()->max('sort_order');

        for ($i = 0; $i < $questionCount; $i++) {
            $difficulty = $levels[$i % count($levels)];
            $question = Question::query()->create([
                'section_id' => $section->id,
                'prompt' => 'Pregunta '.$section->id.'-'.$i,
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'difficulty' => $difficulty,
                'time_limit' => $timeLimit,
                'points' => QuestionScoring::basePoints($difficulty),
                'sort_order' => $startingOrder + 1 + $i,
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
    }
}
