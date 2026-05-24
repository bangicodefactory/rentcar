<?php

namespace Tests\Unit\Services;

use App\Models\Tva;
use App\Services\TvaRenumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class TvaRenumberServiceTest extends TestCase
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

    // ── preview ───────────────────────────────────────────────────────────────

    public function test_preview_returns_empty_for_year_with_no_invoices(): void
    {
        $result = $this->service->preview(2024);

        $this->assertSame(2024, $result['year']);
        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['records']);
    }

    public function test_preview_returns_correct_count_and_new_numbers(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-03-01', 'facture_number' => '10']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-15', 'facture_number' => '25']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-11-30', 'facture_number' => '99']);

        $result = $this->service->preview(2025);

        $this->assertSame(2025, $result['year']);
        $this->assertSame(3, $result['count']);

        $newNumbers = array_column($result['records'], 'new_number');
        $this->assertSame(['1', '2', '3'], $newNumbers);
    }

    public function test_preview_preserves_old_numbers_in_records(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-05-01', 'facture_number' => 'FACT-0042']);

        $result = $this->service->preview(2025);

        $this->assertSame('FACT-0042', $result['records'][0]['old_number']);
    }

    public function test_preview_orders_by_facture_date_asc_then_id_asc(): void
    {
        // Same date — ordering must fall back to id
        $a = Tva::factory()->withInvoice()->create(['facture_date' => '2025-07-10', 'facture_number' => 'Z']);
        $b = Tva::factory()->withInvoice()->create(['facture_date' => '2025-07-10', 'facture_number' => 'A']);
        $c = Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'facture_number' => 'M']);

        $result = $this->service->preview(2025);

        $ids = array_column($result['records'], 'id');
        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_preview_does_not_include_soft_deleted_records(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['facture_date' => '2025-04-01']);
        $tva->delete(); // soft-delete

        $result = $this->service->preview(2025);

        $this->assertSame(0, $result['count']);
    }

    public function test_preview_isolates_by_year(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2024-12-31', 'facture_number' => '1']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'facture_number' => '2']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2026-01-01', 'facture_number' => '3']);

        $result = $this->service->preview(2025);

        $this->assertSame(1, $result['count']);
        $this->assertSame('2', $result['records'][0]['old_number']);
    }

    public function test_preview_does_not_mutate_database(): void
    {
        $tva = Tva::factory()->withInvoice()->create([
            'facture_date'   => '2025-08-01',
            'facture_number' => 'ORIGINAL',
        ]);

        $this->service->preview(2025);

        $this->assertDatabaseHas('tvas', [
            'id'             => $tva->id,
            'facture_number' => 'ORIGINAL',
        ]);
    }

    // ── renumber ──────────────────────────────────────────────────────────────

    public function test_renumber_assigns_sequential_numbers_starting_at_1(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-02-01', 'facture_number' => '7']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-05-15', 'facture_number' => '42']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-09-30', 'facture_number' => '99']);

        $result = $this->service->renumber(2025);

        $this->assertSame(2025, $result['year']);
        $this->assertSame(3, $result['updated']);

        $newNumbers = array_column($result['records'], 'new_number');
        $this->assertSame(['1', '2', '3'], $newNumbers);
    }

    public function test_renumber_persists_new_numbers_to_database(): void
    {
        $t1 = Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-10', 'facture_number' => '100']);
        $t2 = Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-20', 'facture_number' => '200']);

        $this->service->renumber(2025);

        $this->assertDatabaseHas('tvas', ['id' => $t1->id, 'facture_number' => '1']);
        $this->assertDatabaseHas('tvas', ['id' => $t2->id, 'facture_number' => '2']);
    }

    public function test_renumber_is_idempotent(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-03-01', 'facture_number' => 'GAP1']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-07-01', 'facture_number' => 'GAP2']);

        $this->service->renumber(2025);
        $result2 = $this->service->renumber(2025);

        $this->assertSame(['1', '2'], array_column($result2['records'], 'new_number'));
    }

    public function test_renumber_fills_gaps_in_existing_sequence(): void
    {
        // Numbers 1, 5, 10 → should become 1, 2, 3
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'facture_number' => '1']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'facture_number' => '5']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-12-01', 'facture_number' => '10']);

        $result = $this->service->renumber(2025);

        $this->assertSame('1', $result['records'][0]['new_number']);
        $this->assertSame('2', $result['records'][1]['new_number']);
        $this->assertSame('3', $result['records'][2]['new_number']);
    }

    public function test_renumber_does_not_touch_other_years(): void
    {
        $other = Tva::factory()->withInvoice()->create([
            'facture_date'   => '2024-06-01',
            'facture_number' => 'OLD',
        ]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-03-01', 'facture_number' => 'X']);

        $this->service->renumber(2025);

        $this->assertDatabaseHas('tvas', ['id' => $other->id, 'facture_number' => 'OLD']);
    }

    public function test_renumber_skips_soft_deleted_records(): void
    {
        $active  = Tva::factory()->withInvoice()->create(['facture_date' => '2025-05-01', 'facture_number' => '5']);
        $deleted = Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'facture_number' => '6']);
        $deleted->delete();

        $result = $this->service->renumber(2025);

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('tvas', ['id' => $active->id, 'facture_number' => '1']);
        // Soft-deleted row must remain untouched
        $this->assertDatabaseHas('tvas', ['id' => $deleted->id, 'facture_number' => '6']);
    }

    public function test_renumber_empty_year_returns_zero_updated(): void
    {
        $result = $this->service->renumber(2023);

        $this->assertSame(0, $result['updated']);
        $this->assertSame([], $result['records']);
    }

    public function test_preview_and_renumber_produce_matching_new_numbers(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-02-14', 'facture_number' => 'A']);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-08-08', 'facture_number' => 'B']);

        $preview  = $this->service->preview(2025);
        $renumber = $this->service->renumber(2025);

        $this->assertSame(
            array_column($preview['records'],  'new_number'),
            array_column($renumber['records'], 'new_number'),
        );
    }
}
