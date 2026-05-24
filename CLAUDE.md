# CLAUDE.md — Migration Rules for rentcar

This file tells Claude how to work on this repository **during the
Laravel 10 → 12 + Inertia/React + Vite migration**. The migration's
single most important rule is: **same functionality, end to end**. The
client is already running the `dev` branch, so any regression that
escapes to them is a real outage.

Read this file before you touch anything else in the repo. The rules
below override anything you might think is "best practice in general".

---

## 1. Target stack

| Layer        | From                                  | To                                    |
| ------------ | ------------------------------------- | ------------------------------------- |
| PHP          | 8.1                                   | 8.3+                                  |
| Laravel      | 10.48                                 | 12 (latest stable)                    |
| Frontend     | Blade + Alpine.js + jQuery            | Inertia.js + React 19 (full SPA)      |
| Build tool   | Laravel Mix + webpack                 | Vite                                  |
| CSS          | Tailwind 3                            | Tailwind 4 (or latest stable)         |
| Test runner  | PHPUnit 10                            | PHPUnit 11 (or Pest, see plan)        |
| Auth         | Sanctum 3 + Breeze (Blade)            | Sanctum (latest) + Breeze (React/Inertia stack) |
| PDF          | barryvdh/laravel-dompdf 3.x           | latest compatible                     |
| Signature    | creagia/laravel-sign-pad 2.x          | latest compatible                     |
| Permissions  | spatie/laravel-permission 5.x         | latest compatible                     |
| Payments     | stripe-php 7.x + srmklive/paypal 3.x  | latest compatible (test in sandbox!)  |

Anything not in this table is either preserved exactly or flagged in
`docs/migration-plan.md` before being changed.

---

## 2. Branching & commits

- The migration lives on **`feat/modernization`**, branched off `dev`.
- **Never commit directly to `dev`** or `main`. If you're on `dev`,
  stop and switch back to `feat/modernization`.
- Keep commits **small and atomic**, one logical concern each.
  Use the format `type(BAN-N): <imperative short summary>`.
  Examples of good commit messages:
  - `test(BAN-23): cover BookingController@store happy + sad paths`
  - `chore(BAN-87): bump phpunit to ^11.0`
  - `refactor(BAN-42): port booking/index.blade.php to Inertia + React`
  - `perf(BAN-55): document N+1 on /dashboard (do not fix yet)`
  - `docs(BAN-20): add CONTRIBUTING.md with branch flow + test discipline`
- Reference the migration phase in the commit body when relevant
  (e.g. "Phase 1: test backfill").
- **Do not squash** during the migration — the per-step history is
  the safety net if we need to bisect a regression.

---

## 3. The Golden Rule: tests before changes

The migration is gated by tests. Specifically:

1. **No production code change ships without a passing test that
   covers the behavior being preserved.** This includes view changes
   when porting Blade → React.
2. Before modifying any controller, **the controller's endpoints
   must already have feature-test coverage** for both the happy path
   and at least one failure path (validation error, auth/permission
   denial, not-found, etc.). If they don't, write the tests first in
   the same PR, in a separate commit.
3. **Run `php artisan test` before and after every change.** If
   anything new goes red, stop and fix before continuing.
4. Use `RefreshDatabase` (or `DatabaseTransactions` where speed
   matters) — never let tests leak rows into a dev DB.

`docs/test-plan.md` is the authoritative list of what coverage is
required before each migration phase can start.

---

## 4. "Same functionality" — what that means in practice

Same functionality means **observable behavior from the user, the
client, and the API does not change** during the migration. Specifically:

- **Routes and URL shapes stay identical.** Same paths, same HTTP
  verbs, same route names. If you must change a route, it's a feature
  change — open a separate ticket, do not bundle it.
- **Form field names, validation messages (per locale), and response
  status codes stay identical.**
- **Database schema is frozen for the duration of the migration.**
  No `ALTER TABLE` migrations. The only schema migrations allowed are
  *adding indexes that the perf audit has explicitly recommended*, and
  even those land in a separate, clearly-labeled PR after the audit is
  approved.
