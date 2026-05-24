<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                => $this->faker->words(2, true),
            'type'                => 'percentage',
            'rate'                => $this->faker->numberBetween(5, 50),
            'applicable_packages' => null,
            'code'                => strtoupper($this->faker->unique()->bothify('COUP-####')),
            'valid_for'           => now()->addMonths(3)->format('Y-m-d'),
            'use_limit'           => 100,
            'status'              => '1',
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn () => [
            'type' => 'fixed',
            'rate' => $this->faker->numberBetween(5, 50),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['valid_for' => now()->subDay()->format('Y-m-d')]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => '0']);
    }

    public function forPackage(int $subscriptionId): static
    {
        return $this->state(fn () => ['applicable_packages' => (string) $subscriptionId]);
    }
}
