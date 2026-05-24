<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Signature>
 */
class SignatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'signature_path' => 'signatures/' . $this->faker->uuid() . '.png',
        ];
    }
}
