<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────
    // NOTE: PermissionController has no can() checks — any authenticated user may call all routes.

    public function test_index_requires_auth(): void
    {
        $this->get(route('permission.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('permission.store'))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'deleteme', 'guard_name' => 'web']);
        $this->delete(route('permission.destroy', $perm))->assertRedirect(route('login'));
    }

    // ── PermissionController::index ───────────────────────────────────────────

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $this->actingAs($this->owner)->get(route('permission.index'))->assertOk();
    }

    // ── PermissionController::store ───────────────────────────────────────────

    public function test_store_creates_permission_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('permission.store'), ['title' => 'send reports'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', ['name' => 'send reports']);
    }

    public function test_store_flashes_error_on_missing_title(): void
    {
        $this->actingAs($this->owner)
            ->post(route('permission.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_assigns_permission_to_role_when_user_roles_provided(): void
    {
        $role = Role::create(['name' => 'tester', 'guard_name' => 'web', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('permission.store'), [
                'title'      => 'special access',
                'user_roles' => [$role->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($role->fresh()->hasPermissionTo('special access'));
    }

    // ── PermissionController::destroy ─────────────────────────────────────────

    public function test_destroy_deletes_permission(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'deleteme', 'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($this->owner)
            ->delete(route('permission.destroy', $perm))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }
}
