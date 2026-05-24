<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credit>
 */
class CreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'driver_id'   => User::factory(),
            'amount'      => $this->faker->randomFloat(2, 10, 500),
            'status'      => 'non payé',
            'credit_date' => now()->format('Y-m-d'),
            'parent_id'   => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'payé']);
    }
}
