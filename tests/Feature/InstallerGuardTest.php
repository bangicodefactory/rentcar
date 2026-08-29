<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Covers HomeController@index's "app not yet installed" guard (#145).
 *
 * The guard used to be `header('location:install'); die;`. It is now a
 * framework redirect to the installer. This test pins the observable
 * behaviour — guest GET / with no install marker → 302 to /install — so the
 * old die() can never creep back.
 */
class InstallerGuardTest extends TestCase
{
    private ?string $stashedMarker = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the NOT-installed state. If a real marker exists (a dev box
        // running the installed app), stash its exact contents and remove it,
        // restoring in tearDown so local state is never corrupted.
        //
        // NOTE: storage/installed is a process-global file, and the AsInstalledApp suites
        // toggle it the other way (they *create* the marker). That's safe under
        // the single-process `php artisan test` we run today, but these two
        // classes would race if the suite is ever switched to --parallel.
        $marker = setup();
        if (file_exists($marker)) {
            $this->stashedMarker = file_get_contents($marker);
            unlink($marker);
        }
    }

    protected function tearDown(): void
    {
        if ($this->stashedMarker !== null) {
            file_put_contents(setup(), $this->stashedMarker);
            $this->stashedMarker = null;
        }

        parent::tearDown();
    }

    public function test_guest_home_redirects_to_installer_when_not_installed(): void
    {
        $this->assertFileDoesNotExist(setup());

        // Same destination the legacy header('location:install') sent to — but a
        // real 302 response, not a die() that kills the process. Pin the code
        // explicitly: the whole point of the fix is "302, not process death".
        $this->get('/')
            ->assertStatus(302)
            ->assertRedirect('install');
    }
}
