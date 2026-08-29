# Runbook — deploy `directonderweg` to a **NEW host** on `https://directonderweg.com`

**Goal:** stand up the `directonderweg` client on a **brand-new server** behind
the **new domain `https://directonderweg.com`**, by **copying the database and
the uploaded images/files from the existing prod** (`directonderweg.ma`) onto
the new host. The new host runs its **own MySQL** (a *copy* of prod's data, not
a shared connection) and its **own storage**.

> ⚠️ **This is a migration to a separate box, not a DNS repoint.** The new
> host's DB starts as a point-in-time *copy* of prod. From the moment you take
> the copy, the two databases diverge — any writes that keep hitting the old
> `.ma` site after the copy are **not** reflected on `.com`. The cutover plan
> (§8) freezes the old site to prevent that.

**Strategy (per `CLAUDE.md` §10.3):** trunk-based, **tag-driven** deploys to a
per-client **GitHub Environment**. No per-client branch — the "dedicated unit"
for this client is the `production-directonderweg` Environment. A production
deploy = push a `vX.Y.Z` tag, which runs `.github/workflows/deploy.yml` against
`production-directonderweg`.

No application **code** changes are required; this is host provisioning + a
data copy + Environment configuration.

> **Deploying to Namecheap shared hosting (cPanel)?** Use the simplified
> step-by-step companion guide instead:
> [`docs/deploy-namecheap-cpanel.md`](deploy-namecheap-cpanel.md). It adapts
> §1/§6 for no-root cPanel hosting (SSH port 21098, AutoSSL, PHP selector,
> no Redis → §2.1 values, cPanel cron). This runbook remains the source of
> truth for *what* must happen and *why*; the companion only changes *how*.

---

## 0. Prerequisites / decisions

| Decision | Value |
| --- | --- |
| Target host | **New server** (fresh box, own MySQL + storage) |
| Domain | **`https://directonderweg.com`** (new) |
| Data | **Copied** from prod: full DB dump + `storage/` tree |
| Deploy trigger | **Tag-based** (`v*`) → `production-directonderweg` Environment |
| New branch? | **No** (§10.3 — do not create a `client/...` long-lived branch) |
| Old `.ma` | Frozen at cutover, then 301-redirects to `.com` (§8) |

**You will need, before starting:** root/sudo on the new host, SSH + DB access
to the **old** prod host (to take the dump and rsync storage), repo-admin rights
to set the GitHub Environment, and the old server's `APP_KEY` (§3).

---

## 1. Provision the new host

Install the runtime the app expects (PHP **8.3+**, MySQL **8** / MariaDB,
Redis if you use the redis drivers, nginx, certbot, git). The frontend assets
are built in CI and shipped by `deploy.yml`, so **Node is not needed on the
server**.

