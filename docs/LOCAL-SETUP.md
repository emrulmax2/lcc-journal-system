# Running locally

## One-time setup

```bash
cp .env.example .env
php artisan key:generate
```

Create the database **explicitly**, not through phpMyAdmin's defaults:

```sql
CREATE DATABASE lcc_journal      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE lcc_journal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> **This matters.** WAMP's MySQL server defaults to `latin1`. Author names carry
> diacritics (`Papé`, `Ramírez`, `Sørensen`) and titles carry en-dashes — a `latin1`
> column corrupts them **silently**, with no error, and those mangled characters then get
> deposited to Crossref and propagate to every index that consumes it. `lcc_journal_test`
> is the database the test suite uses; the suite runs against real MySQL, not SQLite,
> because the schema depends on a FULLTEXT index and ENUM columns that SQLite does not have.

Then:

```bash
composer install
npm install
php artisan migrate:fresh --seed
php artisan journal:compute-metrics
php artisan storage:link
npm run build
```

## Running it

Three processes. The first two are required.

```bash
php artisan serve --host=127.0.0.1 --port=8000   # the app
php artisan inertia:start-ssr                     # the SSR renderer  <- see below
php artisan queue:work                            # Crossref deposits (only needed to deposit)
```

Then open **http://127.0.0.1:8000**.

### The SSR process is not optional

Without `inertia:start-ssr`, Inertia does **not** error. It silently falls back to
client-side rendering: the site looks perfectly fine in your browser, and every crawler
receives an empty page. That is the exact failure this whole system is built to prevent,
so verify it rather than trusting it:

```bash
php artisan journal:check-ssr
```

It must print `OK — page is server-rendered and machine-readable`.

### Frontend development

For hot reload, run `npm run dev` **instead of** `npm run build`. But note: while
`npm run dev` is running it writes a `public/hot` file, and Inertia then routes SSR
through Vite rather than the SSR process. **Stop `npm run dev` and delete `public/hot`
before testing SSR**, or you will see an empty `<div id="app">` and think SSR is broken.

## Login accounts

All seeded accounts use the password **`password`**.

| Email | What they can do |
|---|---|
| `admin@lcc.ac.uk` | Site admin — bypasses every gate |
| `t.anderson-jaquest@lcc.ac.uk` | Journal editor on JCD&MS — **can publish and deposit DOIs** |
| `t.wyrley-birch@lcc.ac.uk` | Production on JCD&MS — can edit everything, **cannot publish** |
| `author@lcc.ac.uk` | Author — has a manuscript in peer review |
| `reviewer@lcc.ac.uk` | Reviewer — has submitted a report |
| `reviewer2@lcc.ac.uk` | Reviewer — invitation still open, and overdue |

### Worth looking at with your own eyes

**The anonymity guarantee.** Open `/dashboard` as `author@lcc.ac.uk`: the reviewer shows
as *"Reviewer 1 — Identity withheld"*. Open the same submission as
`t.anderson-jaquest@lcc.ac.uk`: you see *Sofia Ramírez, Institute of Marine Ecology*, plus
her confidential comments to the editor. The author cannot reach that name through any
endpoint, prop or error message.

**The publish gate.** As the editor, go to `/admin` → JCD&MS → an issue → publish. It
reports *every* pre-flight problem at once, not one at a time. As
`t.wyrley-birch@lcc.ac.uk` (production), the publish control is absent and the endpoint
returns 403 — publishing spends money at Crossref and freezes URLs forever, so it is an
editorial decision, not a production one.

## What is seeded

- **JCD&MS** — the real journal. Volume 10, Issue 2, all 7 real articles (pp. 8–106),
  including the corporate-author editorial (zero personal authors) and the 5-author
  protocol with Russell Kabir correctly at Anglia Ruskin. ORCIDs only where they genuinely
  exist; none are invented. `doi_prefix` and `issn_online` are deliberately **NULL**.
- **Six fictional "Meridian" journals** (`DemoSeeder`) — invented content so the public UI
  has something to render. **Never runs in production.**
- **Login accounts + a manuscript in peer review** (`LocalDevSeeder`). **Never runs in
  production.**

### The placeholder PDFs

`LocalDevSeeder` attaches a placeholder PDF to each JCD&MS article. This is deliberate:
the publish gate **refuses** to publish an article without a PDF (because
`citation_pdf_url` is advertised to Google Scholar, and an advertised PDF that 404s
downgrades the whole journal), so without them you could never see a publish or a Crossref
deposit actually run locally.

The files say "PLACEHOLDER — not the real article" in their own content. **Replace them
with the real typeset PDFs before anything is published for real.**

## Tests

```bash
php artisan test        # 144 tests, runs against lcc_journal_test
npx tsc --noEmit        # type gate
vendor/bin/pint         # formatting
```

## Useful commands

```bash
php artisan journal:check-ssr          # is the site machine-readable right now?
php artisan journal:check-dois         # do all registered DOIs still resolve?
php artisan journal:compute-metrics    # acceptance rate, median days to decision
php artisan journal:doaj-export jcdms  # DOAJ readiness — lists what is still blocking
```
