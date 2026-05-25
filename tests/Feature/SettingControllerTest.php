<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        $this->owner = User::factory()->create([
            'type'     => 'owner',
            'parent_id' => 0,
            'password' => Hash::make('secret123'),
        ]);
    }

    // ── unauthenticated ───────────────────────────────────────────────────────

    public function test_account_page_requires_auth(): void
    {
        $this->get(route('setting.account'))->assertRedirect(route('login'));
    }

    public function test_password_page_requires_auth(): void
    {
        $this->get(route('setting.password'))->assertRedirect(route('login'));
    }

    public function test_company_page_requires_auth(): void
    {
        $this->get(route('setting.company'))->assertRedirect(route('login'));
    }

    // ── SettingController::accountData ────────────────────────────────────────

    public function test_account_data_updates_name_and_email(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.account'), [
                'name'  => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id'    => $this->owner->id,
            'name'  => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_account_data_flashes_error_on_missing_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.account'), ['email' => 'x@example.com'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_account_data_flashes_error_on_duplicate_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->owner)
            ->post(route('setting.account'), [
                'name'  => 'Owner',
                'email' => 'taken@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── SettingController::passwordData ───────────────────────────────────────

    public function test_password_data_changes_password(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.password'), [
                'current_password' => 'secret123',
                'new_password'     => 'newpass456',
                'confirm_password' => 'newpass456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass456', $this->owner->fresh()->password));
    }

    public function test_password_data_flashes_error_on_wrong_current_password(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.password'), [
                'current_password' => 'wrongpassword',
                'new_password'     => 'newpass456',
                'confirm_password' => 'newpass456',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_password_data_flashes_error_when_confirm_does_not_match(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.password'), [
                'current_password' => 'secret123',
                'new_password'     => 'newpass456',
                'confirm_password' => 'different789',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── SettingController::companyData ────────────────────────────────────────

    public function test_company_data_persists_settings_and_readable_via_helper(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.company'), [
                'company_name'    => 'Acme Rentals',
                'company_email'   => 'info@acme.com',
                'company_phone'   => '+212600000000',
                'company_address' => '1 Main St, Casablanca',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'name'      => 'company_name',
            'value'     => 'Acme Rentals',
            'parent_id' => $this->owner->id,
        ]);

        $settings = settings();
        $this->assertEquals('Acme Rentals', $settings['company_name']);
    }

    public function test_company_data_flashes_error_on_missing_company_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.company'), [
                'company_email'   => 'info@acme.com',
                'company_phone'   => '+212600000000',
                'company_address' => '1 Main St',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── SettingController::smtpData ───────────────────────────────────────────

    public function test_smtp_data_persists_all_smtp_keys(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.smtp'), [
                'sender_name'       => 'Acme',
                'sender_email'      => 'noreply@acme.com',
                'server_driver'     => 'smtp',
                'server_host'       => 'smtp.acme.com',
                'server_port'       => '587',
                'server_username'   => 'user@acme.com',
                'server_password'   => 'smtppassword',
                'server_encryption' => 'tls',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'name'      => 'SERVER_HOST',
            'value'     => 'smtp.acme.com',
            'parent_id' => $this->owner->id,
        ]);
    }

    public function test_smtp_data_flashes_error_on_missing_host(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.smtp'), [
                'sender_name'  => 'Acme',
                'sender_email' => 'noreply@acme.com',
                // server_host intentionally omitted
                'server_driver'     => 'smtp',
                'server_port'       => '587',
                'server_username'   => 'user',
                'server_password'   => 'pass',
                'server_encryption' => 'tls',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── SettingController::paymentData ────────────────────────────────────────

    public function test_payment_data_persists_currency(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.payment'), [
                'CURRENCY'        => 'EUR',
                'CURRENCY_SYMBOL' => '€',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'name'      => 'CURRENCY',
            'value'     => 'EUR',
            'parent_id' => $this->owner->id,
        ]);
    }

    public function test_payment_data_flashes_error_on_missing_currency(): void
    {
        $this->actingAs($this->owner)
            ->post(route('setting.payment'), ['CURRENCY_SYMBOL' => '€'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