```bash
# Debian/Ubuntu example — adjust for your distro
sudo apt update
sudo apt install -y nginx mysql-server redis-server git unzip certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-tokenizer php8.3-redis
# php8.3-redis is REQUIRED if you use the redis drivers (§2): composer.json
# requires neither predis nor ext-redis, so without the extension the first
# request dies with `Class "Redis" not found`. Skip it (and redis-server) only
# if you take the §2.1 no-Redis path.
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

Then lay down the app at `DEPLOY_PATH` (must match the Environment var, §2):

```bash
sudo mkdir -p /var/www/directonderweg
sudo chown -R $USER:www-data /var/www/directonderweg
git clone <repo-url> /var/www/directonderweg     # deploy.yml runs `git fetch` + `git checkout <sha>`
cd /var/www/directonderweg
```

- The server needs **read access to the repo** so its `git fetch` works on each
  deploy (add a GitHub **deploy key** for this box, or clone over an HTTPS token).
- The GitHub Action authenticates to the server with `SSH_PRIVATE_KEY` (§2);
  put the matching **public** key in the deploy user's `~/.ssh/authorized_keys`.
- Make Laravel's writable dirs writable by php-fpm:
  ```bash
  sudo chown -R www-data:www-data storage bootstrap/cache
  sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
  ```

> `vendor/`, `.env`, and `public/build` are created by the **first deploy**
> (§7), not now. Don't run `composer install` by hand here — let the pipeline
> own it so the box matches every later deploy.

---

## 2. GitHub Environment — `production-directonderweg`

`deploy.yml` writes the server `.env` entirely from this Environment's
**secrets** and **vars**, and SSHes to **`SSH_HOST`** (now the **new** box).
Set them under **Repo → Settings → Environments → `production-directonderweg`**.

> ⚠️ `APP_KEY` = the **old** `.ma` key (§3). `DB_*` = the **new** host's
> database (the copy you import in §4), **not** prod's connection.

### Secrets (sensitive — encrypted)

| Secret | Value for the new host |
| --- | --- |
| `APP_KEY` | **Exact same key as the old `.ma` app** (see §3) |
| `DB_HOST` | New host's DB — `127.0.0.1` if MySQL runs on the box |
| `DB_DATABASE` | DB name you create on the new host (§4) |
| `DB_USERNAME` | New host's DB user |
| `DB_PASSWORD` | New host's DB password |
| `MAIL_HOST` | SMTP host (e.g. `smtp.gmail.com`) |
| `MAIL_USERNAME` | SMTP user |
| `MAIL_PASSWORD` | SMTP password / app password |
| `NOCAPTCHA_SITEKEY` | reCAPTCHA site key (see §6 — must allow `directonderweg.com`) |
| `NOCAPTCHA_SECRET` | reCAPTCHA secret |
| `SENTRY_LARAVEL_DSN` | (optional) Sentry DSN |
| `SSH_HOST` | **New** production server host/IP |
| `SSH_USERNAME` | Deploy SSH user on the new host |
| `SSH_PRIVATE_KEY` | Deploy SSH private key (public half in §1) |

### Vars (non-sensitive)

| Var | Value for the new host |
| --- | --- |
| `APP_URL` | **`https://directonderweg.com`**  ← the key change |
| `APP_NAME` | `Direct Onderweg` |
| `APP_CLIENT` | `directonderweg` |
| `APP_ENV` | `production` |
| `CACHE_STORE` | `redis` (or `file` if no Redis) |
| `SESSION_DRIVER` | `redis` (or `file`) |
| `QUEUE_CONNECTION` | `redis` (or `database`) |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` (if used) |
| `MAIL_PORT` | `587` |
| `MAIL_FROM_ADDRESS` | e.g. `no-reply@directonderweg.com` |
| `SENTRY_ENVIRONMENT` | `production` |
| `SSH_PORT` | `22` (or custom) |
| `DEPLOY_PATH` | Absolute app path on the **new** host (e.g. `/var/www/directonderweg`) |

> ⚠️ **Inertia SSR must be explicitly disabled** until an SSR service is
> actually provisioned: `config/inertia.php` defaults `INERTIA_SSR_ENABLED`
> to `true`, and with no SSR server listening every full-page load pays a
> connection attempt to `127.0.0.1:13714` before falling back (perf-audit
> F-23 — measured ~2 s/request on Windows; small but nonzero on Linux).
> `deploy.yml` does not currently write this var — add
> `INERTIA_SSR_ENABLED=false` to the generated `.env` in `deploy.yml`
> (separate PR) before the first deploy.

> **Auth on the new domain works automatically.** This is a same-origin
> Inertia + Sanctum app; `config/sanctum.php` derives its stateful domains from
> `APP_URL` and `SESSION_DOMAIN` defaults to the current host. Setting
> `APP_URL=https://directonderweg.com` is sufficient — no
> `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` overrides needed. (Sessions are
> per-host, so users will simply re-login on `.com`.)

### 2.1 No-Redis option (host without Redis)

`deploy.yml` **defaults all three drivers to `redis`**. On a host without
Redis you must override them in the Environment **vars**, or the app throws
connection-refused on cache/session/queue (a hard break, not just slowness):

| Var | No-Redis value |
| --- | --- |
| `CACHE_STORE` | `file` |
| `SESSION_DRIVER` | `file` |
| `QUEUE_CONNECTION` | `sync` (simplest — see below) |

Leave `REDIS_HOST`/`REDIS_PORT` unset.

**Performance impact at this app's scale (single host): negligible.**
- **Cache** — the only hot cache use is the `settings()` helper
  (`Cache::remember(..., 300)`); a tiny payload with no tags/locks, so `file`
  is microseconds slower than Redis — invisible.
