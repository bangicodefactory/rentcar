# Contributing

This guide covers the workflow rules for contributors to this repository during
the Laravel 10 → 12 + Inertia/React migration. Follow these rules exactly —
they exist to protect the production client from regressions and to keep the
migration history bisectable.

---

## Branch flow

- **Always branch off `feat/modernization`**, never off `dev` or `main`.
  ```
  git checkout feat/modernization
  git pull origin feat/modernization
  git checkout -b ban-<N>-short-description
  ```
- `dev` and `main` are protected. Direct pushes are blocked by branch
  protection rules.
- `feat/modernization` is also protected — open a PR against it; never push
  directly.
- Keep branches short-lived. One ticket = one branch = one PR.

---

## Commit message conventions

Use the format `type(BAN-N): <imperative short summary>`:

```
type(BAN-<N>): <imperative short summary>
```

Examples:
```
test(BAN-23): cover BookingController@store happy + sad paths
chore(BAN-87): bump phpunit to ^11.0
refactor(BAN-42): port booking/index.blade.php to Inertia + React
perf(BAN-55): document N+1 on /dashboard (do not fix yet)
docs(BAN-20): add CONTRIBUTING.md with branch flow + test discipline
```

- Use the imperative mood ("add", "fix", "port", "bump"), not past tense.
- Keep the first line under 72 characters.
- Add a blank line + body when the *why* isn't obvious from the summary.
- Reference the migration phase in the body when relevant
  (e.g. "Phase 1: test backfill").

---

## Test discipline

1. **The suite must be green before you push.** Run it locally:
   ```
   php artisan test
   ```
   If anything new goes red, stop and fix it before pushing.

2. **No production code change ships without a test that covers the behavior
   being preserved.** Write the test first (in a separate commit in the same
   branch), then make the change.

3. **Before modifying any controller**, its endpoints must already have
   feature-test coverage for the happy path *and* at least one failure path
   (validation error, auth denial, not-found). If coverage is missing, add it
   before touching the controller.

4. Use `RefreshDatabase` (or `DatabaseTransactions` where speed matters) so
   tests never leak rows into the dev database.

5. Green tests mean "no regression the suite covers", not "correct". Phase
   boundaries also require manual smoke tests — see `docs/migration-plan.md`.

---

## Never squash migration commits

The granular per-step commit history is the rollback plan. Do **not**:

- Squash-merge a migration PR.
- Amend published commits on a shared branch.
- Use `git rebase -i` to collapse migration steps.

PRs must be merged with **rebase** (linear history, no merge commits) so that
`git bisect` works across the full migration arc:

```
gh pr merge --rebase --delete-branch
```

Rebase-merge preserves every commit individually — it is not the same as
squash. All commits land on `feat/modernization` unchanged.

---

## Adding a new client

Full architecture details are in `docs/client-configurability.md`. The short
version:

1. A new client ships as **one PR** containing:
   - `config/clients/<new-client>.php` — feature flag defaults + locale defaults
   - `app/Clients/<NewClient>/` — client service provider and any bindings
     (stub is fine if no overrides yet)
   - A seed migration row for branding
   - A CI matrix entry (`APP_CLIENT=<new-client>`)
   - A GitHub Environment for the deploy target
     (`production-<new-client>`, `staging-<new-client>`)
2. Never hard-code client-specific behavior in core code. Gate variants behind
   `feature('flag-name')` or an interface bound by the client's service
   provider.
3. The `directonderweg` config must remain the default. New flags default to
   the existing behavior so the production client is unchanged.
4. Secrets (Stripe keys, PayPal credentials, etc.) go in the GitHub
   Environment, never in `config/clients/*.php`.

See `docs/client-configurability.md` for the full interface/binding pattern and
the `WithClient` test trait.
