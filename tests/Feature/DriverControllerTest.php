<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class DriverControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage driver', 'create driver', 'edit driver', 'delete driver'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->subscription = Subscription::factory()->create(['driver_limit' => 10]);
        $this->owner = User::factory()->create([
            'type'         => 'owner',
            'parent_id'    => 0,
            'subscription' => $this->subscription->id,
        ]);
        $this->owner->givePermissionTo($perms);

        Role::create(['name' => 'driver', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('driver.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('driver.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->put(route('driver.update', $driverUser->id))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('driver.destroy', $driverUser->id))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('driver.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('driver.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('driver.destroy', $driverUser->id))->assertSessionHas('error');
    }

    // ── DriverController::index ───────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('driver.index'))->assertOk();
    }

    // ── DriverController::store ───────────────────────────────────────────────

    public function test_store_creates_driver_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload(['first_name' => 'Jane', 'last_name' => 'Doe']))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['name' => 'Jane Doe', 'type' => 'driver', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_underage_driver(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload([
                'birth_date' => Carbon::now()->subYears(17)->format('Y-m-d'),
            ]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_when_driver_limit_reached(): void
    {
        $this->subscription->driver_limit = 1;
        $this->subscription->save();

        User::factory()->driver()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_on_missing_first_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload(['first_name' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── DriverController::update ──────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $driverUser = User::factory()->driver()->create([
            'name'      => 'Old Name',
            'email'     => 'old@example.com',
            'parent_id' => $this->owner->id,
        ]);
        // DriverFactory uses string driver_id ('DR-####') which violates the integer column constraint.
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('driver.update', $driverUser->id), [
                'first_name' => 'New',
                'last_name'  => 'Name',
                'email'      => 'new@example.com',
            ])
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $driverUser->id, 'name' => 'New Name']);
    }

    public function test_update_flashes_error_on_missing_first_name(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('driver.update', $driverUser->id), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── DriverController::destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_driver(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('driver.destroy', $driverUser->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $driverUser->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'      => 'John',
            'last_name'       => 'Driver',
            'phone_number'    => '0612345678',
            'gender'          => 'Male',
            'birth_date'      => Carbon::now()->subYears(25)->format('Y-m-d'),
            'address'         => '123 Main St',
            'license_number'  => 'LIC-123456',
            'issue_date'      => now()->subYears(2)->format('Y-m-d'),
            'expiration_date' => now()->addYears(3)->format('Y-m-d'),
        ], $overrides);
    }
}
