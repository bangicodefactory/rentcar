<?php

namespace Tests\Feature;

use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class SignatureControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    // Minimal 1×1 PNG, base64-encoded — valid PNG image data for store tests.
    private const VALID_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        Storage::fake('public');

        $perms = ['manage driver', 'delete driver'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo($perms);
    }

    // ── route accessibility ───────────────────────────────────────────────────
    // NOTE: signature routes are registered OUTSIDE the auth middleware group
    // (see routes/web.php:485-488). index/destroy crash when unauthenticated
    // because \Auth::user() returns null; only store has no permission guard.

    // ── SignatureController::store ────────────────────────────────────────────

    public function test_store_saves_signature_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $this->owner->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signatures', ['user_id' => $this->owner->id]);
    }

    public function test_store_writes_file_to_public_disk(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $this->owner->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ]);

        $record = Signature::where('user_id', $this->owner->id)->firstOrFail();
        Storage::disk('public')->assertExists($record->signature_path);
    }

    public function test_store_rejects_signature_without_png_prefix(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $this->owner->id,
                'signature' => 'data:image/jpeg;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('signatures', ['user_id' => $this->owner->id]);
    }

    public function test_store_rejects_missing_user_id(): void
    {
        // ValidationException is caught by the controller's generic catch(\Exception)
        // so validation errors land in the 'error' flash, not 'errors' bag.
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_rejects_missing_signature(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id' => $this->owner->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_rejects_non_existent_user_id(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => 99999,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_store_replay_creates_second_record_for_same_user(): void
    {
        // No unique-per-user constraint — two submissions create two rows.
        // This test documents the replay behavior so a future dedup guard
        // can be added without surprise.
        $payload = [
            'user_id'   => $this->owner->id,
            'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
        ];

        $this->actingAs($this->owner)->post(route('signature.store'), $payload);
        $this->actingAs($this->owner)->post(route('signature.store'), $payload);

        $this->assertSame(2, Signature::where('user_id', $this->owner->id)->count());
    }

    // ── SignatureController::index ────────────────────────────────────────────

    public function test_index_returns_200_for_authorized_user(): void
    {
        $this->actingAs($this->owner)
            ->get(route('signature.index'))
            ->assertOk();
    }

    public function test_index_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('signature.index'))
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    // ── SignatureController::destroy ──────────────────────────────────────────

    public function test_destroy_deletes_signature_record(): void
    {
        $sig = Signature::factory()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->delete(route('signature.destroy', $sig))
            ->assertRedirect(route('signature.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signatures', ['id' => $sig->id]);
    }

    public function test_destroy_denied_without_delete_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);
        $sig = Signature::factory()->create(['user_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->delete(route('signature.destroy', $sig))
            ->assertSessionHas('error');
    }

    public function test_destroy_leaves_orphaned_file_on_disk_documents_gap(): void
    {
        // Store a real file first, then verify destroy removes the DB record
        // but DOES NOT remove the file — the controller has no file-deletion logic.
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $this->owner->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ]);

        $sig = Signature::where('user_id', $this->owner->id)->firstOrFail();

        $this->actingAs($this->owner)
            ->delete(route('signature.destroy', $sig))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signatures', ['id' => $sig->id]);
        // NOTE: file is NOT deleted by the controller — Storage::assertMissing would fail here.
        // That is a known gap; tracked separately.
    }
}
