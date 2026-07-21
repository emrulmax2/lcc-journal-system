# Deployment — jcdm.lcc.ac.uk (cPanel)

Read this before the first deploy. One section of it — **the SSR process** — is the only
genuinely fragile thing in the system, and it fails in a way that looks like success.

---

## 1. The thing that will bite you

**When the Node SSR process dies, nothing appears to break.**

Inertia does not error when its SSR server is unreachable. It falls back to client-side
rendering. So:

- Every human, including whoever is checking that the site is up, sees a working site.
- Every crawler — Google Scholar, Crossref, DOAJ, OAI-PMH harvesters — receives an empty
  `<div id="app">`.
- Scholar quietly drops the journal. The DOIs LCC paid to register resolve to pages no
  index can read.
- There is no error. No exception. No alert. It can persist for months.

Two defences are already built in. Do not remove either:

1. **The `citation_*` and Dublin Core meta tags are rendered by Blade, in PHP** — see
   `resources/views/app.blade.php` and `app/Support/CitationMeta.php`. They are in the raw
   HTML whether Node is alive, dead, or was never started. This is the floor: even in total
   SSR failure, the DOI-critical metadata survives.
2. **`php artisan journal:check-ssr`** fetches a real published article over HTTP and
   asserts the body content is present. **Schedule it.** It is the only thing that will
   tell you the SSR process is gone.

---

## 2. Requirements

| | |
|---|---|
| PHP | 8.2+ (`pdo_mysql`, `mbstring`, `openssl`, `xml`, `curl`, `zip`, `intl`, `fileinfo`) |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+. **Database must be `utf8mb4` / `utf8mb4_unicode_ci`.** |
| Node | 18+ — required at BUILD time, and at RUNTIME for SSR |
| Composer | 2.x |

### utf8mb4 is not optional

Author names carry diacritics (Papé, Ramírez, Sørensen); titles carry en-dashes and curly
quotes. cPanel's MySQL wizard often creates databases as `latin1`, which corrupts those
**silently** — no error, just mangled characters that reach Crossref and every index that
consumes it. Verify before importing anything:

```sql
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'lccacuk_jcdm';
-- must be utf8mb4 / utf8mb4_unicode_ci
```

---

## 3. Document root

cPanel points the domain at `~/public_html`. Laravel's front controller is in `public/`.

**Do not move `index.php` up into `public_html` and rewrite the paths.** That is the
common cPanel shortcut and it exposes `.env`, `storage/` and `vendor/` to the web —
`.env` contains the app key and the database password.

Instead, either:

- **(Preferred)** Set the domain's document root to `~/lcc-journal-system/public` in cPanel →
  *Domains* → *Manage* → *Document Root*; or
- Symlink: `ln -s ~/lcc-journal-system/public ~/public_html`

Confirm afterwards that `https://jcdm.lcc.ac.uk/.env` returns **404**, not the file.

---

## 4. Build (locally or in CI — NOT on the server)

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build          # builds BOTH the client bundle and bootstrap/ssr/ssr.js
```

`npm run build` runs `vite build && vite build --ssr`. If `bootstrap/ssr/ssr.js` is
missing after a deploy, SSR silently will not start — see §1.

Upload everything except `node_modules/`, `.git/`, `tests/`.

---

## 5. Environment

```dotenv
APP_ENV=production
APP_DEBUG=false                 # a stack trace on a DOI landing page is a data leak
APP_URL=https://jcdm.lcc.ac.uk  # must be https, and must match the canonical URL exactly

DB_CONNECTION=mysql
DB_DATABASE=lccacuk_jcdm
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

QUEUE_CONNECTION=database       # Crossref deposits run on the queue
CACHE_STORE=database
SESSION_DRIVER=database

INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=http://127.0.0.1:13714   # http:// — NOT https://, see below
INERTIA_SSR_RUNTIME=/opt/cpanel/ea-nodejs22/bin/node   # absolute; `node` is NOT on PATH
INERTIA_SSR_ENSURE_RUNTIME_EXISTS=true                 # fail loudly, not at exit 0

CROSSREF_ENDPOINT=sandbox       # LEAVE ON SANDBOX until §9
```

**`INERTIA_SSR_RUNTIME` must be an absolute path, and `node -v` in your SSH session proves
nothing.** Inertia spawns the runtime through `/bin/sh`. A bare `node` that the shell cannot
find fails with 127 — and `inertia:start-ssr` prints that line and **still exits 0**. systemd
sees a clean exit, restarts on `RestartSec`, and loops forever: nothing listening, nothing
logged, and a unit that looks fine. On cPanel, Node is installed per-version and is *not* on
the default PATH, and a systemd unit's PATH is a minimal `/usr/local/sbin:…:/bin` with no
cPanel directories in it at all. Find the binary and pin it:

```bash
ls -d /opt/cpanel/ea-nodejs*/bin/node     # e.g. /opt/cpanel/ea-nodejs22/bin/node
```

`INERTIA_SSR_ENSURE_RUNTIME_EXISTS=true` converts that silent exit 0 into a loud exit 1 with
a message. The package default is `false`; leaving it false is how this failure hides.
`deploy:check` now fails when the runtime cannot be resolved.

**`INERTIA_SSR_URL` is the one URL here that must stay `http://`.** Everything else on this
site is `https://`, so making this one match is the obvious, well-meant edit — and it takes
SSR down completely. The SSR server is a plain Node HTTP listener bound to loopback: no
certificate, no TLS. Point Inertia at `https://127.0.0.1:13714` and every render fails the
handshake, the gateway swallows the exception, and you get §1 — a working site for humans
and an empty `<div id="app">` for every crawler, with the systemd unit sitting there green.
It needs no TLS because the traffic never leaves the host. `deploy:check` now fails on this.

`APP_URL` matters more than it looks. `citation_pdf_url` and `citation_abstract_html_url`
are generated from it, and Google Scholar treats an `http://` advertised URL on an
`https://` site as a *different page* from the one the DOI resolves to — which is one of
the most common reasons a journal silently fails to get indexed.

Then:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=JcdmsSeeder --force     # REAL content — run this
# DO NOT run DemoSeeder. It publishes six fictional journals and invented research
# under LCC's name. DatabaseSeeder already guards it by environment; do not bypass it.

php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

---

## 6. Keeping the SSR process alive

You have root on the dedicated server, so use it. The account itself is a cPanel user and
cannot run `systemctl`.

### Option A — systemd, system-wide (recommended)

`/etc/systemd/system/jcdm-ssr.service`:

```ini
[Unit]
Description=Meridian Journal Inertia SSR (jcdms.lcc.ac.uk)
After=network.target

[Service]
Type=simple
# the cPanel account user — verify it exists first with: id jcdmslccac
User=jcdmslccac
Group=jcdmslccac
WorkingDirectory=/home/jcdmslccac/lcc-journal-system
# BOTH binaries absolute. /usr/bin/php on cPanel is a perl wrapper that picks a PHP version
# from the environment, and a systemd unit does not have the environment it expects.
# Confirm yours: ls -d /opt/cpanel/ea-php*/root/usr/bin/php
ExecStart=/opt/cpanel/ea-php82/root/usr/bin/php artisan inertia:start-ssr
# systemd's default PATH has no cPanel directories, so a bare `node` is unfindable here even
# though it works in your SSH session. Belt and braces with INERTIA_SSR_RUNTIME in .env.
Environment=PATH=/opt/cpanel/ea-nodejs22/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Comments must be on their own line. systemd does **not** strip trailing `#` comments — a
comment after `User=` becomes part of the username and the unit dies with `217/USER`.

```bash
systemctl enable --now jcdm-ssr
systemctl status jcdm-ssr
```

