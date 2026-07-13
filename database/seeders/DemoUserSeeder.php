<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@quizgol.test'],
            [
                'name' => 'Admin QuizGol',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'maestro@quizgol.test'],
            [
                'name' => 'Maestro Demo',
                'password' => Hash::make('password'),
                'role' => 'teacher',
            ]
        );
    }
}

