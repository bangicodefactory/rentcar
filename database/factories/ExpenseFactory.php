<?php

namespace Database\Factories;

use App\Models\ExpenseType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'     => $this->faker->words(3, true),
            'vehicle'   => Vehicle::factory(),
            'type'      => ExpenseType::factory(),
            'date'      => now()->format('Y-m-d'),
            'amount'    => $this->faker->randomFloat(2, 20, 500),
            'receipt'   => null,
            'notes'     => null,
            'parent_id' => 0,
        ];
    }
}
