<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseType>
 */
class ExpenseTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'     => $this->faker->unique()->words(2, true),
            'parent_id' => 0,
        ];
    }
}
