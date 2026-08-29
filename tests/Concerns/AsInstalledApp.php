<?php

namespace Tests\Concerns;

/**
 * Marks the app as installed for tests that hit a public page as a guest.
 *
 * HomeController@index's guest branch hands off to the installer when the app
 * isn't installed — `setup()` is storage/installed, which a fresh CI checkout
 * lacks. Without the marker a guest GET / redirects to /install (302) instead
 * of reaching the demo-gateway or login branches. Production and CI always run
 * installed, so this makes the test match.
 *
 * This is a genuine "passes on my machine" trap: a developer box that has been
 * installed once keeps the marker forever, so the tests go green locally and
 * fail only on CI. It was documented inline in the first installed-app suite; extracted here
 * once the third and fourth class needed it.
 *
 * The marker is removed in tearDown only if this trait created it, so a dev box
 * that genuinely isn't installed is left as it was.
 *
 * NOTE: storage/installed is a process-global file; InstallerGuardTest *removes*
 * it to assert the not-installed path. Safe under the single-process
 * `php artisan test` we run today, but these classes would race under --parallel.
 */
trait AsInstalledApp
{
    private bool $createdInstalledMarker = false;

    protected function markAppInstalled(): void
    {
        $marker = setup();

        if (! file_exists($marker)) {
            @mkdir(dirname($marker), 0755, true);
            file_put_contents($marker, '');
            $this->createdInstalledMarker = true;
        }
    }

    protected function removeInstalledMarkerIfCreated(): void
    {
        if ($this->createdInstalledMarker && file_exists(setup())) {
            @unlink(setup());
        }
    }
}
