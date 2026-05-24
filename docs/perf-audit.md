# Performance Audit — rentcar

**Audit date:** 2026-05-24
**Phase:** Phase 0 baseline — findings only, no fixes
**Auditor:** Ahmed (static analysis) + Claude Code (code review)
**Branch:** `feat/modernization`

> **Methodology note:** This audit combines static code analysis (verified
> against the source at the commit on `feat/modernization`) with a template
> for live profiling numbers. Columns marked **TBD** must be filled in by
> running the app with Telescope enabled (`TELESCOPE_ENABLED=true`) and
> real/production-shaped data. See `docs/perf-audit-plan.md` for the
> profiling procedure.

---

## Environment

| Field                     | Value                                        |
| ------------------------- | -------------------------------------------- |
| PHP version               | _fill in: `php -v`_                          |
| Laravel version           | 10.48                                        |
| MySQL version             | _fill in: `SELECT VERSION();`_               |
| Telescope version         | 5.20                                         |
| Debugbar version          | 3.16                                         |
| Dataset size (bookings)   | _fill in: `SELECT COUNT(*) FROM bookings;`_  |
| Dataset size (vehicles)   | _fill in: `SELECT COUNT(*) FROM vehicles;`_  |
| Dataset size (users)      | _fill in: `SELECT COUNT(*) FROM users;`_     |
| Dataset size (settings)   | _fill in: `SELECT COUNT(*) FROM settings;`_  |
| Slow-query threshold      | 200ms (`long_query_time = 0.2`)              |

---

## 1. Executive summary

Static analysis of the ten highest-traffic controllers reveals three
systemic performance problems:

1. **The `settings()` helper hits the database on every call with no
   caching.** It is called 21 times across controllers (plus indirectly
   via `getSettingsValByName`, `settingPriceFormat`, `formattedDate`,
   etc.), meaning a single page load can issue 5–15 redundant settings
   queries. At any meaningful request rate this will dominate DB time.

2. **The dashboard runs 36–48 sequential single-row aggregate queries
   in PHP loops** instead of one GROUP BY query per table. A super-admin
   dashboard load fires 12 COUNT queries (`organizationByMonth`) + 12
   SUM queries (`paymentByMonth`); an owner dashboard fires 24 SUM
   queries (`incomeExpenseByMonth`). These grow O(1) with dataset size
   but are structurally wasteful.

3. **All major list pages (`/bookings`, `/vehicles`, `/expenses`) use
   unbounded `->get()` with no pagination.** Every row in the table is
   fetched on every page load. With a production dataset this will cause
   memory exhaustion and multi-second response times.

Expected fastest wins after Phase 7:
- **F-01** — cache `settings()`: eliminates 3–14 redundant queries per page; F-03 is an automatic free fix.
- **F-02** — batch dashboard aggregates: 36–48 queries → 3–4 per load.
- **F-04/F-06/F-07** — paginate list pages: memory becomes O(page size) instead of O(table size).
- **F-05/F-09** — add eager loads / batch lookups: eliminates N+1 query explosions on planning and inspection views.

Together these should cut dashboard and list-page query counts by 80–95%.

---

## 2. Baseline metrics table

Fill in after running a live profiling session (see
`docs/perf-audit-plan.md` §"How to run an audit pass on one page").

| Page / endpoint                 | p50 (ms) | p95 (ms) | DB queries | Peak memory |
| ------------------------------- | -------- | -------- | ---------- | ----------- |
| Dashboard — owner               | TBD      | TBD      | TBD        | TBD         |
| Dashboard — super admin         | TBD      | TBD      | TBD        | TBD         |
| GET /booking (list)             | TBD      | TBD      | TBD        | TBD         |
| GET /booking/create             | TBD      | TBD      | TBD        | TBD         |
| GET /booking/show               | TBD      | TBD      | TBD        | TBD         |
| GET /booking/planning           | TBD      | TBD      | TBD        | TBD         |
| GET /vehicle (list)             | TBD      | TBD      | TBD        | TBD         |
| GET /expense (list)             | TBD      | TBD      | TBD        | TBD         |
| GET /inspection (list)          | TBD      | TBD      | TBD        | TBD         |
| GET /reminder (list)            | TBD      | TBD      | TBD        | TBD         |
| POST /booking (Excel import)    | TBD      | TBD      | TBD        | TBD         |
| GET /rental-agreement/show (PDF)| TBD      | TBD      | TBD        | TBD         |
| GET /setting                    | TBD      | TBD      | TBD        | TBD         |

