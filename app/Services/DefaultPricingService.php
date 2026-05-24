<?php

namespace App\Services;

use App\Contracts\PricingServiceContract;
use DateTime;

class DefaultPricingService implements PricingServiceContract
{
    /**
     * Same rounding rule as the legacy vehicleRateCalculation() helper:
     * any partial hour beyond a whole day counts as an extra day.
     */
    public function calculateVehicleRate(
        float  $dailyRate,
        string $startDateTime,
        string $endDateTime,
    ): array {
        $start    = new DateTime($startDateTime);
        $end      = new DateTime($endDateTime);
        $interval = $end->diff($start);

        $days   = $interval->days;
        $hours  = $interval->h;
        $minuts = $interval->i;

        if ($days > 0 && $hours > 0) {
            $considerDays = $days + 1;
        } elseif ($days > 0 && $hours === 0 && $minuts >= 15) {
            $considerDays = $days + 1;
        } elseif ($days > 0) {
            $considerDays = $days;
        } else {
            $considerDays = 1;
        }

        $totalRate = $considerDays * $dailyRate;

        return [
            'considerDays' => $considerDays,
            'totalDays'    => $days,
            'totalHours'   => $hours,
            'totalMinuts'  => $minuts,
            'totalRate'    => str_replace(',', '', number_format($totalRate, 0)),
        ];
    }
}
