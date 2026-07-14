<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory de User para tests y seeds.
 *
 * Estados útiles: ->teacher(), ->admin(), ->unverified()
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Password cacheado para no hashear en cada instancia. */
    protected static ?string $password;

    /**
     * Estado por defecto: maestro verificado con password "password".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_TEACHER,
        ];
    }

    /** Usuario sin email verificado. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Usuario con rol teacher. */
    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_TEACHER,
        ]);
    }

    /** Usuario con rol admin. */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
