<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AsInstalledApp;
use Tests\Concerns\WithClient;
use Tests\TestCase;

/**
 * Server-rendered SEO metadata (BAN-262).
 *
 * SSR is off by design, so anything React sets client-side does not exist for a
 * crawler that cannot run JavaScript — and social scrapers never can. These
 * assertions run against the raw HTML on purpose: asserting on the rendered DOM
 * would pass while the thing being fixed stayed broken.
 *
 * The B2C storefront landing (/landing) is the indexable public page.
 */
class SeoMetadataTest extends TestCase
{
    use AsInstalledApp;
    use RefreshDatabase;
    use WithClient;

    private const SEO = [
        'title'       => 'Direct Onderweg — Car Rental in Tétouan',
        'description' => 'Rent a car in Tétouan with Direct Onderweg: a modern fleet, transparent prices and fast pickup.',
        'site_name'   => 'Direct Onderweg',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAppInstalled();
        $this->asClient('directonderweg');
        config(['client.seo' => self::SEO]);
    }

    protected function tearDown(): void
    {
        $this->removeInstalledMarkerIfCreated();
        parent::tearDown();
    }

    // ── The storefront landing is the indexable page ─────────────────────────

    public function test_landing_ships_a_title_and_description_in_the_raw_html(): void
    {
        $html = $this->get('/landing')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Direct Onderweg — Car Rental in Tétouan</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Rent a car in Tétouan', $html);
    }

    public function test_landing_ships_open_graph_and_twitter_tags(): void
    {
        $html = $this->get('/landing')->getContent();

        foreach (['og:type', 'og:title', 'og:url', 'og:description', 'og:site_name'] as $tag) {
            $this->assertStringContainsString($tag, $html, "missing {$tag}");
        }

        $this->assertStringContainsString('twitter:card', $html);
    }

    public function test_a_missing_og_image_asset_emits_no_image_tag(): void
    {
        // An og:image pointing at a 404 makes LinkedIn and Slack render a broken
        // card instead of falling back to the text-only one, so the configured
        // path is only honoured once the file actually exists.
        config(['client.seo.og_image' => '/images/does-not-exist.png']);

        $html = $this->get('/landing')->getContent();

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringContainsString('content="summary"', $html);
    }

    public function test_an_existing_og_image_asset_is_emitted_as_a_large_card(): void
    {
        config(['client.seo.og_image' => '/images/hero-login.jpg']);

        $html = $this->get('/landing')->getContent();

        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('summary_large_image', $html);
    }

