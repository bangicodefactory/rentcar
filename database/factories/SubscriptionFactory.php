<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'                   => $this->faker->unique()->words(3, true),
            'package_amount'          => $this->faker->randomFloat(2, 9, 199),
            'interval'                => $this->faker->randomElement(['Monthly', 'Quarterly', 'Yearly']),
            'user_limit'              => $this->faker->numberBetween(1, 20),
            'driver_limit'            => $this->faker->numberBetween(1, 50),
            'enabled_logged_history'  => 1,
        ];
    }
}
