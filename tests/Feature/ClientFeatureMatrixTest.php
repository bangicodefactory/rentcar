<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * What each client's config actually resolves to.
 *
 * Every other test that touches a flag forces it with `config([...])`, which
 * proves the *code branch* works but says nothing about what a given client is
 * really running. A flag flipped by accident — or a typo in a client file —
 * would change behaviour for a live deployment with nothing to catch it.
 *
 * These assertions are deliberately about money and legal behaviour, not the
 * whole flag list, so the file does not need editing every time a cosmetic
 * feature is added.
 */
class ClientFeatureMatrixTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    public function test_directonderweg_takes_no_online_payments(): void
    {
        // All four are `true` in _default.php and deliberately overridden, so
        // this client is the exception. Payments are recorded, never taken.
        $this->asClient('directonderweg');

        $this->assertFalse(feature('paypal'));
        $this->assertFalse(feature('stripe'));
        $this->assertFalse(feature('subscriptions'));
        $this->assertFalse(feature('booking_payment'));
    }

    public function test_directonderweg_splits_cash_over_the_ceiling(): void
    {
        $this->asClient('directonderweg');

        $this->assertTrue(feature('cash_split'), 'cash over 5000 would be refused at the counter');
        $this->assertSame(5000, (int) config('client.cash_payment_max'));
    }

    public function test_directonderweg_still_invoices_every_payment_immediately(): void
    {
        // Deliberately off: one facture per payment, including partials.
        $this->asClient('directonderweg');

        $this->assertFalse(feature('invoice_on_full_payment'));
    }

    public function test_directonderweg_does_not_run_traffic_violations(): void
    {
        // Turned off 2026-08-10. Every traffic-violation suite forces the flag
        // on so it can exercise the module, so this is the only thing standing
        // between a stray re-enable and a module appearing for a client that
        // does not want it. The 404 that results is asserted next to the module
        // in TrafficViolationControllerTest.
        $this->asClient('directonderweg');

        $this->assertFalse(feature('traffic_violations'));
    }

    /**
     * End to end under the client's *own* resolved config — no config() forcing.
     *
     * This is the assertion that would have failed before the flag flip, and the
     * one that fails if someone flips it back.
     */
    public function test_a_cash_payment_over_the_ceiling_splits_for_directonderweg(): void
    {
        $this->asClient('directonderweg');

        // `create booking payment` is the one booking.payment.store checks —
        // without it the request redirects back and records nothing.
        $permissions = ['manage booking', 'create booking', 'edit booking', 'create booking payment'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $owner->givePermissionTo($permissions);

        $booking = Booking::factory()->create([
            'parent_id'      => $owner->id,
            'vehicle'        => Vehicle::factory()->create(['parent_id' => $owner->id])->id,
            'driver'         => User::factory()->create(['parent_id' => $owner->id, 'type' => 'driver'])->id,
            'amount'         => 13000,
            'start_date'     => '2026-07-01',
            'end_date'       => '2026-07-11',
            'payment_status' => 'impaye',
        ]);

        $this->actingAs($owner)->post(route('booking.payment.store', $booking->id), [
            'amount'         => 13000,
            'date'           => '2026-07-01',
            'payment_method' => 'Espece',
        ]);

        $payments = BookingPayment::where('booking_id', $booking->id)->orderBy('id')->get();

        // 13000 over a 5000 cap → 5000 + 5000 + 3000, not one refused payment.
        $this->assertCount(3, $payments, 'cash was not split into compliant receipts');
        $this->assertEqualsCanonicalizing(
            [5000.0, 5000.0, 3000.0],
            $payments->pluck('amount')->map(fn ($a) => (float) $a)->all()
        );

        // Each receipt on its own day — two receipts sharing a date would put
        // more than the ceiling on one day, which is the rule being satisfied.
        $this->assertSame(3, $payments->pluck('date')->unique()->count(), 'receipts share a date');
    }
}
