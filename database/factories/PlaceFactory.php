<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => $this->faker->unique()->city(),
            'city'         => $this->faker->city(),
            'island'       => null,
            'price'        => $this->faker->randomFloat(2, 0, 50),
            'parent_id'    => 0,
            'depo_name'    => null,
            'depo_address' => null,
        ];
    }
}
