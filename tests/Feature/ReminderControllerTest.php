<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\ReminderType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;
    protected Vehicle $vehicle;
    protected ReminderType $reminderType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage reminder', 'create reminder', 'edit reminder', 'delete reminder'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);

        $this->vehicle = Vehicle::factory()->create(['parent_id' => $this->owner->id]);
        $this->reminderType = ReminderType::factory()->create(['parent_id' => $this->owner->id]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('reminder.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('reminder.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $reminder = Reminder::factory()->create(['parent_id' => $this->owner->id]);
        $this->put(route('reminder.update', $reminder))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $reminder = Reminder::factory()->create(['parent_id' => $this->owner->id]);
        $this->delete(route('reminder.destroy', $reminder))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_reminder(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('reminder.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_reminder(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('reminder.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_reminder(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $reminder = Reminder::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->delete(route('reminder.destroy', $reminder))->assertSessionHas('error');
    }

    // ── ReminderController::index ─────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('reminder.index'))->assertOk();
    }

    // ── ReminderController::store ─────────────────────────────────────────────

    public function test_store_creates_reminder_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('reminder.store'), $this->validPayload(['name' => 'Oil Service']))
            ->assertRedirect(route('reminder.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reminders', ['name' => 'Oil Service', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('reminder.store'), $this->validPayload(['name' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_when_reminder_date_is_not_after_today(): void
    {
        $this->actingAs($this->owner)
            ->post(route('reminder.store'), $this->validPayload(['reminder_date' => now()->format('Y-m-d')]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ReminderController::update ────────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $reminder = Reminder::factory()->create([
            'name'      => 'Old Name',
            'parent_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->put(route('reminder.update', $reminder), $this->updatePayload(['name' => 'New Name']))
            ->assertRedirect(route('reminder.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'name' => 'New Name']);
    }

    public function test_update_flashes_error_on_missing_name(): void
    {
        $reminder = Reminder::factory()->create(['parent_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->put(route('reminder.update', $reminder), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── ReminderController::destroy ───────────────────────────────────────────

    public function test_destroy_deletes_reminder(): void
    {
        $reminder = Reminder::factory()->create(['parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('reminder.destroy', $reminder))
            ->assertRedirect(route('reminder.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Tyre Check',
            'type'          => $this->reminderType->id,
            'reminder_date' => now()->addDays(10)->format('Y-m-d'),
            'vehicle'       => $this->vehicle->id,
        ], $overrides);
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Tyre Check',
            'type'          => $this->reminderType->id,
            'reminder_date' => now()->addDays(10)->format('Y-m-d'),
        ], $overrides);
    }
}
