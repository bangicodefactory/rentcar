<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Splits a cash payment that exceeds the legal ceiling into several receipts,
 * each within the cap, on consecutive days from the rental start, with the
 * rental days apportioned.
 *
 * Background: Moroccan tax law (CGI art. 193) treats cash paid above the
 * ceiling (5000 MAD) per day / per transaction as non-deductible. Rather than
 * refusing such a payment, we record it as N compliant receipts — each becomes
 * its own BookingPayment + facture (Tva) via BookingController::recordBookingPayment.
 *
 * This class is pure (no DB, no side effects) so it can be unit-tested and is
 * the single source of truth for the split — both the write path and the
 * preview endpoint call plan().
 */
class CashPaymentSplitter
{
    /**
     * Build the receipt plan for a cash payment.
     *
     * Strategy: "max-out 5000 chunks" — floor(amount / max) receipts of `max`,
     * then the remainder as a final receipt. Days are apportioned proportionally
     * to each receipt (each at least 1 day, for the per-day unit price), summed
     * back to $totalDays. Receipts are dated one day apart from $start
     * (start, start+1, ...).
     *
     * $end is kept in the signature for the callers; dates no longer use it.
     *
     * @param  float  $amount     Full cash amount (TTC) to split.
     * @param  Carbon $start      Booking start date.
     * @param  Carbon $end        Booking end date.
     * @param  int    $totalDays  Rental days the whole payment represents.
     * @param  float  $max        Legal ceiling per receipt (MAD).
     * @return array<int,array{amount:float,days:int,date:string}>
     */
    public function plan(float $amount, Carbon $start, Carbon $end, int $totalDays, float $max = 5000.0): array
    {
        $totalDays = max(1, $totalDays);

        // --- Amounts (work in integer cents so decimals sum back exactly) ---
        $amountCents = (int) round($amount * 100);
        $maxCents    = max(1, (int) round($max * 100));

        $fullChunks     = intdiv($amountCents, $maxCents);
        $remainderCents = $amountCents - $fullChunks * $maxCents;

        $chunkCents = array_fill(0, $fullChunks, $maxCents);
        if ($remainderCents > 0) {
            $chunkCents[] = $remainderCents;
        }
        // Amount at or below the cap → a single receipt (no real split).
        if (empty($chunkCents)) {
            $chunkCents = [$amountCents];
        }

        $n = count($chunkCents);

        $days  = $this->apportionDays($chunkCents, $amountCents, $totalDays, $n);
        $dates = $this->consecutiveDates($start->copy()->startOfDay(), $n);

        $plan = [];
        for ($i = 0; $i < $n; $i++) {
            $plan[] = [
                'amount' => round($chunkCents[$i] / 100, 2),
                'days'   => $days[$i],
                'date'   => $dates[$i],
            ];
        }

        return $plan;
    }

    /**
     * Apportion $totalDays across the receipts, proportional to amount, each
     * receipt getting at least 1 day. When $totalDays >= N the days sum back to
     * exactly $totalDays (largest-remainder). When $totalDays < N every receipt
     * still gets 1 day, so the sum may exceed $totalDays — an accepted nuance,
     * since a per-day invoice cannot show 0 days.
     *
     * @param  int[] $chunkCents
     * @return int[]
     */
    private function apportionDays(array $chunkCents, int $amountCents, int $totalDays, int $n): array
    {
        if ($totalDays <= $n) {
            return array_fill(0, $n, 1);
        }

        $days  = array_fill(0, $n, 1);   // base: 1 day each
        $extra = $totalDays - $n;         // remaining days to distribute

        $frac    = [];
        $floors  = 0;
        for ($i = 0; $i < $n; $i++) {
            $exact      = $extra * ($chunkCents[$i] / $amountCents);
            $base       = (int) floor($exact);
            $days[$i]  += $base;
            $floors    += $base;
            $frac[$i]   = ['i' => $i, 'frac' => $exact - $base, 'cents' => $chunkCents[$i]];
        }

        // Hand the leftover units to the largest fractional parts (ties → the
        // larger receipt), so the total lands on exactly $totalDays.
        $leftover = $extra - $floors;
        usort($frac, fn ($a, $b) => ($b['frac'] <=> $a['frac']) ?: ($b['cents'] <=> $a['cents']));
        for ($k = 0; $k < $leftover; $k++) {
            $days[$frac[$k]['i']] += 1;
        }

        return $days;
    }

    /**
     * N receipt dates one day apart, starting on $start.
     *
     * @return string[] Y-m-d dates.
     */
    private function consecutiveDates(Carbon $start, int $n): array
    {
        $dates = [];
        for ($i = 0; $i < max(1, $n); $i++) {
            $dates[] = $start->copy()->addDays($i)->toDateString();
        }

        return $dates;
    }
}
