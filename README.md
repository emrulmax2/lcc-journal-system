# JCDMS — Journal of Contemporary Development & Management Studies

An open-access **publishing platform and end-to-end journal management system** for
London Churchill College. It runs the launch journal, **JCDMS**, and is built to host more.

The site is two products in one codebase: a public, crawlable reading surface (journals,
articles, DOI landing pages) and a private editorial office (submission, peer review,
publication, Crossref deposit) — plus a CMS so the marketing chrome is editable, not
hardcoded.

- **Stack:** Laravel 12 · PHP 8.2 · MySQL · Inertia v2 + React 18 + TypeScript + Tailwind 3.4 · Spatie permissions
- **Rendering:** server-side (Inertia SSR) for humans, with the citation metadata rendered by Blade so it survives the SSR process dying (see below)

---

## The one thing to understand first

**Public pages must be readable by machines that do not run JavaScript.** Google Scholar,
Crossref, DOAJ and OAI-PMH harvesters read the raw HTML. An article landing page is a DOI
landing page; if it is empty to those crawlers, every DOI pointing at it is wasted money.

Two defences make that guarantee hold, and neither may be removed:

1. **Pages are server-rendered** through an Inertia SSR process (Node).
2. **The `citation_*` and Dublin Core meta tags are rendered by Blade, in PHP** — not by
   React — so they are present in the raw HTML *even when the SSR process is down*. When
   that process dies, Inertia silently falls back to client rendering: humans see a working
   site, crawlers see nothing. The Blade-rendered metadata is the floor beneath that
   failure, and `php artisan journal:check-ssr` asserts it.

The full list of non-negotiables (frozen DOIs, idempotent Crossref deposits, per-journal
roles, utf8mb4, deliberate NULL placeholders) is in [`CLAUDE.md`](CLAUDE.md).

---

## Running it locally

Full instructions, login accounts and gotchas are in
[`docs/LOCAL-SETUP.md`](docs/LOCAL-SETUP.md). The short version:

```bash
cp .env.example .env
php artisan key:generate
```

Create the databases **explicitly as utf8mb4** (the dev MySQL default is `latin1`, which
silently corrupts author diacritics like *Papé*):

```sql
CREATE DATABASE lcc_journal      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE lcc_journal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
composer install
npm install
php artisan migrate:fresh --seed
php artisan journal:compute-metrics
php artisan storage:link
npm run build
```

Run three processes (the first two are required):

```bash
php artisan serve --host=127.0.0.1 --port=8000   # the app       → http://127.0.0.1:8000
php artisan inertia:start-ssr                     # the SSR renderer
php artisan queue:work                            # Crossref deposits (only when depositing)
```

Then confirm the site is actually machine-readable — do not trust the browser, because a
dead SSR process looks fine in one:

```bash
php artisan journal:check-ssr
```

> **Gotcha:** running `npm run dev` writes a `public/hot` file, which routes SSR through
> Vite and makes `<div id="app">` ship empty. Stop the dev server and `rm public/hot`
> before testing crawlability.

### Login accounts (seeded, local only)

All use the password **`password`**:

| Email | Role |
|---|---|
| `admin@lcc.ac.uk` | Site admin — bypasses every gate, and owns the CMS |
| `t.anderson-jaquest@lcc.ac.uk` | Journal editor on JCDMS — can publish and deposit DOIs |
| `t.wyrley-birch@lcc.ac.uk` | Production — can edit, **cannot** publish |
| `author@lcc.ac.uk` | Author — has a manuscript in peer review |
| `reviewer@lcc.ac.uk` | Reviewer — has submitted a report |

Submitting a manuscript needs **no account** — the wizard at `/submit` is public.

---

## What's in it

**Public reading surface** (server-rendered, crawlable)
- `/` — home, with editable sections, real aggregates and a hero from the media library
- `/journals`, `/journals/{slug}` — the journal list and each journal's landing page (aims & scope, ISSN, metrics)
- `/articles`, `/articles/{slug}` — full-text search and the DOI landing page (citation meta, PDF, Cite in Harvard/BibTeX/RIS)
- `/news`, `/topics` — news and calls for papers
- `/{page}` — CMS pages (author guidelines, ethics, APC, privacy, …)
- `/oai`, `/sitemap.xml` — OAI-PMH endpoint and sitemap for harvesters

