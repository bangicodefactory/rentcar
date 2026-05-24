<?php

namespace App\Services;

use App\Contracts\TvaServiceContract;

class DefaultTvaService implements TvaServiceContract
{
    protected float $rate = 0.20;

    /**
     * Back-calculate HT and TVA from a TTC amount at the configured rate.
     * Formula: total_ht = ttc / (1 + rate), tva = ttc - total_ht.
     */
    public function computeFromTtc(float $ttcAmount): array
    {
        $totalHt = round($ttcAmount / (1 + $this->rate), 2);
        $tva     = round($ttcAmount - $totalHt, 2);

        return [
            'total_ht' => $totalHt,
            'tva'      => $tva,
            'tva_rate' => $this->rate,
        ];
    }
}
