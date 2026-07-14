<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

/**
 * Carga los grados escolares (1° Primaria … 9° / 3° Ciclo).
 */
class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['name' => '1° Primaria', 'slug' => '1-primaria', 'level_order' => 1],
            ['name' => '2° Primaria', 'slug' => '2-primaria', 'level_order' => 2],
            ['name' => '3° Primaria', 'slug' => '3-primaria', 'level_order' => 3],
            ['name' => '4° Primaria', 'slug' => '4-primaria', 'level_order' => 4],
            ['name' => '5° Primaria', 'slug' => '5-primaria', 'level_order' => 5],
            ['name' => '6° Primaria', 'slug' => '6-primaria', 'level_order' => 6],
            ['name' => '7° / 1° Ciclo', 'slug' => '7-ciclo', 'level_order' => 7],
            ['name' => '8° / 2° Ciclo', 'slug' => '8-ciclo', 'level_order' => 8],
            ['name' => '9° / 3° Ciclo', 'slug' => '9-ciclo', 'level_order' => 9],
        ];

        foreach ($grades as $gradeData) {
            Grade::updateOrCreate(
                ['slug' => $gradeData['slug']],
                [
                    'name' => $gradeData['name'],
                    'level_order' => $gradeData['level_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
