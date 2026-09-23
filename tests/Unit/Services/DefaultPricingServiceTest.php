<?php

namespace Tests\Unit\Services;

use App\Services\DefaultPricingService;
use PHPUnit\Framework\TestCase;

class DefaultPricingServiceTest extends TestCase
{
    private DefaultPricingService $service;

    protected function setUp(): void
    {
        // 14 = today's default rule: up to 14 min late is free, 15+ adds a day.
        $this->service = new DefaultPricingService(14);
    }

    public function test_multi_day_with_partial_hour_rounds_up(): void
    {
        // 2 days + 3 hours → considerDays = 3
        $result = $this->service->calculateVehicleRate(
            100.0,
            '2024-01-01 09:00:00',
            '2024-01-03 12:00:00',
        );

        $this->assertSame(3, $result['considerDays']);
        $this->assertSame('300', $result['totalRate']);
    }

    public function test_exact_days_no_overflow(): void
    {
        // Exactly 2 days, 0 hours, 0 minutes → considerDays = 2
        $result = $this->service->calculateVehicleRate(
            50.0,
            '2024-01-01 10:00:00',
            '2024-01-03 10:00:00',
        );

        $this->assertSame(2, $result['considerDays']);
        $this->assertSame('100', $result['totalRate']);
    }

    public function test_days_with_15_minute_overflow_rounds_up(): void
    {
        // 1 day + 0 hours + 15 minutes → considerDays = 2
        $result = $this->service->calculateVehicleRate(
            80.0,
            '2024-01-01 10:00:00',
            '2024-01-02 10:15:00',
        );

        $this->assertSame(2, $result['considerDays']);
        $this->assertSame('160', $result['totalRate']);
    }

    public function test_sub_day_rental_charges_one_day(): void
    {
        // 0 days, 5 hours → considerDays = 1
        $result = $this->service->calculateVehicleRate(
            75.0,
            '2024-01-01 08:00:00',
            '2024-01-01 13:00:00',
        );

        $this->assertSame(1, $result['considerDays']);
        $this->assertSame('75', $result['totalRate']);
    }

    public function test_result_contains_expected_keys(): void
    {
        $result = $this->service->calculateVehicleRate(
            100.0,
            '2024-01-01 00:00:00',
            '2024-01-02 00:00:00',
        );

        $this->assertArrayHasKey('considerDays', $result);
        $this->assertArrayHasKey('totalDays',    $result);
        $this->assertArrayHasKey('totalHours',   $result);
        $this->assertArrayHasKey('totalMinuts',  $result);
        $this->assertArrayHasKey('totalRate',    $result);
    }

    public function test_total_rate_has_no_thousands_separator(): void
    {
        // 10 days at 1500/day = 15,000 → must come back as '15000' not '15,000'
        $result = $this->service->calculateVehicleRate(
            1500.0,
            '2024-01-01 00:00:00',
            '2024-01-11 00:00:00',
        );

        $this->assertStringNotContainsString(',', $result['totalRate']);
        $this->assertSame('15000', $result['totalRate']);
    }

    // ── late-return allowance (client.late_return_grace_minutes) ────────────

    /** @return array<string, array{int, string, int}> grace, end, expected considerDays (start 2024-01-01 08:00) */
    public static function graceCases(): array
    {
        return [
            'default: 14 min late is free'   => [14,  '2024-01-08 08:14:00', 7],
            'default: 15 min late adds a day' => [14,  '2024-01-08 08:15:00', 8],
            'default: 1 h late adds a day'    => [14,  '2024-01-08 09:00:00', 8],
            '7 h: 6h59 late is free'         => [420, '2024-01-08 14:59:00', 7],
            '7 h: exactly 7h00 late is free' => [420, '2024-01-08 15:00:00', 7],
            '7 h: 7h01 late adds a day'      => [420, '2024-01-08 15:01:00', 8],
            '7 h: 1 day + 5 h is 1 day'      => [420, '2024-01-02 13:00:00', 1],
            '7 h: under a day is still 1'    => [420, '2024-01-01 10:00:00', 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('graceCases')]
    public function test_late_return_allowance(int $grace, string $end, int $expectedDays): void
    {
        $result = (new DefaultPricingService($grace))->calculateVehicleRate(100.0, '2024-01-01 08:00:00', $end);

        $this->assertSame($expectedDays, $result['considerDays']);
        $this->assertSame((string) ($expectedDays * 100), $result['totalRate']);
    }
}
