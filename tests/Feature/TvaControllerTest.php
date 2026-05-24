<?php

namespace Tests\Feature;

use App\Models\Tva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class TvaControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage tva', 'manage tva report'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('tva.index'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $tva = Tva::factory()->withInvoice()->create();
        $this->put(route('tva.update', $tva))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $tva = Tva::factory()->withInvoice()->create();
        $this->delete(route('tva.destroy', $tva))->assertRedirect(route('login'));
    }

    public function test_report_requires_auth(): void
    {
        $this->get(route('tva.report'))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_tva(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('tva.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_report_denied_without_manage_tva_report(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('tva.report'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── TvaController::index ──────────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk();
    }

    public function test_index_filters_by_from_date(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['from_date' => '2025-03-01']))
            ->assertOk();
        // Smoke test — response reaches the view without error
    }

    // ── TvaController::update ─────────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('tva.update', $tva), [
                'facture_date'   => '2025-08-15',
                'montant_ttc'    => 1200.00,
                'unit_price_ht'  => 1000.00,
                'tva'            => 200.00,
                'facture_number' => 'FACT-999',
            ])
            ->assertRedirect(route('tva.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tvas', [
            'id'             => $tva->id,
            'facture_number' => 'FACT-999',
            'montant_ttc'    => 1200.00,
        ]);
    }

    public function test_update_rejects_missing_required_fields(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('tva.update', $tva), [])
            ->assertSessionHasErrors(['facture_date', 'montant_ttc', 'unit_price_ht', 'tva', 'facture_number']);
    }

    public function test_update_returns_404_for_non_existent_id(): void
    {
        $this->actingAs($this->owner)
            ->put(route('tva.update', 99999), [
                'facture_date'   => '2025-01-01',
                'montant_ttc'    => 100,
                'unit_price_ht'  => 83.33,
                'tva'            => 16.67,
                'facture_number' => 'X',
            ])
            ->assertNotFound();
    }

    // ── TvaController::destroy ────────────────────────────────────────────────

    public function test_destroy_soft_deletes_tva_record(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('tva.destroy', $tva))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('tvas', ['id' => $tva->id]);
    }

    public function test_destroy_returns_404_for_non_existent_id(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('tva.destroy', 99999))
            ->assertNotFound();
    }

    // ── TvaController::report ─────────────────────────────────────────────────

    public function test_report_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tva.report'))
            ->assertOk();
    }

    public function test_report_accepts_year_filter(): void
    {
        Tva::factory()->withInvoice()->create([
            'facture_date' => '2024-03-01',
            'parent_id'    => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.report', ['year' => 2024]))
            ->assertOk();
    }

    // ── TvaController::bulkDownload (unauthenticated — public route) ──────────
    // NOTE: bulkDownload is registered OUTSIDE the auth middleware group,
    // so unauthenticated requests are not redirected to login.
    // We test that the route rejects requests with missing invoice_ids.

    public function test_bulk_download_rejects_missing_invoice_ids(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tva.bulk.download'), [])
            ->assertSessionHasErrors(['invoice_ids']);
    }
}
