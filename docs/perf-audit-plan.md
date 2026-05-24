# Performance Audit Plan

Last updated: 2026-05-24
Owner: Ahmed

The client reports the app is slow. Before changing anything, we
**measure** to find out where the time is actually going. This
document defines how to run the audit and how to report the findings.

The output of running this plan is `docs/perf-audit.md`, a prioritized
list of bottlenecks with evidence and rough fix estimates. **The audit
itself does not include fixes.** Fixes happen in Phase 7 of the
migration, one bottleneck per PR.

---

## Tools

Install all of these as `--dev` dependencies on `feat/modernization`:

| Tool                                       | What it tells us                                        |
| ------------------------------------------ | ------------------------------------------------------- |
| `laravel/telescope`                        | Per-request timeline, queries, jobs, mails, exceptions  |
| `barryvdh/laravel-debugbar`                | In-browser dump of queries, view renders, route, memory |
| `itsgoingd/clockwork` (optional)           | Like Debugbar with a nicer query/job breakdown          |
| MySQL slow-query log                       | Queries taking >200ms in real workloads                 |
| `npm run build --report` (Vite later)      | JS/CSS bundle size analyzer                             |
| Browser DevTools (Network + Performance)   | Real user-perceived page load                           |
| `ab` or `wrk` (optional)                   | Sanity-check throughput at the HTTP layer               |

Telescope and Debugbar must be **disabled in production** (already the
default for both — verify `TELESCOPE_ENABLED=false` for prod envs).

---

## MySQL slow-query log setup (local only)

> **Never enable this on production or staging.** It writes every slow
> query to disk and degrades write throughput under load.

### Option A — Runtime (no config file edit, survives until MySQL restarts)

Connect as root and run:

```sql
SET GLOBAL slow_query_log        = 1;
SET GLOBAL long_query_time       = 0.2;   -- log queries taking >200ms
SET GLOBAL log_queries_not_using_indexes = 1;  -- also log full-scans

-- Optional: set an explicit path (MySQL picks a default if omitted)
-- SET GLOBAL slow_query_log_file = '/path/to/mysql-slow.log';

-- Verify it took effect
SHOW VARIABLES LIKE 'slow_query%';
SHOW VARIABLES LIKE 'long_query_time';
```

These settings reset when MySQL restarts. Use Option B to make them
permanent for local dev.

### Option B — Permanent via `my.cnf` / `my.ini`

Add the block below to the `[mysqld]` section of your MySQL config
file, then restart MySQL.

```ini
[mysqld]
slow_query_log                  = 1
long_query_time                 = 0.2
log_queries_not_using_indexes   = 1
slow_query_log_file             = /path/to/mysql-slow.log   # set per-OS below
```

#### Config file locations by OS

| Setup                        | Config file path                                          | Default log path                              |
| ---------------------------- | --------------------------------------------------------- | --------------------------------------------- |
| macOS — Homebrew MySQL 8     | `/opt/homebrew/etc/my.cnf` (Apple Silicon) or `/usr/local/etc/my.cnf` (Intel) | `/opt/homebrew/var/mysql/<hostname>-slow.log` (Apple Silicon) |
| Linux — Ubuntu/Debian apt    | `/etc/mysql/mysql.conf.d/mysqld.cnf`                      | `/var/log/mysql/mysql-slow.log`               |
| Windows — Laragon             | `C:\laragon\bin\mysql\mysql-8.x.x\my.ini` (adjust version folder) | `C:\laragon\tmp\mysql-slow.log`    |
| Windows — XAMPP               | `C:\xampp\mysql\bin\my.ini`                               | `C:\xampp\mysql\data\<hostname>-slow.log`     |
| WSL2 — Ubuntu                | `/etc/mysql/mysql.conf.d/mysqld.cnf`                      | `/var/log/mysql/mysql-slow.log`               |
| Docker (official mysql image) | Volume-mount a custom file at `/etc/mysql/conf.d/slow.cnf` (e.g. `-v ./slow.cnf:/etc/mysql/conf.d/slow.cnf`) | `/var/lib/mysql/<hostname>-slow.log` |

> **Tip:** the actual log path may differ from the defaults above.
> After enabling, run `SHOW VARIABLES LIKE 'slow_query_log_file';` to
> find the exact path MySQL is writing to on your machine.

After editing, restart MySQL:

```bash
# macOS Homebrew
brew services restart mysql

# Linux systemd
sudo systemctl restart mysql

# Laragon
# Right-click tray icon → MySQL → Stop / Start
```

### Verify queries are landing in the log

Run any query you know is slow, or force a full-table scan:

```sql
SELECT SLEEP(0.3);   -- always lands in the log at long_query_time=0.2
```

Then check the log file:

```bash
tail -n 30 /path/to/mysql-slow.log
```

You should see a block starting with `# Time:` and `# Query_time:`.

### Useful log-analysis commands

```bash
# Summarize the slow log — macOS/Linux only (requires Perl on PATH;
# Windows users: use pt-query-digest via WSL2 or stick to Telescope)
mysqldumpslow -s t /path/to/mysql-slow.log | head -40

# pt-query-digest: more detail, cross-platform via WSL2
# Install: sudo apt install percona-toolkit  (or brew install percona-toolkit)
pt-query-digest /path/to/mysql-slow.log | head -80
```

### Where to record findings

Copy the log path you used into `docs/perf-audit.md` under the
**Environment** section so other team members know where to look.
Log paths are machine-specific and **must not be committed** to any
config or `.env` file.

---

## What we measure

For each audited page, capture:

1. **Server timing** (Telescope):
   - Total request time (target: <300ms p50, <800ms p95).
   - Number of DB queries.
   - Number of duplicate queries (the N+1 signal).
   - Number of cached lookups vs. cache misses.
2. **Database**:
   - Slowest 3 queries by duration.
   - Slowest 3 queries by frequency × duration.
   - Any query without an index on the filtered column.
3. **Rendering**:
   - Number of views compiled / view-cache hit rate.
   - Number of partial includes.
4. **Frontend**:
   - JS bundle size (compressed and uncompressed).
   - CSS bundle size.
   - Number of HTTP requests on first load.
   - LCP (Largest Contentful Paint) from DevTools.
5. **Memory**:
   - Peak memory per request from Telescope.

---

## Pages and flows to audit (minimum)

These are the screens the client uses every day, so they're the
ones most worth speeding up. Audit each both as an empty-cache cold
load and a warm load.

1. **Dashboard** (`/dashboard` or whichever route `HomeController`
   serves) — usually the slowest page in any admin app because of
   aggregations.
2. **Vehicle list** — large table + filters; likely N+1 candidate.
3. **Booking list** — likely N+1 + missing index on date columns.
4. **Booking detail** — joins to vehicle, driver, customer, addons,
   options, payments, signature, rental agreement.
5. **Booking create** — vehicle availability query is the suspect.
6. **Rental agreement generation (PDF)** — DomPDF is slow; how slow?
7. **Excel import** (recent commits added `importExcel`) — large
   payloads tend to hit memory limits.
8. **Excel template download** (recent commits) — should be fast;
   benchmark to confirm.
9. **Inspection list** + **Reminder list** (long-running data sets).
10. **Settings page** — often pulls every setting from DB on every
    request because of a missing cache.

If the client reports a specific page is slow that isn't on this
list, **audit it too** and add it to `docs/perf-audit.md`.

---

## How to run an audit pass on one page

For each page on the list:

1. Reset the test DB and seed it with **production-shaped data**
   (volumes similar to what the client actually has — e.g. 5,000
   bookings, 500 vehicles, 50,000 booking payments). If we don't
   have a seed for this yet, write one in Phase 0 and commit it
   under `database/seeders/PerfAuditSeeder.php`.
2. Open Telescope and Debugbar. Open the page in a private tab so
   no browser cache helps.
3. Record the metrics from the "What we measure" section.
4. Repeat the page load 3 times to get a stable warm read.
5. Note the worst three queries from Telescope's "Queries" tab.
   Run `EXPLAIN` on each in MySQL and note whether an index is being
   used.
6. Write the page's findings to `docs/perf-audit.md` using the
   template below.

---

## Finding template (use one block per finding)

```markdown
### F-XX: <short title>

- **Page / endpoint:** GET /vehicles
- **Symptom:** 1.4s p50, 3.1s p95; 287 DB queries.
- **Likely cause:** N+1 on `Vehicle::vehicleType` and `Vehicle::lastInspection`.
- **Evidence:** Telescope request `abc123` — 211 of 287 queries are
  `select * from vehicle_types where id = ?`. EXPLAIN shows the
  index is used; the problem is the count.
- **Fix sketch:** `with(['vehicleType', 'lastInspection'])` in
  `VehicleController@index`. Likely 1 line.
- **Estimated effort:** S (≤30 min)
- **Estimated impact:** drops queries from 287 → ~5, expect ~10× faster.
- **Risk:** low — eager loading is purely additive.
- **Priority:** P1
```

Priorities:

- **P0** — affects every user every day; obvious quick fix; ship in
  the first cleanup PR after the migration.
- **P1** — affects daily workflows; small/medium fix.
- **P2** — slow but not crippling; backlog for after migration.
- **P3** — nice-to-have, e.g. asset-size tightening.

---

## Sanity-check checklist (low-hanging fruit to look for)

The following almost always show up. Look for them explicitly:

- [ ] `OPCACHE` enabled in production PHP-FPM
- [ ] `config:cache`, `route:cache`, `view:cache` run on deploy
- [ ] `composer install --optimize-autoloader --no-dev` in production
- [ ] Asset caching headers (Cache-Control, ETag) set by the web server
- [ ] Gzip/Brotli compression enabled at the web server
- [ ] Database has indexes on foreign keys (Laravel doesn't always
      add them automatically before 5.8)
- [ ] Indexes on columns we filter / sort by (`bookings.start_date`,
      `bookings.user_id`, `vehicles.branch_id`, etc.)
- [ ] `SESSION_DRIVER` not on `file` in production with many users
      (use `redis` or `database`)
- [ ] `QUEUE_CONNECTION` not on `sync` for slow jobs (PDF generation,
      mail) — drop them on Redis and process async
- [ ] Telescope/Debugbar not running in production

---

## Output

The audit produces `docs/perf-audit.md` with:

1. A one-paragraph executive summary (where time is going).
2. A baseline metrics table (page → p50 → p95 → query count).
3. The full prioritized list of findings using the template above.
4. The sanity-check checklist with its current pass/fail.
5. A re-measurement section to be filled in after Phase 7 fixes.

Commit it on `feat/modernization`. We re-run the audit at the end of
Phase 6 and compare. Persistent regressions or improvements both get
noted.

---

## What this audit is NOT

- Not a redesign. We're not removing features to make things faster.
- Not a database refactor. Schema is frozen during the migration.
- Not the place to deliver fixes. Findings only; fixes ship in Phase 7
  per the migration plan.

---

## Cadence

- Run the audit **once** at Phase 0 (baseline).
- Run it **again** at the end of Phase 6 (post-React port).
- Run it **again** at the end of Phase 7 (post-fixes), and compare.
