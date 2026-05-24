<?php

namespace App\Contracts;

interface TvaServiceContract
{
    /**
     * Compute the TVA breakdown from a TTC (tax-inclusive) amount.
     *
     * The applicable rate is client-specific (e.g. 20 % for France/Netherlands,
     * different rates for other jurisdictions).
     *
     * @return array{
     *     total_ht: float,
     *     tva:      float,
     *     tva_rate: float
     * }
     */
    public function computeFromTtc(float $ttcAmount): array;
}
