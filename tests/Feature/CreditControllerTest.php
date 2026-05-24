<?php

namespace Tests\Feature;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = [
            'manage driver',
            'manage rental agreement',
            'create rental agreement',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner  = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->driver = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('credit.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('credit.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $credit = $this->makeCredit();
        $this->put(route('credit.update', $credit), [])->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $credit = $this->makeCredit();
        $this->delete(route('credit.destroy', $credit))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('credit.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_store_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('credit.store'), $this->validCreditPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_update_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $credit  = $this->makeCredit();

        $this->actingAs($noPerms)
            ->put(route('credit.update', $credit), $this->validCreditPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_destroy_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $credit  = $this->makeCredit();

        $this->actingAs($noPerms)
            ->delete(route('credit.destroy', $credit))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── CreditController::store ───────────────────────────────────────────────

    public function test_store_creates_credit_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('credit.store'), $this->validCreditPayload([
                'amount' => 250.00,
                'status' => 'non payé',
            ]))
            ->assertRedirect(route('credit.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('credits', [
            'driver_id' => $this->driver->id,
            'amount'    => 250.00,
            'status'    => 'non payé',
            'parent_id' => $this->owner->id,
        ]);
    }

    public function test_store_logs_credit_action(): void
    {
        $this->actingAs($this->owner)
            ->post(route('credit.store'), $this->validCreditPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('logged_histories', ['type' => 'credit_create']);
    }

    public function test_store_rejects_missing_driver_id(): void
    {
        $this->actingAs($this->owner)
            ->post(route('credit.store'), ['amount' => 100])
            ->assertSessionHasErrors(['driver_id']);
    }

    public function test_store_rejects_negative_amount(): void
    {
        $this->actingAs($this->owner)
            ->post(route('credit.store'), $this->validCreditPayload(['amount' => -10]))
            ->assertSessionHasErrors(['amount']);
    }

    public function test_store_returns_json_for_ajax_request(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('credit.store'), $this->validCreditPayload(['amount' => 75]))
            ->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    // ── CreditController::update ──────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $credit = $this->makeCredit(['amount' => 100, 'status' => 'non payé']);

        $this->actingAs($this->owner)
            ->put(route('credit.update', $credit), $this->validCreditPayload([
                'amount' => 200,
                'status' => 'payé',
            ]))
            ->assertRedirect(route('credit.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('credits', [
            'id'     => $credit->id,
            'amount' => 200,
            'status' => 'payé',
        ]);
    }

    public function test_update_denied_for_cross_tenant_credit(): void
    {
        $credit = $this->makeCredit(); // belongs to $this->owner

        $this->actingAs($this->makeOtherOwner())
            ->put(route('credit.update', $credit), $this->validCreditPayload())
            ->assertRedirect(route('credit.index'))
            ->assertSessionHas('error');
    }

    // ── CreditController::destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_credit(): void
    {
        $credit = $this->makeCredit();

        $this->actingAs($this->owner)
            ->delete(route('credit.destroy', $credit))
            ->assertRedirect(route('credit.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('credits', ['id' => $credit->id]);
    }

    public function test_destroy_denied_for_cross_tenant_credit(): void
    {
        $credit = $this->makeCredit(); // belongs to $this->owner

        $this->actingAs($this->makeOtherOwner())
            ->delete(route('credit.destroy', $credit))
            ->assertRedirect(route('credit.index'))
            ->assertSessionHas('error');
    }

    // ── CreditController::getDriverCredit (JSON) ──────────────────────────────

    public function test_get_driver_credit_returns_totals_and_history(): void
    {
        Credit::factory()->create([
            'driver_id' => $this->driver->id,
            'parent_id' => $this->owner->id,
            'amount'    => 150,
            'status'    => 'non payé',
        ]);
        Credit::factory()->paid()->create([
            'driver_id' => $this->driver->id,
            'parent_id' => $this->owner->id,
            'amount'    => 50,
        ]);

        $this->actingAs($this->owner)
            ->get(route('credit.driver.details', $this->driver->id))
            ->assertOk()
            ->assertJsonFragment(['total_unpaid' => 150.0]);
    }

    public function test_get_driver_credit_denied_without_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('credit.driver.details', $this->driver->id))
            ->assertStatus(403)
            ->assertJsonFragment(['error' => 'Permission Denied']);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeOtherOwner(): User
    {
        $other = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $other->givePermissionTo('manage driver');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return $other;
    }

    private function makeCredit(array $overrides = []): Credit
    {
        return Credit::factory()->create(array_merge([
            'driver_id' => $this->driver->id,
            'parent_id' => $this->owner->id,
        ], $overrides));
    }

    private function validCreditPayload(array $overrides = []): array
    {
        return array_merge([
            'driver_id'   => $this->driver->id,
            'amount'      => 100,
            'status'      => 'non payé',
            'credit_date' => now()->format('Y-m-d'),
        ], $overrides);
    }
}
