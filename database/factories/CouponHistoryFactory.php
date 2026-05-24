<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CouponHistory>
 */
class CouponHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'coupon'  => Coupon::factory(),
            'package' => Subscription::factory(),
            'user_id' => User::factory(),
            'date'    => now()->format('Y-m-d'),
        ];
    }
}
