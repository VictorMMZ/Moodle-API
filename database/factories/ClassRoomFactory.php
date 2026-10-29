<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassRoomFactory extends Factory
{
	protected $model = ClassRoom::class;

	public function definition(): array
	{
		return [
			'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'code' => fake()->unique()->bothify('???-####'),
            'is_active' => true,
            'teacher_id' => \App\Models\User::factory()->teacher(),
		];
	}
}
