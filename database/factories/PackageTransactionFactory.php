<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackageTransaction>
 */
class PackageTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                       => User::factory(),
            'subscription_id'               => Subscription::factory(),
            'subscription_transactions_id'  => null,
            'amount'                        => $this->faker->randomFloat(2, 9, 199),
            'transaction_id'                => 'txn_' . Str::random(24),
            'payment_status'                => 'Success',
            'payment_type'                  => 'stripe',
            'receipt'                       => null,
            'holder_name'                   => $this->faker->name(),
            'card_number'                   => '4242',
            'card_expiry_month'             => $this->faker->numberBetween(1, 12),
            'card_expiry_year'              => now()->addYears(2)->year,
        ];
    }

    public function paypal(): static
    {
        return $this->state(fn () => [
            'payment_type'  => 'paypal',
            'transaction_id'=> 'PAY-' . strtoupper(Str::random(20)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['payment_status' => 'Failed']);
    }
}
