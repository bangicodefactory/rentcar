<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\RentalAgreement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class RentalAgreementControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected User $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = [
            'manage rental agreement',
            'create rental agreement',
            'show rental agreement',
            'edit rental agreement',
            'delete rental agreement',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner  = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->driver  = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('rental-agreement.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('rental-agreement.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $agreement = $this->makeAgreement();
        $this->put(route('rental-agreement.update', $agreement))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $agreement = $this->makeAgreement();
        $this->delete(route('rental-agreement.destroy', $agreement))->assertRedirect(route('login'));
    }

    public function test_show_requires_auth(): void
    {
        $agreement = $this->makeAgreement();
        $this->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('rental-agreement.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_store_denied_without_create_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('rental-agreement.store'), $this->validPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_update_denied_without_edit_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $agreement = $this->makeAgreement();

        $this->actingAs($noPerms)
            ->put(route('rental-agreement.update', $agreement), $this->validPayload())
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_destroy_denied_without_delete_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $agreement = $this->makeAgreement();

        $this->actingAs($noPerms)
            ->delete(route('rental-agreement.destroy', $agreement))
            ->assertSessionHas('error');
    }

    // ── RentalAgreementController::index ──────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index'))
            ->assertOk();
    }

    public function test_index_returns_agreements_with_resolved_relations(): void
    {
        // F-18: the list now selects a subset of columns + eager-loads relations.
        // F-21 follow-up: the list is now paginated, so the rows live under
        // `agreements.data`. Lock in that the DTO shape and the driver/vehicle
        // lookups still resolve.
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Index')
                ->has('agreements.data', 1)
                ->where('agreements.data.0.driver_name', $this->driver->name)
                ->where('agreements.data.0.vehicle_label', $this->vehicle->name . ' - ' . $this->vehicle->license_plate)
                ->where('agreements.data.0.status', $agreement->status)
                ->has('agreements.data.0.agreement_id')
                ->has('agreements.data.0.encrypted_id')
                ->etc()
            );
    }

    public function test_index_search_filters_by_driver_name(): void
    {
        // F-21 follow-up: server-side search across agreement_id, driver name,
        // and vehicle name/plate. A non-matching query returns no rows.
        $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index', ['search' => 'no-such-driver-xyz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Index')
                ->has('agreements.data', 0)
                ->where('filters.search', 'no-such-driver-xyz')
                ->etc()
            );

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index', ['search' => $this->driver->name]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Index')
                ->has('agreements.data', 1)
                ->etc()
            );
    }

    public function test_index_search_matches_status_label_and_displayed_id(): void
    {
        // Restore parity with the old client-side filter, which searched the
        // status *label* and the *displayed* (prefixed) agreement ID.
        $active = $this->makeAgreement(['status' => 'active', 'agreement_id' => 9001]);
        $this->makeAgreement(['status' => 'cancelled', 'agreement_id' => 9002]);

        // Searching the status label "Active" returns only the active row.
        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index', ['search' => 'Active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agreements.data', 1)
                ->where('agreements.data.0.status', 'active')
                ->etc()
            );

        // Searching the displayed agreement ID (prefix + number) finds that row.
        $displayedId = rentalAgreementPrefix() . $active->agreement_id;
        $this->actingAs($this->owner)
            ->get(route('rental-agreement.index', ['search' => $displayedId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agreements.data', 1)
                ->where('agreements.data.0.agreement_id', $displayedId)
                ->etc()
            );
    }

    // ── RentalAgreementController::store ──────────────────────────────────────

    public function test_store_creates_agreement_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['create_booking' => 0]))
            ->assertRedirect(route('rental-agreement.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rental_agreements', [
            'vehicle'   => $this->vehicle->id,
            'driver'    => $this->driver->id,
            'status'    => 'draft',
            'parent_id' => $this->owner->id,
        ]);
    }

    public function test_store_flashes_error_on_missing_vehicle(): void
    {
        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['vehicle' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors('vehicle');
    }

    public function test_store_flashes_error_on_missing_driver(): void
    {
        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['driver' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors('driver');
    }

    public function test_store_creates_booking_when_flag_is_1(): void
    {
        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['create_booking' => 1]))
            ->assertRedirect(route('rental-agreement.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'vehicle'    => $this->vehicle->id,
            'driver'     => $this->driver->id,
            'parent_id'  => $this->owner->id,
            // Must be the enum key, not the 'Yet to Start' display label, so the
            // booking matches status filters and the badge mapping.
            'status'     => 'yet_to_start',
        ]);
    }

    public function test_store_does_not_create_booking_when_flag_is_0(): void
    {
        $beforeCount = Booking::count();

        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['create_booking' => 0]))
            ->assertRedirect();

        $this->assertSame($beforeCount, Booking::count());
    }

    public function test_store_increments_agreement_id_sequentially(): void
    {
        $this->makeAgreement(['agreement_id' => 5]);

        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['create_booking' => 0]))
            ->assertRedirect();

        $this->assertDatabaseHas('rental_agreements', [
            'parent_id'    => $this->owner->id,
            'agreement_id' => 6,
        ]);
    }

    // ── RentalAgreementController::show ───────────────────────────────────────

    public function test_show_returns_200_with_encrypted_id(): void
    {
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertOk();
    }

    public function test_show_denied_without_show_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $agreement = $this->makeAgreement();

        $this->actingAs($noPerms)
            ->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_show_renders_inertia_component_with_driver_data(): void
    {
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Show')
                ->has('agreement.driver1')
                ->has('agreement.vehicle_name')
            );
    }

    public function test_show_loads_both_drivers_in_one_user_query_and_one_profile_query(): void
    {
        // BAN-240 batched the two drivers' lookups. Count exactly those queries
        // so a revert to per-driver lookups (2 + 2) fails: user queries whose
        // bindings name one of the agreement's drivers, and drivers-table queries.
        $driver2 = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        // drivers.driver_id is an integer column; the factory's 'DR-####' string is rejected.
        Driver::factory()->create(['user_id' => $this->driver->id, 'parent_id' => $this->owner->id, 'driver_id' => 98]);
        Driver::factory()->create(['user_id' => $driver2->id, 'parent_id' => $this->owner->id, 'driver_id' => 99]);

        $agreement = $this->makeAgreement(['driver2' => $driver2->id]);
        $driverIds = [$this->driver->id, $driver2->id];

        $userLookups = 0;
        $profileLookups = 0;
        DB::listen(function ($query) use (&$userLookups, &$profileLookups, $driverIds) {
            if (preg_match('/\bfrom\s+[`"]?drivers[`"]?/i', $query->sql)) {
                $profileLookups++;
            } elseif (preg_match('/\bfrom\s+[`"]?users[`"]?/i', $query->sql)
                && array_intersect($driverIds, array_map('intval', $query->bindings))) {
                $userLookups++;
            }
        });

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertOk();

        $this->assertSame(1, $userLookups, "show() should load both drivers' users in 1 query (fired {$userLookups})");
        $this->assertSame(1, $profileLookups, "show() should load both driver profiles in 1 query (fired {$profileLookups})");
    }

    public function test_show_uses_the_first_driver_profile_when_a_driver_has_two(): void
    {
        // drivers.user_id has no unique index. Before BAN-240 the page used
        // Driver::where(user_id)->first() (lowest id); keyBy() kept the last row
        // instead. Duplicates only come from bad data, but the page must keep
        // showing the same profile as before.
        $first = Driver::factory()->create(['user_id' => $this->driver->id, 'parent_id' => $this->owner->id, 'driver_id' => 101, 'license_number' => 'LIC-FIRST']);
        Driver::factory()->create(['user_id' => $this->driver->id, 'parent_id' => $this->owner->id, 'driver_id' => 102, 'license_number' => 'LIC-SECOND']);

        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.show', Crypt::encrypt($agreement->id)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agreement.driver1.license_number', $first->license_number)
            );
    }

    // ── RentalAgreementController::update ─────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $agreement = $this->makeAgreement(['status' => 'draft']);

        $this->actingAs($this->owner)
            ->put(route('rental-agreement.update', $agreement), $this->validPayload(['status' => 'confirmed']))
            ->assertRedirect(route('rental-agreement.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rental_agreements', [
            'id'     => $agreement->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_update_flashes_error_on_missing_required_fields(): void
    {
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->put(route('rental-agreement.update', $agreement), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['vehicle', 'driver']);
    }

    // ── RentalAgreementController::destroy ────────────────────────────────────

    public function test_destroy_deletes_agreement(): void
    {
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->delete(route('rental-agreement.destroy', $agreement))
            ->assertRedirect(route('rental-agreement.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rental_agreements', ['id' => $agreement->id]);
    }

    // ── RentalAgreementController::create ────────────────────────────────────

    public function test_create_requires_auth(): void
    {
        $this->get(route('rental-agreement.create'))->assertRedirect(route('login'));
    }

    public function test_create_renders_inertia_component(): void
    {
        $this->actingAs($this->owner)
            ->get(route('rental-agreement.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Create')
                ->has('vehicles')
                ->has('drivers')
                ->has('statuses')
            );
    }

    public function test_create_denied_without_create_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('rental-agreement.create'))
            ->assertSessionHas('error');
    }

    // ── RentalAgreementController::edit ──────────────────────────────────────

    public function test_edit_requires_auth(): void
    {
        $agreement = $this->makeAgreement();
        $this->get(route('rental-agreement.edit', $agreement))->assertRedirect(route('login'));
    }

    public function test_edit_renders_inertia_component(): void
    {
        $agreement = $this->makeAgreement();

        $this->actingAs($this->owner)
            ->get(route('rental-agreement.edit', $agreement))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RentalAgreement/Edit')
                ->has('agreement.id')
                ->has('vehicles')
                ->has('drivers')
                ->has('statuses')
            );
    }

    public function test_edit_denied_without_edit_rental_agreement(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $agreement = $this->makeAgreement();

        $this->actingAs($noPerms)
            ->get(route('rental-agreement.edit', $agreement))
            ->assertSessionHas('error');
    }

    // ── RentalAgreementController::update — status change notification ────────

    public function test_update_with_status_change_succeeds(): void
    {
        $agreement = $this->makeAgreement(['status' => 'draft']);

        $this->actingAs($this->owner)
            ->put(route('rental-agreement.update', $agreement), $this->validPayload(['status' => 'confirmed']))
            ->assertRedirect(route('rental-agreement.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rental_agreements', ['id' => $agreement->id, 'status' => 'confirmed']);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeAgreement(array $overrides = []): RentalAgreement
    {
        return RentalAgreement::factory()->create(array_merge([
            'vehicle'   => $this->vehicle->id,
            'driver'    => $this->driver->id,
            'parent_id' => $this->owner->id,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicle'            => $this->vehicle->id,
            'driver'             => $this->driver->id,
            'rental_start_date'  => '2026-07-01',
            'rental_end_date'    => '2026-07-04',
            'rental_start_time'  => '09:00',
            'rental_end_time'    => '18:00',
            'rental_duration'    => 100,
            'status'             => 'draft',
            'create_booking'     => 0,
        ], $overrides);
    }
}
