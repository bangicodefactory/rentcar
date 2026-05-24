<?php

namespace Tests\Feature;

use App\Models\CouponHistory;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->subscription = Subscription::factory()->create(['package_amount' => 99.00]);
    }

    // ── unauthenticated redirects ─────────────────────────────────────────────

    public function test_bank_transfer_requires_auth(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $this->post(route('subscription.bank.transfer', $id))
            ->assertRedirect(route('login'));
    }

    public function test_bank_transfer_action_requires_auth(): void
    {
        $this->get(route('subscription.bank.transfer.action', [1, 'accept']))
            ->assertRedirect(route('login'));
    }

    public function test_paypal_init_requires_auth(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $this->post(route('subscription.paypal', $id))
            ->assertRedirect(route('login'));
    }

    public function test_paypal_status_requires_auth(): void
    {
        $this->get(route('subscription.paypal.status', [$this->subscription->id, 'cancel']))
            ->assertRedirect(route('login'));
    }

    public function test_flutterwave_init_requires_auth(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $this->post(route('subscription.flutterwave', $id))
            ->assertRedirect(route('login'));
    }

    public function test_flutterwave_status_requires_auth(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $this->get(route('subscription.flutterwave.status', [$id, 'txref123']))
            ->assertRedirect(route('login'));
    }

    // ── bank transfer: validation ─────────────────────────────────────────────

    public function test_bank_transfer_flashes_error_when_receipt_missing(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $this->actingAs($this->owner)
            ->post(route('subscription.bank.transfer', $id), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── bank transfer: happy path ─────────────────────────────────────────────

    public function test_bank_transfer_creates_pending_transaction(): void
    {
        Storage::fake('local');
        $id = Crypt::encrypt($this->subscription->id);

        $this->actingAs($this->owner)
            ->post(route('subscription.bank.transfer', $id), [
                'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'name'            => 'Jane Doe',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('package_transactions', [
            'subscription_id' => $this->subscription->id,
            'payment_type'    => 'Bank Transfer',
            'payment_status'  => 'Pending',
            'user_id'         => $this->owner->id,
            'holder_name'     => 'Jane Doe',
        ]);
    }

    public function test_bank_transfer_with_valid_coupon_creates_coupon_history(): void
    {
        Storage::fake('local');
        $id = Crypt::encrypt($this->subscription->id);

        \App\Models\Coupon::factory()->forPackage($this->subscription->id)->create([
            'code'      => 'DISC10',
            'type'      => 'percentage',
            'rate'      => 10,
            'use_limit' => 5,
        ]);

        $this->actingAs($this->owner)
            ->post(route('subscription.bank.transfer', $id), [
                'payment_receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'coupon'          => 'DISC10',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coupon_histories', [
            'coupon'  => \App\Models\Coupon::where('code', 'DISC10')->value('id'),
            'package' => $this->subscription->id,
        ]);
    }

    // ── bank transfer action ──────────────────────────────────────────────────

    public function test_bank_transfer_action_accept_marks_transaction_success(): void
    {
        $transaction = PackageTransaction::factory()->create([
            'user_id'         => $this->owner->id,
            'subscription_id' => $this->subscription->id,
            'payment_status'  => 'Pending',
        ]);

        $this->actingAs($this->owner)
            ->get(route('subscription.bank.transfer.action', [$transaction->id, 'accept']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('package_transactions', [
            'id'             => $transaction->id,
            'payment_status' => 'Success',
        ]);
    }

    public function test_bank_transfer_action_reject_marks_transaction_rejected(): void
    {
        $transaction = PackageTransaction::factory()->create([
            'user_id'         => $this->owner->id,
            'subscription_id' => $this->subscription->id,
            'payment_status'  => 'Pending',
        ]);

        $this->actingAs($this->owner)
            ->get(route('subscription.bank.transfer.action', [$transaction->id, 'reject']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('package_transactions', [
            'id'             => $transaction->id,
            'payment_status' => 'Reject',
        ]);
    }

    public function test_bank_transfer_action_reject_deletes_associated_coupon_history(): void
    {
        $transaction = PackageTransaction::factory()->create([
            'user_id'         => $this->owner->id,
            'subscription_id' => $this->subscription->id,
            'payment_status'  => 'Pending',
        ]);

        // The reject action queries CouponHistory by 'package' = transaction->id (see controller).
        $history = CouponHistory::factory()->create([
            'user_id' => $this->owner->id,
            'package' => $transaction->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('subscription.bank.transfer.action', [$transaction->id, 'reject']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('coupon_histories', ['id' => $history->id]);
    }

    // ── PayPal: cancel callback ───────────────────────────────────────────────

    public function test_paypal_cancel_callback_redirects_with_transaction_failed(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subscription.paypal.status', [$this->subscription->id, 'cancel']))
            ->assertRedirect()
            ->assertSessionHas('error', __('Transaction failed.'));
    }

    // ── Flutterwave: JSON response (no external API call) ─────────────────────
    //
    // TODO BAN-30: cover PayPal create-order + capture + decline paths via sandbox
    // smoke tests once the PayPalClient is injectable (currently `new`-instantiated
    // in the controller, which prevents clean mocking without @runInSeparateProcess).

    public function test_flutterwave_returns_payment_data_when_amount_positive(): void
    {
        $id = Crypt::encrypt($this->subscription->id);

        $response = $this->actingAs($this->owner)
            ->post(route('subscription.flutterwave', $id));

        $response->assertJson([
            'flag'        => 1,
            'email'       => $this->owner->email,
            'total_price' => $this->subscription->package_amount,
        ]);
    }
}
