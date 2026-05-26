# Migration Log

Records manual smoke-test outcomes at each phase gate.
Required fields per entry: date, commit hash, tester, outcome, notes.

---

## Phase 1 — Full feature test coverage

### Automated coverage gate

| Date | Commit | Coverage (app/Http/Controllers/) | Result |
|------|--------|----------------------------------|--------|
| —    | —      | Pending CI run                   | —      |

### Stripe sandbox smoke tests

> Run against Stripe test-mode keys. Card: `4242 4242 4242 4242`, any future expiry, any CVC.

| Date | Commit | Tester | Flow | Outcome | Notes |
|------|--------|--------|------|---------|-------|
| —    | —      | —      | Subscription checkout (new owner) | Pending | — |
| —    | —      | —      | Subscription upgrade | Pending | — |
| —    | —      | —      | Payment refund via Stripe dashboard | Pending | — |
| —    | —      | —      | Booking payment (card) | Pending | — |

### PayPal sandbox smoke tests

> Run against PayPal sandbox credentials (`paypal_mode=sandbox`).

| Date | Commit | Tester | Flow | Outcome | Notes |
|------|--------|--------|------|---------|-------|
| —    | —      | —      | Subscription checkout via PayPal | Pending | — |
| —    | —      | —      | PayPal cancel/return flow | Pending | — |
| —    | —      | —      | Booking payment (PayPal) | Pending | — |

---

## Phase 2 — Laravel 10 → 11

### Automated test suite

| Date | Commit | Tests | Coverage (Controllers) | Result |
|------|--------|-------|------------------------|--------|
| 2026-05-26 | 412d41da | 459 passed (980 assertions) | ≥50% (CI gate) | Pass |

Phase 2 changes shipped: `laravelcollective/html` removed (BAN-37), Laravel framework `^11.0` + PHPUnit `^11.0` + all compatible deps (BAN-37), `$routeMiddleware` → `$middlewareAliases` + `$dates` removal (BAN-39).

### Stripe sandbox smoke tests

> Run against Stripe test-mode keys. Card: `4242 4242 4242 4242`, any future expiry, any CVC.

| Date | Commit | Tester | Flow | Outcome | Notes |
|------|--------|--------|------|---------|-------|
| — | — | — | Login → create booking → Stripe card payment | Pending | — |
| — | — | — | Generate rental-agreement PDF | Pending | — |
| — | — | — | Sign rental agreement (signature pad) | Pending | — |
| — | — | — | Re-download signed PDF | Pending | — |
| — | — | — | Subscription checkout (new owner) | Pending | — |

### PayPal sandbox smoke tests

> Run against PayPal sandbox credentials (`PAYPAL_MODE=sandbox`).

| Date | Commit | Tester | Flow | Outcome | Notes |
|------|--------|--------|------|---------|-------|
| — | — | — | Login → create booking → PayPal payment | Pending | — |
| — | — | — | PayPal cancel/return flow | Pending | — |
| — | — | — | Subscription checkout via PayPal | Pending | — |

---

## Phase 3 — Laravel 11 → 12

*(To be filled after Phase 3 work completes.)*

---

## Phase 4 — Dependency and PHP upgrade

*(To be filled after Phase 4 work completes.)*

---

## Phase 5 — Inertia + React port

*(To be filled after Phase 5 work completes.)*

---

## How to record an entry

1. Run the manual smoke test against the relevant sandbox.
2. Fill in the date (`YYYY-MM-DD`), the short commit hash (`git rev-parse --short HEAD`), your name in the Tester column, and the outcome (`Pass` / `Fail` / `Partial`).
3. Add any failure notes in the Notes column and open a bug ticket if the outcome is not `Pass`.
4. Commit the updated log as `docs(BAN-XX): log Phase N smoke-test results`.
