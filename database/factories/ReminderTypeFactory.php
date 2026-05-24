<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReminderType>
 */
class ReminderTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'      => $this->faker->unique()->words(2, true),
            'parent_id' => 0,
        ];
    }
}
