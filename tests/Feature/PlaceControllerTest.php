<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage place', 'create place', 'edit place', 'delete place'];
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
        $this->get(route('place.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('place.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->put(route('place.update', $place))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('place.destroy', $place))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_place(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('place.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_place(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('place.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_place(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('place.destroy', $place))->assertSessionHas('error');
    }

    // ── PlaceController::index ────────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('place.index'))->assertOk();
    }

    // ── PlaceController::store ────────────────────────────────────────────────

    public function test_store_creates_place_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('place.store'), $this->validPayload(['name' => 'Airport Terminal']))
            ->assertRedirect(route('place.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('places', ['name' => 'Airport Terminal', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('place.store'), $this->validPayload(['name' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── PlaceController::update ───────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $place = Place::factory()->create(['name' => 'Old Place', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('place.update', $place), $this->validPayload(['name' => 'New Place']))
            ->assertRedirect(route('place.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('places', ['id' => $place->id, 'name' => 'New Place']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('place.update', $place), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── PlaceController::destroy ──────────────────────────────────────────────

    public function test_destroy_deletes_place(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('place.destroy', $place))
            ->assertRedirect(route('place.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('places', ['id' => $place->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Hotel Pickup',
            'city'   => 'Casablanca',
            'island' => 'N/A',
            'price'  => 20,
        ], $overrides);
    }
}