- **Emails: subject lines, From addresses, and visible body content
  must match** before and after the migration. PDF templates likewise.
- **All 14 locales** (en, fr, ar, de, es, it, ja, nl, da, pl, pt, ru,
  plus the `*.json` siblings) must keep their existing keys. You may
  *add* keys; you may not *remove* or *rename* keys without a follow-up
  ticket.
- **Permissions matrix (spatie/laravel-permission) is sacred.** Every
  endpoint that's currently behind `permission:...` must remain so,
  with the exact same permission string.
- **Money flows** — Stripe checkout, PayPal checkout, TVA
  (VAT) calculation, coupons, credits — **must be covered by tests
  before they are touched**, and must be smoke-tested manually in
  Stripe/PayPal sandbox after each migration phase.

If a refactor is *tempting but unnecessary* for the migration (e.g.
rename a controller, extract a service, "fix" a naming inconsistency),
**do not do it**. Open a follow-up ticket. The migration is already
huge; orthogonal refactors expand the regression surface.

---

## 5. Frontend rewrite rules (Blade → Inertia + React)

We're rewriting the frontend, not redesigning it. Constraints:

- **Inertia.js is the bridge.** Controllers return
  `Inertia::render('PageName', $props)` instead of `view(...)`. Page
  components live under `resources/js/Pages/` and mirror the existing
  Blade folder structure 1:1 during the port (move-then-clean).
- **Visual fidelity:** the React port must match the current Blade
  page pixel-close at first. Redesign is a separate project.
- **jQuery is being removed.** `jq-signature` must be replaced with a
  React-friendly signature component (e.g. `react-signature-canvas`).
  Confirm the saved-signature payload (base64 PNG / SVG) is byte-compatible
  with what `creagia/laravel-sign-pad` expects, or migrate that side too.
- **Alpine.js is being removed** once its host views are ported. Do
  not introduce new Alpine code.
- **No new jQuery, no new Blade-style global window helpers.** New
  code uses ES modules, React hooks, and TypeScript (preferred) or
  JSDoc-annotated JS.
- **i18n in the SPA**: pass translations through Inertia shared props
  (e.g. via `tightenco/ziggy` for routes, custom translations prop) so
  that the existing `lang/` files remain the single source of truth.
  Do **not** duplicate strings into JS files.
- **Server-side validation stays server-side.** React forms post to
  the existing controller endpoints; Inertia surfaces the validation
  errors. Client-side validation, if added, is duplicative UX only —
  never the only check.
- **Port one route at a time.** Each PR ports a logically grouped set
  of pages (e.g., "Vehicle CRUD pages"), keeps the corresponding Blade
  files until the React version is verified, and only deletes the
  Blade files in a follow-up cleanup commit on the same branch.

---

## 6. Backend migration rules (Laravel 10 → 12)

- Step through **10 → 11 → 12** with the test suite green at each
  step. Don't jump 10 → 12 directly even though it's tempting.
- `app/Helper/helper.php` is autoloaded via Composer's `files`
  array — **keep it autoloaded**, even if Laravel 11's new bootstrap
  changes how providers register. We are not rewriting helpers in
  this migration.
- **Laravel 11's directory restructuring is optional.** Don't move
  `app/Http/Kernel.php` → `bootstrap/app.php` style unless you've
  read the upgrade guide carefully and have time to do it cleanly.
  If you do migrate the structure, the test suite must stay green
  on the very next commit.
- `rachidlaasri/laravel-installer` — confirm it still works with
  Laravel 12 or replace with a documented manual install in the
  README. Either is fine; document the choice in `docs/migration-plan.md`.
- `laravelcollective/html` is dying — if it doesn't have a Laravel 12
  compatible release, port the few `Form::` / `Html::` usages to plain
  Blade/Inertia. Grep the codebase first to size the impact before
  ripping it out.
- Replace deprecated facades, `$dates` → `casts`, `Bus::dispatchNow` →
  `Bus::dispatchSync`, etc., as you encounter them. Each replacement
  is a separate commit.