- **Sessions** — `file` is fine for one host (Redis only buys multi-server
  session sharing, which this deploy doesn't have).
- **Queue** — the mailables (`app/Mail/*`) implement `ShouldQueue`, so **every
  email is queued**. With `sync`, emails send **inline in the request**: the
  reminder **cron is unaffected** (already background), but user-triggered
  emails (send agreement/document, email verification) add SMTP round-trip
  latency (~0.5–2s) to that one request. If that becomes a problem, switch
  `QUEUE_CONNECTION=database` — but that needs a `jobs` table (only
  `failed_jobs` exists today: `php artisan queue:table` + `migrate`) **and** a
  running worker (§6.5).

> ⚠️ With `sync`, if SMTP is down/slow the failure surfaces **on the user's
> request** instead of failing quietly in the background. Make sure `MAIL_*` is
> solid before choosing `sync`.

---

## 3. ⚠️ Reuse the old `APP_KEY` (do not generate a new one)

The new host serves a **copy of the old database** and a **copy of the old
storage**. Anything the old app encrypted at rest — encrypted casts, the
sign-pad signing material, signed/"remember me" cookies, signed URLs — only
decrypts with the **original key**. Copy the **exact** `APP_KEY` from the old
`.ma` server's `.env` into the `production-directonderweg` Environment. Running
`php artisan key:generate` on the new host would be a mistake.

Read it from the old server: `grep ^APP_KEY= /path/to/old/.env`.

---

## 4. Copy the database (prod → new host)

Take a consistent dump on **prod**, transfer it, and import into the **new**
host's MySQL. Do a **bulk copy now** to validate the process; you'll repeat a
**final delta** dump at cutover (§8) so almost no data is lost.

**On the old prod host** (InnoDB → `--single-transaction` keeps it lock-free):

```bash
mysqldump --single-transaction --quick --routines --triggers \
  -u <prod_db_user> -p <prod_db_name> | gzip > /tmp/directonderweg-db.sql.gz
```

**Transfer** to the new host (run from the new host, or scp from prod):

```bash
scp <prod_user>@<prod_host>:/tmp/directonderweg-db.sql.gz /tmp/
```

**On the new host** — create the DB + user (these become the §2 `DB_*`
secrets), then import:

```bash
sudo mysql -e "CREATE DATABASE directonderweg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'directonderweg'@'127.0.0.1' IDENTIFIED BY '<strong-password>';"
sudo mysql -e "GRANT ALL PRIVILEGES ON directonderweg.* TO 'directonderweg'@'127.0.0.1'; FLUSH PRIVILEGES;"

gunzip -c /tmp/directonderweg-db.sql.gz | mysql -u directonderweg -p directonderweg
```

> The imported DB is **already migrated**, so the `php artisan migrate --force`
> step in `deploy.yml` will be a no-op (matching schema = nothing to run).
> Confirm the dump's charset/collation matches prod to avoid mojibake on the
> 14 locales' content.

---

## 5. Copy the images / uploaded files (prod → new host)

Uploads and signing artifacts live under the app's `storage/` tree:

| What | Path | Disk |
| --- | --- | --- |
| Driver/profile/license/expense/inspection/setting uploads | `storage/app/public/upload/**` | `public` |
| Signatures | `storage/signatures/**` | `local` |
| Signed PDFs | `storage/signed_documents/**` | `local` |
| Sign-pad certificate | `storage/app/certificate.crt` | `local` |

Copy the whole `storage/` tree **except** the regenerable/host-specific dirs
(`framework/{cache,sessions,views}`, `logs`). From the **new** host:

```bash
rsync -avz --progress \
  --exclude 'framework/cache/' \
  --exclude 'framework/sessions/' \
  --exclude 'framework/views/' \
  --exclude 'logs/' \
  <prod_user>@<prod_host>:/path/to/old/storage/  /var/www/directonderweg/storage/

# Re-assert ownership after the copy
sudo chown -R www-data:www-data /var/www/directonderweg/storage
```

Then create the public symlink **on the new host** (deploy.yml does *not* do
this — it's a one-time, idempotent step so `public/storage → storage/app/public`):

```bash
cd /var/www/directonderweg && php artisan storage:link
```

> If `FILESYSTEM_DISK`/`SIGNATURES_DISK` are left at defaults (`local`), this is
> all you need. If prod uses S3/Wasabi for any of these instead of local disk,
> **skip the file copy for that disk** — the new host reads the same bucket;
> just carry the same `AWS_*`/`WAS_*` credentials into the Environment.

---

## 6. DNS, web server, TLS, scheduler, queue (new host)

1. **DNS:** point `directonderweg.com` (A/AAAA, and `www` if used) at the
   **new** server's IP. (Keep `.ma` pointing at the old box until cutover, §8.)
2. **Web server vhost:** add `directonderweg.com` (and `www.`) as `server_name`
   with docroot `<DEPLOY_PATH>/public`, wired to php8.3-fpm.
3. **TLS:** `certbot --nginx -d directonderweg.com -d www.directonderweg.com`.
4. **Scheduler cron (required):** without it, reminder status/recurring jobs
   **and** the nightly `logged_histories` prune (F-19) never fire. Add:
   `* * * * * cd <DEPLOY_PATH> && php artisan schedule:run >> /dev/null 2>&1`.
5. **Queue worker (if `QUEUE_CONNECTION=redis` or `database`):** run a
   supervised worker (systemd/supervisor) for `php artisan queue:work`.
   `deploy.yml` issues `php artisan queue:restart` each deploy, which only
   signals an already-running worker to reload. **With `QUEUE_CONNECTION=sync`
   (§2.1) you need no worker at all** — the `queue:restart` call is a harmless
   no-op.
6. **reCAPTCHA:** keys are domain-scoped — in the Google reCAPTCHA admin
   console add `directonderweg.com` to the key's allowed domains (or mint a new
   pair and set `NOCAPTCHA_SITEKEY`/`NOCAPTCHA_SECRET`). Otherwise the login
   captcha fails on the new domain.

---

## 7. First deploy

With the host provisioned (§1), Environment set (§2–3), and data copied
(§4–5), trigger the pipeline. **Dry-run on staging first** if you have a
`staging-directonderweg` Environment + box: **Actions → Deploy → Run workflow →
`staging-directonderweg`**.

Production deploy — from `dev`/`main`:

```bash
git tag v1.0.0          # use the real next version
git push origin v1.0.0  # → triggers deploy.yml → production-directonderweg
```

`deploy.yml` will: build assets in CI, SCP `.env` + `public/build` to the new
host, then on the box `composer install --no-dev`, `php artisan down`,
`migrate --force` (no-op — DB already imported), `client:install` (idempotent
branding seed — does **not** clobber the copied `Setting` rows),
`config/route/view:cache`, `queue:restart`, `php artisan up`.

---

## 8. Cutover (freeze old, promote new)

Because the new DB is a *copy*, minimize the window where both sites accept
writes:

1. **Freeze the old `.ma` site:** put it in maintenance (`php artisan down`) so
   it stops taking writes.
2. **Final delta copy:** re-run the DB dump+import (§4) and the storage rsync
   (§5) to capture everything written since the bulk copy. (rsync only ships
   the diff; the DB re-import is a full replace of the copy.)
3. **Flip DNS:** ensure `directonderweg.com` resolves to the new host; wait for
   propagation; verify (§9).
4. **Retire `.ma`:** on the **old** host, replace the app with a 301 redirect
   `directonderweg.ma → https://directonderweg.com` (keep `.ma`'s cert until
   the redirect settles). Do **not** bring the old app back up for writes — its
   DB is now abandoned.

---

## 9. Smoke test (after deploy / cutover)

- `https://directonderweg.com` loads over HTTPS (valid cert, no mixed content).
- Log in (captcha works); the dashboard shows the **copied** prod data.
- **Images render:** open a driver/booking with an uploaded document or profile
  image — confirms the `storage/` copy + `storage:link` worked.
- A signed agreement PDF / signature displays — confirms `signatures` +
  `signed_documents` + `certificate.crt` + the reused `APP_KEY`.
- One write (create/edit a booking) persists on the **new** DB.
- A test email sends (Settings → SMTP → Send Test).
- After cutover: `https://directonderweg.ma` 301-redirects to `.com`.

---

## 10. Rollback

Two independent levers:

- **Code:** re-deploy the previous good tag (the SSH script checks out a
  specific `github.sha`):
  ```bash
  # Actions → Deploy → Run workflow with an earlier tag's commit, or push it
  git push origin <previous-good-tag>
  ```
- **Cutover:** if the new host is bad and you haven't retired `.ma` yet, point
  DNS back at the old host and `php artisan up` there. Once `.ma` is frozen and
  diverged, rolling back means re-syncing forward, not backward — so don't
  retire `.ma` until §9 is green.

The new DB is independent, so a bad release here can't corrupt prod — but avoid
destructive migrations and **back up the new DB before any schema-altering
release**.

---

## What needs whom

| Task | Owner |
| --- | --- |
| Provision the new host: PHP/MySQL/Redis/nginx, clone, perms (§1) | Host / devops |
| Set `production-directonderweg` Environment secrets/vars (§2–3) | Repo admin |
| Copy DB prod → new host (§4) | Host / devops + DBA |
| Copy `storage/` + `storage:link` (§5) | Host / devops |
| DNS + vhost + TLS + scheduler + queue + `.ma` redirect (§6, §8) | Host / devops |
| reCAPTCHA domain (§6.6) | Whoever owns the Google account |
| Push the release tag (§7) | Maintainer |
| This runbook | In repo (`docs/deploy-directonderweg-com.md`) |
