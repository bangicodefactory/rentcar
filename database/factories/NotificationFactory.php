<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'module'        => $this->faker->randomElement(['new_booking', 'new_driver', 'user_create', 'new_agreement']),
            'name'          => $this->faker->words(2, true),
            'subject'       => $this->faker->sentence(),
            'message'       => $this->faker->paragraph(),
            'short_code'    => '{company_name}',
            'enabled_email' => 1,
            'enabled_sms'   => 0,
            'parent_id'     => 0,
        ];
    }
}
