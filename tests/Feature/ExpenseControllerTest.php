<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected ExpenseType $expenseType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage expense', 'create expense', 'edit expense', 'delete expense'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->expenseType = ExpenseType::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('expense.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('expense.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $expense = Expense::factory()->create(['parent_id' => $this->owner->id]);
        $this->put(route('expense.update', $expense))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $expense = Expense::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('expense.destroy', $expense))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_expense(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('expense.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_expense(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('expense.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_expense(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $expense = Expense::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('expense.destroy', $expense))->assertSessionHas('error');
    }

    // ── ExpenseController::index ──────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('expense.index'))->assertOk();
    }

    // ── ExpenseController::store ──────────────────────────────────────────────

    public function test_store_creates_expense_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('expense.store'), $this->validPayload(['title' => 'Oil Change']))
            ->assertRedirect(route('expense.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', ['title' => 'Oil Change', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_title(): void
    {
        $this->actingAs($this->owner)
            ->post(route('expense.store'), $this->validPayload(['title' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ExpenseController::update ─────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $expense = Expense::factory()->create([
            'title'     => 'Old Title',
            'parent_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->put(route('expense.update', $expense), $this->validPayload(['title' => 'New Title']))
            ->assertRedirect(route('expense.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'title' => 'New Title']);
    }

    public function test_update_flashes_error_on_missing_title(): void
    {
        $expense = Expense::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('expense.update', $expense), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ExpenseController::destroy ────────────────────────────────────────────

    public function test_destroy_deletes_expense(): void
    {
        $expense = Expense::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('expense.destroy', $expense))
            ->assertRedirect(route('expense.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'  => 'Tyre Replacement',
            'type'   => $this->expenseType->id,
            'date'   => now()->format('Y-m-d'),
            'amount' => 120,
        ], $overrides);
    }
}
