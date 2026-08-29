<?php

namespace Tests\Feature\Console;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientInstallTest extends TestCase
{
    use RefreshDatabase;

    // These tests exercise the command against a fixed client. Pin --client so
    // they're deterministic regardless of the CI matrix's ambient APP_CLIENT —
    // without it, client:install installs the active client and the
    // directonderweg assertions break.
    public function test_seeds_branding_on_first_run(): void
    {
        config(['clients.directonderweg.branding_seed' => [
            'app_name'  => 'Direct Onderweg',
            'theme_color' => 'color1',
        ]]);

        $this->artisan('client:install', ['--client' => 'directonderweg'])
            ->assertSuccessful()
            ->expectsOutputToContain('Seeded');

        $this->assertDatabaseHas('settings', ['name' => 'app_name',    'value' => 'Direct Onderweg', 'parent_id' => 1]);
        $this->assertDatabaseHas('settings', ['name' => 'theme_color', 'value' => 'color1',          'parent_id' => 1]);
    }

    public function test_second_run_skips_existing_values(): void
    {
        config(['clients.directonderweg.branding_seed' => [
            'app_name' => 'Direct Onderweg',
        ]]);

        $this->artisan('client:install', ['--client' => 'directonderweg'])->assertSuccessful();

        // Admin manually changes the value.
        Setting::where('name', 'app_name')->where('parent_id', 1)
            ->update(['value' => 'Custom Name']);

        // Second run must not overwrite the admin's edit.
        $this->artisan('client:install', ['--client' => 'directonderweg'])
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped');

        $this->assertDatabaseHas('settings', ['name' => 'app_name', 'value' => 'Custom Name', 'parent_id' => 1]);
    }

    public function test_no_branding_seed_exits_cleanly(): void
    {
        config(['clients.directonderweg.branding_seed' => []]);

        $this->artisan('client:install', ['--client' => 'directonderweg'])
            ->assertSuccessful()
            ->expectsOutputToContain('Nothing to do');
    }

    public function test_client_option_overrides_app_client(): void
    {
        config(['clients.acme.branding_seed' => [
            'app_name' => 'Acme Rentals',
        ]]);

        $this->artisan('client:install', ['--client' => 'acme'])
            ->assertSuccessful()
            ->expectsOutputToContain('acme'); // confirms the --client flag was honoured

        $this->assertDatabaseHas('settings', ['name' => 'app_name', 'value' => 'Acme Rentals', 'parent_id' => 1]);
    }
}
