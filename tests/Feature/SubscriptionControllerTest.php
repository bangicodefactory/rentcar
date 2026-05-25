<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = [
            'manage pricing packages',
            'create pricing packages',
            'edit pricing packages',
            'delete pricing packages',
            'buy pricing packages',
            'manage pricing transation',
        ];
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
        $this->get(route('subscriptions.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('subscriptions.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $sub = Subscription::factory()->create();
        $this->put(route('subscriptions.update', $sub))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $sub = Subscription::factory()->create();
        $this->delete(route('subscriptions.destroy', $sub))->assertRedirect(route('login'));
    }

    public function test_transaction_requires_auth(): void
    {
        $this->get(route('subscription.transaction'))->assertRedirect(route('login'));
    }

    public function test_stripe_payment_requires_auth(): void
    {
        $sub = Subscription::factory()->create();
        $this->post(route('subscription.stripe.payment', Crypt::encrypt($sub->id)))
            ->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_pricing_packages(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('subscriptions.index'))
            ->assertSessionHas('error');
    }

    public function test_store_denied_without_create_pricing_packages(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('subscriptions.store'), $this->validPayload())
            ->assertSessionHas('error');
    }

    public function test_update_denied_without_edit_pricing_packages(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $sub = Subscription::factory()->create();

        $this->actingAs($noPerms)
            ->put(route('subscriptions.update', $sub), $this->validPayload())
            ->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_pricing_packages(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $sub = Subscription::factory()->create();

        $this->actingAs($noPerms)
            ->delete(route('subscriptions.destroy', $sub))
            ->assertSessionHas('error');
    }

    public function test_transaction_denied_without_manage_pricing_transation(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('subscription.transaction'))
            ->assertSessionHas('error');
    }

    // ── SubscriptionController::index ─────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subscriptions.index'))
            ->assertOk();
    }

    // ── SubscriptionController::store ─────────────────────────────────────────

    public function test_store_creates_subscription_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('subscriptions.store'), $this->validPayload(['title' => 'Premium Plan']))
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', ['title' => 'Premium Plan']);
    }

    public function test_store_flashes_error_on_missing_title(): void
    {
        $this->actingAs($this->owner)
            ->post(route('subscriptions.store'), $this->validPayload(['title' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_on_missing_interval(): void
    {
        $this->actingAs($this->owner)
            ->post(route('subscriptions.store'), $this->validPayload(['interval' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_saves_enabled_logged_history_flag(): void
    {
        $this->actingAs($this->owner)
            ->post(route('subscriptions.store'), $this->validPayload([
                'title'                   => 'LogHistory Plan',
                'enabled_logged_history'  => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'title'                  => 'LogHistory Plan',
            'enabled_logged_history' => 1,
        ]);
    }

    public function test_store_stores_zero_for_enabled_logged_history_when_absent(): void
    {
        // Controller does isset($request->enabled_logged_history) ? 1 : 0
        // so omitting the field must save 0.
        $this->actingAs($this->owner)
            ->post(route('subscriptions.store'), $this->validPayload(['title' => 'NoLog Plan']))
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'title'                  => 'NoLog Plan',
            'enabled_logged_history' => 0,
        ]);
    }

    // ── SubscriptionController::update ────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $sub = Subscription::factory()->create(['title' => 'Old Name']);

        $this->actingAs($this->owner)
            ->put(route('subscriptions.update', $sub), $this->validPayload([
                'title'          => 'New Name',
                'package_amount' => 99,
                'interval'       => 'Yearly',
            ]))
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', [
            'id'             => $sub->id,
            'title'          => 'New Name',
            'package_amount' => 99,
            'interval'       => 'Yearly',
        ]);
    }

    public function test_update_flashes_error_on_missing_fields(): void
    {
        $sub = Subscription::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('subscriptions.update', $sub), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── SubscriptionController::destroy ───────────────────────────────────────

    public function test_destroy_deletes_subscription(): void
    {
        $sub = Subscription::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('subscriptions.destroy', $sub))
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subscriptions', ['id' => $sub->id]);
    }

    // ── SubscriptionController::show ──────────────────────────────────────────

    public function test_show_returns_200_with_encrypted_id(): void
    {
        $sub = Subscription::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('subscriptions.show', Crypt::encrypt($sub->id)))
            ->assertOk();
    }

    public function test_show_denied_without_buy_pricing_packages(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $sub = Subscription::factory()->create();

        $this->actingAs($noPerms)
            ->get(route('subscriptions.show', Crypt::encrypt($sub->id)))
            ->assertSessionHas('error');
    }

    // ── SubscriptionController::transaction ───────────────────────────────────

    public function test_transaction_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subscription.transaction'))
            ->assertOk();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'          => 'Basic Plan',
            'package_amount' => 29,
            'interval'       => 'Monthly',
            'user_limit'     => 5,
            'driver_limit'   => 10,
        ], $overrides);
    }
}
