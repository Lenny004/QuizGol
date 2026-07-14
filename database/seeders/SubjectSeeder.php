<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Carga materias base y las vincula a todos los grados existentes.
 */
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

            // En el MVP cada materia está disponible en todos los grados.
            if ($gradeIds->isNotEmpty()) {
                $subject->grades()->sync($gradeIds);
            }
        }
    }
}
