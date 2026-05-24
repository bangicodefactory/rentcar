<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(1);
        $end   = now()->addDays(4);

        return [
            'booking_id'        => $this->faker->unique()->numberBetween(1000, 9999),
            'vehicle'           => Vehicle::factory(),
            'driver'            => User::factory(),
            'start_date'        => $start->format('Y-m-d'),
            'start_time'        => '09:00',
            'end_date'          => $end->format('Y-m-d'),
            'end_time'          => '18:00',
            'pickup_address'    => Place::factory(),
            'drop_off_address'  => Place::factory(),
            'status'            => 'yet_to_start',
            'amount'            => 300,
            'payment_status'    => 'impaye',
            'payment_method'    => 'Espece',
            'payment_notes'     => null,
            'parent_id'         => 0,
            'addon'             => null,
            'details'           => null,
            'notes'             => null,
            'vehicle_details'   => null,
            'discount'          => 0,
            'daily_price_final' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['payment_status' => 'paye']);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn () => ['payment_status' => 'partiellement_paye']);
    }

    public function onGoing(): static
    {
        return $this->state(fn () => ['status' => 'on_going']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }

    public function withRentalAgreement(): static
    {
        return $this->afterCreating(function (\App\Models\Booking $booking) {
            \App\Models\RentalAgreement::factory()->create([
                'driver'  => $booking->driver,
                'vehicle' => $booking->vehicle,
            ]);
        });
    }
}
