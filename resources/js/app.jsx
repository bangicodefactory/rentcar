import './bootstrap';
import '@fontsource-variable/nunito'; // self-hosted brand typeface (replaces Google Fonts CDN)
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from 'next-themes';
import * as Sentry from '@sentry/react';
import { initSentry } from '@/lib/sentry';

// Browser-side error reporting. No-op without VITE_SENTRY_DSN (local/dev stay
// silent); complements sentry-laravel, which only sees backend errors.
initSentry();

// Injects or updates a <style id="brand-dark"> element with .dark-scoped vars.
function injectDarkVars(darkMap) {
    const id = 'brand-dark';
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement('style');
        el.id = id;
        document.head.appendChild(el);
    }
    const rules = Object.entries(darkMap)
        .map(([k, v]) => `  ${k}: ${v};`)
        .join('\n');
    el.textContent = `.dark {\n${rules}\n}`;
}

// RTL applies when the active locale is Arabic, or when the admin has set the
// layout_direction setting to rtlmode.
// Locale takes precedence.
function applyDirection(locale, branding) {
    const rtl = (locale || '').startsWith('ar') || branding?.layoutDirection === 'rtlmode';
    document.documentElement.dir = rtl ? 'rtl' : 'ltr';
    document.documentElement.lang = locale || 'en';
}

function applyBranding(branding) {
    if (!branding) return;

    if (branding.cssVars) {
        const root = document.documentElement;

        if (branding.cssVars.light) {
            // BAN-243: full derived palette { light: {...}, dark: {...} }
            Object.entries(branding.cssVars.light).forEach(([k, v]) => root.style.setProperty(k, v));
            injectDarkVars(branding.cssVars.dark ?? {});
        } else {
            // Legacy: flat 3-var object (no brand_color set)
            Object.entries(branding.cssVars).forEach(([k, v]) => root.style.setProperty(k, v));
        }
    }

}

createInertiaApp({
    // The suffix comes from the server-rendered <meta name="app-name">, which is
    // the client's own name from config/clients/<client>.php. It used to fall
    // back to the build-time VITE_APP_NAME and, when that was unset on a deploy,
    // every public page rendered "… - RentCar" — leaking the white-label
    // template name onto a client's commercial domain. A page that sets no
    // title now gets the app name alone instead of a bare " - RentCar".
    title: (title) => {
        const appName = document.querySelector('meta[name="app-name"]')?.content
            || import.meta.env.VITE_APP_NAME
            || 'RentCar';

        if (!title) return appName;

        // A page whose own title already names the product would otherwise
        // render "Name — … - Name".
        return title.includes(appName) ? title : `${title} - ${appName}`;
    },
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        const branding = props.initialPage.props.branding;
        const locale   = props.initialPage.props.locale;

        // Apply before first paint so there's no theme flash
        applyBranding(branding);
        applyDirection(locale, branding);

        // Keep in sync on SPA navigations (e.g. admin changes theme/locale mid-session)
        router.on('navigate', (event) => {
            applyBranding(event.detail.page.props.branding);
            applyDirection(event.detail.page.props.locale, event.detail.page.props.branding);
        });

        const initialTheme = branding?.layoutMode === 'darkmode' ? 'dark' : 'light';

        createRoot(el).render(
            <Sentry.ErrorBoundary
                fallback={<div className="p-6 text-center text-sm text-muted-foreground">Something went wrong. Please reload the page.</div>}
            >
                <ThemeProvider
                    attribute="class"
                    defaultTheme={initialTheme}
                    enableSystem={false}
                >
                    <App {...props} />
                </ThemeProvider>
            </Sentry.ErrorBoundary>
        );
    },
});
