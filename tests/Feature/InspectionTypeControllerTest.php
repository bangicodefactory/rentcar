<?php

namespace Tests\Feature;

use App\Models\InspectionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class InspectionTypeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage inspection type', 'create inspection type', 'edit inspection type', 'delete inspection type'];
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
        $this->get(route('inspection-type.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('inspection-type.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $it = InspectionType::factory()->create();
        $this->put(route('inspection-type.update', $it))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $it = InspectionType::factory()->create();
        $this->delete(route('inspection-type.destroy', $it))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_inspection_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('inspection-type.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_inspection_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('inspection-type.store'), ['type' => 'Annual'])->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_inspection_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $it = InspectionType::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('inspection-type.destroy', $it))->assertSessionHas('error');
    }

    // ── InspectionTypeController::index ───────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('inspection-type.index'))->assertOk();
    }

    // ── InspectionTypeController::store ───────────────────────────────────────

    public function test_store_creates_inspection_type_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inspection-type.store'), ['type' => 'Pre-Rental'])
            ->assertRedirect(route('inspection-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inspection_types', ['type' => 'Pre-Rental', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_type(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inspection-type.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── InspectionTypeController::update ──────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $it = InspectionType::factory()->create(['type' => 'Old Type', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('inspection-type.update', $it), ['type' => 'New Type'])
            ->assertRedirect(route('inspection-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inspection_types', ['id' => $it->id, 'type' => 'New Type']);
    }

    public function test_update_flashes_error_on_missing_type(): void
    {
        $it = InspectionType::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('inspection-type.update', $it), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── InspectionTypeController::destroy ─────────────────────────────────────

    public function test_destroy_deletes_inspection_type(): void
    {
        $it = InspectionType::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('inspection-type.destroy', $it))
            ->assertRedirect(route('inspection-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inspection_types', ['id' => $it->id]);
    }
}
