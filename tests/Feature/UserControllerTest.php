<?php

namespace Tests\Feature;

use App\Models\LoggedHistory;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Subscription $subscription;
    protected Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = [
            'manage user',
            'create user',
            'edit user',
            'delete user',
            'manage logged history',
            'delete logged history',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->subscription = Subscription::factory()->create([
            'user_limit'             => 5,
            'enabled_logged_history' => 1,
        ]);
        $this->owner->subscription = $this->subscription->id;
        $this->owner->save();

        // store() (non-super-admin path) accesses this record unconditionally before
        // the null-guard, so it must exist for any store test to reach the DB write.
        // Use create() directly — NotificationFactory includes 'enabled_sms' which is not in the table.
        Notification::create([
            'module'        => 'user_create',
            'name'          => 'New User',
            'subject'       => 'Welcome',
            'message'       => 'Your account was created.',
            'short_code'    => '{company_name}',
            'enabled_email' => 0,
            'parent_id'     => $this->owner->id,
        ]);

        $this->employeeRole = Role::create([
            'name'       => 'employee',
            'guard_name' => 'web',
            'parent_id'  => $this->owner->id,
        ]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('users.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $target = User::factory()->create(['parent_id' => $this->owner->id]);
        $this->put(route('users.update', $target))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $target = User::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('users.destroy', $target))->assertRedirect(route('login'));
    }

    public function test_logged_history_requires_auth(): void
    {
        $this->get(route('logged.history'))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_user(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('users.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_user(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('users.store'), [])->assertSessionHas('error');
    }

    public function test_update_denied_without_edit_user(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $target  = User::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->put(route('users.update', $target), [])->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_user(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $target  = User::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('users.destroy', $target))->assertSessionHas('error');
    }

    public function test_logged_history_denied_without_manage_logged_history(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('logged.history'))->assertSessionHas('error');
    }

    // ── UserController::index ─────────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('users.index'))->assertOk();
    }

    // ── UserController::store ─────────────────────────────────────────────────

    public function test_store_creates_employee_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('users.store'), [
                'name'     => 'John Doe',
                'email'    => 'johndoe@test.com',
                'password' => 'password123',
                'role'     => $this->employeeRole->id,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'johndoe@test.com']);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('users.store'), [
                'email'    => 'johndoe@test.com',
                'password' => 'password123',
                'role'     => $this->employeeRole->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_enforces_subscription_user_limit(): void
    {
        // user_limit = 1; 1 existing employee makes totalUser() == user_limit → denied.
        $this->subscription->user_limit = 1;
        $this->subscription->save();
        User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('users.store'), [
                'name'     => 'Over Limit',
                'email'    => 'over@test.com',
                'password' => 'password123',
                'role'     => $this->employeeRole->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── UserController::update ────────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $target = User::factory()->create(['name' => 'Old Name', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('users.update', $target), [
                'name'  => 'New Name',
                'email' => $target->email,
                'role'  => $this->employeeRole->id,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'New Name']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $target = User::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('users.update', $target), [
                'email' => $target->email,
                'role'  => $this->employeeRole->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── UserController::destroy ───────────────────────────────────────────────

    public function test_destroy_deletes_user(): void
    {
        $target = User::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    // ── UserController::loggedHistory ─────────────────────────────────────────

    public function test_logged_history_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('logged.history'))->assertOk();
    }

    public function test_logged_history_denied_when_subscription_flag_is_off(): void
    {
        $this->subscription->enabled_logged_history = 0;
        $this->subscription->save();

        $this->actingAs($this->owner)->get(route('logged.history'))->assertSessionHas('error');
    }

    // ── UserController::loggedHistoryShow ─────────────────────────────────────
    // NOTE: resources/views/logged_history/show.blade.php does not exist yet,
    // so only the auth guard is verifiable; the 200 path is untestable until the view lands.

    public function test_logged_history_show_requires_auth(): void
    {
        $history = LoggedHistory::factory()->create(['parent_id' => $this->owner->id]);
        $this->get(route('logged.history.show', $history->id))->assertRedirect(route('login'));
    }

    // ── UserController::loggedHistoryDestroy ──────────────────────────────────

    public function test_logged_history_destroy_requires_auth(): void
    {
        $history = LoggedHistory::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('logged.history.destroy', $history->id))->assertRedirect(route('login'));
    }

    public function test_logged_history_destroy_denied_without_delete_logged_history(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $history = LoggedHistory::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->delete(route('logged.history.destroy', $history->id))
            ->assertSessionHas('error');
    }

    public function test_logged_history_destroy_deletes_record(): void
    {
        $history = LoggedHistory::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('logged.history.destroy', $history->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('logged_histories', ['id' => $history->id]);
    }
}
