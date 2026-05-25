<?php

namespace Tests\Feature;

use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class ExpenseTypeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage expense type', 'create expense type', 'edit expense type', 'delete expense type'];
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
        $this->get(route('expense-type.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('expense-type.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $et = ExpenseType::factory()->create();
        $this->put(route('expense-type.update', $et))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $et = ExpenseType::factory()->create();
        $this->delete(route('expense-type.destroy', $et))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_expense_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('expense-type.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_expense_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('expense-type.store'), ['title' => 'Fuel'])->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_expense_type(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $et = ExpenseType::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('expense-type.destroy', $et))->assertSessionHas('error');
    }

    // ── ExpenseTypeController::index ──────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('expense-type.index'))->assertOk();
    }

    // ── ExpenseTypeController::store ──────────────────────────────────────────

    public function test_store_creates_expense_type_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('expense-type.store'), ['title' => 'Maintenance'])
            ->assertRedirect(route('expense-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expense_types', ['title' => 'Maintenance', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_title(): void
    {
        $this->actingAs($this->owner)
            ->post(route('expense-type.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ExpenseTypeController::update ─────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $et = ExpenseType::factory()->create(['title' => 'Old', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('expense-type.update', $et), ['title' => 'New'])
            ->assertRedirect(route('expense-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expense_types', ['id' => $et->id, 'title' => 'New']);
    }

    public function test_update_flashes_error_on_missing_title(): void
    {
        $et = ExpenseType::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('expense-type.update', $et), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ExpenseTypeController::destroy ────────────────────────────────────────

    public function test_destroy_deletes_expense_type(): void
    {
        $et = ExpenseType::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('expense-type.destroy', $et))
            ->assertRedirect(route('expense-type.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('expense_types', ['id' => $et->id]);
    }
}
