# Simple guide — deploy `directonderweg.com` to Namecheap (cPanel) with GitHub CI

A plain, do-this-then-that version of the full runbook
(`docs/deploy-directonderweg-com.md`) for a **Namecheap shared-hosting
(cPanel) account**. Goal: GitHub deploys the app to the Namecheap host
automatically when you push a tag; the database and uploaded files come from
the old host.

> Namecheap specifics that differ from a normal server: SSH runs on port
> **21098**, you have **no root**, there is **no Redis**, and TLS is handled
> by cPanel **AutoSSL** — no certbot. If you actually bought a Namecheap
> **VPS** (root access), use the main runbook instead; this guide is for
> shared/Stellar cPanel hosting.

Throughout, replace `CPUSER` with your cPanel username.

---

## Step 1 — Turn on SSH and add your key (cPanel)

1. cPanel → **Manage Shell** (under "Exclusive for Namecheap Customers") →
   enable SSH access.
2. cPanel → **SSH Access → Manage SSH Keys → Import Key**: paste the
   **public** key of a fresh keypair you generate for deployments
   (`ssh-keygen -t ed25519 -f deploy_key`). After importing, click
   **Manage → Authorize**.
3. Test from your machine (note the port!):
   ```bash
   ssh -p 21098 -i deploy_key CPUSER@directonderweg.com   # or the server hostname from your welcome email
   ```

The **private** key from this pair becomes the `SSH_PRIVATE_KEY` GitHub
secret in Step 6.

## Step 2 — Set PHP 8.3 (cPanel)

cPanel → **Select PHP Version** → choose **8.3**, and tick these extensions:
`mysqli`/`pdo_mysql`, `mbstring`, `xml`/`dom`, `curl`, `zip`, `gd`, `bcmath`,
`intl`, `opcache`.

Then make `php` and `composer` resolve correctly for the CI's SSH session:

```bash
mkdir -p ~/bin
ln -sf /usr/local/bin/ea-php83 ~/bin/php
which composer || { curl -sS https://getcomposer.org/installer | ~/bin/php && mv composer.phar ~/bin/composer; }
grep -q 'PATH=$HOME/bin' ~/.bashrc || echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc
php -v   # must say 8.3
```

## Step 3 — Put the app on the host

```bash
cd ~
git clone https://github.com/bangicodefactory/rentcar.git directonderweg
```

- For a **private** repo: generate a key on the host (`ssh-keygen`), add the
  public half as a **Deploy key** on GitHub (Repo → Settings → Deploy keys),
  and clone over SSH instead. CI's `git fetch` on each deploy needs this.
- Point the website at the app's `public/` folder by replacing `public_html`
  with a symlink:
  ```bash
  mv ~/public_html ~/public_html.bak
  ln -s ~/directonderweg/public ~/public_html
  ```
- Your app path is `/home/CPUSER/directonderweg` — that's the `DEPLOY_PATH`
  value in Step 6.

## Step 4 — Copy the database from the old host

1. **On the old host** (SSH if you have it):
   ```bash
   mysqldump --single-transaction --quick --routines --triggers \
     -u OLD_DB_USER -p OLD_DB_NAME | gzip > ~/db.sql.gz
   ```
   *No SSH on the old host?* Use its cPanel → phpMyAdmin → **Export**
   (format: SQL, gzipped) instead.
2. **In Namecheap cPanel → MySQL Databases:** create a database (it will be
   named `CPUSER_directonderweg`), create a DB user with a strong password,
   and **Add User To Database** with All Privileges. These three values are
   the `DB_*` secrets in Step 6.
3. Move the dump over and import (run on the Namecheap host):
   ```bash
   scp -P 22 OLDUSER@old-host:~/db.sql.gz ~/        # or upload via File Manager
   gunzip -c ~/db.sql.gz | mysql -u CPUSER_dbuser -p CPUSER_directonderweg
   ```

## Step 5 — Copy the uploaded files from the old host

```bash
# Run on the Namecheap host. Copies uploads, signatures, signed PDFs, the
# signing certificate — everything except regenerable cache/session/log dirs.
rsync -avz -e "ssh -p 22" \
  --exclude 'framework/cache/' --exclude 'framework/sessions/' \
  --exclude 'framework/views/' --exclude 'logs/' \
  OLDUSER@old-host:/path/to/old/app/storage/  ~/directonderweg/storage/

cd ~/directonderweg && php artisan storage:link
```