---

## 3. Prioritized findings

### F-01: `settings()` helper — uncached DB query on every call

- **File:** `app/Helper/helper.php:93–120`
- **Symptom:** TBD — measure with Telescope "Queries" tab on any page
  that calls `settings()`.
- **Likely cause:** `settings()` runs a full `SELECT * FROM settings WHERE parent_id = ?`
  on every invocation. There is no per-request or cross-request cache.
  The function is called **21 times across controllers** (confirmed via
  `grep -rn "settings()" app/Http/Controllers/`) plus indirectly through
  `getSettingsValByName()`, `settingPriceFormat()`, `formattedDate()`,
  and `formattedTime()`. A booking create form alone calls it 4+ times.
- **Evidence (static):**
  ```php
  // helper.php:93–102 — full query every call, no Cache::remember
  function settings() {
      $settingData = DB::table('settings');
      if (\Auth::check()) {
          $userId = parentId();
          $settingData = $settingData->where('parent_id', $userId);
      }
      $settingData = $settingData->get();
  ```
- **Fix sketch:** Wrap in `Cache::remember("settings_{$userId}", 300, fn() => ...)`.
  Invalidate the key when `SettingController` saves. ~10 lines.
- **Estimated effort:** S (≤30 min)
- **Estimated impact:** Eliminates 3–14 redundant queries per page. On a
  busy owner dashboard this is likely the single biggest win.
- **Risk:** Low — additive cache. Must flush on settings save (one line
  in `SettingController::generalData()`).
- **Priority:** P0

---

### F-02: Dashboard — 36–48 aggregate queries fired in PHP loops

- **File:** `app/Http/Controllers/HomeController.php`
- **Symptom:** TBD — expect 40+ queries on super-admin dashboard, 28+
  on owner dashboard.
- **Likely cause:** Three methods loop over 12 months and fire one DB
  aggregate per iteration instead of using a single `GROUP BY` query:

  | Method                 | Lines     | Queries/load | Table              |
  | ---------------------- | --------- | ------------ | ------------------ |
  | `organizationByMonth`  | 83–90     | 12 × COUNT   | `users`            |
  | `paymentByMonth`       | 104–110   | 12 × SUM     | `package_transactions` |
  | `incomeExpenseByMonth` | 125–133   | 12 × SUM × 2 | `bookings`, `expenses` |

- **Evidence (static):**
  ```php
  // HomeController.php:88 — one COUNT per loop iteration
  while ($currentdate <= $end) {
      $organization['data'][] = User::where('type', 'owner')
          ->whereMonth('created_at', $month)
          ->whereYear('created_at', $year)
          ->count();   // ← 12 separate queries
  }
  ```
- **Fix sketch:** Replace each loop with a single query:
  ```php
  User::where('type', 'owner')
      ->whereYear('created_at', date('Y'))
      ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
      ->groupByRaw('MONTH(created_at)')
      ->pluck('total', 'month');
  ```
  Then fill the 12-slot array from the result collection. ~15 lines each.
- **Estimated effort:** M (1–2 hours for all three methods)
- **Estimated impact:** 36–48 queries → 3–4 queries per dashboard load.
- **Risk:** Low — pure read path, no writes.
- **Priority:** P0

---

### F-03: `getSettingsValByName('landing_page')` on unauthenticated landing

- **File:** `app/Http/Controllers/HomeController.php:63`
- **Symptom:** Every unauthenticated visitor to `/` fires a full settings
  query even before the landing page is rendered.
- **Likely cause:** `getSettingsValByName()` calls `settings()` internally,
  which is uncached (see F-01).
- **Evidence (static):** Line 63: `$landingPage = getSettingsValByName('landing_page');`
- **Fix sketch:** Resolved automatically when F-01 is fixed (cache covers
  unauthenticated calls via `parent_id = 1` path).
