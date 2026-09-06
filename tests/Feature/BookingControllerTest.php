<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Driver;
use App\Models\Place;
use App\Models\Tva;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected User $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        // Create permissions required by BookingController
        $perms = [
            'manage booking', 'create booking', 'show booking',
            'edit booking', 'delete booking',
            'create booking payment', 'delete booking payment',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner  = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->driver  = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'vehicle'        => $this->vehicle->id,
            'driver'         => $this->driver->id,
            'parent_id'      => $this->owner->id,
            'vehicle_details' => [
                'id'            => $this->vehicle->id,
                'name'          => $this->vehicle->name,
                'license_plate' => $this->vehicle->license_plate,
            ],
        ], $overrides));
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('booking.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_paginated_inertia_component(): void
    {
        $this->makeBooking();

        $this->actingAs($this->owner)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Index')
                ->where('bookings.current_page', 1)
                ->has('bookings.data')
                ->has('bookings.last_page')
                ->has('bookings.total')
            );
    }

    public function test_index_filters_by_month(): void
    {
        $this->makeBooking(['start_date' => '2026-05-10']);
        $this->makeBooking(['start_date' => '2026-05-20']);
        $this->makeBooking(['start_date' => '2026-06-15']);

        $this->actingAs($this->owner)
            ->get(route('booking.index', ['month' => '2026-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Index')
                ->where('filters.month', '2026-05')
                ->where('bookings.total', 2)
            );
    }

    public function test_index_ignores_invalid_month(): void
    {
        $this->makeBooking(['start_date' => '2026-05-10']);
        $this->makeBooking(['start_date' => '2026-06-15']);

        // A malformed month must be ignored (filter not applied), never reach
        // the query builder, and echo back as empty in the filters prop.
        $this->actingAs($this->owner)
            ->get(route('booking.index', ['month' => '2026-13']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.month', '')
                ->where('bookings.total', 2)
            );
    }

    public function test_index_shows_all_bookings_on_one_page_when_month_selected(): void
    {
        // 30 May bookings would span two pages at 25/page; selecting the month
        // returns them all on a single page (page size capped at 300).
        collect(range(1, 30))->each(fn () => $this->makeBooking(['start_date' => '2026-05-10']));
        $this->makeBooking(['start_date' => '2026-06-15']); // other month, excluded

        $this->actingAs($this->owner)
            ->get(route('booking.index', ['month' => '2026-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Index')
                ->where('bookings.total', 30)
                ->where('bookings.per_page', 300) // capped page size, not 25
                ->where('bookings.last_page', 1)
                ->has('bookings.data', 30)
            );
    }

    public function test_index_still_paginates_when_no_month_selected(): void
    {
        // Without a month filter the list keeps the default 25/page pagination.
        collect(range(1, 30))->each(fn () => $this->makeBooking());

        $this->actingAs($this->owner)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bookings.per_page', 25)
                ->where('bookings.last_page', 2)
                ->has('bookings.data', 25)
            );
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('booking.store'))->assertRedirect(route('login'));
    }

    public function test_show_requires_auth(): void
    {
        $booking = $this->makeBooking();
        $this->get(route('booking.show', Crypt::encrypt($booking->id)))
            ->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $booking = $this->makeBooking();
        $this->delete(route('booking.destroy', $booking->id))
            ->assertRedirect(route('login'));
    }

    public function test_payment_store_requires_auth(): void
    {
        $booking = $this->makeBooking();
        $this->post(route('booking.payment.store', $booking->id))
            ->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_store_denied_without_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('booking.store'), [])
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_show_denied_without_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking();

        $this->actingAs($noPerms)
            ->get(route('booking.show', Crypt::encrypt($booking->id)))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── BookingController::create / edit form loaders (BAN-239) ──────────────

    public function test_create_renders_inertia_component_with_dropdown_props(): void
    {
        $this->actingAs($this->owner)
            ->get(route('booking.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Create')
                ->has('vehicles')
                ->has('drivers')
                ->has('places')
                ->has('addons')
                ->has('statuses')
            );
    }

    public function test_edit_renders_inertia_component_with_dropdown_props(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->owner)
            ->get(route('booking.edit', Crypt::encrypt($booking->id)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Edit')
                ->has('booking')
                ->has('vehicles')
                ->has('drivers')
                ->has('places')
                ->has('addons')
            );
    }

    public function test_create_returns_all_drivers_newest_first(): void
    {
        // Regression for BAN-266: the picker filters client-side, so the create
        // page must send every driver (not a capped slice that hid older ones),
        // ordered newest-first. created_at isn't fillable, so set it directly
        // (not via mass assignment) to keep the order deterministic.
        $this->driver->name = 'Old One';
        $this->driver->created_at = '2025-01-01 00:00:00';
        $this->driver->save();
        $mid = User::factory()->driver()->create(['parent_id' => $this->owner->id, 'name' => 'Mid One']);
        $mid->created_at = '2025-06-01 00:00:00';
        $mid->save();
        $new = User::factory()->driver()->create(['parent_id' => $this->owner->id, 'name' => 'New One']);
        $new->created_at = '2025-12-01 00:00:00';
        $new->save();

        $this->actingAs($this->owner)
            ->get(route('booking.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('drivers', 3)
                ->where('drivers.0.name', 'New One')  // most recently created first
                ->where('drivers.2.name', 'Old One')
            );
    }

    // ── BookingController::store ──────────────────────────────────────────────

    public function test_store_flashes_error_on_invalid_dates(): void
    {
        $this->actingAs($this->owner)
            ->post(route('booking.store'), [
                'vehicle'          => $this->vehicle->id,
                'start_date_time'  => '2026-06-05 09:00',
                'end_date_time'    => '2026-06-01 09:00', // before start → fails after:
                'driver'           => $this->driver->id,
                // String literals pass the 'string' validator rule; date validation
                // fires first so these never reach the DB insert.
                'pickup_address'   => 'Airport',
                'drop_off_address' => 'Hotel',
                'status'           => 'yet_to_start',
                'amount'           => 300,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_creates_booking_and_redirects_to_show(): void
    {
        $pickup  = Place::factory()->create();
        $dropOff = Place::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('booking.store'), [
                'vehicle'          => $this->vehicle->id,
                'start_date_time'  => '2026-06-01 09:00',
                'end_date_time'    => '2026-06-04 18:00',
                'driver'           => $this->driver->id,
                'pickup_address'   => (string) $pickup->id,
                'drop_off_address' => (string) $dropOff->id,
                'status'           => 'yet_to_start',
                'amount'           => 300,
            ])
            ->assertRedirect();

        $booking = Booking::where('vehicle', $this->vehicle->id)
            ->where('driver', $this->driver->id)
            ->first();

        $this->assertNotNull($booking);
        $this->assertEquals('impaye', $booking->payment_status);
        $this->assertEquals($this->owner->id, $booking->parent_id);
    }

    public function test_store_flashes_error_when_required_field_missing(): void
    {
        $this->actingAs($this->owner)
            ->post(route('booking.store'), [
                // missing vehicle, start_date_time, etc.
                'status' => 'yet_to_start',
                'amount' => 300,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── BookingController::show ───────────────────────────────────────────────

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $otherOwner->givePermissionTo('show booking');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $booking = $this->makeBooking(); // belongs to $this->owner

        $this->actingAs($otherOwner)
            ->get(route('booking.show', Crypt::encrypt($booking->id)))
            ->assertStatus(404);
    }

    public function test_show_returns_200_for_own_booking(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->owner)
            ->get(route('booking.show', Crypt::encrypt($booking->id)))
            ->assertStatus(200);
    }

    // ── BookingController::destroy ────────────────────────────────────────────

    public function test_destroy_deletes_booking_and_associated_tva(): void
    {
        $booking = $this->makeBooking();
        $tva = Tva::factory()->create(['booking_id' => $booking->id]);

        $this->actingAs($this->owner)
            ->delete(route('booking.destroy', $booking->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('tvas', ['id' => $tva->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_destroy_requires_delete_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking();

        $this->actingAs($noPerms)
            ->delete(route('booking.destroy', $booking->id))
            ->assertSessionHas('error');
    }

    // ── BookingController::bulkDestroy ────────────────────────────────────────

    public function test_bulk_destroy_deletes_selected_bookings(): void
    {
        $b1 = $this->makeBooking();
        $b2 = $this->makeBooking();

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-destroy'), ['ids' => [$b1->id, $b2->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bookings', ['id' => $b1->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $b2->id]);
    }

    public function test_bulk_destroy_flashes_error_when_no_ids(): void
    {
        $this->actingAs($this->owner)
            ->post(route('booking.bulk-destroy'), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── BookingController::matchingIds (select-all-across-pages) ──────────────

    public function test_matching_ids_returns_every_id_for_month_across_pages(): void
    {
        // 30 May bookings span two paginated pages (25/page); 5 June bookings
        // must be excluded. matchingIds returns the whole filtered set, the
        // pre-Inertia "select all" reach the page-only checkbox lost.
        $may = collect(range(1, 30))->map(fn () => $this->makeBooking(['start_date' => '2026-05-10'])->id);
        $this->makeBooking(['start_date' => '2026-06-15']);
        $this->makeBooking(['start_date' => '2026-06-16']);
        $this->makeBooking(['start_date' => '2026-06-17']);
        $this->makeBooking(['start_date' => '2026-06-18']);
        $this->makeBooking(['start_date' => '2026-06-19']);

        $res = $this->actingAs($this->owner)
            ->getJson(route('booking.matching-ids', ['month' => '2026-05']))
            ->assertOk()
            ->assertJsonCount(30, 'ids');

        $this->assertEqualsCanonicalizing($may->all(), $res->json('ids'));
    }

    public function test_matching_ids_respects_search_filter(): void
    {
        // Search matches on the booking's driver name (the orWhereHas branch).
        $named = User::factory()->driver()->create(['parent_id' => $this->owner->id, 'name' => 'Zsearchdriver']);
        $hit   = $this->makeBooking(['driver' => $named->id]);
        $this->makeBooking(); // default driver, unrelated name

        $this->actingAs($this->owner)
            ->getJson(route('booking.matching-ids', ['search' => 'Zsearchdriver']))
            ->assertOk()
            ->assertExactJson(['ids' => [$hit->id]]);
    }

    public function test_matching_ids_scoped_to_tenant(): void
    {
        $mine = $this->makeBooking();

        $other = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->makeBooking(['parent_id' => $other->id]);

        $this->actingAs($this->owner)
            ->getJson(route('booking.matching-ids'))
            ->assertOk()
            ->assertExactJson(['ids' => [$mine->id]]);
    }

    public function test_matching_ids_ignores_invalid_month(): void
    {
        // A malformed month must be ignored (no filter), so every booking is
        // returned — mirrors index()'s handling of the same input.
        $this->makeBooking(['start_date' => '2026-05-10']);
        $this->makeBooking(['start_date' => '2026-06-15']);

        $this->actingAs($this->owner)
            ->getJson(route('booking.matching-ids', ['month' => '2026-13']))
            ->assertOk()
            ->assertJsonCount(2, 'ids');
    }

    public function test_matching_ids_denied_without_manage_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->getJson(route('booking.matching-ids'))
            ->assertStatus(403);
    }

    public function test_matching_ids_requires_auth(): void
    {
        $this->get(route('booking.matching-ids'))->assertRedirect(route('login'));
    }

    public function test_bulk_destroy_only_deletes_callers_own_bookings_and_tva(): void
    {
        // Security regression (BAN-268): ids are client-supplied. A crafted list
        // mixing the caller's own booking with another tenant's must delete ONLY
        // the caller's booking + its TVA, never the other tenant's rows.
        $mine   = $this->makeBooking();
        $myTva  = Tva::factory()->create(['booking_id' => $mine->id]);

        $other     = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $theirs    = $this->makeBooking(['parent_id' => $other->id]);
        $theirTva  = Tva::factory()->create(['booking_id' => $theirs->id]);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-destroy'), ['ids' => [$mine->id, $theirs->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Caller's own rows gone…
        $this->assertDatabaseMissing('bookings', ['id' => $mine->id]);
        $this->assertSoftDeleted('tvas', ['id' => $myTva->id]);

        // …the other tenant's rows untouched.
        $this->assertDatabaseHas('bookings', ['id' => $theirs->id]);
        $this->assertDatabaseHas('tvas', ['id' => $theirTva->id, 'deleted_at' => null]);
    }

    public function test_bulk_destroy_requires_delete_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking();

        $this->actingAs($noPerms)
            ->post(route('booking.bulk-destroy'), ['ids' => [$booking->id]])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    // ── BookingController::bulkMarkPaid (IST-233) ────────────────────────────

    public function test_bulk_mark_paid_records_payment_and_facture_per_booking(): void
    {
        // Company settings the facture generation reads.
        foreach (['company_name' => 'Co', 'company_address' => 'Addr', 'ice' => 'I', 'rc' => 'R', 'if' => 'F'] as $n => $v) {
            \App\Models\Setting::create(['name' => $n, 'value' => $v, 'parent_id' => $this->owner->id]);
        }
        $a = $this->makeBooking(['amount' => 600, 'payment_status' => 'impaye']);
        $b = $this->makeBooking(['amount' => 400, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), [
                'ids'            => [$a->id, $b->id],
                'payment_method' => 'Virement bancaire',
                'date'           => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('booking.index'))
            ->assertSessionHas('success');

        // Each booking: a payment for its full balance, a facture, status paye.
        foreach ([[$a->id, 600], [$b->id, 400]] as [$id, $amount]) {
            $this->assertDatabaseHas('booking_payments', [
                'booking_id' => $id, 'amount' => $amount, 'payment_method' => 'Virement bancaire',
            ]);
            $this->assertDatabaseHas('bookings', ['id' => $id, 'payment_status' => 'paye']);
            $this->assertDatabaseHas('tvas', ['booking_id' => $id, 'parent_id' => $this->owner->id, 'deleted_at' => null]);
        }
    }

    public function test_bulk_mark_paid_skips_fully_paid_bookings(): void
    {
        foreach (['company_name' => 'Co', 'company_address' => 'Addr', 'ice' => 'I', 'rc' => 'R', 'if' => 'F'] as $n => $v) {
            \App\Models\Setting::create(['name' => $n, 'value' => $v, 'parent_id' => $this->owner->id]);
        }
        $paid = $this->makeBooking(['amount' => 500, 'payment_status' => 'paye']);
        BookingPayment::factory()->create(['booking_id' => $paid->id, 'parent_id' => $this->owner->id, 'amount' => 500]);
        $unpaid = $this->makeBooking(['amount' => 300, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), [
                'ids' => [$paid->id, $unpaid->id], 'payment_method' => 'Carte',
            ])
            ->assertRedirect()->assertSessionHas('success');

        // The fully-paid booking gets NO new payment (still exactly 1).
        $this->assertSame(1, BookingPayment::where('booking_id', $paid->id)->count());
        // The unpaid one is now paid with a recorded payment.
        $this->assertDatabaseHas('booking_payments', ['booking_id' => $unpaid->id, 'amount' => 300]);
        $this->assertDatabaseHas('bookings', ['id' => $unpaid->id, 'payment_status' => 'paye']);
    }

    public function test_bulk_mark_paid_skips_cash_over_5000(): void
    {
        // The skip branch, pinned explicitly — directonderweg now splits instead.
        config(['client.features.cash_split' => false]);
        $big = $this->makeBooking(['amount' => 6000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), [
                'ids' => [$big->id], 'payment_method' => 'Espece',
            ])
            ->assertRedirect()->assertSessionHas('success');

        // Over the cash limit → no payment recorded, status unchanged.
        $this->assertSame(0, BookingPayment::where('booking_id', $big->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $big->id, 'payment_status' => 'impaye']);
    }

    public function test_bulk_mark_paid_splits_cash_over_cap_when_flag_on(): void
    {
        config(['client.features.cash_split' => true]);
        $big = $this->makeBooking([
            'amount' => 13000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), [
                'ids' => [$big->id], 'payment_method' => 'Espece',
            ])
            ->assertRedirect()->assertSessionHas('success');

        // Split into compliant receipts instead of being skipped.
        $this->assertSame(3, BookingPayment::where('booking_id', $big->id)->count());
        $this->assertSame(3, Tva::where('booking_id', $big->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $big->id, 'payment_status' => 'paye']);
    }

    public function test_bulk_mark_paid_requires_payment_method(): void
    {
        $booking = $this->makeBooking(['payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), ['ids' => [$booking->id]])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, BookingPayment::where('booking_id', $booking->id)->count());
    }

    public function test_bulk_mark_paid_requires_create_booking_payment_permission(): void
    {
        // Bulk now creates payments + factures, so 'edit booking' alone is NOT
        // enough — it requires 'create booking payment' like the single flow.
        $editOnly = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $editOnly->givePermissionTo('edit booking');
        $booking = $this->makeBooking(['payment_status' => 'impaye']);

        $this->actingAs($editOnly)
            ->post(route('booking.bulk-mark-paid'), ['ids' => [$booking->id], 'payment_method' => 'Carte'])
            ->assertSessionHas('error');

        $this->assertSame(0, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'impaye']);
    }

    public function test_bulk_mark_paid_only_touches_callers_bookings(): void
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $foreign = Booking::factory()->create(['parent_id' => $otherOwner->id, 'amount' => 500, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-mark-paid'), [
                'ids' => [$foreign->id], 'payment_method' => 'Carte',
            ])
            ->assertRedirect()->assertSessionHas('success');

        // Cross-tenant booking untouched.
        $this->assertSame(0, BookingPayment::where('booking_id', $foreign->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $foreign->id, 'payment_status' => 'impaye']);
    }

    // ── BookingController::paymentStore ──────────────────────────────────────

    public function test_payment_store_creates_payment_and_marks_partially_paid(): void
    {
        $booking = $this->makeBooking(['amount' => 600, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 200,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Virement bancaire',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('booking_payments', [
            'booking_id'     => $booking->id,
            'amount'         => 200,
            'payment_method' => 'Virement bancaire',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id'             => $booking->id,
            'payment_status' => 'partiellement_paye',
        ]);
    }

    public function test_payment_store_marks_paid_when_balance_cleared(): void
    {
        $booking = $this->makeBooking(['amount' => 300, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 300,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Carte',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id'             => $booking->id,
            'payment_status' => 'paye',
        ]);
    }

    public function test_payment_store_rejects_cash_above_5000(): void
    {
        // Pins the refusal branch itself, so it no longer depends on which
        // clients happen to have cash_split off (directonderweg now has it on).
        config(['client.features.cash_split' => false]);
        $booking = $this->makeBooking(['amount' => 10000]);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 5001,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseMissing('booking_payments', ['booking_id' => $booking->id]);
    }

    public function test_payment_store_splits_cash_over_cap_when_flag_on(): void
    {
        config(['client.features.cash_split' => true]);
        $booking = $this->makeBooking([
            'amount'     => 13000,
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 13000,
                'date'           => '2026-07-01',
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // 13000 → three receipts, each within the 5000 cap.
        $payments = BookingPayment::where('booking_id', $booking->id)->orderBy('id')->get();
        $this->assertCount(3, $payments);
        $this->assertEqualsCanonicalizing([5000.0, 5000.0, 3000.0], $payments->pluck('amount')->map(fn ($a) => (float) $a)->all());
        foreach ($payments as $p) {
            $this->assertLessThanOrEqual(5000, (float) $p->amount);
            $this->assertSame('Espece', $p->payment_method);
        }

        // One facture per receipt; rental days apportioned back to the total (10).
        $this->assertSame(3, Tva::where('booking_id', $booking->id)->count());
        $this->assertSame(10.0, (float) Tva::where('booking_id', $booking->id)->sum('quantity'));

        // Distinct days spread across the rental period.
        $dates = $payments->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $this->assertSame($dates, array_values(array_unique($dates)));

        // Full balance cleared.
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paye']);
    }

    public function test_split_generates_successive_facture_numbers(): void
    {
        config(['client.features.cash_split' => true]);
        // Establish the current global invoice-number high-water mark.
        Tva::factory()->create(['facture_number' => 500, 'parent_id' => $this->owner->id]);

        $booking = $this->makeBooking([
            'amount' => 13000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 13000,
                'date'           => '2026-07-01',
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // The three receipts continue the sequence strictly consecutively, no gaps.
        $numbers = Tva::where('booking_id', $booking->id)
            ->orderBy('id')
            ->pluck('facture_number')
            ->map(fn ($n) => (int) $n)
            ->all();

        $this->assertSame([501, 502, 503], $numbers);
    }

    public function test_payment_store_cash_at_cap_is_not_split(): void
    {
        config(['client.features.cash_split' => true]);
        $booking = $this->makeBooking(['amount' => 5000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 5000,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, BookingPayment::where('booking_id', $booking->id)->count());
    }

    public function test_payment_store_non_cash_over_cap_is_not_split(): void
    {
        config(['client.features.cash_split' => true]);
        $booking = $this->makeBooking(['amount' => 13000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 13000,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Virement bancaire',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Non-cash is never split, regardless of the flag.
        $this->assertSame(1, BookingPayment::where('booking_id', $booking->id)->count());
    }

    public function test_payment_store_still_rejects_cash_over_cap_when_flag_off(): void
    {
        // Set explicitly rather than inherited: directonderweg turned cash_split
        // on (2026-08-10), and this test is about the off branch, not about any
        // particular client's current setting.
        config(['client.features.cash_split' => false]);
        $booking = $this->makeBooking(['amount' => 13000]);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 6000,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseMissing('booking_payments', ['booking_id' => $booking->id]);
    }

    // ── BookingController::paymentSplitPreview ───────────────────────────────

    public function test_split_preview_returns_plan_when_flag_on(): void
    {
        config(['client.features.cash_split' => true]);
        $booking = $this->makeBooking([
            'amount' => 13000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
        ]);

        $this->actingAs($this->owner)
            ->postJson(route('booking.payment.split-preview', $booking->id), [
                'amount' => 13000, 'payment_method' => 'Espece',
            ])
            ->assertOk()
            ->assertJson(['split' => true, 'count' => 3, 'total' => 13000])
            ->assertJsonCount(3, 'receipts')
            ->assertJsonPath('receipts.0.amount', 5000)
            ->assertJsonPath('receipts.2.amount', 3000);
    }

    public function test_split_preview_returns_false_when_flag_off(): void
    {
        config(['client.features.cash_split' => false]);
        $booking = $this->makeBooking(['amount' => 13000]);

        $this->actingAs($this->owner)
            ->postJson(route('booking.payment.split-preview', $booking->id), [
                'amount' => 13000, 'payment_method' => 'Espece',
            ])
            ->assertOk()
            ->assertJson(['split' => false]);
    }

    public function test_split_preview_requires_permission(): void
    {
        config(['client.features.cash_split' => true]);
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking(['amount' => 13000]);

        $this->actingAs($noPerms)
            ->postJson(route('booking.payment.split-preview', $booking->id), [
                'amount' => 13000, 'payment_method' => 'Espece',
            ])
            ->assertStatus(403);
    }

    public function test_payment_store_rejects_zero_amount(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 0,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Carte',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['amount']);
    }

    public function test_payment_store_requires_create_booking_payment_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking();

        $this->actingAs($noPerms)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 100,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Carte',
            ])
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── invoice_on_full_payment: defer factures until fully paid ─────────────

    public function test_partial_payment_defers_invoice_when_flag_on(): void
    {
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking(['amount' => 1000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount' => 300, 'date' => now()->format('Y-m-d'), 'payment_method' => 'Carte',
            ])
            ->assertRedirect()->assertSessionHas('success');

        // Payment recorded, but no invoice while the balance is outstanding.
        $this->assertDatabaseHas('booking_payments', ['booking_id' => $booking->id, 'amount' => 300]);
        $this->assertSame(0, Tva::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'partiellement_paye']);
    }

    public function test_final_payment_flushes_all_invoices_when_flag_on(): void
    {
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking(['amount' => 1000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 300, 'date' => '2026-07-01', 'payment_method' => 'Carte',
        ])->assertSessionHas('success');
        $this->assertSame(0, Tva::where('booking_id', $booking->id)->count());

        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 700, 'date' => '2026-07-05', 'payment_method' => 'Carte',
        ])->assertSessionHas('success');

        // Balance cleared → one invoice per payment, emitted together, successive.
        $this->assertSame(2, Tva::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paye']);
        $nums = Tva::where('booking_id', $booking->id)->orderBy('id')->pluck('facture_number')->map(fn ($n) => (int) $n)->all();
        $this->assertSame($nums[0] + 1, $nums[1]);
    }

    public function test_flag_off_invoices_each_payment_immediately(): void
    {
        // Default (directonderweg) keeps one invoice per payment, incl. partial.
        $booking = $this->makeBooking(['amount' => 1000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 300, 'date' => now()->format('Y-m-d'), 'payment_method' => 'Carte',
        ])->assertSessionHas('success');

        $this->assertSame(1, Tva::where('booking_id', $booking->id)->count());
    }

    public function test_cash_split_full_payment_flushes_receipts_when_both_flags_on(): void
    {
        config(['client.features.cash_split' => true, 'client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking([
            'amount' => 13000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        // One 13000 cash payment clears the booking and splits into 3 receipts.
        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 13000, 'date' => '2026-07-01', 'payment_method' => 'Espece',
        ])->assertSessionHas('success');

        $this->assertSame(3, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertSame(3, Tva::where('booking_id', $booking->id)->count());
        foreach (Tva::where('booking_id', $booking->id)->get() as $t) {
            $this->assertLessThanOrEqual(5000, (float) $t->montant_ttc);
        }
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paye']);
    }

    public function test_cash_split_partial_defers_then_flushes_when_both_flags_on(): void
    {
        config(['client.features.cash_split' => true, 'client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking([
            'amount' => 20000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        // 13000 cash → 3 receipts, but 7000 still due → no invoices yet.
        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 13000, 'date' => '2026-07-01', 'payment_method' => 'Espece',
        ])->assertSessionHas('success');
        $this->assertSame(3, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertSame(0, Tva::where('booking_id', $booking->id)->count());

        // Clear the remaining 7000 → flush invoices for all four payments.
        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 7000, 'date' => '2026-07-11', 'payment_method' => 'Carte',
        ])->assertSessionHas('success');
        $this->assertSame(4, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertSame(4, Tva::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paye']);
    }

    public function test_fully_paid_via_decimal_installments_is_invoiced_when_flag_on(): void
    {
        // Regression (review #1): getTotalDueAmount sums float payment amounts,
        // so cent-valued installments leave a sub-cent residual — 1.13 + 0.18 +
        // 0.69 = 2.00, but the float sum is 1.9999…, leaving due ≈ 1e-16 > 0.
        // Without rounding the "fully paid" test, the booking reads as still
        // owing and its invoices are never flushed. The triple is order-robust:
        // the residual stays in (0, 0.005) for any payment ordering.
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking(['amount' => 2, 'payment_status' => 'impaye']);

        foreach (['1.13', '0.18', '0.69'] as $i => $amt) {
            $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
                'amount' => $amt, 'date' => '2026-07-0' . ($i + 1), 'payment_method' => 'Carte',
            ])->assertSessionHas('success');
        }

        // Balance cleared (bar a float residual) → all three payments invoiced.
        $this->assertSame(3, Tva::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'paye']);
    }

    public function test_manual_quantity_override_survives_deferred_flush(): void
    {
        // Option 3: an explicit "Quantity (Days)" is persisted on the payment and
        // used at flush, instead of being re-derived. Booking 8000 over 10 days,
        // paid in full with days=4 → invoice shows 4, not the proportional 10.
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking([
            'amount' => 8000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 8000, 'date' => '2026-07-01', 'payment_method' => 'Carte', 'quantity' => 4,
        ])->assertSessionHas('success');

        $this->assertSame(4, (int) Tva::where('booking_id', $booking->id)->firstOrFail()->quantity);
    }

    public function test_split_apportioned_days_survive_deferred_flush(): void
    {
        // Option 3: cash-split receipts persist their apportioned days, so the
        // issued invoices match the split plan (and the preview). 9000 over 10
        // days splits 5000/4000 → 5/5 days; proportional re-derivation would
        // have given 6/4.
        config(['client.features.cash_split' => true, 'client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking([
            'amount' => 9000, 'start_date' => '2026-07-01', 'end_date' => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 9000, 'date' => '2026-07-01', 'payment_method' => 'Espece',
        ])->assertSessionHas('success');

        $days = Tva::where('booking_id', $booking->id)->orderBy('id')->pluck('quantity')->map(fn ($q) => (int) $q)->all();
        $this->assertSame([5, 5], $days);
    }

    public function test_flush_does_not_regenerate_a_soft_deleted_invoice(): void
    {
        config(['client.features.invoice_on_full_payment' => true]);
        $booking = $this->makeBooking(['amount' => 1000, 'payment_status' => 'impaye']);

        // Full payment → one invoice flushed.
        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 1000, 'date' => '2026-07-01', 'payment_method' => 'Carte',
        ])->assertSessionHas('success');
        $this->assertSame(1, Tva::where('booking_id', $booking->id)->count());

        // The invoice is deleted (soft delete), then a later payment lands.
        Tva::where('booking_id', $booking->id)->firstOrFail()->delete();
        $this->actingAs($this->owner)->post(route('booking.payment.store', $booking->id), [
            'amount' => 200, 'date' => '2026-07-05', 'payment_method' => 'Carte',
        ])->assertSessionHas('success');

        // The trashed invoice is NOT regenerated — only the new payment is invoiced.
        $this->assertSame(1, Tva::where('booking_id', $booking->id)->count());          // active
        $this->assertSame(2, Tva::withTrashed()->where('booking_id', $booking->id)->count()); // + trashed
    }

    // ── BookingController::paymentStore — Inertia requests ───────────────────

    public function test_payment_store_inertia_error_returns_redirect_not_json(): void
    {
        // Relies on cash-over-cap being an *error*, so pin the refusal branch.
        config(['client.features.cash_split' => false]);
        $booking = $this->makeBooking(['amount' => 10000]);

        $this->actingAs($this->owner)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 5001,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseMissing('booking_payments', ['booking_id' => $booking->id]);
    }

    public function test_payment_store_inertia_success_returns_redirect_not_json(): void
    {
        $booking = $this->makeBooking(['amount' => 300]);

        $this->actingAs($this->owner)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 100,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Carte',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    // ── BookingController::paymentDestroy ─────────────────────────────────────

    public function test_payment_destroy_deletes_payment_and_recalculates_to_impaye(): void
    {
        $booking = $this->makeBooking(['amount' => 300, 'payment_status' => 'partiellement_paye']);
        $payment = BookingPayment::factory()->create([
            'booking_id'     => $booking->id,
            'amount'         => 100,
            'payment_method' => 'Carte',
            'parent_id'      => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [$booking->id, $payment->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('booking_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('bookings', [
            'id'             => $booking->id,
            'payment_status' => 'impaye',
        ]);
    }

    public function test_payment_destroy_deletes_linked_tva(): void
    {
        $booking = $this->makeBooking(['amount' => 300]);
        $payment = BookingPayment::factory()->create([
            'booking_id' => $booking->id,
            'amount'     => 100,
            'parent_id'  => $this->owner->id,
        ]);
        $tva = Tva::factory()->create([
            'booking_id' => $booking->id,
            'idpaiment'  => $payment->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [$booking->id, $payment->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('tvas', ['id' => $tva->id]);
    }

    // ── paymentDestroy / paymentCreate — tenant + ordering ────────────────────
    // paymentDestroy found the payment by id alone, soft-deleted its factures
    // and deleted it, and only THEN loaded the booking (unscoped, unguarded).
    // paymentCreate had no permission check and no tenant scope.

    private function foreignBookingWithPayment(): array
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $booking = Booking::factory()->create(['parent_id' => $otherOwner->id, 'amount' => 300, 'payment_status' => 'partiellement_paye']);
        $payment = BookingPayment::factory()->create(['booking_id' => $booking->id, 'amount' => 100, 'parent_id' => $otherOwner->id]);
        $tva = Tva::factory()->create(['booking_id' => $booking->id, 'idpaiment' => $payment->id, 'parent_id' => $otherOwner->id]);

        return [$booking, $payment, $tva];
    }

    public function test_payment_destroy_refuses_other_tenants_booking(): void
    {
        [$booking, $payment, $tva] = $this->foreignBookingWithPayment();

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [$booking->id, $payment->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('tvas', ['id' => $tva->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'payment_status' => 'partiellement_paye']);
    }

    public function test_payment_destroy_refuses_payment_that_belongs_to_another_booking(): void
    {
        // Own booking in the URL, foreign payment id: the payment must be
        // constrained to the booking, not found by id alone.
        [, $payment, $tva] = $this->foreignBookingWithPayment();
        $own = $this->makeBooking();

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [$own->id, $payment->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('tvas', ['id' => $tva->id, 'deleted_at' => null]);
    }

    public function test_payment_destroy_with_unknown_booking_deletes_nothing(): void
    {
        // Previously: payment + factures deleted, then a null-deref 500 on the
        // missing booking, leaving the delete committed.
        $booking = $this->makeBooking(['amount' => 300]);
        $payment = BookingPayment::factory()->create(['booking_id' => $booking->id, 'amount' => 100, 'parent_id' => $this->owner->id]);
        $tva = Tva::factory()->create(['booking_id' => $booking->id, 'idpaiment' => $payment->id, 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [999999, $payment->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('tvas', ['id' => $tva->id, 'deleted_at' => null]);
    }

    public function test_payment_create_denied_without_create_booking_payment(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $booking = $this->makeBooking();

        $this->actingAs($noPerms)
            ->get(route('booking.payment.create', $booking->id))
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_payment_create_refuses_other_tenants_booking(): void
    {
        [$booking] = $this->foreignBookingWithPayment();

        $this->actingAs($this->owner)
            ->get(route('booking.payment.create', $booking->id))
            ->assertNotFound();
    }

    public function test_payment_create_renders_for_own_booking(): void
    {
        $booking = $this->makeBooking(['amount' => 300]);

        $this->actingAs($this->owner)
            ->get(route('booking.payment.create', $booking->id))
            ->assertOk();
    }

    // ── BookingController::planning (BAN-238) ────────────────────────────────

    public function test_planning_returns_200_with_booking_and_vehicle_data(): void
    {
        $this->makeBooking();

        $this->actingAs($this->owner)
            ->get(route('planning'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Planning')
                ->has('bookingData')
                ->has('vehicleData')
            );
    }

    public function test_planning_denied_without_manage_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('planning'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_planning_fires_at_most_2_booking_related_queries(): void
    {
        // Create 5 bookings — before the fix each triggered a lazy driver load
        foreach (range(1, 5) as $_) {
            $this->makeBooking();
        }

        $bookingQueries = 0;
        DB::listen(function ($query) use (&$bookingQueries) {
            if (preg_match('/\bbookings\b|\busers\b/i', $query->sql)) {
                $bookingQueries++;
            }
        });

        $this->actingAs($this->owner)->get(route('planning'))->assertOk();

        $this->assertLessThanOrEqual(2, $bookingQueries,
            "planning() should fire at most 2 queries for bookings+drivers (fired {$bookingQueries})"
        );
    }

    // ── BookingController::importExcel (BAN-236) ─────────────────────────────

    private function makeImportFile(array $dataRows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['NOM & PRENOM', 'DATE DEBUT', 'HEURE', 'LA MARQUE', 'IMMATRICULATION', 'DATE RETOUR', 'HEURE RETOUR', 'PERIODE', 'PRIX', 'METHOD'],
            ...$dataRows,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_creates_booking_for_new_driver_and_vehicle(): void
    {
        $file = $this->makeImportFile([
            ['John Doe', '2026-06-01', '09:00', 'Toyota', 'AA-123-BB', '2026-06-05', '18:00', '4', '500', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['parent_id' => $this->owner->id, 'amount' => 500]);
        $this->assertDatabaseHas('users', ['name' => 'John Doe', 'type' => 'driver', 'parent_id' => $this->owner->id]);
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'AA-123-BB', 'parent_id' => $this->owner->id]);
    }

    // ── Chèque payment method + import normalization (BAN-241) ────────────────

    public function test_import_normalizes_recognized_payment_methods(): void
    {
        // Excel files carry accented / lowercase / misspelled French; each must
        // land on the canonical method used everywhere else in the app.
        $file = $this->makeImportFile([
            ['Cheque Payer',  '2026-06-01', '09:00', 'Toyota', 'CQ-001-BB', '2026-06-02', '18:00', '1', '100', 'chèque'],
            ['Espece Payer',  '2026-06-01', '09:00', 'Toyota', 'CQ-002-BB', '2026-06-02', '18:00', '1', '100', 'espèce'],
            ['Virment Payer', '2026-06-01', '09:00', 'Toyota', 'CQ-003-BB', '2026-06-02', '18:00', '1', '100', 'virment'],
            ['Carte Payer',   '2026-06-01', '09:00', 'Toyota', 'CQ-004-BB', '2026-06-02', '18:00', '1', '100', 'CARTE'],
            ['Cash Payer',    '2026-06-01', '09:00', 'Toyota', 'CQ-005-BB', '2026-06-02', '18:00', '1', '100', 'cash'],
            ['Cheques Payer', '2026-06-01', '09:00', 'Toyota', 'CQ-008-BB', '2026-06-02', '18:00', '1', '100', 'Chèques'],
            ['Canon Payer',   '2026-06-01', '09:00', 'Toyota', 'CQ-009-BB', '2026-06-02', '18:00', '1', '100', 'Virement bancaire'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ([
            'CQ-001-BB' => 'Chèque',
            'CQ-002-BB' => 'Espece',
            'CQ-003-BB' => 'Virement bancaire',
            'CQ-004-BB' => 'Carte',
            'CQ-005-BB' => 'Espece',
            'CQ-008-BB' => 'Chèque',            // plural + accent → canonical
            'CQ-009-BB' => 'Virement bancaire', // already-canonical value round-trips
        ] as $plate => $expected) {
            $vehicle = Vehicle::where('license_plate', $plate)->where('parent_id', $this->owner->id)->firstOrFail();
            $this->assertDatabaseHas('bookings', [
                'vehicle'        => $vehicle->id,
                'payment_method' => $expected,
            ]);
        }
    }

    public function test_import_keeps_unrecognized_payment_method_raw(): void
    {
        $file = $this->makeImportFile([
            ['Bitcoin Payer', '2026-06-01', '09:00', 'Toyota', 'CQ-006-BB', '2026-06-02', '18:00', '1', '100', '  bitcoin  '],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $vehicle = Vehicle::where('license_plate', 'CQ-006-BB')->where('parent_id', $this->owner->id)->firstOrFail();
        $this->assertDatabaseHas('bookings', [
            'vehicle'        => $vehicle->id,
            'payment_method' => 'bitcoin', // trimmed, otherwise unchanged (no data loss)
        ]);
    }

    public function test_import_empty_payment_method_stays_null(): void
    {
        $file = $this->makeImportFile([
            ['Blank Payer', '2026-06-01', '09:00', 'Toyota', 'CQ-007-BB', '2026-06-02', '18:00', '1', '100', ''],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $vehicle = Vehicle::where('license_plate', 'CQ-007-BB')->where('parent_id', $this->owner->id)->firstOrFail();
        $this->assertDatabaseHas('bookings', [
            'vehicle'        => $vehicle->id,
            'payment_method' => null,
        ]);
    }

    public function test_index_exposes_cheque_payment_method(): void
    {
        $this->makeBooking();

        $this->actingAs($this->owner)
            ->get(route('booking.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Booking/Index')
                ->where('paymentMethods', fn ($methods) => collect($methods)
                    ->contains(fn ($m) => ($m['value'] ?? null) === 'Chèque' && ($m['label'] ?? null) === 'Chèque'))
            );
    }

    public function test_payment_store_cheque_over_cap_is_not_split(): void
    {
        // Chèque must never hit the 5000-MAD cash ceiling (which keys on
        // 'espece'), even with the cash_split flag on — a single payment.
        config(['client.features.cash_split' => true]);
        $booking = $this->makeBooking(['amount' => 13000, 'payment_status' => 'impaye']);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 13000,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Chèque',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, BookingPayment::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('booking_payments', [
            'booking_id'     => $booking->id,
            'amount'         => 13000,
            'payment_method' => 'Chèque',
        ]);
    }

    public function test_import_reuses_vehicle_when_plate_differs_only_by_nbsp(): void
    {
        // Pre-existing vehicle; the import row's plate carries a leading
        // non-breaking space (as pasted from Excel). It must reuse the vehicle,
        // not create a duplicate (IST-229).
        $this->makeBooking(); // ensures $this->vehicle exists for the owner
        $existing = Vehicle::factory()->create(['parent_id' => $this->owner->id, 'license_plate' => 'NB-555-SP']);
        $before = Vehicle::where('parent_id', $this->owner->id)->count();

        $file = $this->makeImportFile([
            ['Nora Space', '2026-06-01', '09:00', 'Seat', "\u{00a0}NB-555-SP", '2026-06-03', '18:00', '2', '250', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame($before, Vehicle::where('parent_id', $this->owner->id)->count(), 'NBSP plate variant must not create a duplicate vehicle');
        $this->assertSame(1, Vehicle::where('parent_id', $this->owner->id)->where('license_plate', 'NB-555-SP')->count());
    }

    public function test_import_two_new_drivers_with_colliding_emails_get_unique_emails(): void
    {
        // "Ali O'Brien" and "Ali O Brien" both normalise to ali.o.brien@import.local
        // (space → '.' and apostrophe → '.' via str_replace) — second must get a suffix
        $file = $this->makeImportFile([
            ["Ali O'Brien", '2026-06-01', '09:00', 'Ford', 'XX-001-YY', '2026-06-03', '18:00', '2', '200', 'cash'],
            ['Ali O Brien',  '2026-06-04', '09:00', 'Ford', 'XX-002-YY', '2026-06-06', '18:00', '2', '200', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $emails = User::where('type', 'driver')->where('parent_id', $this->owner->id)
            ->pluck('email')->toArray();

        $this->assertCount(count(array_unique($emails)), $emails, 'All imported driver emails must be unique');
    }

    public function test_import_driver_ids_increment_correctly_across_rows(): void
    {
        $file = $this->makeImportFile([
            ['Alpha Driver', '2026-06-01', '09:00', 'BMW', 'ZZ-001-AA', '2026-06-02', '18:00', '1', '100', 'cash'],
            ['Beta Driver',  '2026-06-03', '09:00', 'BMW', 'ZZ-002-AA', '2026-06-04', '18:00', '1', '100', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $driverIds = Driver::where('parent_id', $this->owner->id)->pluck('driver_id')->sort()->values()->toArray();
        $this->assertCount(count(array_unique($driverIds)), $driverIds, 'driver_id must be unique across imported drivers');
    }

    public function test_import_reuses_existing_driver_and_vehicle_across_rows(): void
    {
        $file = $this->makeImportFile([
            ['John Doe', '2026-06-01', '09:00', 'Toyota', 'AA-123-BB', '2026-06-03', '18:00', '2', '300', 'cash'],
            ['John Doe', '2026-06-05', '09:00', 'Toyota', 'AA-123-BB', '2026-06-07', '18:00', '2', '300', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(1, User::where('name', 'John Doe')->where('type', 'driver')->count(), 'Same driver name must not create a duplicate user');
        $this->assertSame(1, Vehicle::where('license_plate', 'AA-123-BB')->where('parent_id', $this->owner->id)->count(), 'Same plate must not create a duplicate vehicle');
        $this->assertSame(2, Booking::where('parent_id', $this->owner->id)->count(), 'Both rows should produce a booking');
    }

    public function test_import_denied_without_create_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $file = $this->makeImportFile([
            ['Jane Doe', '2026-06-01', '09:00', 'Fiat', 'BB-999-CC', '2026-06-02', '18:00', '1', '100', 'cash'],
        ]);

        $this->actingAs($noPerms)
            ->post(route('booking.import'), ['file' => $file])
            ->assertSessionHas('error');
    }

    public function test_import_skips_rows_where_start_is_not_before_end(): void
    {
        // Row 1: return date before start (day/month swapped) → must be skipped.
        // Row 2: valid → must be imported. Proves the guard is per-row and that
        // a skipped row creates no driver/vehicle/booking.
        $file = $this->makeImportFile([
            ['Swap Victim',  '2026-05-01', '09:00', 'Seat', 'SW-001-AP', '2026-03-05', '18:00', '2', '800', 'cash'],
            ['Valid Client', '2026-05-01', '09:00', 'Seat', 'VL-002-AP', '2026-05-03', '18:00', '2', '800', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_skipped');

        // Swapped row imported nothing…
        $this->assertDatabaseMissing('users', ['name' => 'Swap Victim']);
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'SW-001-AP']);
        // …valid row went through.
        $this->assertDatabaseHas('users', ['name' => 'Valid Client', 'parent_id' => $this->owner->id]);
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'VL-002-AP', 'parent_id' => $this->owner->id]);
    }

    public function test_import_allows_same_day_rental_when_return_time_is_later(): void
    {
        // Same calendar day is valid as long as the return time is after pickup.
        $file = $this->makeImportFile([
            ['Same Day', '2026-05-10', '09:00', 'Seat', 'SD-003-AP', '2026-05-10', '18:00', '1', '300', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', ['license_plate' => 'SD-003-AP', 'parent_id' => $this->owner->id]);
    }

    public function test_import_parses_slash_dates_as_day_first(): void
    {
        // Slash dates are read day-first (d/m/Y), the import locale. "01/06/2026"
        // is 1 June (not 6 Jan), and "13/05/2026" → 13 May (IST-231).
        $file = $this->makeImportFile([
            ['Day First', '01/06/2026', '09:00', 'Seat', 'DF-010-AP', '13/06/2026', '18:00', '12', '700', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'parent_id'  => $this->owner->id,
            'start_date' => '2026-06-01', // 1 June, not 6 January
            'end_date'   => '2026-06-13',
        ]);
    }

    public function test_import_rejects_us_month_day_dates(): void
    {
        // A US m/d/Y value with day > 12 ("06/20/2026") is not valid d/m/Y, so it
        // is rejected as an invalid date rather than silently swapped (IST-231).
        $file = $this->makeImportFile([
            ['US Format', '06/20/2026', '09:00', 'Seat', 'US-011-AP', '06/25/2026', '18:00', '5', '500', 'cash'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_skipped');

        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'US-011-AP']);
    }
}
