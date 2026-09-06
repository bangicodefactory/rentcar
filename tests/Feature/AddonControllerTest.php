<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class AddonControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage addon', 'create addon', 'edit addon', 'delete addon'];
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
        $this->get(route('addon.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('addon.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $addon = Addon::factory()->create();
        $this->put(route('addon.update', $addon))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $addon = Addon::factory()->create();
        $this->delete(route('addon.destroy', $addon))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_addon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('addon.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_addon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('addon.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_addon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('addon.destroy', $addon))->assertSessionHas('error');
    }

    // ── AddonController::index ────────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('addon.index'))->assertOk();
    }

    // ── AddonController::store ────────────────────────────────────────────────

    public function test_store_creates_addon_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('addon.store'), $this->validPayload(['name' => 'Roof Rack']))
            ->assertRedirect(route('addon.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addons', ['name' => 'Roof Rack', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('addon.store'), $this->validPayload(['name' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── AddonController::update ───────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $addon = Addon::factory()->create(['name' => 'Old Addon', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('addon.update', $addon), $this->validPayload(['name' => 'New Addon']))
            ->assertRedirect(route('addon.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name' => 'New Addon']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('addon.update', $addon), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── AddonController::destroy ──────────────────────────────────────────────

    public function test_destroy_deletes_addon(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('addon.destroy', $addon))
            ->assertRedirect(route('addon.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('addons', ['id' => $addon->id]);
    }

    // ── AddonController::create ───────────────────────────────────────────────

    public function test_create_requires_auth(): void
    {
        $this->get(route('addon.create'))->assertRedirect(route('login'));
    }

    public function test_create_renders_inertia_component(): void
    {
        $this->actingAs($this->owner)
            ->get(route('addon.create'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Addon/Create')
                ->has('billingType')
            );
    }

    // ── AddonController::edit ─────────────────────────────────────────────────

    public function test_edit_requires_auth(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->get(route('addon.edit', $addon))->assertRedirect(route('login'));
    }

    public function test_edit_renders_inertia_component(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('addon.edit', $addon))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Addon/Edit')
                ->has('addon')
                ->has('billingType')
            );
    }

    // ── AddonController::update — permission denied ───────────────────────────

    public function test_update_denied_without_edit_addon(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)
            ->put(route('addon.update', $addon), $this->validPayload())
            ->assertSessionHas('error');
    }

    // ── AddonController::store — additional validation ────────────────────────

    public function test_store_flashes_error_on_missing_price(): void
    {
        $this->actingAs($this->owner)
            ->post(route('addon.store'), $this->validPayload(['price' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_on_missing_billing_type(): void
    {
        $this->actingAs($this->owner)
            ->post(route('addon.store'), $this->validPayload(['billing_type' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── AddonController::update — additional validation ───────────────────────

    public function test_update_flashes_error_on_missing_price(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('addon.update', $addon), $this->validPayload(['price' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_update_flashes_error_on_missing_billing_type(): void
    {
        $addon = Addon::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('addon.update', $addon), $this->validPayload(['billing_type' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── AddonController::getAddonRateCalculation ──────────────────────────────

    public function test_rate_calculation_requires_auth(): void
    {
        $this->get(route('addon.rate.calculation'))->assertRedirect(route('login'));
    }

    public function test_rate_calculation_returns_json_with_totals(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create(['parent_id' => $this->owner->id, 'daily_rate' => 50]);
        $addon   = Addon::factory()->daily()->create(['parent_id' => $this->owner->id, 'price' => 10]);

        $this->actingAs($this->owner)
            ->getJson(route('addon.rate.calculation', [
                'vahicle_id'     => $vehicle->id,
                'start_date_time' => now()->addDay()->format('Y/m/d') . ' 09:00',
                'end_date_time'   => now()->addDays(3)->format('Y/m/d') . ' 09:00',
                'addons'          => [$addon->id],
                'daychange'       => 0,
            ]))
            ->assertOk();
    }

    // ── AddonController::getReductionRateCalculation ──────────────────────────

    public function test_reduction_rate_calculation_requires_auth(): void
    {
        $this->get(route('addon.rate.reduction'))->assertRedirect(route('login'));
    }

    public function test_reduction_rate_calculation_returns_json(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create(['parent_id' => $this->owner->id, 'daily_rate' => 60]);

        $this->actingAs($this->owner)
            ->getJson(route('addon.rate.reduction', [
                'vahicle_id'      => $vehicle->id,
                'start_date_time' => now()->addDay()->format('Y/m/d') . ' 10:00',
                'end_date_time'   => now()->addDays(3)->format('Y/m/d') . ' 10:00',
            ]))
            ->assertOk();
    }

    // ── unresolvable place ids ────────────────────────────────────────────────

    public function test_rate_calculation_rejects_unknown_place_id(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('addon.rate.calculation', ['pickup_place' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_place']);
    }

    public function test_reduction_rate_calculation_rejects_unknown_place_id(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('addon.rate.reduction', ['drop_off_place' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['drop_off_place']);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'GPS Navigator',
            'price'        => 15,
            'billing_type' => 'daily',
        ], $overrides);
    }
}
