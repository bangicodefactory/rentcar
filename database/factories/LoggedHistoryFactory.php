<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoggedHistory>
 */
class LoggedHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'ip'        => $this->faker->ipv4(),
            'date'      => now()->format('Y-m-d'),
            'details'   => $this->faker->sentence(),
            'type'      => 'login',
            'parent_id' => 0,
        ];
    }
}
