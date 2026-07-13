<?php

namespace Database\Seeders;

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

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(
                ['slug' => $subject['slug']],
                [
                    'name' => $subject['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}

