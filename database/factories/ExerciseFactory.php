<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['tarea', 'examen', 'proyecto', 'cuestionario']),
            'max_grade' => 10,
            'published_at' => now()->subDays(fake()->numberBetween(1, 20)),
            'due_date' => now()->addDays(fake()->numberBetween(-5, 30)),
        ];
    }

    // Borrador: los alumnos no lo ven
    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    // Programado para el futuro: tampoco visible todavía
    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDays(3)]);
    }
}
