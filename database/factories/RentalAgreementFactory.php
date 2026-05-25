<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RentalAgreement>
 */
class RentalAgreementFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(1);
        $end   = now()->addDays(4);

        return [
            'agreement_id'      => $this->faker->unique()->numberBetween(1, 99999),
            'date'              => now()->format('Y-m-d H:i:s'),
            'rental_start_date' => $start->format('Y-m-d H:i:s'),
            'rental_end_date'   => $end->format('Y-m-d H:i:s'),
            'rental_duration'   => 3,
            'driver'            => User::factory(),
            'driver2'           => null,
            'vehicle'           => Vehicle::factory(),
            'terms_condition'   => 'Standard terms and conditions apply.',
            'description'       => null,
            'status'            => 'draft',
            'parent_id'         => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
