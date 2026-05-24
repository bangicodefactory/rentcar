<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'      => $this->faker->unique()->word(),
            'notes'     => $this->faker->optional()->sentence(),
            'parent_id' => 0,
        ];
    }
}