- **Estimated effort:** XS (fixed by F-01)
- **Estimated impact:** Low in isolation; covered by F-01.
- **Risk:** None.
- **Priority:** P1 (fixed as part of F-01)

---

### F-04: `BookingController::index()` — unbounded `->get()` loads all bookings

- **File:** `app/Http/Controllers/BookingController.php:31`
- **Symptom:** TBD — measure memory and query time with 5,000+ bookings.
  Likely >500ms and >50MB at production scale.
- **Likely cause:** No pagination. All bookings for the tenant are fetched
  into memory on every list page load.
- **Evidence (static):**
  ```php
  // BookingController.php:31
  $bookings = Booking::where('parent_id', '=', parentId())
      ->orderBy('created_at', 'desc')
      ->get();   // ← no limit, no paginate
  ```
- **Fix sketch:** Replace `->get()` with `->paginate(20)` and add
  `{{ $bookings->links() }}` to the Blade view.
- **Estimated effort:** S (≤1 hour including view change)
- **Estimated impact:** Memory drops from O(n bookings) to O(20). Response
  time becomes constant regardless of dataset size.
- **Risk:** Low — pagination is additive. Existing filters still work;
  just need to thread the page parameter through.
- **Priority:** P1

---

### F-05: `BookingController::planning()` — N+1 on `$booking->drivers`

- **File:** `app/Http/Controllers/BookingController.php:1116–1130`
- **Symptom:** TBD — expect 1 query per booking for driver lookup.
  With 500 bookings: 500+ extra queries.
- **Likely cause:** All bookings are fetched without eager loading the
  driver relationship, then `$booking->drivers->name` is accessed inside
  the `foreach` loop triggering one lazy-load per booking.
- **Evidence (static):**
  ```php
  // BookingController.php:1116, 1130
  $bookings = Booking::where('parent_id', $parentId)->get();  // no with()
  foreach ($bookings as $booking) {
      $driver = !empty($booking->drivers) ? $booking->drivers->name : '';  // N+1
  ```
- **Fix sketch:** `Booking::where('parent_id', $parentId)->with('drivers')->get();`
  — one extra word eliminates all driver queries.
- **Estimated effort:** XS (1 line)
- **Estimated impact:** Query count drops from N+2 to 2 regardless of
  booking count.
- **Risk:** None.
- **Priority:** P1

---

### F-06: `VehicleController::index()` — unbounded `->get()` on all vehicles

- **File:** `app/Http/Controllers/VehicleController.php:20`
- **Symptom:** TBD — measure with 1,000+ vehicles.
- **Likely cause:** No pagination on vehicle list.
- **Evidence (static):**
  ```php
  // VehicleController.php:20
  $vehicles = Vehicle::where('parent_id', '=', parentId())->get();
  ```
- **Fix sketch:** `->paginate(20)`. Also applies to `create()` (line 42)
  where vehicles are loaded for the booking form dropdown — consider
  switching to an AJAX autocomplete for large fleets.
- **Estimated effort:** S
- **Estimated impact:** Constant memory regardless of fleet size.
- **Risk:** Low.
- **Priority:** P1

---

### F-07: `ExpenseController::index()` — unbounded `->get()` on all expenses

- **File:** `app/Http/Controllers/ExpenseController.php:16` (confirmed
  by grep — line numbers subject to verification).
- **Symptom:** TBD — grows linearly with years of expense records.
- **Fix sketch:** `->paginate(20)`.
- **Estimated effort:** XS
- **Estimated impact:** Constant memory.
- **Risk:** None.
- **Priority:** P1

---

### F-08: `BookingController::create()` — 4 unbounded `->get()` calls per form load

- **File:** `app/Http/Controllers/BookingController.php:42–55`
- **Symptom:** TBD — slow booking form at scale, especially vehicle
  and driver dropdowns.
- **Likely cause:** Four unscoped `->get()` calls load every vehicle,
  driver, place, and addon for the tenant on every create-form render.
- **Evidence (static):**
  ```php
  // BookingController.php:42–55
  $vehicles = Vehicle::where('parent_id', parentId())->get();         // all
  $drivers  = User::where('parent_id', parentId())
      ->where('type', 'driver')->orderBy('created_at', 'desc')->get(); // all
  $places   = Place::where('parent_id', parentId())->get();            // all
  $addon    = Addon::where('parent_id', parentId())->get()->pluck(...); // all
  ```