    public function test_landing_is_canonical_and_indexable(): void
    {
        $html = $this->get('/landing')->getContent();

        $this->assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^"]+/landing">#', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    // ── Absolute URLs come from config, not the request ──────────────────────

    public function test_a_spoofed_host_header_cannot_choose_the_canonical(): void
    {
        // TrustHosts aside, `Host` is attacker controlled at the app layer.
        // Deriving canonical/og:url from it lets a spoofed header point them at
        // another domain — cache-poisonable, and a canonical is the worst
        // possible tag to hand over.
        //
        // Absolute URL, not a Host header: Laravel's test client builds the
        // request from its own base URL, so a header never reaches
        // getSchemeAndHttpHost() and the assertion would pass with or without
        // the fix.
        $html = $this->get('http://evil.example.com/landing')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/landing">', $html);
        $this->assertStringNotContainsString('rel="canonical" href="http://evil', $html);
        $this->assertStringNotContainsString('property="og:url" content="http://evil', $html);
    }

    public function test_an_unset_app_url_does_not_send_canonicals_to_localhost(): void
    {
        // config/app.php defaults `url` to the truthy string 'http://localhost',
        // so a plain `?:` fallback never fires and every canonical and sitemap
        // entry would silently point at localhost in production — invisible to
        // the deploy smoke test, which curls vars.APP_URL directly.
        config(['app.url' => 'http://localhost']);

        $html = $this->get('http://example.ma/landing')->getContent();

        $this->assertStringNotContainsString('href="http://localhost/', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://example.ma/landing">', $html);
    }

    public function test_an_empty_app_url_falls_back_to_the_request_host(): void
    {
        config(['app.url' => '']);

        $html = $this->get('http://example.ma/landing')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="http://example.ma/landing">', $html);
    }

    public function test_a_configured_app_url_still_beats_a_spoofed_host(): void
    {
        // The fallback must not become a way back in for the Host header.
        config(['app.url' => 'https://example.ma']);

        $html = $this->get('http://evil.example.com/landing')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="https://example.ma/landing">', $html);
        $this->assertStringNotContainsString('rel="canonical" href="http://evil', $html);
    }

    public function test_a_spoofed_host_cannot_choose_the_sitemap_urls(): void
    {
        $xml = $this->get('http://evil.example.com/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('evil.example.com', $xml);
    }

    // ── Everything else must not be indexed ──────────────────────────────────

    public function test_an_anonymous_visitor_at_the_root_is_sent_to_login(): void
    {
        // The app is internal-only: "/" has no public page.
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_an_authenticated_visitor_at_the_root_is_not_treated_as_public(): void
    {
        // HomeController@index returns the Dashboard for a signed-in user at "/".
        // Marking that indexable also selected the trimmed public Ziggy group, so
        // the dashboard rendered with 7 routes and its sidebar's
        // route('booking.index') threw client-side.
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        $html = $this->actingAs($owner)->get('/')->getContent();

        $this->assertStringContainsString('noindex', $html);
        $this->assertStringNotContainsString('og:title', $html);
        $this->assertStringContainsString('"booking.index"', $html, 'the dashboard lost the routes it needs');
    }

    public function test_login_is_noindex(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('og:title', $html);
    }

    public function test_admin_pages_are_noindex(): void
    {
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        $html = $this->actingAs($owner)->get('/dashboard')->getContent();

        $this->assertStringContainsString('noindex', $html);
    }

    public function test_landing_is_noindex_once_the_storefront_is_off(): void
    {
        config(['features.public_storefront' => false]);

        // The route 404s; the shell that renders the error page must not claim
        // to be indexable either.
        $this->get('/landing')->assertNotFound();
    }

    // ── Language and direction ───────────────────────────────────────────────

    public function test_arabic_declares_rtl(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->get('/landing')->getContent();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
    }

    public function test_latin_locales_declare_ltr(): void
    {
        $html = $this->withSession(['locale' => 'fr'])->get('/landing')->getContent();

        $this->assertStringContainsString('<html lang="fr" dir="ltr">', $html);
    }

    // ── The title suffix ─────────────────────────────────────────────────────

    public function test_the_app_name_meta_carries_the_client_name_not_the_template(): void
    {
        // app.jsx reads this for the title suffix; it used to fall back to the
        // white-label template name and render "… - RentCar" publicly.
        $html = $this->get('/landing')->getContent();

        $this->assertStringContainsString('<meta name="app-name" content="Direct Onderweg">', $html);
    }

    // ── Ziggy payload ────────────────────────────────────────────────────────

    public function test_installer_routes_are_not_published_to_the_browser(): void
    {
        $html = $this->get('/landing')->getContent();

        $this->assertStringNotContainsString('LaravelInstaller::', $html);
        $this->assertStringNotContainsString('LaravelUpdater::', $html);
    }

    public function test_routes_the_spa_actually_calls_survive_the_filter(): void
    {
        // Guards the Ziggy `except` list: over-filtering breaks route() at
        // runtime, which fails silently until a user clicks the thing.
        $html = $this->get('/login')->getContent();

        foreach (['"login"', '"logout"', '"password.request"'] as $route) {
            $this->assertStringContainsString($route, $html, "Ziggy dropped {$route}");
        }
    }

    public function test_public_pages_ship_the_routes_reachable_without_a_page_load(): void
    {
        // @routes writes window.Ziggy once per *document*, and Inertia navigates
        // without one — so a public page must carry what the pages it links to
        // need. Landing.jsx links every vehicle card through route('client.details')
        // and its Log in leads into the whole app.
        $html = $this->get('/landing')->getContent();

        foreach (['"client.details"', '"login"', '"password.request"', '"booking.index"'] as $route) {
            $this->assertStringContainsString($route, $html, "Ziggy dropped {$route}");
        }
    }

    public function test_the_app_still_gets_the_full_route_list(): void
    {
        $owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);

        $html = $this->actingAs($owner)->get('/dashboard')->getContent();

        $this->assertStringContainsString('"booking.index"', $html);
    }

    // ── sitemap.xml ──────────────────────────────────────────────────────────

    public function test_sitemap_lists_the_storefront_landing(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $xml      = $response->getContent();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('#<loc>https?://[^/]+/landing</loc>#', $xml);
    }

    public function test_the_sitemap_omits_pages_that_do_not_use_this_shell(): void
    {
        // /contact and /search render client.layouts.app, so they carry no
        // canonical or robots directive — and /contact was 500ing on one client.
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/contact', $xml);
        $this->assertStringNotContainsString('/search', $xml);
    }

    public function test_sitemap_is_404_when_the_client_has_no_public_pages(): void
    {
        config(['features.public_storefront' => false]);

        // An empty sitemap tells Google the site has no indexable pages, which
        // is worse than not having one.
        $this->get('/sitemap.xml')->assertNotFound();
    }
}