**Editorial office** (behind login; per-journal authorisation)
- `/submit` — the five-step submission wizard (public)
- `/dashboard` — submissions, the review queue, decision-time chart; reviewer identities withheld from authors (single-blind)
- `/admin/*` — journals, volumes/issues, the article editor, the **publish gate**, the Crossref deposit log, settings and per-journal users
- `/admin/content/*` — the **CMS**: site settings, pages (Markdown), menus, home sections, news, topics, media library, newsletter

**Domain logic worth knowing about**
- **Publish gate** — reports *every* pre-flight failure at once, freezes the slug/sequence/DOI on publish, and dispatches the Crossref deposit *outside* the transaction so a Crossref outage never blocks going live.
- **Crossref** — XML built against the real 5.3.1 XSD and validated against it in tests; deposits are idempotent on the DOI; sandbox by default.
- **DOIs** — one generator, read everywhere via `Article::doi()`. Changing `doi_prefix` on one row moves every DOI that journal owns, with no code change.

---

## Testing

Tests run against **real MySQL** (`lcc_journal_test`), not SQLite — the schema depends on a
FULLTEXT index and ENUM columns SQLite does not have.

```bash
php artisan test        # 179 tests
npx tsc --noEmit        # the frontend type gate
vendor/bin/pint         # code style
```

---

## Project shape

```
app/
  Actions/            PublishArticleAction, PublishIssueAction, SubmitManuscriptAction, …
  Console/Commands/   journal:check-ssr, journal:check-dois, journal:compute-metrics, journal:doaj-export
  Http/Controllers/   Public/*  ·  Admin/*  ·  Admin/Content/*  (the CMS)
  Jobs/               DepositToCrossref, PollCrossrefSubmission
  Models/             Article, Journal, Issue, Submission, Review… + CMS: Page, Menu, HomeSection, Media, SiteSetting
  Services/           Doi/  ·  Citations/  ·  Crossref/  ·  Content/ (MarkdownRenderer, SiteContent)
  Support/            CitationMeta (the Blade-rendered tag set), SubmissionPresenter (anonymity)
resources/js/         Inertia React — pages/, components/, Layout.tsx, app.tsx, ssr.tsx
resources/views/      app.blade.php (the SSR root + citation meta), auth/login, oai/*, sitemap
database/             39 migrations, seeders (JcdmsSeeder = real, DemoSeeder/CmsSeeder/LocalDevSeeder)
docs/                 LOCAL-SETUP.md, DEPLOYMENT.md
design-system/        the authoritative design system (teal / navy, Newsreader + Inter)
```

---

## Deployment

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — it covers the cPanel document root, keeping
the SSR process alive under supervision, the queue worker, backups (Crossref stores
nothing — the PDFs are the only copy of the record), and the switch from the deliberate
`NULL` ISSN/DOI-prefix placeholders to real identifiers once the British Library and
Crossref issue them.

### Before go-live, humans still owe three things

- an **ISSN** (British Library) and a **Crossref DOI prefix** — until then, depositing is *impossible* by design, not merely disabled;
- a chosen **open-access licence** (e.g. CC BY 4.0) — DOAJ requires one, and `journal:doaj-export jcdms` lists this and the other blockers;
- real content for the **placeholder pages** (privacy policy, terms, accessibility statement) — the privacy page is seeded as a placeholder and is not yet a lawful notice.

---

## Notes

- The six **demo** journals (business, development, economics, policy, education, health) and
  their articles are **fictional showcase content**, seeded only outside production so the
  multi-journal UI has something to render. The one real journal is **JCDMS**.
- Hero and cover imagery is self-hosted in the media library. Replace the placeholder
  manuscript PDFs and any illustrative imagery with the real assets before publishing.
