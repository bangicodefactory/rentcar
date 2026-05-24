<?php

namespace Tests\Unit\Services;

use App\Services\DefaultTvaService;
use PHPUnit\Framework\TestCase;

class DefaultTvaServiceTest extends TestCase
{
    private DefaultTvaService $service;

    protected function setUp(): void
    {
        $this->service = new DefaultTvaService();
    }

    public function test_standard_ttc_splits_correctly(): void
    {
        $result = $this->service->computeFromTtc(120.0);

        $this->assertSame(100.0, $result['total_ht']);
        $this->assertSame(20.0,  $result['tva']);
        $this->assertSame(0.20,  $result['tva_rate']);
    }

    public function test_zero_amount_returns_zeros(): void
    {
        $result = $this->service->computeFromTtc(0.0);

        $this->assertSame(0.0, $result['total_ht']);
        $this->assertSame(0.0, $result['tva']);
        $this->assertSame(0.20, $result['tva_rate']);
    }

    public function test_ht_plus_tva_equals_ttc(): void
    {
        // Floating-point rounding: HT + TVA must reconstitute TTC within 1 cent.
        foreach ([100.0, 99.99, 1234.56, 7.0] as $ttc) {
            $result = $this->service->computeFromTtc($ttc);
            $this->assertEqualsWithDelta(
                $ttc,
                $result['total_ht'] + $result['tva'],
                0.01,
                "HT + TVA should equal TTC for input {$ttc}",
            );
        }
    }

    public function test_tva_rate_is_returned(): void
    {
        $result = $this->service->computeFromTtc(60.0);

        $this->assertArrayHasKey('tva_rate', $result);
        $this->assertSame(0.20, $result['tva_rate']);
    }
}
