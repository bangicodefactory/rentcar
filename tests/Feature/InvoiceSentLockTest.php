<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * An invoice marked "sent" has been handed to a client: its number must never
 * change or disappear. Found on directonderweg 2026-09-26, when deleting the
 * old August bookings trashed invoice #788, the one invoice a client held.
 */
class InvoiceSentLockTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage tva', 'manage booking', 'delete booking', 'create booking payment', 'delete booking payment'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        foreach (['company_name' => 'Test Co', 'company_address' => '1 Rue Test', 'ice' => 'ICE-1', 'rc' => 'RC-1', 'if' => 'IF-1'] as $name => $value) {
            \App\Models\Setting::create(['name' => $name, 'value' => $value, 'parent_id' => $this->owner->id]);
        }
    }

    /** A booking with one payment and its invoice. */
    private function invoicedBooking(string $number = '5', string $date = '2026-08-04', bool $sent = false): array
    {
        $booking = Booking::factory()->create(['parent_id' => $this->owner->id]);
        $payment = BookingPayment::factory()->create([
            'booking_id' => $booking->id, 'parent_id' => $this->owner->id, 'date' => $date, 'amount' => 1200,
        ]);
        $tva = Tva::factory()->withInvoice()->create([
            'parent_id' => $this->owner->id, 'booking_id' => $booking->id, 'idpaiment' => $payment->id,
            'facture_number' => $number, 'facture_date' => $date, 'montant_ttc' => 1200,
            'sent_at' => $sent ? now() : null,
        ]);

        return [$booking, $payment, $tva];
    }

    // ── mark / unmark ────────────────────────────────────────────────────────

    public function test_mark_sent_sets_sent_at_and_sent_by(): void
    {
        [, , $tva] = $this->invoicedBooking();

        $this->actingAs($this->owner)
            ->post(route('tva.mark-sent'), ['ids' => [$tva->id]])
            ->assertRedirect()->assertSessionHas('success');

        $tva->refresh();
        $this->assertNotNull($tva->sent_at);
        $this->assertSame($this->owner->id, (int) $tva->sent_by);
        $this->assertTrue($tva->isSent());
    }

    public function test_mark_sent_in_bulk_and_unmark(): void
    {
        [, , $a] = $this->invoicedBooking('1');
        [, , $b] = $this->invoicedBooking('2');
        [, , $c] = $this->invoicedBooking('3');

        $this->actingAs($this->owner)->post(route('tva.mark-sent'), ['ids' => [$a->id, $b->id]])->assertSessionHas('success');
        $this->assertTrue($a->fresh()->isSent());
        $this->assertTrue($b->fresh()->isSent());
        $this->assertFalse($c->fresh()->isSent());

        $this->actingAs($this->owner)->post(route('tva.unmark-sent'), ['ids' => [$a->id]])->assertSessionHas('success');
        $this->assertFalse($a->fresh()->isSent());
        $this->assertNull($a->fresh()->sent_by);
        $this->assertTrue($b->fresh()->isSent());
    }

    public function test_mark_sent_denied_without_manage_tva(): void
    {
        [, , $tva] = $this->invoicedBooking();
        $clerk = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($clerk)->post(route('tva.mark-sent'), ['ids' => [$tva->id]])->assertSessionHas('error');
        $this->assertFalse($tva->fresh()->isSent());
    }

    public function test_mark_sent_ignores_another_tenants_invoices(): void
    {
        $other = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $theirs = Tva::factory()->withInvoice()->create(['parent_id' => $other->id]);

        $this->actingAs($this->owner)->post(route('tva.mark-sent'), ['ids' => [$theirs->id]]);
        $this->assertFalse($theirs->fresh()->isSent());
    }

    // ── guards on the invoice itself ─────────────────────────────────────────

    public function test_a_sent_invoice_cannot_be_edited(): void
    {
        [, , $tva] = $this->invoicedBooking('788', '2026-08-04', true);

        $this->actingAs($this->owner)->put(route('tva.update', $tva), [
            'facture_date' => '2026-08-04', 'montant_ttc' => 1200, 'unit_price_ht' => 200,
            'tva' => 200, 'facture_number' => '999',
        ])->assertSessionHas('error');

        $this->assertSame('788', $tva->fresh()->facture_number);
    }

    public function test_a_sent_invoice_cannot_be_deleted(): void
    {
        [, , $tva] = $this->invoicedBooking('788', '2026-08-04', true);

        $this->actingAs($this->owner)->delete(route('tva.destroy', $tva))->assertSessionHas('error');
        $this->assertNotSoftDeleted($tva);
    }

    // ── guards on the booking / payment that owns it ─────────────────────────

    public function test_deleting_a_booking_with_a_sent_invoice_is_refused(): void
    {
        [$booking, , $tva] = $this->invoicedBooking('788', '2026-08-04', true);

        $this->actingAs($this->owner)->delete(route('booking.destroy', $booking->id))->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->assertNotSoftDeleted($tva);
    }

    public function test_deleting_a_booking_without_a_sent_invoice_still_works(): void
    {
        [$booking, , $tva] = $this->invoicedBooking('5', '2026-08-04', false);

        $this->actingAs($this->owner)->delete(route('booking.destroy', $booking->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertSoftDeleted($tva);
    }

    public function test_bulk_delete_deletes_nothing_when_any_booking_has_a_sent_invoice(): void
    {
        [$sentBooking, , $sentTva] = $this->invoicedBooking('788', '2026-08-04', true);
        [$plainBooking, , $plainTva] = $this->invoicedBooking('5', '2026-08-05', false);

        $this->actingAs($this->owner)
            ->post(route('booking.bulk-destroy'), ['ids' => [$sentBooking->id, $plainBooking->id]])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', ['id' => $sentBooking->id]);
        $this->assertDatabaseHas('bookings', ['id' => $plainBooking->id]);
        $this->assertNotSoftDeleted($sentTva);
        $this->assertNotSoftDeleted($plainTva);
    }

    public function test_deleting_a_payment_with_a_sent_invoice_is_refused(): void
    {
        [$booking, $payment, $tva] = $this->invoicedBooking('788', '2026-08-04', true);

        $this->actingAs($this->owner)
            ->delete(route('booking.payment.destroy', [$booking->id, $payment->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('booking_payments', ['id' => $payment->id]);
        $this->assertNotSoftDeleted($tva);
    }

    // ── monthly rebuild ──────────────────────────────────────────────────────

    public function test_monthly_rebuild_keeps_sent_invoices_and_numbers_the_rest_after_them(): void
    {
        [, $sentPayment, $sentTva] = $this->invoicedBooking('5', '2026-08-04', true);
        [, , $unsentTva] = $this->invoicedBooking('6', '2026-08-06', false);

        $this->actingAs($this->owner)
            ->post(route('tva.generate'), ['month' => '2026-08'])
            ->assertSessionHas('success');

        // The sent invoice is untouched: same row, same number, still active.
        $this->assertNotSoftDeleted($sentTva);
        $this->assertSame('5', $sentTva->fresh()->facture_number);
        // Its payment is not invoiced a second time.
        $this->assertSame(1, Tva::where('idpaiment', $sentPayment->id)->count());

        // The unsent invoice is re-issued after the highest number (5 → 6).
        $this->assertSoftDeleted($unsentTva);
        $this->assertSame(
            ['5', '6'],
            Tva::where('parent_id', $this->owner->id)->whereYear('facture_date', 2026)
                ->orderByRaw('CAST(facture_number AS UNSIGNED)')->pluck('facture_number')->all()
        );
    }
}
