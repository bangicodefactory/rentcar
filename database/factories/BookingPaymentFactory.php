<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookingPayment>
 */
class BookingPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id'     => Booking::factory(),
            'amount'         => $this->faker->randomFloat(2, 50, 500),
            'date'           => now()->format('Y-m-d'),
            'payment_method' => $this->faker->randomElement(['Espece', 'Virement bancaire', 'Carte']),
            'notes'          => null,
            'parent_id'      => 0,
        ];
    }
}