*No SSH between hosts?* On the old host's cPanel, File Manager → compress the
`storage/` folder → download → upload + extract on Namecheap, then run the
`storage:link` line.

## Step 6 — Connect GitHub to the host

GitHub repo → **Settings → Environments → `production-directonderweg`**
(create it if missing). Add:

**Secrets** (Environment secrets, encrypted):

| Secret | Value |
| --- | --- |
| `SSH_HOST` | Your Namecheap server hostname (welcome email) or the domain |
| `SSH_USERNAME` | `CPUSER` |
| `SSH_PRIVATE_KEY` | The **private** key from Step 1 (full file contents) |
| `APP_KEY` | **Copied from the OLD host's `.env`** — never generate a new one (encrypted data + signatures break otherwise) |
| `DB_HOST` | `localhost` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | The Step 4 values |
| `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | Your SMTP (Namecheap Private Email or Gmail etc.) — **real credentials, never `null`** |
| `NOCAPTCHA_SITEKEY` / `NOCAPTCHA_SECRET` | reCAPTCHA keys — **add `directonderweg.com` to the key's allowed domains** in the Google reCAPTCHA console first |

**Variables** (Environment variables, plain):

| Var | Value |
| --- | --- |
| `SSH_PORT` | **`21098`** ← Namecheap's SSH port, easy to miss |
| `DEPLOY_PATH` | `/home/CPUSER/directonderweg` |
| `APP_URL` | `https://directonderweg.com` |
| `APP_NAME` | `Direct Onderweg` |
| `APP_CLIENT` | `directonderweg` |
| `APP_ENV` | `production` |
| `CACHE_STORE` | `file` ← shared hosting has no Redis |
| `SESSION_DRIVER` | `file` |
| `QUEUE_CONNECTION` | `sync` (no worker processes on shared hosting; emails send inline — see runbook §2.1 for the trade-off) |
| `MAIL_PORT` / `MAIL_FROM_ADDRESS` | `587` / `no-reply@directonderweg.com` |

> Also make sure `deploy.yml` writes `INERTIA_SSR_ENABLED=false` into the
> generated `.env` (perf-audit **F-23** — without it every page load tries to
> reach an SSR server that doesn't exist). If that line hasn't been added to
> `deploy.yml` yet, do that first (separate small PR).

## Step 7 — Domain + HTTPS (cPanel / Namecheap dashboard)

1. Point the `directonderweg.com` DNS **A record** at the Namecheap server IP
   (Namecheap dashboard → Domain → Advanced DNS; the host IP is in your
   hosting welcome email).
2. Wait for DNS, then cPanel → **SSL/TLS Status** → **Run AutoSSL** for the
   domain. No certbot needed.

## Step 8 — Scheduler cron (cPanel)

cPanel → **Cron Jobs** → add, every minute (`* * * * *`):

```
/usr/local/bin/ea-php83 /home/CPUSER/directonderweg/artisan schedule:run >/dev/null 2>&1
```

Without this, reminders and the nightly activity-log prune never run.

## Step 9 — First deploy 🚀

From the repo (on `dev`/`main`):

```bash
git tag v1.0.0
git push origin v1.0.0
```

That triggers `.github/workflows/deploy.yml`: it builds the frontend in CI,
copies `.env` + assets to the host over SSH (port 21098), then runs
`composer install`, migrations (a no-op — the DB is already imported),
the branding seed, cache warm-up, and brings the app up.

Watch it under GitHub → **Actions → Deploy**.

## Step 10 — Check it works

- `https://directonderweg.com` loads with a valid padlock.
- Log in → dashboard shows the **old host's data**.
- Open a driver with documents and a signed agreement → images and the
  signature/PDF display (proves the Step 5 file copy + `APP_KEY` reuse).
- Create or edit a booking → it saves.
- Settings → send a test email.
- Finally, freeze the old site and 301-redirect it to `.com` (runbook §8) so
  no new data lands on the abandoned old database.

---

**If the deploy fails at `composer` or `php`:** the CI's SSH session didn't
pick up `~/bin` — check Step 2's `.bashrc` line, or edit `deploy.yml` to use
full paths (`~/bin/php artisan …`). **If pages are white:** check that
`public_html` is the symlink from Step 3 and `php artisan storage:link` ran.
For anything deeper, see the full runbook: `docs/deploy-directonderweg-com.md`.
