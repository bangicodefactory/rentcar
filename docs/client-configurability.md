# Multi-Client Configurability

Last updated: 2026-05-15
Owner: Ahmed

This document is the implementation deep-dive for the rules summarized
in `CLAUDE.md` section 10. Read that section first if you haven't.

**Constraints:**

- One codebase serves multiple clients.
- Each client gets its **own isolated deployment** (own DB, own URL,
  own secrets). No shared infrastructure between clients.
- Differences between clients span four categories: branding,
  integrations/credentials, feature toggles, and business-logic
  variants.
- Today: 1 client (`directonderweg`). 12-month horizon: 2–5.
- Customization happens at three layers: **runtime (admin UI),
  deploy-time (env), and code-level (per-client namespace)**.

---

## 1. The four layers

| Layer        | What lives here                                    | Where it lives                                          | Who edits it                       |
| ------------ | -------------------------------------------------- | ------------------------------------------------------- | ---------------------------------- |
| **Runtime** | Branding, copy, admin-toggleable settings          | DB (`settings` table via the `Setting` model)           | Client admin via the admin UI      |
| **Env**     | Secrets, integration credentials, `APP_CLIENT`     | `.env` on the deployed server / GitHub Environment      | Ops / devops at deploy time        |
| **Config**  | Feature-flag defaults, locale defaults, switches   | `config/clients/<client>.php`                           | Developers, via PR                 |
| **Code**    | Real behavior variants (e.g. different TVA rules)  | `app/Clients/<ClientName>/` namespace                   | Developers, via PR                 |

Precedence at runtime: **runtime > env > config > core defaults**.

---

## 2. The `APP_CLIENT` selector

A single env variable selects the active client per deployment:

```dotenv
# .env (production for client "directonderweg")
APP_CLIENT=directonderweg
```

There is no detection magic — the deployment explicitly says which
client it is. The current production deployment uses
`APP_CLIENT=directonderweg`; that's also the default in
`config/app.php` so local dev "just works" without setting it.

```php
// config/app.php
'client' => env('APP_CLIENT', 'directonderweg'),
```

Anywhere in the code:

```php
config('app.client'); // 'directonderweg'
```

---

## 3. Directory layout

```
app/
  Clients/
    DirectOnderweg/
      Providers/
        DirectOnderwegServiceProvider.php
      Services/
        DirectOnderwegPricingService.php
        DirectOnderwegTvaService.php
      Seeders/
        BrandingSeeder.php
    AcmeRentals/                              # hypothetical second client
      Providers/
        AcmeRentalsServiceProvider.php
      Services/
        AcmeRentalsPricingService.php
      Seeders/
        BrandingSeeder.php
  Contracts/                                  # core interfaces (new)
    PricingServiceContract.php
    TvaServiceContract.php
  ...
config/
  clients/
    _default.php                              # baseline that every client inherits
    directonderweg.php
    acme.php
  features.php                                # feature flag defaults
```

The `App\Contracts\*` namespace holds the interfaces that vary per
client. The core code (controllers, services, etc.) injects the
interface; the client's `ServiceProvider` binds the concrete
implementation. This is just plain Laravel container binding — no
custom infrastructure required.

---

## 4. `config/clients/<client>.php`

Example for the current client:

```php
// config/clients/directonderweg.php
return [
    'name'           => 'Direct Onderweg',
    'default_locale' => 'nl',
    'supported_locales' => ['nl', 'fr', 'en', 'ar'],

    'features' => [
        'paypal'        => true,
        'stripe'        => true,
        'excel_import'  => true,
        'multi_branch'  => false,
        'tva_renumber'  => true,
        'signatures'    => true,
    ],

    'bindings' => [
        \App\Contracts\PricingServiceContract::class
            => \App\Clients\DirectOnderweg\Services\DirectOnderwegPricingService::class,
        \App\Contracts\TvaServiceContract::class
            => \App\Clients\DirectOnderweg\Services\DirectOnderwegTvaService::class,
    ],

    'branding_seed' => [
        'app_name'      => 'Direct Onderweg',
        'theme_color'   => 'color1',
        'company_logo'  => 'logo.png',
        'meta_seo_title'=> 'Direct Onderweg — Car Rental',
    ],
];
```

