<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Place;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(1);
        $end   = now()->addDays(4);

        return [
            'guest_id'         => Guest::factory(),
            'vehicle'          => Vehicle::factory(),
            'driver'           => null,
            'start_date'       => $start->format('Y-m-d'),
            'start_time'       => '09:00',
            'end_date'         => $end->format('Y-m-d'),
            'end_time'         => '18:00',
            'pickup_address'   => Place::factory(),
            'drop_off_address' => Place::factory(),
            'status'           => 'pending',
            'amount'           => 300,
            'payment_status'   => 'impaye',
            'payment_notes'    => null,
            'notes'            => null,
            'addon'            => null,
            'details'          => null,
            'vehicle_details'  => null,
            'parent_id'        => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }
}
