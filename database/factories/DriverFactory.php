<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'driver_id'       => $this->faker->unique()->numerify('DR-####'),
            'user_id'         => User::factory()->driver(),
            'gender'          => $this->faker->randomElement(['Male', 'Female']),
            'age'             => $this->faker->numberBetween(18, 65),
            'address'         => $this->faker->address(),
            'birth_date'      => $this->faker->date('Y-m-d', '-20 years'),
            'license_number'  => $this->faker->bothify('LIC-######'),
            'issue_date'      => now()->subYears(2)->format('Y-m-d'),
            'expiration_date' => now()->addYears(3)->format('Y-m-d'),
            'document'        => null,
            'license'         => null,
            'reference'       => $this->faker->optional()->bothify('REF-####'),
            'parent_id'       => 0,
            'notes'           => null,
            'document_1'      => null,
            'license_1'       => null,
            'ICE_company'     => null,
        ];
    }
}