And a baseline that everyone inherits (so we don't repeat ourselves):

```php
// config/clients/_default.php
return [
    'features' => [
        'paypal'        => true,
        'stripe'        => true,
        'excel_import'  => true,
        'multi_branch'  => false,
        'tva_renumber'  => true,
        'signatures'    => true,
    ],
    'bindings' => [
        // any safe defaults
    ],
];
```

A small `ClientServiceProvider` merges `_default.php` with the active
client's file (the client's keys win), publishes the result to the
runtime config, and binds the requested interfaces.

### Business-rule values

Besides feature flags, a client file can override a few scalar business
rules. `_default.php` documents each one; read them with
`config('client.<key>', <default>)`.

| Key | Default | directonderweg | Meaning |
|---|---|---|---|
| `cash_payment_max` | 5000 | 5000 | Largest single cash receipt (MAD). |
| `violation_match_grace_hours` | 12 | 12 | How far outside a rental a traffic violation is still matched to it. |
| `late_return_grace_minutes` | 14 | 420 | How late a return may be, past the last whole rental day, before a full extra day is charged (this value included). Applied in `vehicleRateCalculation()`. |

---

## 5. The `ClientServiceProvider`

```php
// app/Providers/ClientServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $client = config('app.client');
        $default = config("clients._default", []);
        $specific = config("clients.{$client}", []);

        // Deep-merge: client overrides defaults.
        $resolved = array_replace_recursive($default, $specific);
        config(["client" => $resolved]);

        // Bind client-specific implementations to core interfaces.
        foreach ($resolved['bindings'] ?? [] as $contract => $concrete) {
            $this->app->bind($contract, $concrete);
        }

        // Register the active client's own ServiceProvider if it exists.
        $clientProvider = sprintf(
            'App\\Clients\\%s\\Providers\\%sServiceProvider',
            studly_case($client),
            studly_case($client)
        );
        if (class_exists($clientProvider)) {
            $this->app->register($clientProvider);
        }
    }
}
```

