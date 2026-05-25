<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage vehicle', 'create vehicle', 'edit vehicle', 'delete vehicle'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->vehicleType = VehicleType::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('vehicle.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('vehicle.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
        $this->put(route('vehicle.update', $vehicle))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('vehicle.destroy', $vehicle))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_vehicle(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('vehicle.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_vehicle(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('vehicle.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_vehicle(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('vehicle.destroy', $vehicle))->assertSessionHas('error');
    }

    // ── VehicleController::index ──────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('vehicle.index'))->assertOk();
    }

    // ── VehicleController::store ──────────────────────────────────────────────

    public function test_store_creates_vehicle_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('vehicle.store'), $this->validPayload(['name' => 'Test Car']))
            ->assertRedirect(route('vehicle.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', ['name' => 'Test Car', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('vehicle.store'), $this->validPayload(['name' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── VehicleController::update ─────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $vehicle = Vehicle::factory()->create(['name' => 'Old Car', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('vehicle.update', $vehicle), $this->validPayload(['name' => 'New Car']))
            ->assertRedirect(route('vehicle.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'name' => 'New Car']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('vehicle.update', $vehicle), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── VehicleController::destroy ────────────────────────────────────────────

    public function test_destroy_deletes_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('vehicle.destroy', $vehicle))
            ->assertRedirect(route('vehicle.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type'                          => $this->vehicleType->id,
            'name'                          => 'Renault Clio',
            'model'                         => '2022 Clio',
            'engine_type'                   => 'V4',
            'engine_no'                     => 'ENG-1234AB',
            'license_plate'                 => 'AB-1234-CD',
            'registration_expiry_date'      => now()->addYear()->format('Y-m-d'),
            'daily_rate'                    => 50,
            'year_of_ﬁrst_immatriculation' => 2022,
            'gearbox'                       => 'manual',
            'fuel_type'                     => 'diesel',
            'number_of_seats'               => 5,
            'kilometers'                    => 10000,
        ], $overrides);
    }
}
