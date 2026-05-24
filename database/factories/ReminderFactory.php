<?php

namespace Database\Factories;

use App\Models\ReminderType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_vehicle'       => Vehicle::factory(),
            'parent_id'        => 0,
            'name'             => $this->faker->words(3, true),
            'note'             => $this->faker->optional()->sentence(),
            'reminder_date'    => now()->addDays(30)->format('Y-m-d'),
            'status'           => 'pending',
            'reminder_type_id' => ReminderType::factory(),
        ];
    }
}