- **Fix sketch:** Short-term: add `->limit(500)` on each. Long-term
  (Phase 5+): replace dropdowns with server-side AJAX search.
- **Estimated effort:** S short-term, M long-term
- **Estimated impact:** Prevents OOM on large tenants; improves TTI on
  the create form.
- **Risk:** Low — limits are conservative and well above typical fleet sizes.
- **Priority:** P2

---

### F-09: `InspectionController::show()` — N+1 on `InspectionType::find()` in loop

- **File:** `app/Http/Controllers/InspectionController.php` (verify exact
  lines — agent found this at ~106–111).
- **Symptom:** TBD — one `SELECT` per checklist item on each inspection
  view.
- **Likely cause:** `InspectionType::find($k)` called inside a foreach
  loop over the checklist JSON keys.
- **Fix sketch:** Collect all checklist IDs first, then
  `$types = InspectionType::findMany($checklistIds)->keyBy('id');`
  and look up from the collection inside the loop.
- **Estimated effort:** S
- **Estimated impact:** Drops from N queries to 1 per inspection view.
- **Risk:** None.
- **Priority:** P2

---

### F-10: `helper.php` — row-by-row `->save()` loop in subscription assignment

- **File:** `app/Helper/helper.php` — `assignSubscription()` and
  `assignManuallySubscription()` (lines ~286–306 and ~334–354).
- **Symptom:** TBD — slow subscription toggle when tenant has many users.
- **Likely cause:** Each User model is fetched then saved individually
  inside a `foreach` loop instead of using a batch SQL UPDATE.
- **Fix sketch:** Replace the save loop with:
  `User::where('parent_id', parentId())->whereNotIn('type', ['driver'])->update(['is_active' => 1]);`
- **Estimated effort:** S
- **Estimated impact:** O(n) queries → 1 query per subscription event.
- **Risk:** Low — same WHERE clause, different execution strategy.
- **Priority:** P2

---

### F-11: `RentalAgreementController::show()` — multiple sequential User/Driver lookups

- **File:** `app/Http/Controllers/RentalAgreementController.php` (~lines 186–188).
- **Symptom:** 2–3 extra queries per rental agreement view.
- **Likely cause:** Driver 1 and Driver 2 are fetched with separate
  `User::find()` and `Driver::where()->first()` calls after the main
  record is loaded, instead of being eager loaded.
- **Fix sketch:** Add `->with(['primaryDriver', 'secondaryDriver'])` to
  the initial `RentalAgreement` query (requires the relationships to be
  defined on the model).
- **Estimated effort:** S
- **Estimated impact:** Minor — 2–3 queries saved per page view.
- **Risk:** Low.
- **Priority:** P3

---

### F-12: `HomeController::index()` — `Subscription::get()` on landing page (no limit)

- **File:** `app/Http/Controllers/HomeController.php:66`
- **Symptom:** Every unauthenticated visitor loads all subscription plans.
- **Evidence (static):** `$subscriptions = Subscription::get();`
- **Fix sketch:** Plans table is small and rarely changes — wrap in
  `Cache::remember('subscriptions', 3600, ...)`. Or at minimum add
  `->where('active', true)` if such a column exists.
- **Estimated effort:** XS
- **Estimated impact:** Minimal — table is small. Cache removes the query
  entirely on a busy landing page.
- **Risk:** None.
- **Priority:** P3

---

### F-13: `BookingController::importExcel()` — per-row DB queries inside import loop

- **File:** `app/Http/Controllers/BookingController.php:622–809`
- **Symptom:** TBD — import time grows linearly with row count; likely
  multi-second for files with hundreds of rows.
