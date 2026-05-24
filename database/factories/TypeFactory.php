<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Type>
 */
class TypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'     => $this->faker->unique()->words(2, true),
            'type'      => $this->faker->randomElement(['invoice', 'expense', 'issue', 'maintainer_type']),
            'parent_id' => 0,
        ];
    }
}
