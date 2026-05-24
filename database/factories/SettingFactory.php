<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->slug(2),
            'value'     => $this->faker->word(),
            'type'      => null,
            'parent_id' => 1,
        ];
    }
}
