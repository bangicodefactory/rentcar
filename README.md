# rentcar

Multi-client Laravel platform for car rental businesses. Originally
built for **Direct Onderweg** (operating in Morocco) and now being
prepared to serve multiple clients from one codebase, one isolated
deployment per client. Features include vehicle and driver management,
bookings, rental agreements with digital signatures, inspections,
expenses, coupons and credits, TVA (VAT) handling, multi-locale
support, Stripe and PayPal payments, reCAPTCHA, and role/permission
management.

> **Heads up:** the project is in the middle of a Laravel 10 → 12 +
> Inertia/React modernization. Read `CLAUDE.md` and
> `docs/migration-plan.md` before opening a PR.
>
> The repo is `bangicodefactory/rentcar`. The first onboarded client
> identifier is `directonderweg` (selected via `APP_CLIENT=directonderweg`
> in each deployment's environment). See `docs/client-configurability.md`
> for how the multi-client architecture works.

---

## Tech stack (current, pre-migration)

- PHP **8.1+** (target during migration: **8.3+**)
- Laravel **10.48** (target: **12**)
- MySQL **8.0+** (MariaDB 10.6+ also works)
- Node **18+** (target during migration: **20+ LTS**)
- Frontend: Blade + Alpine.js + jQuery + Tailwind, built with Laravel Mix
  (target: Inertia.js + React 19 + Vite)

Key packages: `spatie/laravel-permission`, `laravel/sanctum`,
`barryvdh/laravel-dompdf`, `creagia/laravel-sign-pad`,
`anhskohbo/no-captcha` (reCAPTCHA v2), `srmklive/paypal`,
`stripe/stripe-php`, `kkomelin/laravel-translatable-string-exporter`,
`rachidlaasri/laravel-installer`, `phpoffice/phpspreadsheet`.

---

## Local development setup

### 1. Prerequisites

Install these first:

- PHP 8.1 (the project still runs on 8.1; 8.3 is required to follow the
  migration branch)
- Composer 2.x
- Node.js 18+ and npm 9+
- MySQL 8 or MariaDB 10.6+ running locally
- (optional) Redis if you want cache/queue/session on Redis
- (optional) [MailHog](https://github.com/mailhog/MailHog) or
  [Mailpit](https://github.com/axllent/mailpit) for catching dev emails

If you're on macOS the easiest path is Homebrew:

```bash
brew install php@8.1 composer node@20 mysql mailpit
```

On Windows, [Laragon](https://laragon.org/) or WSL2 + Ubuntu is the
smoothest setup.

### 2. Clone and install

```bash
git clone git@github.com:bangicodefactory/rentcar.git
cd rentcar

composer install
npm ci
cp .env.example .env
php artisan key:generate
```

### 3. Configure your `.env`

At minimum, edit these:

```dotenv
APP_NAME="Direct Onderweg"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rentcar
DB_USERNAME=root
DB_PASSWORD=

# Active client for this deployment (default for local dev)
APP_CLIENT=directonderweg

# Mail (Mailpit defaults)
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@directonderweg.local"
MAIL_FROM_NAME="${APP_NAME}"

# Queue — leave as 'sync' for local dev unless you're testing jobs
QUEUE_CONNECTION=sync

# Cache / Session — 'file' is fine locally
CACHE_DRIVER=file
SESSION_DRIVER=file
```

The app additionally reads these payment / captcha keys from `.env`.
**Use sandbox/test keys only for local dev.** Never commit real keys.

```dotenv
# Stripe (test mode)
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx

# PayPal (sandbox)
PAYPAL_MODE=sandbox
PAYPAL_SANDBOX_CLIENT_ID=
PAYPAL_SANDBOX_CLIENT_SECRET=

# Google reCAPTCHA v2 — the always-pass test keys below work for local dev
NOCAPTCHA_SITEKEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
NOCAPTCHA_SECRET=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

These reCAPTCHA values are Google's public test keys — safe to use locally,
they always pass. Ask the team lead for Stripe and PayPal sandbox credentials
if you don't have them.

### 4. Create the database, migrate, and seed

```bash
mysql -u root -e "CREATE DATABASE rentcar;"
php artisan migrate
php artisan db:seed
```

**Seeding is required.** It creates the roles, permissions, default subscription
tier, and the three built-in accounts you need to log in:

| Role        | Email                   | Password |
| ----------- | ----------------------- | -------- |
| Super admin | superadmin@gmail.com    | 123456   |
| Owner       | owner@gmail.com         | 123456   |
| Manager     | manager@gmail.com       | 123456   |

Change these passwords immediately on any non-local environment.

A storage symlink is required for uploads, generated PDFs, and signatures:

```bash
php artisan storage:link
```

> **Windows users:** `storage:link` creates a directory junction that requires
> either Developer Mode enabled (Settings → System → Developer Mode) or an
> elevated (Administrator) terminal. If the command fails silently, re-run it
> as Administrator or enable Developer Mode first.

### 5. Run the app

In separate terminals:

```bash
php artisan serve            # http://localhost:8000
npm run watch                # rebuild assets on file change
mailpit                      # http://localhost:8025 to see outgoing mail
```

After the Vite migration these become:

```bash
php artisan serve
npm run dev                  # Vite dev server with HMR
```

### 6. (Optional) Sail / Docker

Laravel Sail is available via `composer require --dev laravel/sail` and
`php artisan sail:install`. If you'd rather skip the local PHP/MySQL
installs, run:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run watch
```

---

## Common scripts

| Task                                  | Command                                      |
| ------------------------------------- | -------------------------------------------- |
| Run tests                             | `php artisan test`                           |
| Run a single test file                | `php artisan test --filter=BookingTest`      |
| Tinker (REPL)                         | `php artisan tinker`                         |
| Clear all caches                      | `php artisan optimize:clear`                 |
| Re-run migrations from scratch        | `php artisan migrate:fresh --seed`           |
| Build assets for production           | `npm run prod` (Mix) / `npm run build` (Vite) |
| Export translatable strings           | `php artisan translatable:export <locale>`   |

---

## Project structure (high level)

```
app/
  Helper/helper.php          # global helper functions (autoloaded)
  Http/Controllers/          # 30 domain controllers + Auth/
  Models/                    # 30 Eloquent models
  Services/                  # currently 1: TvaRenumberService
  Mail/                      # mailables (EmailVerification, Common, Document, TestMail)
  Providers/
config/                      # standard Laravel config + captcha, paypal, dompdf, sign-pad, installer
database/
  migrations/                # 57 migrations
  factories/, seeders/
lang/                        # 14 locales + matching <locale>.json files
resources/
  views/                     # 179 Blade files (being ported to React)
  js/                        # app.js, bootstrap.js (Alpine.js + jQuery today)
routes/
  web.php  (527 lines)       # main user/admin routes
  api.php                    # API routes (Sanctum)
  auth.php                   # Breeze auth scaffold
  channels.php               # broadcasting
tests/
  Feature/                   # Breeze defaults today; full coverage in progress (see docs/test-plan.md)
  Unit/
```

---

## Locales

The app currently ships with 14 locales: `ar`, `da`, `de`, `en`, `es`,
`fr`, `it`, `ja`, `nl`, `pl`, `pt`, `ru`, plus the corresponding
`*.json` sibling files. Both formats are used. **Don't remove or rename
existing keys** — the migration depends on byte-for-byte locale
parity. You can use `kkomelin/laravel-translatable-string-exporter` to
add new strings consistently.

---

## Payments & external integrations

Three integrations need extra care:

1. **Stripe** (`stripe/stripe-php`) — Stripe Checkout for bookings.
   Local dev uses test-mode keys. Webhooks (if any) point at a Stripe
   CLI forwarder: `stripe listen --forward-to localhost:8000/stripe/webhook`.
2. **PayPal** (`srmklive/paypal`) — sandbox-only locally. PayPal's
   sandbox sometimes returns flaky responses; retry before declaring
   a bug.
3. **reCAPTCHA** (`anhskohbo/no-captcha`) — Google's test keys
   (`6LeIxAcTAAAAA...`) make reCAPTCHA always pass; use them locally.

---

## Signatures and PDFs

Rental agreements are generated with `barryvdh/laravel-dompdf` and
signed via `creagia/laravel-sign-pad` (which currently uses the
`jq-signature` jQuery plugin client-side). Generated files land under
`storage/app/` — confirm `php artisan storage:link` has been run if
they don't render.

---

## Migration & testing

The repo is mid-modernization. If you're working on the migration:

- Branch off `feat/modernization` (or a ticket sub-branch of it — see `CONTRIBUTING.md`).
- Read `CLAUDE.md` for the rules-of-engagement.
- Check `docs/migration-plan.md` for the current phase and what's
  blocking the next gate.
- Check `docs/test-plan.md` for what coverage is required before
  touching a given controller.
- Run `php artisan test` before pushing, every time.

---

## Troubleshooting

| Symptom                                                | Likely fix                                                   |
| ------------------------------------------------------ | ------------------------------------------------------------ |
| `Class "App\\Helper\\..." not found`                   | `composer dump-autoload`                                     |
| Blank page after install                               | `php artisan key:generate`, check `APP_DEBUG=true`           |
| 419 on POST forms                                      | Session cookie/CSRF — clear browser cookies for `localhost`  |
| Stored PDFs / signatures not displaying                | `php artisan storage:link`                                   |
| Mix builds asset paths under `/public/...` not found   | `npm run watch` not running, or wrong `APP_URL`              |
| reCAPTCHA always fails locally                         | Use Google's test site/secret keys (see above)               |
| Migrations error on `enum` change                      | Ensure `doctrine/dbal` is installed (it already is)          |

---

## License

Proprietary. All rights reserved by the project owner.
