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
