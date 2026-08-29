<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    /**
     * Locales the app can actually serve. A client may list `nl` in
     * supported_locales without it being servable here (nl users fall back
     * to fr).
     */
    public const SUPPORTED = ['ar', 'fr', 'en'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = null;
        $supportedLanguages = self::SUPPORTED;

        // Per-client default for anonymous/guest visitors. Defaults to 'fr'
        // (today's behaviour) when a client doesn't set it.
        $clientDefault = config('client.public_default_locale', 'fr');

        // Priority 1: Get from authenticated user.
        //
        // Note this is not the last word for signed-in visitors: the XSS route
        // middleware runs after this group and re-asserts Auth::user()->lang
        // app-wide.
        if (Auth::check() && Auth::user()->lang) {
            $locale = Auth::user()->lang;
        }

        // Priority 2: Get from session
        if (!$locale) {
            $locale = session('locale');
        }

        // Priority 3: the client's public default (else 'fr')
        if (!$locale) {
            $locale = $clientDefault;
        }

        // Validate locale - fall back to the client default, then 'fr'
        if (!in_array($locale, $supportedLanguages)) {
            $locale = in_array($clientDefault, $supportedLanguages) ? $clientDefault : 'fr';
        }

        // Set the application locale
        app()->setLocale($locale);

        // Ensure session has the current locale
        session(['locale' => $locale]);

        return $next($request);
    }
}
