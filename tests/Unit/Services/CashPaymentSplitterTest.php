<?php

namespace Tests\Unit\Services;

use App\Services\CashPaymentSplitter;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CashPaymentSplitterTest extends TestCase
{
    private CashPaymentSplitter $service;

    protected function setUp(): void
    {
        $this->service = new CashPaymentSplitter();
    }

    private function plan(float $amount, string $start, string $end, int $days, float $max = 5000.0): array
    {
        return $this->service->plan($amount, Carbon::parse($start), Carbon::parse($end), $days, $max);
    }

    // --- Amount splitting -----------------------------------------------------

    public function test_thirteen_thousand_splits_into_5000_5000_3000(): void
    {
        $plan = $this->plan(13000, '2026-07-01', '2026-07-15', 10);

        $this->assertCount(3, $plan);
        $this->assertSame([5000.0, 5000.0, 3000.0], array_column($plan, 'amount'));
    }

    public function test_exact_multiple_has_no_remainder_receipt(): void
    {
        $plan = $this->plan(10000, '2026-07-01', '2026-07-15', 10);

        $this->assertCount(2, $plan);
        $this->assertSame([5000.0, 5000.0], array_column($plan, 'amount'));
    }

    public function test_amount_at_cap_is_single_receipt(): void
    {
        $plan = $this->plan(5000, '2026-07-01', '2026-07-10', 5);

        $this->assertCount(1, $plan);
        $this->assertSame(5000.0, $plan[0]['amount']);
    }

    public function test_tiny_remainder_is_its_own_receipt(): void
    {
        $plan = $this->plan(5000.01, '2026-07-01', '2026-07-10', 5);

        $this->assertCount(2, $plan);
        $this->assertSame([5000.0, 0.01], array_column($plan, 'amount'));
    }

    public function test_decimal_amount_sums_back_exactly(): void
    {
        $plan = $this->plan(12500.50, '2026-07-01', '2026-07-15', 10);

        $this->assertCount(3, $plan);
        $this->assertSame([5000.0, 5000.0, 2500.50], array_column($plan, 'amount'));
        $this->assertEqualsWithDelta(12500.50, array_sum(array_column($plan, 'amount')), 0.001);
    }

    // --- Day apportioning -----------------------------------------------------

    public function test_days_sum_to_total_when_days_exceed_receipts(): void
    {
        $plan = $this->plan(13000, '2026-07-01', '2026-07-15', 10);

        $this->assertSame(10, array_sum(array_column($plan, 'days')));
        foreach ($plan as $r) {
            $this->assertGreaterThanOrEqual(1, $r['days']);
        }
        // Larger receipts get at least as many days as the smaller remainder.
        $this->assertGreaterThanOrEqual($plan[2]['days'], $plan[0]['days']);
    }

    public function test_each_receipt_gets_at_least_one_day_when_days_below_receipts(): void
    {
        // 15000 → 3 receipts but only 2 rental days.
        $plan = $this->plan(15000, '2026-07-01', '2026-07-03', 2);

        $this->assertCount(3, $plan);
        foreach ($plan as $r) {
            $this->assertSame(1, $r['days']);
        }
    }

    // --- Dates ----------------------------------------------------------------

    public function test_dates_are_consecutive_from_the_start_date(): void
    {
        // Receipts are one day apart from the rental start, not spread across
        // the whole period (a 2-week rental no longer ends on 07-15).
        $plan  = $this->plan(13000, '2026-07-01', '2026-07-15', 10);
        $dates = array_column($plan, 'date');

        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $dates);
    }

    public function test_five_receipts_on_a_month_rental_take_the_first_five_days(): void
    {
        // #BOK-0002099 shape: 21,600 DH over Aug 3 → Sep 3 (31 days).
        $plan = $this->plan(21600, '2026-08-03', '2026-09-03', 31);

        $this->assertSame(
            ['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'],
            array_column($plan, 'date'),
        );
        $this->assertSame([7, 7, 7, 7, 3], array_column($plan, 'days'));
    }

    public function test_short_period_falls_back_to_consecutive_days(): void
    {
        // 3 receipts but a 1-day rental window (start == end): can't spread.
        $plan  = $this->plan(13000, '2026-07-01', '2026-07-01', 3);
        $dates = array_column($plan, 'date');

        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $dates);
        $this->assertSame($dates, array_values(array_unique($dates)));
    }

    public function test_dates_distinct_when_period_just_barely_fits(): void
    {
        // 3 receipts, exactly 2-day span (07-01..07-03) → distinct.
        $plan  = $this->plan(13000, '2026-07-01', '2026-07-03', 6);
        $dates = array_column($plan, 'date');

        $this->assertSame(['2026-07-01', '2026-07-02', '2026-07-03'], $dates);
    }
}