`Restart=always` is the point: the SSR process dying is the failure mode from §1, and it
must come straight back.

#### If the unit will not start

Read the exit code in `systemctl status` before anything else — these fire *before* PHP runs:

| Code | Meaning | Fix |
|---|---|---|
| `217/USER` | `User=`/`Group=` does not exist | `id <user>`; check for a trailing comment on the line |
| `200/CHDIR` | `WorkingDirectory` wrong or unreadable | `ls -ld /home/jcdmslccac/lcc-journal-system` |
| `203/EXEC` | `ExecStart` binary wrong | `which php` as the account; on cPanel it is often `/opt/cpanel/ea-php82/root/usr/bin/php` |
| `1/FAILURE` in ~200 ms | PHP booted and the command returned FAILURE. It **always prints why** — `journalctl -u jcdms-ssr -n 50 --no-pager` has the line. Only three things do this: SSR disabled in config, no SSR bundle, or `ENSURE_RUNTIME_EXISTS=true` with an unfindable runtime. | Read the journal line; it names which |

Note what is **not** in that table: a missing Node runtime, on its own, does **not** produce a
failing exit code. The shell fails with 127, `inertia:start-ssr` prints it and exits **0**, and
systemd restarts a clean exit forever. If the unit flaps with no failure status and nothing
ever listens, that is the one to suspect — see `INERTIA_SSR_RUNTIME` in §5.

`systemctl cat jcdm-ssr` shows what systemd actually parsed, which is the fastest way to
spot a mangled value. Once it starts, `journalctl -u jcdm-ssr -n 50` has the app-level errors.

#### If the unit IS running but `check-ssr` still says SSR IS DOWN

This is the confusing one, and "start the SSR process" is the wrong advice for it. Inertia
never contacts the SSR server at all in three of these cases, so the service looks perfectly
healthy while every page falls back to client rendering:

```bash
php artisan journal:check-ssr    # prints the reason, in the order Inertia checks it
```

| What it reports | Why | Fix |
|---|---|---|
| SSR is DISABLED in config | `config:cache` ran while `.env` still lacked `INERTIA_SSR_ENABLED=true`. The cached value wins. | `php artisan config:clear && php artisan config:cache` |
| `public/hot` EXISTS | Inertia checks `Vite::isRunningHot()` FIRST and posts to Vite's dev server instead of the SSR server. The file is gitignored, so it never arrives by `git pull` — but a zip or FTP mirror of a dev machine carries it straight into production. | `rm public/hot` |
| NO SSR BUNDLE | `bootstrap/ssr/ssr.js` is gitignored too. It is built by `npm run build` and **rsynced** by the deploy workflow — a hand deploy that only ran `git pull` will never have it. | Build locally, upload `bootstrap/ssr/` |
| INERTIA_SSR_URL is HTTPS | The SSR server is plain HTTP on loopback and speaks no TLS, so the handshake fails and is swallowed. Matching it to `APP_URL` is the usual cause. | `INERTIA_SSR_URL=http://127.0.0.1:13714`, then `config:clear && config:cache` |
| nothing answering on :13714 | Only *now* is the process genuinely down. | `systemctl restart jcdms-ssr` |
| all four pass, render failing | The bundle is there and stale or throwing. PHP sees nothing; only Node logs it. | `journalctl -u jcdm-ssr -n 50`, then rebuild and redeploy `bootstrap/ssr/` |

Both traps in the middle come from the same root cause: **`public/hot` and `bootstrap/ssr/`
are gitignored build artefacts.** Git will neither deliver the one you need nor remove the
one you must not have. Only §11's rsync does that, which is why the first hand deploy is the
one that gets bitten.

### Option B — no root, cron watchdog

If you cannot touch systemd, a minute-by-minute watchdog is an acceptable fallback. It is
worse than systemd — it can leave the site invisible to crawlers for up to 60 seconds —
but it is far better than nothing:

```cron
* * * * * pgrep -f "inertia:start-ssr" > /dev/null || (cd ~/lcc-journal-system && nohup php artisan inertia:start-ssr >> storage/logs/ssr.log 2>&1 &)
```

### Queue worker and scheduler (both required)

```cron
* * * * * cd ~/lcc-journal-system && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/lcc-journal-system && php artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1
```

The queue is what deposits DOIs to Crossref. Without a worker, articles publish correctly
and **no DOI is ever registered** — and, because publish is deliberately decoupled from
Crossref, nothing will look wrong.

`schedule:run` drives three things, all of which guard against silent failure:

| Command | When | Guards against |
|---|---|---|
| `journal:check-ssr` | hourly | The SSR process dying — humans see a working site, crawlers see nothing |
| `journal:check-dois` | weekly | Link rot — a registered DOI that now 404s, invisible from inside the site |
| `journal:compute-metrics` | daily | Stale acceptance rates and decision times. Never touches impact_factor / cite_score, which are external (JCR / Scopus) |

---

## 7. Verify the deploy — the only test that matters

```bash
php artisan journal:check-ssr
```

It must print `OK — page is server-rendered and machine-readable`. If it does not, stop:
the DOI programme is not working, regardless of how the site looks in a browser.

Then confirm by hand, exactly as a crawler would:

```bash
curl -s https://jcdm.lcc.ac.uk/articles/<slug> | grep citation_
```

You must see the full tag set — `citation_title`, `citation_journal_title`, one
`citation_author` per author, `citation_doi`, `citation_pdf_url`, and the rest. **If this
returns nothing, no DOI is worth registering.**

Also check:

```bash
curl -s https://jcdm.lcc.ac.uk/ | grep -c '<div id="app"></div>'   # must be 0 → SSR is up
curl -sI https://jcdm.lcc.ac.uk/.env                               # must be 404
```

---

## 8. Backups — what actually has to survive

Crossref stores **nothing**. It only resolves identifiers. If this server dies, the DOIs
point at nothing, permanently.

Back up, off-site, restorable:

1. `storage/app/private/articles/` — **the PDFs**. These are the only copy of the
   published record. Losing them cannot be undone by any amount of database recovery.
2. The `articles`, `article_authors`, `article_files`, `issues`, `volumes`, `journals` and
   `doi_deposits` tables.
3. `storage/app/private/crossref/` — the exact XML of every deposit made.

Test a restore. An untested backup is a belief, not a backup.

Longer term, arrange real preservation (PKP PN, CLOCKSS, or Portico). LCC has undertaken
to keep these landing pages resolving indefinitely; a single server in one rack is not
that undertaking.

---

## 9. Going live with real identifiers

Today `doi_prefix` and both ISSN columns are **NULL** — deliberately. `Journal::canMintDois()`
returns false, so it is impossible to deposit a malformed DOI.

When the British Library issues the ISSN and Crossref issues the prefix:

```sql
UPDATE journals
SET issn_online = '<real>', doi_prefix = '10.xxxxx'
WHERE slug = 'jcdms';
```

**That is the entire change.** Nothing in the codebase hardcodes either value. Verify:

```bash
php artisan journal:check-ssr    # citation_doi and citation_issn now appear
```

If anything else needed touching, that is a bug — fix it before go-live, not after.

Only then set `CROSSREF_ENDPOINT=production`, and add the journal's Crossref credentials
via the admin (they are encrypted at rest, and never returned in any API response).

---

## 10. The commitment, in plain English

Registering a DOI is a promise that a URL will keep working. Whoever signs off should
understand what is being undertaken:

> **London Churchill College undertakes to keep these article landing pages resolving
> indefinitely.** If the site is ever restructured, moved or replatformed, 301 redirects
> are mandatory and the DOI resource URLs must be updated at Crossref by redeposit.
> A DOI that 404s is worse than no DOI: it is a broken promise that other people have
> already cited in work they cannot now correct.

