<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AsInstalledApp;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * The public B2C rental storefront (BAN-261).
 *
 * `/landing` and the pages its layout partials link to serve renters: a fleet
 * list, a booking widget, contact and search. A client whose public face is
 * not a rental storefront turns it off and the whole family 404s.
 *
 * directonderweg keeps it, which is today's behavior (CLAUDE.md §10.2 rule 2).
 */
class PublicStorefrontTest extends TestCase
{
    use AsInstalledApp;
    use RefreshDatabase;
    use WithClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAppInstalled();
    }

    protected function tearDown(): void
    {
        $this->removeInstalledMarkerIfCreated();
        parent::tearDown();
    }

    /** Every route in the storefront family, as [method, uri]. */
    public static function storefrontRoutes(): array
    {
        return [
            'landing'  => ['get', '/landing'],
            'contact'  => ['get', '/contact'],
            'search'   => ['get', '/search'],
            'newsletter' => ['post', '/newsletter/subscribe'],
        ];
    }

    #[DataProvider('storefrontRoutes')]
    public function test_storefront_is_404_when_the_flag_is_off(string $method, string $uri): void
    {
        $this->asClient('directonderweg');
        config(['features.public_storefront' => false]);

        $this->{$method}($uri)->assertNotFound();
    }

    public function test_landing_still_serves_clients_that_keep_the_storefront(): void
    {
        // directonderweg is unchanged by BAN-261 — the flag defaults to on.
        $this->asClient('directonderweg');

        $this->get('/landing')->assertOk();
    }

    public function test_the_flag_alone_decides_visibility(): void
    {
        // Guards against the gate being wired to APP_CLIENT rather than the
        // feature — an inline client check is exactly what §10.2 rule 1 forbids.
        $this->asClient('directonderweg');
        config(['features.public_storefront' => true]);

        $this->get('/landing')->assertOk();

        config(['features.public_storefront' => false]);

        $this->get('/landing')->assertNotFound();
    }

    public function test_removing_the_storefront_leaves_login_reachable(): void
    {
        $this->asClient('directonderweg');
        config(['features.public_storefront' => false]);

        $this->get('/login')->assertOk();
    }
}
