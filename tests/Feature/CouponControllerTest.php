<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponHistory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = [
            'manage coupon', 'create coupon', 'edit coupon',
            'delete coupon', 'manage coupon history', 'delete coupon history',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->subscription = Subscription::factory()->create();
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('coupons.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('coupons.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $coupon = Coupon::factory()->create();
        $this->put(route('coupons.update', $coupon), [])->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $coupon = Coupon::factory()->create();
        $this->delete(route('coupons.destroy', $coupon))->assertRedirect(route('login'));
    }

    public function test_history_requires_auth(): void
    {
        $this->get(route('coupons.history'))->assertRedirect(route('login'));
    }

    public function test_apply_requires_auth(): void
    {
        $this->get(route('coupons.apply'))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_coupon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('coupons.index'))
            ->assertSessionHas('error');
    }

    public function test_store_denied_without_create_coupon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('coupons.store'), $this->validCouponPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_update_denied_without_edit_coupon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $coupon  = Coupon::factory()->create();

        $this->actingAs($noPerms)
            ->put(route('coupons.update', $coupon), $this->validCouponPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_destroy_denied_without_delete_coupon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $coupon  = Coupon::factory()->create();

        $this->actingAs($noPerms)
            ->delete(route('coupons.destroy', $coupon))
            ->assertSessionHas('error');
    }

    public function test_history_denied_without_manage_coupon_history(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('coupons.history'))
            ->assertSessionHas('error');
    }

    // ── CouponController::store ───────────────────────────────────────────────

    public function test_store_creates_coupon(): void
    {
        $this->actingAs($this->owner)
            ->post(route('coupons.store'), $this->validCouponPayload(['code' => 'SUMMER10']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER10', 'type' => 'percentage']);
    }

    public function test_store_flashes_error_on_missing_fields(): void
    {
        $this->actingAs($this->owner)
            ->post(route('coupons.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_stores_applicable_packages_as_comma_separated(): void
    {
        $sub2 = Subscription::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('coupons.store'), $this->validCouponPayload([
                'code' => 'MULTI',
                'applicable_packages' => [$this->subscription->id, $sub2->id],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $coupon = Coupon::where('code', 'MULTI')->firstOrFail();
        $this->assertStringContainsString((string) $this->subscription->id, $coupon->applicable_packages);
        $this->assertStringContainsString((string) $sub2->id, $coupon->applicable_packages);
    }

    // ── CouponController::update ──────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('coupons.update', $coupon), $this->validCouponPayload([
                'name' => 'New Name',
                'type' => 'fixed',
                'rate' => 20,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coupons', [
            'id'   => $coupon->id,
            'name' => 'New Name',
            'type' => 'fixed',
            'rate' => 20,
        ]);
    }

    public function test_update_flashes_error_on_missing_fields(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('coupons.update', $coupon), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── CouponController::destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('coupons.destroy', $coupon))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    // ── CouponController::historyDestroy ──────────────────────────────────────

    public function test_history_destroy_deletes_coupon_history(): void
    {
        $history = CouponHistory::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('coupons.history.destroy', $history->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('coupon_histories', ['id' => $history->id]);
    }

    public function test_history_destroy_denied_without_manage_coupon_history(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $history = CouponHistory::factory()->create();

        $this->actingAs($noPerms)
            ->delete(route('coupons.history.destroy', $history->id))
            ->assertSessionHas('error');
    }

    // ── CouponController::apply (JSON) ────────────────────────────────────────

    public function test_apply_returns_discounted_price_for_valid_percentage_coupon(): void
    {
        $coupon = Coupon::factory()->forPackage($this->subscription->id)->create([
            'type'      => 'percentage',
            'rate'      => 10,
            'use_limit' => 100,
            'status'    => '1',
        ]);

        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($this->subscription->id),
                'coupon'  => $coupon->code,
            ]))
            ->assertJsonFragment(['status' => true])
            ->assertJsonFragment(['msg' => __('Coupon successfully applied.')]);
    }

    public function test_apply_returns_discounted_price_for_valid_fixed_coupon(): void
    {
        $coupon = Coupon::factory()->fixed()->forPackage($this->subscription->id)->create([
            'rate'      => 20,
            'use_limit' => 100,
            'status'    => '1',
        ]);

        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($this->subscription->id),
                'coupon'  => $coupon->code,
            ]))
            ->assertJsonFragment(['status' => true])
            ->assertJsonFragment(['msg' => __('Coupon successfully applied.')]);
    }

    public function test_apply_returns_error_for_unknown_coupon_code(): void
    {
        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($this->subscription->id),
                'coupon'  => 'DOESNOTEXIST',
            ]))
            ->assertJsonFragment(['status' => false]);
    }

    public function test_apply_returns_error_for_coupon_not_applicable_to_package(): void
    {
        $otherSub = Subscription::factory()->create();
        $coupon   = Coupon::factory()->forPackage($this->subscription->id)->create(['status' => '1']);

        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($otherSub->id),
                'coupon'  => $coupon->code,
            ]))
            ->assertJsonFragment(['status' => false]);
    }

    public function test_apply_returns_error_for_expired_coupon(): void
    {
        $coupon = Coupon::factory()->expired()->forPackage($this->subscription->id)->create(['status' => '1']);

        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($this->subscription->id),
                'coupon'  => $coupon->code,
            ]))
            ->assertJsonFragment(['status' => false]);
    }

    public function test_apply_returns_error_when_use_limit_exhausted(): void
    {
        $coupon = Coupon::factory()->forPackage($this->subscription->id)->create([
            'use_limit' => 1,
            'status'    => '1',
        ]);
        // Exhaust the limit with one history record
        CouponHistory::factory()->create(['coupon' => $coupon->id]);

        $this->actingAs($this->owner)
            ->get(route('coupons.apply', [
                'package' => Crypt::encrypt($this->subscription->id),
                'coupon'  => $coupon->code,
            ]))
            ->assertJsonFragment(['status' => false]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validCouponPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                => 'Test Coupon',
            'type'                => 'percentage',
            'rate'                => 10,
            'applicable_packages' => [$this->subscription->id],
            'code'                => 'TESTCODE',
            'valid_for'           => now()->addMonths(3)->format('Y-m-d'),
            'use_limit'           => 50,
            'status'              => '1',
        ], $overrides);
    }
}
