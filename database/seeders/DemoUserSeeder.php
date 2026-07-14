<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de prueba para desarrollo local.
 *
 * admin@quizgol.test / password  (rol admin)
 * maestro@quizgol.test / password (rol teacher)
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@quizgol.test'],
            [
                'name' => 'Admin QuizGol',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'maestro@quizgol.test'],
            [
                'name' => 'Maestro Demo',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER,
            ]
        );
    }
}
