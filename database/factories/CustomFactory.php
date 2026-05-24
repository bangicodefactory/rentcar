<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stub factory for the utility Custom model.
 * Custom has no database columns of its own; this factory exists only to
 * satisfy the HasFactory contract if it is ever added to the model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Custom>
 */
class CustomFactory extends Factory
{
    public function definition(): array
    {
        return [];
    }
}
