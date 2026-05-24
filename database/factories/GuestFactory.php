<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Guest>
 */
class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => $this->faker->name(),
            'email'        => $this->faker->unique()->safeEmail(),
            'type'         => 'client',
            'phone_number' => $this->faker->phoneNumber(),
            'lang'         => 'en',
            'parent_id'    => 0,
            'is_active'    => 1,
            'company_name' => null,
            'city'         => $this->faker->city(),
        ];
    }
}
