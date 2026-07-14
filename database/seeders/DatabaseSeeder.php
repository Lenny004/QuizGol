<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal: catálogo (grados/materias) + datos demo para probar el juego.
 *
 * Orden importa: primero grados, luego materias (sincronizan grados),
 * después usuarios demo y por último el quiz de ejemplo.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Ejecuta los seeders de QuizGol en orden.
     */
    public function run(): void
    {
        $this->call([
            GradeSeeder::class,
            SubjectSeeder::class,
            DemoUserSeeder::class,
            DemoQuizSeeder::class,
        ]);
    }
}
