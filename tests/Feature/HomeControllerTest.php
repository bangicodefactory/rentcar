<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        Permission::firstOrCreate(['name' => 'manage reminder', 'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ── HomeController::index — owner ─────────────────────────────────────────

    public function test_dashboard_returns_200_for_owner(): void
    {
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $owner->givePermissionTo('manage reminder');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_dashboard_contains_booking_and_driver_totals_for_owner(): void
    {
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        User::factory()->create(['type' => 'driver', 'parent_id' => $owner->id]);
        Booking::factory()->create(['parent_id' => $owner->id, 'amount' => 150]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('result', function ($result) {
            return $result['totalDriver'] >= 1 && $result['totalBooking'] >= 1;
        });
    }

    public function test_dashboard_owner_without_manage_reminder_gets_empty_reminders(): void
    {
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('reminders', function ($reminders) {
            return $reminders->isEmpty();
        });
    }

    // ── HomeController::index — super admin ───────────────────────────────────

    public function test_dashboard_returns_200_for_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['parent_id' => 0]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_super_admin_dashboard_contains_organization_totals(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['parent_id' => 0]);

        User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        $response = $this->actingAs($superAdmin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('result', function ($result) {
            return $result['totalOrganization'] >= 2;
        });
    }
}
