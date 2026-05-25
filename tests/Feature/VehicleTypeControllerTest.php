<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class VehicleTypeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage vehicle type', 'create vehicle type', 'edit vehicle type', 'delete client'];
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
        $this->get(route('vehicle-type.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('vehicle-type.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $vt = VehicleType::factory()->create();
        $this->put(route('vehicle-type.update', $vt))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $vt = VehicleType::factory()->create();
        $this->delete(route('vehicle-type.destroy', $vt))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_vehicle_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('vehicle-type.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_vehicle_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('vehicle-type.store'), ['type' => 'SUV'])->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_client(): void
    {
        // NOTE: VehicleTypeController::destroy checks 'delete client' (not 'delete vehicle type').
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $vt = VehicleType::factory()->create();
        $this->actingAs($noPerms)->delete(route('vehicle-type.destroy', $vt))->assertSessionHas('error');
    }

    // ── VehicleTypeController::index ──────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('vehicle-type.index'))->assertOk();
    }

    // ── VehicleTypeController::store ──────────────────────────────────────────

    public function test_store_creates_vehicle_type_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('vehicle-type.store'), ['type' => 'Convertible'])
            ->assertRedirect(route('vehicle-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_types', ['type' => 'Convertible', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_type(): void
    {
        $this->actingAs($this->owner)
            ->post(route('vehicle-type.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── VehicleTypeController::update ─────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $vt = VehicleType::factory()->create(['type' => 'Old Type', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('vehicle-type.update', $vt), ['type' => 'New Type'])
            ->assertRedirect(route('vehicle-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicle_types', ['id' => $vt->id, 'type' => 'New Type']);
    }

    public function test_update_flashes_error_on_missing_type(): void
    {
        $vt = VehicleType::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('vehicle-type.update', $vt), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── VehicleTypeController::destroy ────────────────────────────────────────

    public function test_destroy_deletes_vehicle_type(): void
    {
        $vt = VehicleType::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('vehicle-type.destroy', $vt))
            ->assertRedirect(route('vehicle-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicle_types', ['id' => $vt->id]);
    }
}
