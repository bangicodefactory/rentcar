<?php

namespace Database\Factories;

use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id'                    => $this->faker->unique()->numberBetween(1000, 99999),
            'type'                          => VehicleType::factory(),
            'name'                          => $this->faker->word() . ' ' . $this->faker->word(),
            'model'                         => $this->faker->year() . ' ' . $this->faker->word(),
            'engine_type'                   => $this->faker->randomElement(['V4', 'V6', 'V8']),
            'engine_no'                     => $this->faker->bothify('ENG-####??'),
            'registration_expiry_date'      => now()->addYear()->format('Y-m-d'),
            'license_plate'                 => strtoupper($this->faker->bothify('??-####-??')),
            'document'                      => null,
            'daily_rate'                    => $this->faker->randomFloat(2, 30, 300),
            'year_of_ﬁrst_immatriculation' => $this->faker->numberBetween(2000, 2024),
            'gearbox'                       => $this->faker->randomElement(['automatic', 'manual']),
            'fuel_type'                     => $this->faker->randomElement(['essence', 'diesel', 'electric']),
            'number_of_seats'               => $this->faker->randomElement([2, 4, 5, 7]),
            'kilometers'                    => (string) $this->faker->numberBetween(0, 200000),
            'option'                        => null,
            'notes'                         => null,
            'parent_id'                     => 0,
        ];
    }
}
