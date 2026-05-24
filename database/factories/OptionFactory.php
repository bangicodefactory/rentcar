<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Option>
 */
class OptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->words(2, true),
            'parent_id' => 0,
        ];
    }
}
