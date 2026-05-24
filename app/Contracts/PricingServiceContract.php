<?php

namespace App\Contracts;

interface PricingServiceContract
{
    /**
     * Calculate the rental rate for a vehicle given a daily rate and date range.
     *
     * The rounding rule (partial day = full day, etc.) is client-specific and
     * lives in the concrete implementation.
     *
     * @return array{
     *     considerDays: int,
     *     totalDays: int,
     *     totalHours: int,
     *     totalMinuts: int,
     *     totalRate: string
     * }
     */
    public function calculateVehicleRate(
        float  $dailyRate,
        string $startDateTime,
        string $endDateTime,
    ): array;
}
