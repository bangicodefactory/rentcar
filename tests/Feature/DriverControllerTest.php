<?php

namespace Tests\Feature;

use App\Models\Driver;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage driver', 'create driver', 'edit driver', 'delete driver'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
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

    public function test_index_search_matches_displayed_driver_id_and_license(): void
    {
        // Restore parity with the old client-side filter, which searched the
        // displayed (prefixed) driver ID and the license number.
        $match = User::factory()->driver()->create(['name' => 'Match Driver', 'parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => 700001, 'user_id' => $match->id, 'license_number' => 'LIC-7777', 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $other = User::factory()->driver()->create(['name' => 'Other Driver', 'parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => 700002, 'user_id' => $other->id, 'license_number' => 'ZZZ-0000', 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        // By the displayed driver ID (prefix + driver_id).
        $displayedId = driverPrefix() . '700001';
        $this->actingAs($this->owner)
            ->get(route('driver.index', ['search' => $displayedId]))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('drivers.data', 1)
                ->where('drivers.data.0.driver_id_display', $displayedId)
                ->etc()
            );

        // By license number.
        $this->actingAs($this->owner)
            ->get(route('driver.index', ['search' => 'LIC-7777']))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('drivers.data', 1)
                ->where('drivers.data.0.license_number', 'LIC-7777')
                ->etc()
            );
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

    // ── DriverController::show ────────────────────────────────────────────────

    public function test_show_requires_auth(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->get(route('driver.show', $driverUser->id))->assertRedirect(route('login'));
    }

    public function test_show_renders_inertia_component(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('driver.show', $driverUser->id))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Driver/Show')
                ->has('driver')
                ->has('user')
            );
    }

    // ── DriverController::edit ────────────────────────────────────────────────

    public function test_edit_requires_auth(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        $this->get(route('driver.edit', $driverUser->id))->assertRedirect(route('login'));
    }

    public function test_edit_renders_inertia_component(): void
    {
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('driver.edit', $driverUser->id))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Driver/Edit')
                ->has('gender')
                ->has('user')
            );
    }

    // ── DriverController::create ──────────────────────────────────────────────

    public function test_create_requires_auth(): void
    {
        $this->get(route('driver.create'))->assertRedirect(route('login'));
    }

    public function test_create_renders_inertia_component(): void
    {
        $this->actingAs($this->owner)
            ->get(route('driver.create'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Driver/Create')
                ->has('gender')
            );
    }

    // ── DriverController::store — with explicit email ─────────────────────────

    public function test_store_creates_driver_with_explicit_email(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload([
                'first_name' => 'Maria',
                'last_name'  => 'Silva',
                'email'      => 'maria.silva@test.com',
            ]))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'maria.silva@test.com', 'type' => 'driver']);
    }

    // ── DriverController::update — with document upload ───────────────────────

    public function test_update_with_document_upload_saves_successfully(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $driverUser = User::factory()->driver()->create([
            'name'      => 'Old Name',
            'email'     => 'old@example.com',
            'parent_id' => $this->owner->id,
        ]);
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $document = \Illuminate\Http\UploadedFile::fake()->create('id_card.pdf', 100, 'application/pdf');

        $this->actingAs($this->owner)
            ->put(route('driver.update', $driverUser->id), [
                'first_name' => 'New',
                'last_name'  => 'Name',
                'email'      => 'new@example.com',
                'document'   => $document,
            ])
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');
    }

    // ── DriverController::store — without email (auto-generated) ─────────────

    public function test_store_creates_driver_without_email_auto_generates_one(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload([
                'first_name' => 'Auto',
                'last_name'  => 'Email',
                'email'      => '', // empty email triggers auto-generation
            ]))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        // The user should have been created with an auto-generated email
        $this->assertDatabaseHas('users', [
            'name' => 'Auto Email',
            'type' => 'driver',
        ]);
    }

    public function test_store_flashes_error_on_missing_phone_without_email(): void
    {
        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload([
                'email'        => '',
                'phone_number' => '',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── DriverController::store — with license upload ─────────────────────────

    public function test_store_creates_driver_with_license_upload(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $license = \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

        $this->actingAs($this->owner)
            ->post(route('driver.store'), array_merge($this->validPayload([
                'first_name' => 'Licensed',
                'last_name'  => 'Driver',
            ]), [
                'license' => $license,
            ]))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['name' => 'Licensed Driver', 'type' => 'driver']);
    }

    // ── tenant scope + type guard ─────────────────────────────────────────────
    // show/edit/update/destroy resolved the user with a bare User::find($id):
    // any authenticated user could read any driver's file, and update/destroy
    // wrote to / deleted any users row before any check. All four now resolve
    // through one helper: same tenant, type = 'driver', super admin exempt.

    private function foreignDriver(): User
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $driver = User::factory()->driver()->create(['name' => 'Foreign Driver', 'parent_id' => $otherOwner->id]);
        Driver::create(['driver_id' => $driver->id, 'user_id' => $driver->id, 'gender' => 'Male', 'parent_id' => $otherOwner->id]);

        return $driver;
    }

    public function test_show_denied_for_other_tenants_driver(): void
    {
        $foreign = $this->foreignDriver();

        $this->actingAs($this->owner)
            ->get(route('driver.show', $foreign->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_edit_denied_for_other_tenants_driver(): void
    {
        $foreign = $this->foreignDriver();

        $this->actingAs($this->owner)
            ->get(route('driver.edit', $foreign->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_update_does_not_write_to_other_tenants_driver(): void
    {
        $foreign = $this->foreignDriver();

        $this->actingAs($this->owner)
            ->put(route('driver.update', $foreign->id), [
                'first_name' => 'Hijacked',
                'last_name'  => 'Name',
                'email'      => 'hijacked@example.com',
            ])
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('users', ['id' => $foreign->id, 'name' => 'Foreign Driver', 'email' => $foreign->email]);
    }

    public function test_destroy_does_not_delete_other_tenants_driver(): void
    {
        $foreign = $this->foreignDriver();

        $this->actingAs($this->owner)
            ->delete(route('driver.destroy', $foreign->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
        $this->assertDatabaseHas('drivers', ['user_id' => $foreign->id]);
    }

    public function test_destroy_refuses_same_tenant_non_driver_user(): void
    {
        // Without a type constraint, destroy was "delete any user by id":
        // an employee, or the owner's own account.
        $employee = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('driver.destroy', $employee->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('users', ['id' => $employee->id]);

        $this->actingAs($this->owner)
            ->delete(route('driver.destroy', $this->owner->id))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('users', ['id' => $this->owner->id]);
    }

    public function test_update_refuses_same_tenant_non_driver_user(): void
    {
        $employee = User::factory()->create(['name' => 'Staff Member', 'type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('driver.update', $employee->id), [
                'first_name' => 'Renamed',
                'last_name'  => 'Staff',
                'email'      => $employee->email,
            ])
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'name' => 'Staff Member']);
    }

    public function test_show_and_edit_denied_without_permission(): void
    {
        // show/edit had no can() at all: a driver-type login could read every
        // driver's file. show follows index (manage driver), edit follows update
        // (edit driver).
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);
        Driver::create(['driver_id' => $driverUser->id, 'user_id' => $driverUser->id, 'gender' => 'Male', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('driver.show', $driverUser->id))
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->actingAs($noPerms)
            ->get(route('driver.edit', $driverUser->id))
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_super_admin_can_show_any_tenants_driver(): void
    {
        // parentId() returns the SA's own id, which is never a driver's
        // parent_id, so a plain parent_id scope would lock the SA out.
        $superAdmin = User::factory()->create(['type' => 'super admin', 'parent_id' => 0]);
        $superAdmin->givePermissionTo(['manage driver', 'edit driver', 'delete driver']);
        $foreign = $this->foreignDriver();

        $this->actingAs($superAdmin)
            ->get(route('driver.show', $foreign->id))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Driver/Show'));
    }

    public function test_show_unknown_id_redirects_instead_of_crashing(): void
    {
        $this->actingAs($this->owner)
            ->get(route('driver.show', 999999))
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── DriverController::update — permission denied ──────────────────────────

    public function test_update_denied_without_edit_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $driverUser = User::factory()->driver()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->put(route('driver.update', $driverUser->id), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── DriverController::update — with license upload ────────────────────────

    public function test_update_with_license_upload_saves_successfully(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $driverUser = User::factory()->driver()->create([
            'name'      => 'Old Name',
            'email'     => 'driver@example.com',
            'parent_id' => $this->owner->id,
        ]);
        Driver::create([
            'driver_id' => $driverUser->id,
            'user_id'   => $driverUser->id,
            'gender'    => 'Male',
            'parent_id' => $this->owner->id,
        ]);

        $license = \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

        $this->actingAs($this->owner)
            ->put(route('driver.update', $driverUser->id), [
                'first_name' => 'Updated',
                'last_name'  => 'Driver',
                'email'      => 'driver@example.com',
                'license'    => $license,
            ])
            ->assertRedirect(route('driver.index'))
            ->assertSessionHas('success');
    }

    // ── DriverController::store — duplicate email validation ─────────────────

    public function test_store_flashes_error_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@test.com']);

        $this->actingAs($this->owner)
            ->post(route('driver.store'), $this->validPayload([
                'email' => 'dup@test.com',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error');
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
