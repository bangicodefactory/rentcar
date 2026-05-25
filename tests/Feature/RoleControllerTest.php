<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Permission $permA;
    protected Permission $permB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $this->permA = Permission::firstOrCreate(['name' => 'perm alpha', 'guard_name' => 'web']);
        $this->permB = Permission::firstOrCreate(['name' => 'perm beta',  'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo([$this->permA, $this->permB]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────
    // NOTE: RoleController has no can() checks — any authenticated user may call all routes.

    public function test_index_requires_auth(): void
    {
        $this->get(route('role.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('role.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $role = Role::create(['name' => 'testrole', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);
        $this->put(route('role.update', $role))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $role = Role::create(['name' => 'testrole', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);
        $this->delete(route('role.destroy', $role))->assertRedirect(route('login'));
    }

    // ── RoleController::index ─────────────────────────────────────────────────

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $this->actingAs($this->owner)->get(route('role.index'))->assertOk();
    }

    // ── RoleController::store ─────────────────────────────────────────────────

    public function test_store_creates_role_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('role.store'), [
                'title'           => 'Manager',
                'user_permission' => [$this->permA->id, $this->permB->id],
            ])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['name' => 'Manager', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_title(): void
    {
        $this->actingAs($this->owner)
            ->post(route('role.store'), [
                'user_permission' => [$this->permA->id],
            ])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_on_missing_user_permission(): void
    {
        $this->actingAs($this->owner)
            ->post(route('role.store'), ['title' => 'Manager'])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('error');
    }

    // ── RoleController::update ────────────────────────────────────────────────

    public function test_update_reassigns_permissions_and_redirects(): void
    {
        $role = Role::create(['name' => 'testrole', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);
        $role->givePermissionTo($this->permA);

        $this->actingAs($this->owner)
            ->put(route('role.update', $role), [
                'title'           => 'testrole',
                'user_permission' => [$this->permB->id],
            ])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('success');

        $this->assertTrue($role->fresh()->hasPermissionTo($this->permB));
        $this->assertFalse($role->fresh()->hasPermissionTo($this->permA));
    }

    public function test_update_flashes_error_on_missing_user_permission(): void
    {
        $role = Role::create(['name' => 'testrole', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('role.update', $role), ['title' => 'testrole'])
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('error');
    }

    // ── RoleController::destroy ───────────────────────────────────────────────

    public function test_destroy_deletes_role(): void
    {
        $role = Role::create(['name' => 'testrole', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('role.destroy', $role))
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
