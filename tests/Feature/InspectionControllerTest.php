<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class InspectionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage inspection', 'create inspection', 'edit inspection', 'delete inspection'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('inspection.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('inspection.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $inspection = $this->makeInspection();
        $this->put(route('inspection.update', $inspection))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $inspection = $this->makeInspection();
        $this->delete(route('inspection.destroy', $inspection))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_inspection(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('inspection.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_inspection(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('inspection.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_inspection(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $inspection = $this->makeInspection();
        $this->actingAs($noPerms)->delete(route('inspection.destroy', $inspection))->assertSessionHas('error');
    }

    // ── InspectionController::index ───────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('inspection.index'))->assertOk();
    }

    // ── InspectionController::store ───────────────────────────────────────────

    public function test_store_creates_inspection_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inspection.store'), $this->validPayload())
            ->assertRedirect(route('inspection.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inspections', [
            'vehicle'   => $this->vehicle->id,
            'parent_id' => $this->owner->id,
        ]);
    }

    public function test_store_flashes_error_on_missing_vehicle(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inspection.store'), $this->validPayload(['vehicle' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── InspectionController::update ──────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $inspection = $this->makeInspection(['amount' => 100]);

        $this->actingAs($this->owner)
            ->put(route('inspection.update', $inspection), $this->validPayload(['amount' => 250]))
            ->assertRedirect(route('inspection.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'amount' => 250]);
    }

    public function test_update_flashes_error_on_missing_vehicle(): void
    {
        $inspection = $this->makeInspection();
        $this->actingAs($this->owner)
            ->put(route('inspection.update', $inspection), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── InspectionController::destroy ─────────────────────────────────────────

    public function test_destroy_deletes_inspection(): void
    {
        $inspection = $this->makeInspection();

        $this->actingAs($this->owner)
            ->delete(route('inspection.destroy', $inspection))
            ->assertRedirect(route('inspection.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inspections', ['id' => $inspection->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicle'                => $this->vehicle->id,
            'inspector'              => $this->owner->id,
            'inspection_date'        => now()->format('Y-m-d'),
            'incoming_date'          => now()->format('Y-m-d'),
            'meter_reading_incoming' => 15000,
            'status'                 => 'pending',
            'repair_status'          => 'none',
            'amount'                 => 100,
        ], $overrides);
    }

    // InspectionFactory includes columns not in the DB schema (meter_reading_outgoing, outgoing_date, etc.).
    private function makeInspection(array $overrides = []): Inspection
    {
        return Inspection::create(array_merge([
            'vehicle'                => $this->vehicle->id,
            'inspector'              => $this->owner->id,
            'inspection_date'        => now()->format('Y-m-d'),
            'meter_reading_incoming' => 0,
            'incoming_date'          => now()->format('Y-m-d'),
            'status'                 => 'pending',
            'repair_status'          => 'none',
            'amount'                 => 0,
            'parent_id'              => $this->owner->id,
        ], $overrides));
    }
}
