<?php

namespace Tests\Unit\Services;

use App\Models\Tva;
use App\Services\TvaRenumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * Renumbering must never move a SENT invoice (handed to a client). The other
 * invoices take the free numbers around them, in date order. When that's
 * impossible without breaking date order or leaving a gap, the preview reports
 * it and apply refuses. DB-backed like TvaRenumberServiceTest.
 */
class TvaRenumberAroundSentTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    private TvaRenumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');
        $this->service = new TvaRenumberService();
    }

    private function inv(string $date, string $number, bool $sent = false): Tva
    {
        return Tva::factory()->withInvoice()->create([
            'facture_date' => $date, 'facture_number' => $number, 'sent_at' => $sent ? now() : null,
        ]);
    }

    private function numbers(): array
    {
        return Tva::whereYear('facture_date', 2026)->orderBy('facture_date')->orderBy('id')
            ->pluck('facture_number', 'id')->all();
    }

    public function test_sent_invoices_keep_their_number_and_the_rest_fill_the_gaps(): void
    {
        $a = $this->inv('2026-01-01', '1', true);
        $b = $this->inv('2026-01-02', '2', true);
        $c = $this->inv('2026-02-01', '50');          // unsent, after a gap
        $d = $this->inv('2026-02-02', '4', true);     // sent #4
        $e = $this->inv('2026-02-03', '60');

        $preview = $this->service->preview(2026);
        $this->assertSame([], $preview['conflicts']);

        $this->service->renumber(2026);

        $this->assertSame([$a->id => '1', $b->id => '2', $c->id => '3', $d->id => '4', $e->id => '5'], $this->numbers());
    }

    public function test_a_sent_invoice_keeps_its_place_within_its_day(): void
    {
        // #788 case: the sent invoice is not the first row of its day by id,
        // but its number is the first free one that day → it goes first.
        $x = $this->inv('2026-08-03', '900');
        $y = $this->inv('2026-08-04', '901');          // unsent, same day, lower id
        $z = $this->inv('2026-08-04', '2', true);      // sent #2 on Aug 4
        $w = $this->inv('2026-08-05', '903');

        $this->service->renumber(2026);

        $this->assertSame([$x->id => '1', $y->id => '3', $z->id => '2', $w->id => '4'], $this->numbers());
    }

    public function test_already_consistent_numbering_changes_nothing(): void
    {
        $this->inv('2026-07-31', '1', true);
        $this->inv('2026-08-01', '2');
        $this->inv('2026-08-04', '3', true);
        $this->inv('2026-08-04', '4');

        $preview = $this->service->preview(2026);
        $this->assertSame([], $preview['conflicts']);
        $this->assertSame(0, $preview['changes']);
    }

    public function test_a_sent_number_that_would_break_date_order_is_a_conflict_and_apply_refuses(): void
    {
        // Sent #1 is dated after an unsent invoice: the unsent one would need
        // a number before 1.
        $early = $this->inv('2026-01-01', '5');
        $this->inv('2026-03-01', '1', true);

        $preview = $this->service->preview(2026);
        $this->assertNotEmpty($preview['conflicts']);

        try {
            $this->service->renumber(2026);
            $this->fail('renumber should refuse while conflicts exist');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('#1', $e->getMessage());
        }
        $this->assertSame('5', $early->fresh()->facture_number); // nothing written
    }

    public function test_a_gap_before_a_sent_number_that_cannot_be_filled_is_a_conflict(): void
    {
        // Sent #10 with only one earlier invoice: numbers 2-9 can't be filled
        // without moving later-dated invoices before it.
        $this->inv('2026-01-01', '1');
        $this->inv('2026-01-05', '10', true);
        $this->inv('2026-01-06', '11');

        $this->assertNotEmpty($this->service->preview(2026)['conflicts']);
    }

    public function test_preview_flags_sent_rows(): void
    {
        $this->inv('2026-01-01', '1', true);
        $this->inv('2026-01-02', '7');

        $records = $this->service->preview(2026)['records'];
        $this->assertTrue($records[0]['sent']);
        $this->assertFalse($records[1]['sent']);
        $this->assertSame('2', $records[1]['new_number']);
    }

    public function test_a_sent_number_is_kept_exactly_as_stored_even_with_leading_zeros(): void
    {
        // Review of #235: "0001" must not be rewritten as "1" — the client holds "0001".
        $sent = $this->inv('2026-01-01', '0001', true);
        $this->inv('2026-01-02', '2');

        $preview = $this->service->preview(2026);
        $this->assertSame([], $preview['conflicts']);
        $this->assertSame(0, $preview['changes']);

        $this->service->renumber(2026);
        $this->assertSame('0001', $sent->fresh()->facture_number);
    }
}
