<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Addon>
 */
class AddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => $this->faker->unique()->words(2, true),
            'price'        => $this->faker->randomFloat(2, 5, 100),
            'billing_type' => $this->faker->randomElement(['daily', 'total']),
            'parent_id'    => 0,
        ];
    }

    public function daily(): static
    {
        return $this->state(fn () => ['billing_type' => 'daily']);
    }

    public function total(): static
    {
        return $this->state(fn () => ['billing_type' => 'total']);
    }
}
