<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class OptionControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage options', 'create options', 'edit options', 'delete options'];
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
        $this->get(route('option.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('option.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $option = Option::factory()->create();
        $this->put(route('option.update', $option))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $option = Option::factory()->create();
        $this->delete(route('option.destroy', $option))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_options(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('option.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_options(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('option.store'), ['name' => 'GPS'])->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_options(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $option = Option::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('option.destroy', $option))->assertSessionHas('error');
    }

    // ── OptionController::index ───────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('option.index'))->assertOk();
    }

    // ── OptionController::store ───────────────────────────────────────────────

    public function test_store_creates_option_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('option.store'), ['name' => 'Child Seat'])
            ->assertRedirect(route('option.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('options', ['name' => 'Child Seat', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('option.store'), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── OptionController::update ──────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $option = Option::factory()->create(['name' => 'Old', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->put(route('option.update', $option), ['name' => 'New'])
            ->assertRedirect(route('option.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('options', ['id' => $option->id, 'name' => 'New']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $option = Option::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('option.update', $option), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── OptionController::destroy ─────────────────────────────────────────────

    public function test_destroy_deletes_option(): void
    {
        $option = Option::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('option.destroy', $option))
            ->assertRedirect(route('option.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('options', ['id' => $option->id]);
    }
}
