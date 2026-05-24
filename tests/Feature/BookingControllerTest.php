<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Place;
use App\Models\Tva;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
        $booking = $this->makeBooking(['amount' => 10000]);

        $this->actingAs($this->owner)
            ->post(route('booking.payment.store', $booking->id), [
                'amount'         => 5001,
                'date'           => now()->format('Y-m-d'),
                'payment_method' => 'Espece',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('booking_payments', ['booking_id' => $booking->id]);
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
            ->assertSessionHas('error');
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
}