`php artisan journal:check-dois` (scheduled) resolves every registered DOI and reports any
that 404. Link rot is the failure that destroys a journal's credibility, and it happens
silently.

---

## 11. Automated deploys (GitHub Actions)

§4–§7 are the **first** deploy, done by hand once. After that, pushing to `main` deploys
itself. The pipeline lives in two files:

- `.github/workflows/deploy.yml` — runs in GitHub Actions on every push to `main`.
- `.github/workflows/deploy.sh` — runs **on the server**, over SSH, invoked by the workflow.

### What it does

```
push to main
   │
   ├─ GitHub Actions (ubuntu):
   │     npm ci
   │     npm run build            → tsc gate + public/build + bootstrap/ssr
   │     rsync public/build/  →  server
   │     rsync bootstrap/ssr/ →  server      (both are git-ignored — they travel by rsync,
   │                                          NOT by the git pull the server runs)
   │     ssh server 'bash -s' < deploy.sh
   │
   └─ deploy.sh (on the server):
         git pull origin main
         composer install --no-dev --optimize-autoloader
         php artisan migrate --force
         php artisan storage:link
         optimize:clear + config:cache + route:cache + view:cache + event:cache
         php artisan queue:restart          ← workers pick up new code (§6)
         restart the SSR process            ← loads the new bootstrap/ssr bundle (§1)
         php artisan deploy:check           ← FATAL: a FAIL fails the deploy
         php artisan journal:check-ssr      ← non-fatal warning; the hourly check is the backstop
```

The build runs in CI, never on the server (§4). The `tsc --noEmit` gate inside `npm run
build` means a TypeScript error fails the deploy in Actions rather than shipping a broken
bundle. `deploy:check` is the gate that makes the *pipeline* honest: it re-checks charset,
migrations, the Vite manifest, the SSR bundle and (in production) `APP_DEBUG=false` and an
`https://` `APP_URL`, and exits non-zero on any FAIL — so "the deploy script ran" and "the
site is serving correctly" stop being two different things.

### Required repository secrets

*Settings → Secrets and variables → Actions:*

| Secret | Value |
|---|---|
| `SSH_PRIVATE_KEY` | Private half of a deploy key. Generate with `ssh-keygen -t ed25519`, put the **public** half in the server account's `~/.ssh/authorized_keys`. |
| `SSH_PUBLIC_KEY` | The public half (optional; written alongside the private key). |
| `SSH_HOST` | Server hostname or IP. |
| `SSH_USERNAME` | The cPanel / SSH account user. |
| `SSH_DEPLOY_PATH` | Absolute path to the app on the server: `/home/jcdmslccac/lcc-journal-system`. This is the `~/lcc-journal-system` from §6, spelled out in full. |

### Two things to adapt before the first automated deploy

1. **The SSR restart.** `deploy.sh` defaults to *kill-and-relaunch-detached*, which leans
   on the cron watchdog from §6 Option B. If you took **§6 Option A (systemd)** — the
   recommended path — replace that block in `deploy.sh` with `sudo systemctl restart
   jcdm-ssr` (and give the deploy user a targeted `sudoers` entry for exactly that command).
   Systemd restarts cleanly; the detached fallback can be reaped when the SSH session ends,
   which is why the watchdog exists.

2. **The prerequisites `deploy.sh` assumes.** It runs `git pull`, so the server copy must be
   a clone of this repo with `origin` set and `main` checked out. It does **not** write
   `.env`, run `key:generate`, or seed — those are the one-time §5 steps. It also never runs
   `DemoSeeder`. In short: do the §4–§7 first deploy by hand, confirm `journal:check-ssr` is
   green, *then* let Actions take over.

`--no-dev` means production never installs dev dependencies, so nothing in `deploy.sh`,
`deploy:check` or the running app may depend on them.
