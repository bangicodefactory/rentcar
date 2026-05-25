<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $perms = ['manage notification', 'create notification', 'edit notification', 'delete notification'];
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
        $this->get(route('notification.index'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('notification.store'))->assertRedirect(route('login'));
    }

    public function test_update_requires_auth(): void
    {
        $notification = $this->makeNotification('new_booking');
        $this->put(route('notification.update', $notification))->assertRedirect(route('login'));
    }

    public function test_destroy_requires_auth(): void
    {
        $notification = $this->makeNotification('new_booking');
        $this->delete(route('notification.destroy', $notification))->assertRedirect(route('login'));
    }

    // ── permission denied ─────────────────────────────────────────────────────

    public function test_index_denied_without_manage_notification(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->get(route('notification.index'))->assertSessionHas('error');
    }

    public function test_store_denied_without_create_notification(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $this->actingAs($noPerms)->post(route('notification.store'), $this->validPayload())->assertSessionHas('error');
    }

    public function test_destroy_denied_without_delete_notification(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $notification = $this->makeNotification('new_booking');
        $this->actingAs($noPerms)->delete(route('notification.destroy', $notification))->assertSessionHas('error');
    }

    // ── NotificationController::index ─────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)->get(route('notification.index'))->assertOk();
    }

    // ── NotificationController::store ─────────────────────────────────────────

    public function test_store_creates_notification_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('notification.store'), $this->validPayload(['module' => 'new_booking']))
            ->assertRedirect(route('notification.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', ['module' => 'new_booking', 'parent_id' => $this->owner->id]);
    }

    public function test_store_flashes_error_on_missing_module(): void
    {
        $this->actingAs($this->owner)
            ->post(route('notification.store'), $this->validPayload(['module' => '']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_flashes_error_when_module_already_exists_for_owner(): void
    {
        $this->makeNotification('new_booking');

        $this->actingAs($this->owner)
            ->post(route('notification.store'), $this->validPayload(['module' => 'new_booking']))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── NotificationController::update ────────────────────────────────────────

    public function test_update_persists_changes(): void
    {
        $notification = $this->makeNotification('new_driver');

        $this->actingAs($this->owner)
            ->put(route('notification.update', $notification), [
                'subject'       => 'New Subject',
                'message'       => 'New message body.',
                'enabled_email' => 0,
            ])
            ->assertRedirect(route('notification.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'subject' => 'New Subject']);
    }

    public function test_update_flashes_error_on_missing_subject(): void
    {
        $notification = $this->makeNotification('new_driver');
        $this->actingAs($this->owner)
            ->put(route('notification.update', $notification), [])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── NotificationController::destroy ──────────────────────────────────────

    public function test_destroy_deletes_notification(): void
    {
        $notification = $this->makeNotification('new_booking');

        $this->actingAs($this->owner)
            ->delete(route('notification.destroy', $notification))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'module'  => 'user_create',
            'subject' => 'Welcome to the platform',
            'message' => 'Your account has been created.',
        ], $overrides);
    }

    // NotificationFactory includes enabled_sms which is not in the DB table.
    private function makeNotification(string $module): Notification
    {
        return Notification::create([
            'module'         => $module,
            'name'           => ucfirst(str_replace('_', ' ', $module)),
            'subject'        => 'Test Subject',
            'message'        => 'Test message.',
            'short_code'     => '{company_name}',
            'enabled_email'  => 0,
            'parent_id'      => $this->owner->id,
        ]);
    }
}
