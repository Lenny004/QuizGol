<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Matemáticas', 'slug' => 'matematicas'],
            ['name' => 'Lenguaje', 'slug' => 'lenguaje'],
            ['name' => 'Ciencias', 'slug' => 'ciencias'],
            ['name' => 'Estudios Sociales', 'slug' => 'estudios-sociales'],
        ];

        $gradeIds = Grade::query()->pluck('id');

        foreach ($subjects as $subjectData) {
            $subject = Subject::updateOrCreate(
                ['slug' => $subjectData['slug']],
                [
                    'name' => $subjectData['name'],
                    'is_active' => true,
                ]
            );

            if ($gradeIds->isNotEmpty()) {
                $subject->grades()->sync($gradeIds);
            }
        }
    }
}
