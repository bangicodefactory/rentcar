<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\RentalAgreement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_on_missing_driver(): void
    {
        $this->actingAs($this->owner)
            ->post(route('rental-agreement.store'), $this->validPayload(['driver' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
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
            ->assertSessionHas('error');
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