- **Run the test suite after every dependency bump.** If `composer
  update` causes red tests, bisect the cause before continuing.

---

## 7. Performance: audit, don't optimize

The client says the app is slow. We're **auditing first**, fixing
later. Specifically:

- **Do not add caching, eager-load relations, or add indexes during
  the migration** unless a test you just wrote depends on it.
- Use Telescope + Debugbar + the slow-query log to gather data.
  Output goes in `docs/perf-audit.md` (created from the template in
  `docs/perf-audit-plan.md`) as a *prioritized list* of findings with
  reproduction steps and rough fix estimates.
- The audit PR is **report-only**. Fixes are scheduled after the
  migration completes, or as separate PRs explicitly approved by the
  user, one bottleneck at a time.

---

## 8. Things to never do during this migration

Each of these has bitten teams doing exactly this kind of work:

- ❌ **Schema changes** ("while I'm here, this column should be
  nullable"). The migration freezes schema.
- ❌ **Renaming routes, controllers, or models.** Even "obvious"
  ones. Cosmetic renames are a separate ticket.
- ❌ **Deleting Blade files before the React replacement is verified
  in the running app** (not just in tests).
- ❌ **Mocking Stripe or PayPal in a way that lets the real
  integration drift.** Use the official Stripe test-mode and
  PayPal sandbox; record interactions; smoke-test after each phase.
- ❌ **Committing `.env`, real API keys, or storage uploads.**
- ❌ **Running `composer update` without a corresponding
  `composer.lock` commit and a passing test run.**
- ❌ **Removing or renaming translation keys.** Adding is fine.
- ❌ **Squash-merging migration commits.** The granular history is
  the rollback plan.
- ❌ **Bundling perf fixes into migration commits.** Audit only;
  fixes ship separately.

---

## 9. Working agreements with Claude

When you (Claude) work on this repo:

- **Start each session by reading `CLAUDE.md`, `docs/migration-plan.md`,
  and `docs/test-plan.md`.** They tell you what phase we're in.
- **Confirm the current branch is `feat/modernization`** before
  writing files. If not, stop and tell the user.
- **Before any non-trivial change, restate the goal back to the user
  in one sentence** so we both agree on scope.
- **Default to small PRs.** If a task would be more than ~400 lines
  of diff, propose breaking it up first.
- **When in doubt, ask.** Especially around money flows, PDFs, and
  the signature pad.
- **Don't celebrate green tests as proof of correctness.** A green
  suite means "no regression *that the suite covers*". The migration
  also requires manual smoke tests at phase boundaries (see
  `docs/migration-plan.md`).

---

## 10. Multi-client configurability

This codebase is being prepared to serve **multiple clients from one
codebase**, with **one isolated deployment per client**. Today there is
one client (the original one running on `dev`); we expect 2–5 within a
year. Each client may differ in **branding, integrations, feature
toggles, and business-logic variants**. The rules below define how that
is structured. Full implementation details are in
`docs/client-configurability.md`; this section is the contract Claude
must follow.

### 10.1 The mental model

- **One `main` branch is the source of truth.** Every client runs the
  same code, configured differently. No per-client forks.
- **A single env var, `APP_CLIENT`, selects the active client** at
  deploy time (e.g. `APP_CLIENT=directonderweg`). The current
  production client is `directonderweg`; that's the default.
- **Configuration layers, from highest precedence to lowest:**
  1. Runtime DB settings (the existing `Setting` model — branding,
     copy, admin-flippable toggles).
  2. Per-deploy `.env` (secrets, integration credentials, the
     `APP_CLIENT` selector itself).
  3. Per-client config file at `config/clients/<client>.php`
     (feature flag defaults, locale defaults, business-rule pickers).
  4. Core defaults at `config/features.php` and `config/clients/_default.php`.
- **Code-level variants** live under `app/Clients/<ClientName>/`,
  registered by a small `ClientServiceProvider` that runs only when
  `APP_CLIENT` matches. Core code talks to **interfaces**; client
  namespaces provide the bindings.

### 10.2 Rules Claude must follow

1. **Never hard-code client-specific behavior in core code.** If a
   feature differs by client, gate it behind a feature flag
   (`feature('paypal')`) or behind an interface bound per client.
   Inline `if ($client === 'acme') { ... }` checks are forbidden.
2. **Default everything to the current behavior.** The
   `directonderweg` config must reproduce today's behavior exactly.
   When you add a flag for a new variant, the default in
   `config/features.php` must keep the existing client unchanged.
3. **Routes for optional features are guarded by `feature:<name>`
   middleware.** Disabled features return 404, never a half-rendered
   page.
4. **Branding stays in DB (the `Setting` model)**, seeded per-client
   at install from `config/clients/<client>.php`. Don't move it back
   into config files.
5. **Secrets never go in `config/clients/*.php`.** Those files are
   committed. Secrets (Stripe keys, PayPal credentials, DB password,
   mail credentials) live in the deployment's environment — in
   GitHub Environments for CI/CD, in the server env for the host.
6. **Every test that exercises a client-specific path declares its
   client** with the `WithClient` trait (`$this->asClient('acme')`).
   The default test client is `directonderweg`, matching prod today.
7. **When adding a new client**, do it as a single PR that:
   - adds `config/clients/<new>.php`,
   - adds `app/Clients/<NewClient>/` (even if just a stub),
   - adds a seed migration row for branding,
   - adds a CI matrix entry so the suite runs once per client,
   - adds a GitHub Environment for the deploy target.
   No client gets added piecemeal across multiple PRs.
8. **React reads features and branding from Inertia shared props**,
   not from a separate API call. Add them to the shared-props
   contract (`docs/inertia-shared-props.md`).

### 10.3 Git / GitHub strategy

- **Trunk-based.** `main` is the only long-lived development branch
  post-migration. The current migration is on `feat/modernization`;
  it merges to `dev`, then `dev` is promoted to `main` once we
  unify the world.
- **Tags ship.** Releases are tagged `vMAJOR.MINOR.PATCH` on `main`.
  Each client deploy uses a specific tag. A client can stay one tag
  behind for as long as they need to (no code branch required).
- **GitHub Environments per client.** Each client has at minimum
  `production-<client>` and `staging-<client>` Environments in the
  repo. Each environment holds its own secrets (`STRIPE_SECRET`,
  `PAYPAL_*`, `DB_*`, `APP_CLIENT`, etc.) and required reviewers.
- **Branch protection on `main`.** Require PR review, require the
  test matrix (one job per client) to pass, require linear history.
- **CI matrix.** One CI workflow runs the test suite once per
  `APP_CLIENT` value. A test that's red for any client fails the PR.
- **Per-client release branches are an escape hatch, not the norm.**
  Open a `release/<client>/<version>` branch *only* when a client
  must be held back from a specific upgrade for a verifiable reason
  (e.g. their PHP version isn't ready). Document the reason in the
  branch description; close it within one release cycle.
- **CODEOWNERS** on `app/Clients/<Client>/` and
  `config/clients/<client>.php` if different team members own
  different client overlays.
- **Per-client secrets are never committed.** Even `.env.<client>`
  is committed only as `.env.<client>.example` with placeholders.
- **Don't introduce a `client/<name>` long-lived branch.** That path
  leads to drift, merge hell, and forgotten clients.

### 10.4 When this gets built

The multi-client skeleton is part of **Phase 0** of the migration
(see `docs/migration-plan.md`). The skeleton ships as a no-op:
`APP_CLIENT=directonderweg`, all flags default to today's behavior,
no code paths change. Subsequent migration phases use the new
structure — e.g. Phase 5 surfaces `features` as Inertia shared props.

---

## 11. Reference

- `README.md` — local development setup
- `docs/migration-plan.md` — phased migration roadmap with gates
- `docs/test-plan.md` — test coverage requirements per phase
- `docs/perf-audit-plan.md` — how to run the perf audit
- `docs/perf-audit.md` — (output, created during Phase 0) the findings
- `docs/client-configurability.md` — multi-client architecture deep-dive

Last updated: 2026-05-15.
