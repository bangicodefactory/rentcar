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

    // ── PlaceController::create ───────────────────────────────────────────────

    public function test_create_requires_auth(): void
    {
        $this->get(route('place.create'))->assertRedirect(route('login'));
    }

    public function test_create_renders_inertia_component(): void
    {
        $this->actingAs($this->owner)
            ->get(route('place.create'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Place/Create')
            );
    }

    // ── PlaceController::edit ─────────────────────────────────────────────────

    public function test_edit_requires_auth(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->get(route('place.edit', $place))->assertRedirect(route('login'));
    }

    public function test_edit_renders_inertia_component(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('place.edit', $place))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Place/Edit')
                ->has('place')
            );
    }

    // ── PlaceController::update — denied without edit place ───────────────────

    public function test_update_denied_without_edit_place(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $place = Place::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)
            ->put(route('place.update', $place), $this->validPayload())
            ->assertSessionHas('error');
    }

    // ── PlaceController::getPlaceRateCalculation ──────────────────────────────

    public function test_place_rate_calculation_requires_auth(): void
    {
        $this->get(route('place.rate.calculation'))->assertRedirect(route('login'));
    }

    public function test_place_rate_calculation_returns_json(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id, 'price' => 50]);

        $this->actingAs($this->owner)
            ->getJson(route('place.rate.calculation', [
                'pickup_place'  => $place->id,
                'drop_off_place' => $place->id,
            ]))
            ->assertOk();
    }

    // ── place ids that cannot be resolved ─────────────────────────────────────
    // specificPlacesRateCalculation() dereferenced a missing place (500), and
    // none of the rate endpoints validated pickup/drop-off ids, so a stale or
    // foreign id either crashed the quote or priced another tenant's place.

    public function test_place_rate_calculation_rejects_unknown_place_id(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('place.rate.calculation', ['pickup_place' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_place']);

        $this->actingAs($this->owner)
            ->getJson(route('place.rate.calculation', ['drop_off_place' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['drop_off_place']);
    }

    public function test_place_rate_calculation_rejects_other_tenants_place(): void
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $foreign = Place::factory()->create(['parent_id' => $otherOwner->id, 'price' => 999]);

        $this->actingAs($this->owner)
            ->getJson(route('place.rate.calculation', ['pickup_place' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pickup_place']);
    }

    public function test_place_rate_calculation_prices_own_place(): void
    {
        $place = Place::factory()->create(['parent_id' => $this->owner->id, 'price' => 50, 'name' => 'Aeroport']);

        $response = $this->actingAs($this->owner)
            ->getJson(route('place.rate.calculation', ['pickup_place' => $place->id]))
            ->assertOk();

        $data = json_decode($response->getContent(), true);
        $this->assertSame('50', (string) $data['placeAmount']);
        $this->assertStringContainsString('Aeroport', $data['pickup_place']);
    }

    public function test_specific_places_rate_helper_survives_missing_place(): void
    {
        $this->assertSame(['place' => '', 'final_price' => 0], specificPlacesRateCalculation(999999));
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
