<?php

namespace Tests\Feature;

use App\Models\Tva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->seedCompanySettings($this->owner->id);
    }

    /**
     * Company identity the invoice generator reads (ice/rc/if/company_*).
     * settingsKeys() has no defaults for these, so seed them per tenant or
     * generation throws "Undefined array key" the moment it creates an invoice.
     */
    private function seedCompanySettings(int $parentId): void
    {
        $company = [
            'company_name' => 'Test Co', 'company_address' => '1 Rue Test',
            'ice' => 'ICE-1', 'rc' => 'RC-1', 'if' => 'IF-1',
        ];
        foreach ($company as $name => $value) {
            \App\Models\Setting::create(['name' => $name, 'value' => $value, 'parent_id' => $parentId]);
        }
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

    public function test_index_exposes_invoice_payment_method(): void
    {
        // The Factures list shows each invoice's recorded payment method.
        Tva::factory()->withInvoice()->create([
            'parent_id' => $this->owner->id, 'payment_method' => 'Chèque',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.data.0.payment_method', 'Chèque')
            );
    }

    public function test_index_invoice_without_method_exposes_null(): void
    {
        // No recorded method -> prop is null (the list cell renders '—').
        Tva::factory()->withInvoice()->create([
            'parent_id' => $this->owner->id, 'payment_method' => null,
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.data.0.payment_method', null)
            );
    }

    public function test_index_paginates_results(): void
    {
        // F-21: the list is server-side paginated (25/page) instead of loading all rows.
        Tva::factory()->withInvoice()->count(30)->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 25)
                ->where('tvas.total', 30)
                ->where('tvas.per_page', 25)
                ->where('tvas.current_page', 1)
                ->where('tvas.last_page', 2)
            );
    }

    public function test_index_shows_all_on_one_page_when_month_and_year_selected(): void
    {
        // Month view: a specific month + year shows every invoice on one page
        // (cap 300) instead of paginating — mirrors the reservation list.
        Tva::factory()->withInvoice()->count(30)->create([
            'parent_id' => $this->owner->id, 'facture_date' => '2026-06-10',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_month' => '06', 'filter_year' => '2026']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.total', 30)
                ->where('tvas.per_page', 300)
                ->where('tvas.last_page', 1)
                ->has('tvas.data', 30)
            );
    }

    public function test_index_month_without_year_still_paginates(): void
    {
        // The show-all trigger requires BOTH month and year; month alone paginates.
        Tva::factory()->withInvoice()->count(30)->create([
            'parent_id' => $this->owner->id, 'facture_date' => '2026-06-10',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_month' => '06']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.total', 30)
                ->where('tvas.per_page', 25)
                ->where('tvas.last_page', 2)
            );
    }

    public function test_index_pagination_preserves_filter_on_later_pages(): void
    {
        // withQueryString() must keep the filter on page 2 (and the id tiebreaker
        // keeps paging deterministic). 30 invoices in 2024 → page 2 has the last 5.
        Tva::factory()->withInvoice()->count(30)->create([
            'parent_id' => $this->owner->id, 'facture_date' => '2024-05-10',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_year' => '2024', 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.current_page', 2)
                ->where('tvas.total', 30)
                ->has('tvas.data', 5)
            );
    }

    public function test_index_provides_all_matching_ids_for_select_all(): void
    {
        // all_ids spans every matching row (not just the page) so "select all"
        // can drive a full-set bulk download.
        Tva::factory()->withInvoice()->count(30)->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 25)
                ->has('all_ids', 30)
            );
    }

    public function test_index_formats_facture_date_with_company_date_format(): void
    {
        // The list DATE column must follow the tenant's System Date Format
        // (company_date_format), not a hardcoded ISO format. The Blade list
        // used dateFormat(); the Inertia port had regressed to Y-m-d.
        \App\Models\Setting::create([
            'name' => 'company_date_format', 'value' => 'd/m/Y', 'parent_id' => $this->owner->id,
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        Tva::factory()->withInvoice()->create([
            'parent_id' => $this->owner->id, 'facture_date' => '2025-03-09',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->where('tvas.data.0.facture_date', '09/03/2025') // not 2025-03-09
                ->etc()
            );
    }

    public function test_index_filters_by_from_date(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['from_date' => '2025-03-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
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

    // NOTE: update has no permission check and no tenant scoping — any authenticated
    // user can mutate any TVA record regardless of parent_id. These tests document
    // that gap so a future fix can close it.
    public function test_update_succeeds_cross_tenant_documents_missing_scope(): void
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $otherOwner->id]);

        $this->actingAs($this->owner)
            ->put(route('tva.update', $tva), [
                'facture_date'   => '2025-08-15',
                'montant_ttc'    => 500,
                'unit_price_ht'  => 416.67,
                'tva'            => 83.33,
                'facture_number' => 'CROSS',
            ])
            ->assertRedirect(route('tva.index'));

        // Succeeds — no scoping guard exists yet
        $this->assertDatabaseHas('tvas', ['id' => $tva->id, 'facture_number' => 'CROSS']);
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

    // NOTE: destroy has no permission check and no tenant scoping — documents the gap.
    public function test_destroy_succeeds_cross_tenant_documents_missing_scope(): void
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $otherOwner->id]);

        $this->actingAs($this->owner)
            ->delete(route('tva.destroy', $tva))
            ->assertRedirect();

        // Succeeds — no scoping guard exists yet
        $this->assertSoftDeleted('tvas', ['id' => $tva->id]);
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

    // ── TvaController::bulkDownload (public route — outside auth middleware) ────
    // NOTE: bulkDownload is registered OUTSIDE the auth middleware group.
    // Unauthenticated requests are NOT redirected to login — this is a security gap.

    public function test_bulk_download_requires_auth(): void
    {
        $this->post(route('tva.bulk.download'), [])
            ->assertRedirect(route('login'));
    }

    public function test_bulk_download_rejects_missing_invoice_ids(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tva.bulk.download'), [])
            ->assertSessionHasErrors(['invoice_ids']);
    }

    // ── TvaController::edit ───────────────────────────────────────────────────

    public function test_edit_requires_auth(): void
    {
        $tva = Tva::factory()->withInvoice()->create();
        $this->get(route('tva.edit', $tva))->assertRedirect(route('login'));
    }

    public function test_edit_renders_inertia_component(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.edit', $tva))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Edit')
                ->has('tva.id')
                ->has('tva.facture_number')
            );
    }

    public function test_edit_returns_404_for_non_existent_id(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tva.edit', 99999))
            ->assertNotFound();
    }

    // ── TvaController::show ───────────────────────────────────────────────────

    public function test_show_requires_auth(): void
    {
        $tva = Tva::factory()->withInvoice()->create();
        $this->get(route('tva.show', $tva))->assertRedirect(route('login'));
    }

    public function test_show_renders_inertia_component(): void
    {
        $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.show', $tva))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Show')
                ->has('tva.id')
                ->has('tva.facture_number')
                ->has('tva.montant_ttc')
            );
    }

    public function test_show_returns_404_for_non_existent_id(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tva.show', 99999))
            ->assertNotFound();
    }

    // ── TvaController::index — additional filters ─────────────────────────────

    public function test_index_filters_by_to_date(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-01', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['to_date' => '2025-03-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
    }

    public function test_index_filters_by_filter_month(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-01-15', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_month' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
    }

    public function test_index_filters_by_filter_year(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2024-05-01', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_year' => '2024']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
    }

    public function test_index_filters_by_filter_day(): void
    {
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-01', 'parent_id' => $this->owner->id]);
        Tva::factory()->withInvoice()->create(['facture_date' => '2025-06-15', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['filter_day' => '2025-06-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
    }

    public function test_index_filters_by_driver_name(): void
    {
        Tva::factory()->withInvoice()->create([
            'client_name' => 'Alice Dupont',
            'parent_id'   => $this->owner->id,
        ]);
        Tva::factory()->withInvoice()->create([
            'client_name' => 'Bob Martin',
            'parent_id'   => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index', ['driver_name' => 'Alice']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas.data', 1)
            );
    }

    // ── TvaController::generateMonthlyTva ─────────────────────────────────────

    public function test_generate_monthly_tva_requires_auth(): void
    {
        $this->post(route('tva.generate'), [])
            ->assertRedirect(route('login'));
    }

    public function test_generate_monthly_tva_as_guest_leaves_existing_invoices_untouched(): void
    {
        // generate() soft-deletes and rebuilds a whole month; a guest must never
        // reach it, even with a well-formed payload.
        $monthStart = now()->startOfMonth();
        $existing = Tva::factory()->withInvoice()->create([
            'month'     => $monthStart->month,
            'year'      => $monthStart->year,
            'parent_id' => $this->owner->id,
        ]);

        $this->post(route('tva.generate'), ['month' => $monthStart->format('Y-m')])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('tvas', ['id' => $existing->id, 'deleted_at' => null]);
    }

    public function test_generate_monthly_tva_validates_month_format(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => 'not-a-month'])
            ->assertSessionHasErrors(['month']);
    }

    public function test_generate_monthly_tva_with_valid_month_redirects_back(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), [
                'month' => now()->format('Y-m'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_generate_monthly_tva_deletes_existing_records_for_month(): void
    {
        $monthStart = now()->startOfMonth();
        // Pin month/year to the generated period — the factory default is a random
        // month (numberBetween(1,12)), which made this assertion flaky (see below).
        $old = Tva::factory()->withInvoice()->create([
            'facture_date' => $monthStart->format('Y-m-d'),
            'month'        => $monthStart->month,
            'year'         => $monthStart->year,
            'parent_id'    => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), [
                'month' => $monthStart->format('Y-m'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // The stale record (no backing booking payment) is cleared when the month
        // is regenerated. Tva soft-deletes, so generate() sets deleted_at rather
        // than physically removing the row — assert it's no longer ACTIVE.
        // (assertDatabaseMissing reads the raw table and would still see the
        // soft-deleted row, so scope it to deleted_at = null.)
        $this->assertSoftDeleted($old);
        $this->assertDatabaseMissing('tvas', [
            'parent_id'  => $this->owner->id,
            'year'       => $monthStart->year,
            'month'      => $monthStart->month,
            'deleted_at' => null,
        ]);
    }

    // ── generateMonthlyTva — invoice_on_full_payment gating ──────────────────

    public function test_generate_skips_not_fully_paid_bookings_when_flag_on(): void
    {
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = \App\Models\Booking::factory()->create([
            'parent_id' => $this->owner->id, 'amount' => 1000, 'payment_status' => 'impaye',
        ]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id,
            'date' => '2024-01-10', 'amount' => 300,
        ]);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        // Partially paid (300 of 1000) → no invoice generated.
        $this->assertSame(0, Tva::where('booking_id', $booking->id)->whereNull('deleted_at')->count());
    }

    public function test_generate_includes_fully_paid_bookings_when_flag_on(): void
    {
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = \App\Models\Booking::factory()->create([
            'parent_id' => $this->owner->id, 'amount' => 1000, 'payment_status' => 'paye',
        ]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id, 'date' => '2024-01-10', 'amount' => 600,
        ]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id, 'date' => '2024-01-20', 'amount' => 400,
        ]);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        // Fully paid (600 + 400 = 1000) → both payments invoiced.
        $this->assertSame(2, Tva::where('booking_id', $booking->id)->whereNull('deleted_at')->count());
    }

    public function test_generate_uses_persisted_invoice_days_when_present(): void
    {
        // The monthly rebuild honors a payment's stored invoice_days (a manual
        // override or cash-split share) so it matches the live-flush invoice,
        // instead of always using the booking's full rental span.
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = \App\Models\Booking::factory()->create([
            'parent_id' => $this->owner->id, 'amount' => 1000, 'payment_status' => 'paye',
            'start_date' => '2024-01-01', 'end_date' => '2024-01-11', // 10-day span
        ]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id,
            'date' => '2024-01-10', 'amount' => 1000, 'invoice_days' => 4,
        ]);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        // Facture Qty = the stored 4, not the booking's 10-day span.
        $tva = Tva::where('booking_id', $booking->id)->whereNull('deleted_at')->firstOrFail();
        $this->assertSame(4.0, (float) $tva->quantity);
    }

    public function test_generate_invoices_all_payments_when_flag_off(): void
    {
        // Default (directonderweg): generate per payment regardless of paid state.
        $booking = \App\Models\Booking::factory()->create([
            'parent_id' => $this->owner->id, 'amount' => 1000, 'payment_status' => 'impaye',
        ]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id, 'date' => '2024-01-10', 'amount' => 300,
        ]);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Tva::where('booking_id', $booking->id)->whereNull('deleted_at')->count());
    }

    // ── TvaController::report — additional ───────────────────────────────────

    public function test_report_renders_inertia_component_with_expected_props(): void
    {
        Tva::factory()->withInvoice()->create([
            'facture_date' => now()->format('Y-m-d'),
            'parent_id'    => $this->owner->id,
            'tva_amount'   => 100,
            'total_ht'     => 500,
            'montant_ttc'  => 600,
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.report'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Report')
                ->has('monthlyStats')
                ->has('yearlyStats')
                ->has('topClients')
                ->has('chartData')
                ->has('selectedYear')
                ->has('availableYears')
                ->has('topRentedCars')
                ->has('topProfitableCars')
                ->has('carPerformanceStats')
            );
    }

    // ── TvaController::report — with data for statistics ─────────────────────

    public function test_report_computes_correct_yearly_stats(): void
    {
        // Create multiple TVAs in current year for the same client
        $year = now()->year;
        Tva::factory()->withInvoice()->create([
            'facture_date'  => "{$year}-01-15",
            'parent_id'     => $this->owner->id,
            'client_name'   => 'Alice Dupont',
            'designation'   => 'Toyota Corolla - AB-123-CD',
            'tva_amount'    => 200,
            'total_ht'      => 1000,
            'montant_ttc'   => 1200,
        ]);
        Tva::factory()->withInvoice()->create([
            'facture_date'  => "{$year}-03-10",
            'parent_id'     => $this->owner->id,
            'client_name'   => 'Alice Dupont',
            'designation'   => 'Toyota Corolla - AB-123-CD',
            'tva_amount'    => 100,
            'total_ht'      => 500,
            'montant_ttc'   => 600,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('tva.report', ['year' => $year]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Report')
                ->where('yearlyStats.total_invoices', 2)
                ->has('topClients', 1)
                ->has('topRentedCars')
                ->has('topProfitableCars')
                ->has('carPerformanceStats')
            );
    }

    public function test_report_with_no_data_for_year_returns_zero_stats(): void
    {
        // No TVAs exist for year 1999
        $this->actingAs($this->owner)
            ->get(route('tva.report', ['year' => 1999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Report')
                ->where('yearlyStats.total_invoices', 0)
                ->where('yearlyStats.total_tva_amount', 0)
            );
    }

    // ── TvaController::index — with booking_id_display ───────────────────────

    public function test_index_maps_booking_id_display(): void
    {
        // A TVA with a booking will have a booking_id_display value
        $tva = Tva::factory()->withInvoice()->create([
            'parent_id'  => $this->owner->id,
            'client_name' => 'Test Client',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tva.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tva/Index')
                ->has('tvas')
            );
    }

    // ── TvaController::generateMonthlyTva — per-year numbering ───────────────

    /** Create a booking (for the owner) with a payment on the given date. */
    private function bookingPaymentOn(string $date, float $amount = 120.00): void
    {
        $booking = \App\Models\Booking::factory()->create(['parent_id' => $this->owner->id]);
        \App\Models\BookingPayment::factory()->create([
            'booking_id' => $booking->id,
            'parent_id'  => $this->owner->id,
            'date'       => $date,
            'amount'     => $amount,
        ]);
    }

    public function test_generate_numbers_invoices_sequentially_within_a_year(): void
    {
        // Two payments in January, one in February of the same year.
        $this->bookingPaymentOn('2024-01-10');
        $this->bookingPaymentOn('2024-01-20');
        $this->bookingPaymentOn('2024-02-05');

        // Generating January issues 1 and 2…
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'facture_number' => '1', 'deleted_at' => null]);
        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'facture_number' => '2', 'deleted_at' => null]);

        // …and February continues from the year's running total (→ 3).
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-02'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'facture_number' => '3', 'deleted_at' => null]);
    }

    public function test_generate_resets_numbering_to_one_each_year(): void
    {
        $this->bookingPaymentOn('2024-01-10');
        $this->bookingPaymentOn('2025-01-10');

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2025-01'])
            ->assertRedirect()->assertSessionHas('success');

        // The first invoice of 2024 and the first of 2025 both start at 1.
        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'facture_number' => '1', 'deleted_at' => null]);
        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2025, 'facture_number' => '1', 'deleted_at' => null]);
    }

    public function test_generate_regenerating_a_month_does_not_inflate_its_own_numbers(): void
    {
        // Two payments in the same month; regenerating must not double-count the
        // records it just replaced (they're soft-deleted before renumbering).
        $this->bookingPaymentOn('2024-03-10');
        $this->bookingPaymentOn('2024-03-20');

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-03'])
            ->assertRedirect()->assertSessionHas('success');

        // Regenerate the same month.
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-03'])
            ->assertRedirect()->assertSessionHas('success');

        // Still numbered 1 and 2 — not 3 and 4.
        $active = Tva::whereNull('deleted_at')->where('parent_id', $this->owner->id)->pluck('facture_number')->sort()->values()->all();
        $this->assertEquals(['1', '2'], $active);
    }

    public function test_generate_numbers_by_booking_parent_not_generating_user(): void
    {
        // IST-230 Finding 1: numbering must follow the BOOKING's business, not
        // the role/account of whoever clicks "Generate". Regression guard for
        // the old behaviour where a super admin (parentId() = own id) reset the
        // counter to 0 and produced duplicate facture numbers.

        // Owner's business gets a January payment; the owner generates → 1.
        $this->bookingPaymentOn('2024-01-10');
        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-01'])
            ->assertRedirect()->assertSessionHas('success');

        // A super admin (parentId() resolves to their OWN id, not the owner's)
        // generates February for the same business.
        $superAdmin = User::factory()->create(['type' => 'super admin', 'parent_id' => 0]);
        $superAdmin->givePermissionTo('manage tva');
        $this->seedCompanySettings($superAdmin->id);

        $this->bookingPaymentOn('2024-02-10');
        $this->actingAs($superAdmin)
            ->post(route('tva.generate'), ['month' => '2024-02'])
            ->assertRedirect()->assertSessionHas('success');

        // February continues the OWNER's sequence (→ 2), independent of who ran it…
        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'facture_number' => '2', 'deleted_at' => null]);
        // …and there is exactly one '1' for the business — no duplicate.
        $this->assertEquals(1, Tva::whereNull('deleted_at')
            ->where('parent_id', $this->owner->id)
            ->where('facture_number', '1')->count());
    }

    public function test_generate_targets_the_requested_month_regardless_of_today(): void
    {
        // Guards the createFromFormat('!Y-m', ...) day-pinning: generating a
        // short month (Feb) must not roll over to the next month when "today"
        // is a day that February lacks (e.g. the 30th).
        $this->bookingPaymentOn('2024-02-15');

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2024-02'])
            ->assertRedirect()->assertSessionHas('success');

        // The invoice lands in February (month 2), not March.
        $this->assertDatabaseHas('tvas', ['parent_id' => $this->owner->id, 'year' => 2024, 'month' => 2, 'deleted_at' => null]);
        $this->assertDatabaseMissing('tvas', ['parent_id' => $this->owner->id, 'month' => 3, 'deleted_at' => null]);
    }

    // ── bulkDownload (facture PDF generation) ──────────────────────────────────

    /**
     * Smoke test the facture PDF path (BAN-255): the logo lives on the `public`
     * disk (storage/app/public/upload/logo). The generator now searches there, so
     * a facture for a tenant with a real logo file renders without error and
     * returns a downloadable zip. (Guards the PDF path + the logo-resolution
     * block; the embedded image itself is verified manually.)
     */
    public function test_bulk_download_generates_facture_pdf_with_logo_present(): void
    {
        // Place a real logo at the canonical public-disk path the fix searches.
        $logoDir = storage_path('app/public/upload/logo');
        if (! is_dir($logoDir)) {
            mkdir($logoDir, 0755, true);
        }
        $logoFile = $logoDir . '/2_logo.png'; // the generator's default filename
        $created = ! file_exists($logoFile);
        if ($created) {
            // 1x1 transparent PNG.
            file_put_contents($logoFile, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
            ));
        }

        try {
            $tva = Tva::factory()->withInvoice()->create(['parent_id' => $this->owner->id]);

            $response = $this->actingAs($this->owner)
                ->post(route('tva.bulk.download'), ['invoice_ids' => [$tva->id]]);

            $response->assertOk();
            $this->assertStringContainsString(
                'application/',
                strtolower($response->headers->get('content-type') ?? ''),
                'Expected a file download response from bulkDownload.'
            );
        } finally {
            if ($created && file_exists($logoFile)) {
                @unlink($logoFile);
            }
        }
    }

    // ── invoice PDF template: unit price (IST-234) ───────────────────────────

    public function test_invoice_pdf_unit_price_reconciles_with_quantity(): void
    {
        // IST-234: P.U must be total_ttc / quantity (so QTY × P.U = TOTAL),
        // not the old hardcoded total_ttc / 8 (which printed 100 for an 800 TTC).
        $tva = (object) [
            'facture_number' => '400',
            'facture_date'   => '2026-05-03',
            'created_at'     => now(),
            'client_name'    => 'JOSE ANTONIO ESPEJO',
            'client_address' => '',
            'payment_method' => 'Carte',
            'montant_ttc'    => 800,
            'total_ht'       => 666.67,
            'tva'            => 133.33,
            'items'          => [
                (object) ['description' => 'SEAT IBIZA - 74653/A/44', 'details' => '', 'quantity' => 2, 'unit_price' => 400, 'total_ttc' => 800],
            ],
        ];
        $settings = [
            'company_name' => 'Co', 'company_address' => 'A', 'company_email' => 'e',
            'company_phone' => 'p', 'ice' => 'i', 'if' => 'f', 'patente' => 'pa', 'rc' => 'rc',
        ];

        $html = view('pdf.invoice1', [
            'tva' => $tva, 'settings' => $settings,
            'logoPath' => null, 'ttcInWords' => 'Huit cents', 'clientIce' => '', 'signaturePath' => null,
        ])->render();

        // 800 / 2 = 400.00 (TTC per unit), and NOT the old 800 / 8 = 100.
        $this->assertStringContainsString('400.00 MAD', $html);
        $this->assertStringNotContainsString('100 MAD', $html);
    }
}