Register `ClientServiceProvider` early in `config/app.php` (before
the app's own providers that depend on the bindings).

After this, anywhere in the app:

```php
config('client.name');                      // 'Direct Onderweg'
config('client.default_locale');            // 'nl'
config('client.features.paypal');           // true

$pricing = app(\App\Contracts\PricingServiceContract::class);
// resolved to App\Clients\DirectOnderweg\Services\DirectOnderwegPricingService
```

---

## 6. Feature flags

Defaults live in `config/features.php`:

```php
// config/features.php
return [
    'paypal'        => env('FEATURE_PAYPAL', null),
    'stripe'        => env('FEATURE_STRIPE', null),
    'excel_import'  => env('FEATURE_EXCEL_IMPORT', null),
    'multi_branch'  => env('FEATURE_MULTI_BRANCH', null),
    'tva_renumber'  => env('FEATURE_TVA_RENUMBER', null),
    'signatures'    => env('FEATURE_SIGNATURES', null),
];
```

Resolution order, lowest to highest precedence:

1. Hardcoded boolean default in `config/features.php` (rarely used —
   `null` means "ask the client").
2. The client's `config/clients/<client>.php` `features` array.
3. The `.env` `FEATURE_*` override (for emergency overrides only).
4. *(future)* DB-stored runtime flag in `settings`, for admin-flippable
   features.

A helper:

```php
// app/Helper/helper.php
if (!function_exists('feature')) {
    function feature(string $name): bool
    {
        // .env override wins if explicitly set.
        $envOverride = config("features.{$name}");
        if ($envOverride !== null) {
            return (bool) $envOverride;
        }
        return (bool) config("client.features.{$name}", false);
    }
}
```

Usage:

```php
if (feature('paypal')) { /* ... */ }
```

In Blade (still relevant during the migration):

```blade
@if (feature('paypal'))
    <a href="{{ route('paypal.checkout') }}">Pay with PayPal</a>
@endif
```

In Inertia + React (post-Phase 5): pass `features` as a shared prop
(see `docs/inertia-shared-props.md`):

```jsx
import { usePage } from '@inertiajs/react';
const { features } = usePage().props;
{features.paypal && <PayPalButton />}
```

For routes:

```php
// app/Http/Middleware/RequireFeature.php
class RequireFeature
{
    public function handle($request, $next, string $feature)
    {
        abort_unless(feature($feature), 404);
        return $next($request);
    }
}

// routes/web.php
Route::middleware('feature:paypal')->group(function () {
    Route::post('/checkout/paypal', [PaymentController::class, 'paypal']);
});
```

---

## 7. Branding (the `Setting` model)

Branding stays where it is today — in the DB `settings` table read via
the `Setting` model and the `settingsKeys()` helper in
`app/Helper/helper.php`. Two changes:

1. **Seeded per-client at install** from the `branding_seed` block in
   `config/clients/<client>.php`. Add a `php artisan client:install`
   Artisan command that takes `APP_CLIENT` and seeds the settings if
   they don't already exist (idempotent).
2. **Admin UI continues to edit them.** Once seeded, the admin owns
   them. Re-seeding doesn't overwrite live values.

---

## 8. Tests

Default test client is `directonderweg` (matches prod today).

```php
// tests/Concerns/WithClient.php
namespace Tests\Concerns;

trait WithClient
{
    protected function asClient(string $client): self
    {
        config(['app.client' => $client]);
        // Re-run the ClientServiceProvider merge.
        $this->app->register(\App\Providers\ClientServiceProvider::class, true);
        return $this;
    }
}
```

Usage:

```php
class PricingTest extends TestCase
{
    use WithClient, RefreshDatabase;

    public function test_directonderweg_charges_eu_vat(): void
    {
        $this->asClient('directonderweg');
        // ...
    }

    public function test_acme_uses_morocco_tva(): void
    {
        $this->asClient('acme');
        // ...
    }
}
```

**CI runs the test suite once per client.** GitHub Actions matrix:

```yaml
strategy:
  matrix:
    client: [directonderweg, acme]
env:
  APP_CLIENT: ${{ matrix.client }}
```

A failing suite for any client fails the PR.

---

## 9. Onboarding a new client (the runbook)

When adding a new client, do everything in **one PR**:

1. `config/clients/<new>.php` — branding seed, feature flags, locale
   defaults, contract bindings (start with empty bindings; add as
   variants emerge).
2. `app/Clients/<NewClient>/Providers/<NewClient>ServiceProvider.php`
   — empty stub. Real bindings show up later.
3. Add `<new>` to the CI matrix.
4. Add `.env.<new>.example` with placeholders for the keys that
   client will need (Stripe pubkey/secret naming, PayPal, mail).
5. Create a GitHub Environment `production-<new>` and `staging-<new>`
   with the actual secrets and required reviewers.
6. Add a GitHub Actions deploy workflow (or a matrix entry on the
   existing one) targeting the new Environment.
7. Run `php artisan client:install` against the new database to seed
   branding from the config.

Done in one PR so we never end up with a "half-onboarded" client.

---

## 10. Git / GitHub strategy (the longer version)

### Trunk-based development

- `main` is the source of truth. Everyone develops against it.
- Short-lived feature branches (`feat/...`, `fix/...`, `chore/...`)
  merge into `main` via PR.
- The current migration lives on `feat/modernization`; once merged
  it joins the trunk.

### Releases as tags

- Releases are tagged on `main`: `v2.0.0`, `v2.0.1`, `v2.1.0`, ...
- Tags follow SemVer. Breaking changes for a client (changed feature
  flag default, removed feature) bump major.
- A client deploy = "Environment `production-<client>` at tag `vX.Y.Z`".
- Clients can be on different tags. There is no client branch.

### GitHub Environments

For each client, the repo has:

- `staging-<client>` — for pre-production verification.
- `production-<client>` — the live deploy.

Each environment holds:

- **Secrets**: `DB_HOST`, `DB_PASSWORD`, `STRIPE_SECRET`, `PAYPAL_*`,
  `MAIL_PASSWORD`, `APP_KEY`, etc.
- **Required reviewers**: at least one human approves the deploy.
- **Deployment branches/tags**: only tags from `main` may deploy to
  `production-<client>`.

### CI

A single workflow on every push/PR to `main`:

```yaml
jobs:
  test:
    strategy:
      matrix:
        client: [directonderweg]   # extend as clients onboard
        php: ['8.3']
    env:
      APP_CLIENT: ${{ matrix.client }}
    steps:
      - ...checkout, composer install, npm ci...
      - run: php artisan test
      - run: npm test --if-present
```

### CD

A separate workflow per environment, triggered manually on a tag:

```yaml
on:
  workflow_dispatch:
    inputs:
      tag:
        required: true
  release:
    types: [published]
jobs:
  deploy:
    environment: production-directonderweg
    env:
      APP_CLIENT: directonderweg
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ inputs.tag || github.event.release.tag_name }}
      - ...build, ssh deploy, php artisan migrate --force, php artisan optimize...
```

One client = one Environment = one deploy workflow target.

### Branch protection on `main`

- Require PR review (1+ approving reviews).
- Require the CI matrix to pass (one job per client).
- Require linear history (no merge commits — rebase or squash).
- Forbid direct pushes.

(During the migration, the same rules apply to `feat/modernization`
once it stabilizes.)

### When to use a release branch (escape hatch)

Open `release/<client>/<vMAJOR.MINOR>` **only** when:

- A client genuinely cannot upgrade — e.g. they're stuck on an old
  PHP version and we just bumped the requirement.
- They need a critical patch backported.

Rules:

- The branch name explains *why* it exists.
- It cherry-picks fixes from `main`; it does not host net-new features.
- Close it within one release cycle. If you can't close it, the
  underlying upgrade blocker is now a P0 ticket.

### CODEOWNERS (optional, useful at 3+ clients)

```
/app/Clients/DirectOnderweg/      @alice
/config/clients/directonderweg.php @alice
/app/Clients/Acme/                @bob
/config/clients/acme.php          @bob
/CLAUDE.md                        @lead
/docs/                            @lead
```

### Secrets hygiene

- Never commit secrets. Even per-client `.env` files are committed
  only as `.env.<client>.example` with placeholders.
- `composer.lock` is committed (already is).
- `npm` lockfile is committed (already is).
- Storage uploads, generated PDFs, signatures: all ignored.

---

## 11. Anti-patterns (don't do these)

- ❌ `if ($client === 'acme') { ... }` in core controllers — use a
  feature flag or a contract binding.
- ❌ A long-lived `client/acme` branch — drifts immediately, costs
  forever.
- ❌ Putting secrets in `config/clients/<client>.php` — that file is
  committed. Secrets go in env / GitHub Environments.
- ❌ Hardcoding a client's locales/feature list in JS or React —
  read from Inertia shared props that come from `config('client.*')`.
- ❌ Forgetting to add a new client to the CI matrix — they'll silently
  rot.
- ❌ Onboarding a new client across multiple PRs — leaves an
  inconsistent state.
- ❌ Modifying the `Setting` model schema to add client-id columns —
  each client has its own DB, settings are already isolated.

---

## 12. What this enables (and what it doesn't)

**Enables:**

- Different clients running different versions (just deploy a different
  tag).
- Different clients with different feature mixes — no code branching.
- Different business-logic variants — explicitly in per-client
  namespaces, code-reviewed, testable.
- Adding a new client without touching core code.

**Doesn't enable (and we don't need it yet):**

- Multi-tenancy in a single deployment. If a client appears who can't
  afford their own deploy, that's a separate architecture (`stancl/tenancy`
  or similar) and a separate decision.
- Live A/B testing of feature flags. The flags here are
  per-client-deploy, not per-user. Add that later if needed.
- Per-client schema differences. Schema is the same across clients;
  data differs.
