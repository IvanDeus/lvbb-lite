<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => Str::lower(fake()->unique()->userName()).Str::random(4),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_staff' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function staff(): static
    {
        return $this->state(['is_staff' => true]);
    }
}
