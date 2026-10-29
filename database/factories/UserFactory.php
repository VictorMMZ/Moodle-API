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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
           'username' => fake()->unique()->userName(),
           'first_name' => fake()->firstName(),
           'last_name' => fake()->lastName(),
           'email' => fake()->unique()->safeEmail(),
           'password' => static::$password ??= Hash::make('password'),
           'role' => 'student',
           'id_number' => fake()->unique()->numerify('########'),
           'avatar' => null,
           'is_active' => true,
           'last_login_at' => now(),
        ];
    }

    public function teacher(): static
    {
        return $this->state(fn () => ['role' => 'teacher']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
