<?php

namespace Tests\Feature;

use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
    // Sentry DIRECTONDERWEG-8 / DIRECTONDERWEG-B: guests reaching /signature and
    // /signature/create hit \Auth::user()->... on null and got a 500. All four
    // signature routes now sit in the auth+XSS group like their neighbours.

    public function test_index_requires_auth(): void
    {
        $this->get(route('signature.index'))->assertRedirect(route('login'));
    }

    public function test_create_requires_auth(): void
    {
        $this->get(route('signature.create'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('signature.store'), [
            'user_id'   => $this->owner->id,
            'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('signatures', 0);
    }

    public function test_destroy_requires_auth(): void
    {
        $sig = Signature::factory()->create(['user_id' => $this->owner->id]);

        $this->delete(route('signature.destroy', $sig))->assertRedirect(route('login'));

        $this->assertDatabaseHas('signatures', ['id' => $sig->id]);
    }

    // ── SignatureController::store ────────────────────────────────────────────

    public function test_store_saves_signature_and_redirects(): void
    {
        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $this->owner->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect(route('signature.index'))
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

    public function test_index_renders_inertia_component(): void
    {
        Signature::factory()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->get(route('signature.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Signature/Index')
                ->has('signatures')
            );
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

    // ── tenant scope + store permission ───────────────────────────────────────
    // Signatures carry no parent_id; the tenant is the signature's user (the
    // owner, or a user whose parent_id is the owner). index/destroy/store are
    // scoped through that relation, and store now requires 'manage driver'
    // like index does.

    private function otherTenantDriver(): User
    {
        $otherOwner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        return User::factory()->create(['type' => 'driver', 'parent_id' => $otherOwner->id]);
    }

    public function test_index_only_lists_signatures_of_own_tenant(): void
    {
        $ownDriver = User::factory()->create(['type' => 'driver', 'parent_id' => $this->owner->id]);
        $mine      = Signature::factory()->create(['user_id' => $ownDriver->id]);
        $ownerSig  = Signature::factory()->create(['user_id' => $this->owner->id]);
        Signature::factory()->create(['user_id' => $this->otherTenantDriver()->id]);

        $this->actingAs($this->owner)
            ->get(route('signature.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Signature/Index')
                ->has('signatures', 2)
                ->where('signatures', fn ($sigs) => collect($sigs)->pluck('id')->sort()->values()->all()
                    === collect([$ownerSig->id, $mine->id])->sort()->values()->all())
            );
    }

    public function test_destroy_denied_for_other_tenants_signature(): void
    {
        $foreign = Signature::factory()->create(['user_id' => $this->otherTenantDriver()->id]);

        $this->actingAs($this->owner)
            ->delete(route('signature.destroy', $foreign))
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseHas('signatures', ['id' => $foreign->id]);
    }

    public function test_super_admin_sees_and_can_delete_every_tenants_signatures(): void
    {
        // Owners hang off the super admin (parent_id = SA id) and drivers hang
        // off owners, so a parent_id scope would hide every driver from the SA.
        // Pre-scope behaviour was "SA sees all"; keep it.
        $superAdmin = User::factory()->create(['type' => 'super admin', 'parent_id' => 0]);
        $superAdmin->givePermissionTo(['manage driver', 'delete driver']);
        $ownDriver = User::factory()->create(['type' => 'driver', 'parent_id' => $this->owner->id]);
        $a = Signature::factory()->create(['user_id' => $ownDriver->id]);
        $b = Signature::factory()->create(['user_id' => $this->otherTenantDriver()->id]);

        $this->actingAs($superAdmin)
            ->get(route('signature.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('signatures', 2));

        $this->actingAs($superAdmin)
            ->delete(route('signature.destroy', $b))
            ->assertRedirect(route('signature.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signatures', ['id' => $b->id]);
        $this->assertDatabaseHas('signatures', ['id' => $a->id]);
    }

    public function test_orphaned_signature_is_still_listed_and_deletable(): void
    {
        // DriverController@destroy removes the user but not their signatures.
        // The migration declares ON DELETE CASCADE, but the directonderweg prod
        // tables are MyISAM, so the FK is silently dropped and orphans are real
        // there. Those rows used to show with driver '—' and be deletable; the
        // tenant scope must not strand them. Disable FK checks to model prod.
        $driver = User::factory()->create(['type' => 'driver', 'parent_id' => $this->owner->id]);
        $orphan = Signature::factory()->create(['user_id' => $driver->id]);
        Schema::disableForeignKeyConstraints();
        $driver->forceDelete();
        Schema::enableForeignKeyConstraints();
        $this->assertDatabaseHas('signatures', ['id' => $orphan->id]);

        $this->actingAs($this->owner)
            ->get(route('signature.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('signatures', 1)
                ->where('signatures.0.id', $orphan->id)
                ->where('signatures.0.driver_name', null)
            );

        $this->actingAs($this->owner)
            ->delete(route('signature.destroy', $orphan))
            ->assertRedirect(route('signature.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signatures', ['id' => $orphan->id]);
    }

    public function test_create_denied_without_manage_driver(): void
    {
        // store is gated on 'manage driver'; the pad page must be gated the
        // same way so a user is refused before drawing, not after.
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->get(route('signature.create'))
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));
    }

    public function test_store_denied_without_manage_driver(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('signature.store'), [
                'user_id'   => $noPerms->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('Permission Denied.'));

        $this->assertDatabaseCount('signatures', 0);
    }

    public function test_store_rejects_user_from_other_tenant(): void
    {
        $foreignDriver = $this->otherTenantDriver();

        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $foreignDriver->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('signatures', ['user_id' => $foreignDriver->id]);
    }

    public function test_store_accepts_own_tenants_driver(): void
    {
        $ownDriver = User::factory()->create(['type' => 'driver', 'parent_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('signature.store'), [
                'user_id'   => $ownDriver->id,
                'signature' => 'data:image/png;base64,' . self::VALID_PNG_BASE64,
            ])
            ->assertRedirect(route('signature.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signatures', ['user_id' => $ownDriver->id]);
    }

    // ── SignatureController::create ───────────────────────────────────────────

    public function test_create_renders_inertia_component(): void
    {
        $this->actingAs($this->owner)
            ->get(route('signature.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Signature/Create')
                ->has('drivers')
            );
    }

}
