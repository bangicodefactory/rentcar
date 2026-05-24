<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inspection>
 */
class InspectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle'               => Vehicle::factory(),
            'meter_reading_outgoing'=> $this->faker->numberBetween(0, 100000),
            'meter_reading_incoming'=> null,
            'outgoing_date'         => now()->format('Y-m-d'),
            'outgoing_time'         => '09:00',
            'incoming_date'         => null,
            'incoming_time'         => null,
            'details'               => null,
            'notes'                 => null,
            'parent_id'             => 0,
            'status'                => 'pending',
            'inspector'             => User::factory(),
            'inspection_date'       => now()->format('Y-m-d'),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
