<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'exercise_id' => Exercise::factory(),
            'student_id' => User::factory(),
            'content' => fake()->paragraphs(2, true),
            'file_path' => null,
            'status' => 'submitted',
            'submitted_at' => now()->subDays(fake()->numberBetween(0, 5)),
            'grade' => null,
            'feedback' => null,
            'graded_at' => null,
        ];
    }

    public function graded(float $maxGrade = 10): static
    {
        return $this->state(fn () => [
            'status' => 'graded',
            'grade' => fake()->randomFloat(2, $maxGrade * 0.3, $maxGrade),
            'feedback' => fake()->sentence(),
            'graded_at' => now(),
        ]);
    }
}
