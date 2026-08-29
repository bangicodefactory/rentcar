<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Request;

/**
 * sitemap.xml (BAN-262).
 *
 * Generated rather than a static file because which pages exist depends on the
 * client's feature flags — a client without the B2C storefront must not have
 * /landing listed, or crawlers are pointed at a 404.
 */
class SeoController extends Controller
{
    /** XML sitemap covering only pages this client actually serves and indexes. */
    public function sitemap(Request $request)
    {
        // Configured origin, never the request Host — see Seo::baseUrl().
        $base = Seo::baseUrl($request);
        $urls = [];

        if (feature('public_storefront')) {
            $urls[] = ['loc' => $base.'/landing', 'priority' => '0.9', 'changefreq' => 'weekly'];
            // /contact and /search are intentionally absent: they render
            // client.layouts.app rather than the Inertia shell, so they carry no
            // canonical or robots directive, and /contact was returning 500 on
            // at least one client. Listing a page we do not control the SEO of —
            // and have not verified renders — is how you get 500s into a sitemap.
        }

        // A sitemap listing nothing is worse than none — it tells Google the
        // site has no indexable pages. Clients with no public surface (the
        // internal-only tenants) get a 404 instead.
        if ($urls === []) {
            abort(404);
        }

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
