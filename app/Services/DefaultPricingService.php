<?php

namespace App\Services;

use App\Contracts\PricingServiceContract;

class DefaultPricingService implements PricingServiceContract
{
    /**
     * @param int|null $graceMinutes Late-return allowance; null reads
     *                               client.late_return_grace_minutes.
     */
    public function __construct(private ?int $graceMinutes = null)
    {
    }

    /**
     * Same rounding rule as the legacy vehicleRateCalculation() helper, which
     * owns it: time past the last whole day beyond the client's late-return
     * allowance counts as an extra day.
     */
    public function calculateVehicleRate(
        float  $dailyRate,
        string $startDateTime,
        string $endDateTime,
    ): array {
        return vehicleRateCalculation($dailyRate, $startDateTime, $endDateTime, $this->graceMinutes);
    }
}
