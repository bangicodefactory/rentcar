<?php

namespace Tests\Feature;

use App\Http\Kernel;
use App\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Host-header validation (#210).
 *
 * Without TrustHosts the `Host` header is attacker-controlled, and several
 * absolute URLs in every response derive from it — most importantly Vite's
 * <script src>. Behind a caching layer a poisoned response would serve script
 * tags pointing at another origin, so this is a different category of problem
 * from the poisoned canonical fixed in #209.
 *
 * Laravel makes the middleware a no-op in `local` and under tests
 * (TrustHosts::shouldSpecifyTrustedHosts), which is why the end-to-end cases
 * below force the environment to production. That bypass is also why enabling
 * this cannot break local development or CI.
 */
class TrustHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Symfony keeps trusted hosts in static state, so a test that sets them
        // would otherwise leak into every later test in the process.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    /** Force the middleware out of its local/testing bypass. */
    private function asDeployedApp(string $appUrl): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => $appUrl]);
    }

    // ── Registration ─────────────────────────────────────────────────────────

    public function test_the_middleware_is_in_the_global_stack(): void
    {
        // It shipped commented out for a long time; this is what stops it
        // quietly going back.
        $middleware = (new \ReflectionClass(Kernel::class))
            ->getDefaultProperties()['middleware'];

        $this->assertContains(TrustHosts::class, $middleware);
    }

    // ── The pattern ──────────────────────────────────────────────────────────

    public function test_the_trusted_pattern_is_derived_from_the_app_url(): void
    {
        config(['app.url' => 'https://example.ma']);

        $this->assertSame(
            ['^(.+\.)?example\.ma$'],
            (new TrustHosts($this->app))->hosts()
        );
    }

    public static function hostCases(): array
    {
        return [
            'apex'            => ['example.ma', true],
            'www subdomain'   => ['www.example.ma', true],
            'deeper subdomain' => ['staging.example.ma', true],
            'foreign host'    => ['evil.example.com', false],
            // The dot in the pattern is quoted, so this must not match.
            'dot is literal'  => ['exampleXma', false],
            // Suffix attack: the pattern is anchored at both ends.
            'suffix attack'   => ['example.ma.evil.com', false],
            'prefix attack'   => ['evil-example.ma', false],
        ];
    }

    /**
     * @dataProvider hostCases
     */
    public function test_the_pattern_matches_only_this_deploys_hosts(string $host, bool $expected): void
    {
        config(['app.url' => 'https://example.ma']);

        $pattern = (new TrustHosts($this->app))->hosts()[0];

        $this->assertSame($expected, (bool) preg_match('{'.$pattern.'}i', $host), $host);
    }

    // ── End to end, with the bypass lifted ───────────────────────────────────

    public function test_a_foreign_host_is_rejected_on_a_real_deploy(): void
    {
        $this->asDeployedApp('https://example.ma');

        // Absolute URL, not a Host header: Laravel's test client builds the
        // request from its own base URL, so a header alone never reaches
        // getHost() and the assertion would pass vacuously (that is exactly how
        // a spoof test in #209 passed while proving nothing).
        $this->get('https://evil.example.com/login')->assertStatus(400);
    }

    public function test_the_configured_host_still_serves_on_a_real_deploy(): void
    {
        $this->asDeployedApp('https://example.ma');

        $this->get('https://example.ma/login')->assertOk();
    }

    public function test_a_www_subdomain_still_serves(): void
    {
        // Both clients' vhosts and TLS certs cover www., so rejecting it would
        // take down a real entry point.
        $this->asDeployedApp('https://example.ma');

        $this->get('https://www.example.ma/login')->assertOk();
    }

    public function test_another_domain_is_trusted_only_under_its_own_app_url(): void
    {
        $this->asDeployedApp('https://directonderweg.com');

        $this->get('https://directonderweg.com/login')->assertOk();
        $this->get('https://example.ma/login')->assertStatus(400);
    }

    // ── The bypass itself ────────────────────────────────────────────────────

    public function test_host_checking_stays_off_under_tests(): void
    {
        // The whole reason enabling this is safe for local dev and CI. If the
        // framework ever drops the bypass, every absolute-URL test in the suite
        // starts failing — this says why.
        config(['app.url' => 'https://example.ma']);

        $this->get('https://anything.example.com/login')->assertOk();
    }
}
