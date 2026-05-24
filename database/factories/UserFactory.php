<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'             => $this->faker->name(),
            'email'            => $this->faker->unique()->safeEmail(),
            'email_verified_at'=> now(),
            'password'         => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'   => Str::random(10),
            'type'             => 'employee',
            'phone_number'     => $this->faker->phoneNumber(),
            'lang'             => 'en',
            'is_active'        => 1,
            'parent_id'        => 0,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['type' => 'admin']);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['type' => 'super admin']);
    }

    public function driver(): static
    {
        return $this->state(fn () => ['type' => 'driver']);
    }
}