- **Likely cause:** Three query patterns fire inside (or nested inside)
  the main `foreach` row loop:

  1. **Email-uniqueness while-loop (line 723):** For each new driver, a
     `User::where('email', $email)->exists()` query runs in a `while`
     loop until a unique email is found — potentially unbounded queries
     per driver.
  2. **`Driver::latest()->first()` per new driver (line 743):** Fetches
     the highest `driver_id` individually for every new driver row
     instead of tracking the max in memory.
  3. **`Vehicle::latest()->first()` per new vehicle (line 757):** Same
     pattern — re-queries the max `vehicle_id` on every new vehicle.

  Drivers and vehicles already have an in-memory cache (`$driversCache`,
  `$vehiclesCache` at lines 649–650) for lookups; the `->latest()->first()`
  calls are the gap in that optimization.

- **Evidence (static):**
  ```php
  // BookingController.php:723 — while-in-foreach
  while (User::where('email', $email)->exists()) { ... }

  // BookingController.php:743 — re-queries max driver_id per new driver
  $latestDriver = Driver::where('parent_id', $pid)->latest()->first();

  // BookingController.php:757 — re-queries max vehicle_id per new vehicle
  $latestVehicle = Vehicle::where('parent_id', $pid)->latest()->first();
  ```
- **Fix sketch:**
  - Before the loop, resolve the next available `driver_id` and
    `vehicle_id` once and increment in memory:
    ```php
    $nextDriverId  = (Driver::where('parent_id', $pid)->max('driver_id') ?? 0) + 1;
    $nextVehicleId = (Vehicle::where('parent_id', $pid)->max('vehicle_id') ?? 0) + 1;
    ```
  - For email uniqueness, pre-load existing emails into a `Set` before
    the loop rather than querying on each iteration.
- **Estimated effort:** S (≤1 hour)
- **Estimated impact:** Eliminates 2–3 queries per new driver/vehicle row.
  For a 500-row import with 100 new drivers/vehicles: saves 200–300
  queries. Email-uniqueness loop elimination removes unbounded query risk.
- **Risk:** Low — in-memory counter produces the same IDs as the DB
  re-query, assuming no concurrent imports (single-user admin tool).
- **Priority:** P1

---

## 4. Sanity-check checklist

Run these checks and tick them off. Items already confirmed from code
review are pre-ticked.

- [ ] `OPCACHE` enabled in production PHP-FPM
- [ ] `config:cache`, `route:cache`, `view:cache` run on deploy
- [ ] `composer install --optimize-autoloader --no-dev` in production
- [ ] Asset caching headers (Cache-Control, ETag) set by the web server
- [ ] Gzip/Brotli compression enabled at the web server
- [ ] Database indexes on foreign keys (check `SHOW INDEX FROM bookings;` etc.)
- [ ] Indexes on `bookings.start_date`, `bookings.parent_id`, `bookings.status`
- [ ] Indexes on `vehicles.parent_id`, `expenses.parent_id`
- [ ] Indexes on `reminders.parent_id`, `reminders.reminder_date`
- [ ] Indexes on `inspections.parent_id`
- [ ] `SESSION_DRIVER` not `file` in production under load
- [ ] `QUEUE_CONNECTION` not `sync` for PDF generation and mail
- [x] `TELESCOPE_ENABLED=false` in production (enforced via `.env.example` default)
- [x] `DEBUGBAR_ENABLED=false` in production (enforced via `.env.example` default)

---

## 5. Re-measurement section

Fill in after Phase 7 fixes are applied.

| Finding | Pre-fix query count | Post-fix query count | Pre-fix p50 | Post-fix p50 |
| ------- | ------------------- | -------------------- | ----------- | ------------ |
| F-01    | TBD                 | TBD                  | TBD         | TBD          |
| F-02    | TBD                 | TBD                  | TBD         | TBD          |
| F-04    | TBD                 | TBD                  | TBD         | TBD          |
| F-05    | TBD                 | TBD                  | TBD         | TBD          |
| F-06    | TBD                 | TBD                  | TBD         | TBD          |
| F-07    | TBD                 | TBD                  | TBD         | TBD          |
| F-08    | TBD                 | TBD                  | TBD         | TBD          |
| F-09    | TBD                 | TBD                  | TBD         | TBD          |
| F-10    | TBD                 | TBD                  | TBD         | TBD          |
| F-13    | TBD                 | TBD                  | TBD         | TBD          |

---

_This document is the output of the Phase 0 audit. It is intentionally
report-only. Fixes are scheduled for Phase 7, one bottleneck per PR,
each approved individually._
