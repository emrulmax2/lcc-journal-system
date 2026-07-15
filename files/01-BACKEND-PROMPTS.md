# 01 — Backend Build Prompts (Laravel 12 + MySQL)
### Meridian Open Science

Read `00-ARCHITECTURE-DECISIONS.md` first and settle **D1–D4**. These prompts assume:
- **D1** = build multi-journal, launch with one (JCD&MS)
- **D2** = peer review **in scope** (Phase 5 — skip it if you're deferring)
- **D3** = Meridian design system wins
- **D4** = slugs frozen at publication

**Repo context to give Claude Code:**
| File | Why |
|---|---|
| `postcss_config.tar` source (`src/lib/data.ts`, `src/pages/*.tsx`) | The real frontend data shapes and screens |
| `design-system/meridian-open-science/MASTER.md` | Design tokens and rules |
| `jcdms-journal-manager.html` | Admin domain logic + **real JCD&MS Vol 10 No 2 seed data** |
| `jcdms-site.zip` | The **correct citation meta tag markup** — copy it verbatim |

Run one prompt per session. Don't advance until the acceptance criteria pass.

---

## Prompt 0 — `CLAUDE.md` (a file, not a prompt)

Put this at the project root so every session inherits it.

```markdown
# Meridian Open Science

Open-access publishing platform + journal management system.
Laravel 12 · PHP 8.2 · MySQL 8 · Inertia + React 18 + TS + Tailwind 3.4 · Spatie permissions.

## Non-negotiables

1. PUBLIC PAGES ARE SERVER-RENDERED. Google Scholar, Crossref, DOAJ and OAI-PMH
   harvesters do not execute JavaScript. Any article landing page that renders
   client-side is invisible to them, and every DOI pointing at it is wasted money.
   Acceptance test for any public page: `curl` it and see the content and the
   citation_* meta tags in the raw HTML.

2. URLS AND DOIS ARE PERMANENT. On publish, an article's slug, sequence and
   doi_suffix are FROZEN. Editing the title later must NOT regenerate the slug.
   Enforce in a model observer AND a policy. A changed suffix is a dead DOI, and
   there is no undo.

3. CROSSREF DEPOSITS ARE IDEMPOTENT ON THE DOI. Redepositing UPDATES the record.
   Retry is always safe. Never add an "already deposited?" guard — it would block
   legitimate metadata corrections.

4. DEPOSIT IS DECOUPLED FROM PUBLISH. If Crossref is unreachable, pages still go
   live; the deposit retries later from the admin. Never wrap the Crossref call in
   the publish transaction. The public site must never depend on Crossref being up.

5. TWO PUBLICATION MODELS. journals.publication_model is 'continuous' or
   'issue_based'. articles.issue_id is NULLABLE. JCD&MS is issue_based (Vol 10,
   No 2, pp. 8-106). Don't force one model on the other.

6. ROLES ARE PER-JOURNAL. Someone edits Journal A and reviews for Journal B.
   Use Spatie teams (team = journal) or a journal_user pivot carrying the role.

## Placeholders (deliberate)
ISSN and doi_prefix are NULL until the British Library and Crossref issue them.
They live in the `journals` table. Nothing else in the codebase may hardcode them —
if changing one DB row doesn't update every DOI in the app, that's a bug.

## Vocabulary (from the frontend — do not rename)
Pipeline stages : Submitted → Editor check → Peer review → Decision → Production (0-4)
Submission      : Draft | Submitted | Under Review | Revisions Requested | Accepted | Rejected
Reviewer        : Invited | Accepted | Report submitted | Declined
Recommendation  : Accept | Minor revision | Major revision | Reject
```

---

## Phase 1 — Foundation

### Prompt 1 · Recon and transport decision

> Before writing any code, study this codebase and report back. Write nothing.
>
> Read the existing frontend source in full: `src/lib/data.ts` (the data shapes), all of `src/pages/*.tsx`, `src/components/*.tsx`, `src/App.tsx`, `tailwind.config.js`, `src/index.css`, and `design-system/meridian-open-science/MASTER.md`.
>
> Then produce a memo covering:
>
> 1. **Every data shape the UI needs**, page by page. For each of Home, Journals, Articles, ArticleDetail, Submit and Dashboard, list exactly what props/fields it reads. This is the contract the backend must satisfy — I want it derived from the code, not guessed.
> 2. **The Inertia migration plan.** We are converting this react-router SPA to Inertia v2 with SSR, because the public pages must be server-rendered (Google Scholar does not run JavaScript, and our DOI landing pages are currently invisible to it — I verified this against the live site). Assess: how much of `App.tsx`'s `AnimatePresence`-on-`useLocation` page-transition machinery survives the move to Inertia's router? What breaks in `Reveal.tsx`, `Counter.tsx`, and the parallax hero under SSR (`window`/`document` access at module scope)? Be specific, cite line numbers.
> 3. **Tailwind.** The frontend is Tailwind **3.4** with a custom `brand`/`ink`/`gold` palette and Newsreader/Inter. Confirm what needs to change to build it through Laravel's Vite pipeline, and flag any conflict with an existing Tailwind config in the repo.
> 4. **A proposed module/file layout** for the Laravel side.
>
> End with anything in the architecture doc you think is wrong.

**Acceptance:** a written per-page data contract, and a specific list of what breaks under SSR.

---

### Prompt 2 · Schema and migrations

> Create the MySQL migrations for Meridian. The data model is specified in `00-ARCHITECTURE-DECISIONS.md` §6 — implement it exactly, and cross-check every column against the TypeScript types in `src/lib/data.ts` so nothing the UI needs is missing.
>
> Tables: `fields`, `journals`, `journal_metrics`, `journal_sections`, `volumes`, `issues`, `articles`, `article_authors`, `article_references`, `article_files`, `article_metric_daily`, `doi_deposits`, `doi_deposit_items`, `news_items`, `research_topics`, `research_topic_editors`, `reviewer_profiles`, plus the `journal_user` role pivot.
>
> (Submission/review tables come in Phase 5 — leave them out for now, but design `articles` so a Submission can later become an Article without a migration that touches published rows.)
>
> Non-negotiable constraints:
> - **utf8mb4 throughout.** Author names carry diacritics (Papé, Ramírez, Sørensen) and titles carry en-dashes and curly quotes. A `latin1` column here corrupts real data silently.
> - `articles.doi_suffix` — **unique across the whole table**, not per journal.
> - `articles.slug` — unique per journal. This becomes the permanent public URL.
> - `articles.issue_id` — **nullable**, for continuous-publication journals.
> - `journals.crossref_password` — encrypted cast, never plain.
> - `journals.doi_prefix` and both ISSN columns — **nullable**. They are not yet issued.
> - Foreign keys with deliberate cascade rules. A published issue must not be deletable — use `restrictOnDelete` where it expresses that, and back it with a policy.
> - Index what the UI actually filters on: `articles(journal_id, status, published_at)`, `articles(slug)`, `journal_sections(journal_id)`. The Articles page filters by journal + type + full-text query — add a FULLTEXT index on `articles(title, abstract)`.
>
> Follow Laravel 12 migration conventions. No models yet.

**Acceptance:** `migrate:fresh` runs clean; every field in `data.ts` has a home; `doi_suffix` and `(journal_id, slug)` are both unique.

---

### Prompt 3 · Models, DOI logic, citations

> Build the Eloquent models. Laravel 12 conventions: the `casts()` method (not the `$casts` property), PHP 8.2 backed enums for every status, typed properties, no `mixed`.
>
> Relationships per §6 of the architecture doc. Then the domain logic that actually matters:
>
> 1. **`DoiSuffixGenerator`** — one service, one place. Reads `journals.doi_suffix_pattern` and produces the suffix. Support both observed patterns: `jcdms.v{vol}i{issue}.{seq}` (issue-based) and `mrdn.{year}.{seq}` (continuous). The pattern string lives in the DB. **No other file in the codebase may construct a DOI string.**
>
> 2. **`Article::doi()`** → `{journal->doi_prefix}/{doi_suffix}`; **`Article::doiUrl()`** → `https://doi.org/{doi}`. Every view, export and XML deposit reads these accessors. Test: changing `doi_prefix` on one journal row changes every DOI that journal owns, everywhere, with no other code change.
>
> 3. **Freeze-on-publish observer.** Once `status === published`, `slug`, `sequence` and `doi_suffix` are immutable — throw a domain exception on any attempt to change them. **Critically: editing the title of a published article must NOT regenerate the slug.** Write the test that proves it, because this is the single most likely way to silently kill a DOI, and slug regeneration on title change is the default behaviour of most slug packages.
>
> 4. **`CitationFormatter`** — Harvard, BibTeX, RIS. Port the exact logic from the `harvard()`, `bibtex()` and `ris()` functions in `jcdms-journal-manager.html`, including the corporate-author special case (an article with no personal authors, e.g. an editorial by a research centre).
>
> 5. **Metrics.** `views_count` / `citations_count` on articles are denormalised counters; `article_metric_daily` is the source of truth. Add a scheduled command to roll up.
>
> Factories for every model. Unit tests for the suffix generator (both patterns), the freeze observer, and all three citation formats.

**Acceptance:** tests green; the "edit a published title, slug does not move" test passes.

---

### Prompt 4 · Seed real data

> Two seeders.
>
> **`JcdmsSeeder` — real, and the one that matters.** Extract the `SEED_JOURNAL`, `SEED_VOLUMES`, `SEED_ISSUES` and `SEED_ARTICLES` arrays from `jcdms-journal-manager.html`. This is genuine published content — copy titles, abstracts, keywords, page ranges, ORCIDs and affiliations **exactly**. Never paraphrase, never use faker on these records.
>
> Get these right, they're the edge cases that break naive code:
> - Journal: JCD&MS, `publication_model = issue_based`, `doi_prefix = NULL`, `issn_online = NULL` (not yet assigned — deliberate).
> - Volume 10 → Issue 1 (Autumn 2025, published, no article records) and Issue 2 (Spring 2026, draft, 7 articles).
> - **Article 001** is an editorial with a `corporate_author` and **zero** rows in `article_authors`.
> - **Article 005** has five authors; Russell Kabir is at **Anglia Ruskin University**, the rest at LCC. Author order is meaningful — preserve it.
> - ORCIDs exist for Papé, Hasan, Takamura, Kabir, Mahmud, Rahim. The others have none. Leave them NULL. **Do not fabricate an ORCID** — a wrong ORCID in a Crossref deposit attributes someone else's work to a real person.
> - Sections: Editorial, Research Article, Research Report, Research Protocol.
>
> **`DemoSeeder` — the Meridian showcase.** Port `src/lib/data.ts` (6 journals, 6 articles, news, research topics, stats). Keep it in a separate seeder, clearly labelled fictional, and **never run it in production**. It exists so the UI has something to render in dev.

**Acceptance:** JCD&MS seeds to 7 articles with correct suffixes; the corporate-author editorial and the 5-author protocol both render correctly; no fabricated ORCIDs.

---

### Prompt 5 · Per-journal RBAC

> Wire up Spatie permissions. Roles are **per journal** — a user can be `journal-editor` on JCD&MS and `reviewer` on another journal. Use Spatie's teams feature (team = journal) or a `journal_user` pivot carrying the role; pick one, justify it, and be consistent.
>
> Roles: `site-admin` (global bypass), `publisher-admin`, `journal-editor`, `section-editor`, `production`, `reviewer`, `author`.
>
> Permissions: `journal.view`, `journal.settings.manage`, `journal.issue.manage`, `journal.article.manage`, `journal.publish`, `journal.doi.deposit`, `journal.users.manage`, `submission.create`, `submission.view.own`, `submission.view.all`, `review.assign`, `review.submit`, `decision.record`.
>
> `journal.publish` is the high-privilege gate — it makes URLs permanent and spends money at Crossref. `production` must have `journal.article.manage` but explicitly **NOT** `journal.publish`.
>
> Policies for Journal, Issue, Article. The Issue policy enforces: a published issue cannot be edited, deleted, or have articles added, removed or reordered.
>
> Feature-test the full matrix **including the negatives** — production cannot publish; an editor of Journal A cannot touch Journal B; a reviewer cannot see other reviewers' reports.

**Acceptance:** cross-journal isolation is proven by test, not assumed.

---

## Phase 2 — Public reading surface (**unblocks DOIs**)

### Prompt 6 · Inertia + SSR, and the citation meta tags

> This is the prompt that makes the DOI programme actually work. Read the SSR finding in `00-ARCHITECTURE-DECISIONS.md` §2 first.
>
> Convert the frontend from a react-router SPA to **Inertia v2 with SSR**, and build the public controllers.
>
> **Public routes** (no auth, cacheable, must be crawlable):
> ```
> GET /                      → Home
> GET /journals              → Journals
> GET /articles              → Articles   (query: q, journal, type — see Articles.tsx)
> GET /articles/{slug}       → ArticleDetail
> GET /articles/{slug}.pdf   → PDF, streamed from disk, stable URL
> GET /sitemap.xml
> ```
> Only `published` articles are reachable. A draft returns 404 to guests, 200 to an authenticated editor previewing it.
>
> **The article landing page must emit, server-side, in the `<head>`:**
> - Highwire Press tags: `citation_journal_title`, `citation_journal_abbrev`, `citation_publisher`, `citation_title`, one `citation_author` + `citation_author_institution` pair **per author in sequence order**, `citation_author_orcid` where present, `citation_publication_date`, `citation_volume`, `citation_issue`, `citation_firstpage`, `citation_lastpage`, `citation_issn`, `citation_doi`, `citation_abstract_html_url`, `citation_pdf_url`, `citation_language`, `citation_keywords`
> - Dublin Core equivalents
> - `<link rel="canonical">`
>
> The correct markup already exists in `jcdms-site.zip` — copy it verbatim rather than reconstructing it from memory.
>
> Put it in **one** component/partial taking an Article, so it can never drift from what the Crossref deposit sends. Same accessors, same source.
>
> `citation_pdf_url` must **exactly** match the real PDF route and `citation_abstract_html_url` the canonical URL. Mismatches between these are the most common reason Google Scholar silently refuses to index a journal — it looks fine to a human and fails invisibly.
>
> Fix under SSR: `Reveal.tsx`, `Counter.tsx` and the parallax hero all touch `window`/`IntersectionObserver`. Guard them so the server render doesn't crash and produces sensible static output. `<MotionConfig reducedMotion="user">` must survive.
>
> Wire `ArticleDetail`'s existing **Cite** button to `CitationFormatter` (Harvard / BibTeX / RIS) and the **Download PDF** button to the real route.
>
> **Feature tests are the point of this prompt:** assert that a plain HTTP GET of a published article URL — with no JavaScript — returns HTML containing every citation meta tag with correct values. And that a draft 404s for guests.

**Acceptance:** `curl https://…/articles/{slug} | grep citation_` returns the full tag set. **If it doesn't, stop — nothing downstream is worth building.**

---

## Phase 3 — Editorial management

### Prompt 7 · Journal, issue and article admin

> Build the authenticated management side: Inertia pages + controllers + form requests.
>
> - **Journals** — CRUD, settings (masthead, ISSN, DOI prefix + suffix pattern, Crossref credentials, licence, sections). The Crossref password is **write-only in the API** and never returned in a response.
> - **Sections** — per-journal article types, with the `doi_eligible` flag (front matter gets no DOI; editorials do).
> - **Volumes / Issues** — only for `issue_based` journals; hide the UI entirely for continuous ones.
> - **Articles** — full CRUD, with nested authors and references saved in a **single transaction**, not N requests. Reorder endpoint. PDF upload → private disk, path in DB, served through the stable route from Prompt 6.
> - **Metrics** — a scheduled job computing `acceptance_rate`, `median_days_to_decision`, `article_count`, `editor_count` from real data. `impact_factor` and `cite_score` are entered by hand (they come from JCR/Scopus) — mark them clearly as external in the UI.
>
> Reuse the Meridian design system: `.btn-primary`, `.card`, `.input`, `.eyebrow` from `index.css`, Lucide icons, the documented z-index scale. The admin should look like the same product as the public site, because it is.

**Acceptance:** every management screen works; the Crossref password never appears in a JSON response.

---

### Prompt 8 · The publish gate

> `PublishArticleAction` and `PublishIssueAction`, guarded by `journal.publish`. This is the highest-risk operation in the system.
>
> **Pre-flight validation** — refuse to publish and return **every** problem at once (not the first): missing title, abstract, PDF, page range (issue-based only), or — for non-corporate articles — at least one author; duplicate sequence; overlapping page ranges; journal has no `doi_prefix`.
>
> Then, in a **DB transaction**: set status to published, stamp `published_at`, and **freeze** slug, sequence and `doi_suffix`.
>
> Then **dispatch** the Crossref deposit to the queue — outside the transaction.
>
> Then invalidate the page cache and regenerate the sitemap.
>
> **The deposit is decoupled from publication.** If Crossref is unreachable, credentials have lapsed, or the XML is rejected, the pages still go live and stay live; the editor retries from the deposit log. The public site must never depend on Crossref being up. Do not wrap the deposit in the publish transaction.
>
> Tests: each pre-flight rule individually; publish is atomic; a simulated Crossref outage still yields a live public article plus a `failed` deposit that can be retried to success.

**Acceptance:** publishing during a Crossref outage produces live pages and a retryable failed deposit.

---

## Phase 4 — Crossref

### Prompt 9 · Deposit service

> Build the Crossref deposit service.
>
> **Fetch the current Crossref documentation and schema before writing the XML builder.** Do not rely on training data for the schema version, element order, or endpoint URLs — get `https://www.crossref.org/documentation/member-setup/direct-deposit-xml/` and the current deposit XSD, and validate against the real XSD in a test.
>
> 1. **`CrossrefXmlBuilder`** — takes an Issue (issue-based) or a set of Articles (continuous) and produces the deposit XML: `doi_batch` → `head` (batch id, timestamp, depositor, registrant) → `body` → `journal` → `journal_metadata` (full title, abbrev, electronic ISSN) → `journal_issue` (publication date, volume, issue — omit for continuous) → one `journal_article` per **DOI-eligible** article, each carrying titles, contributors (ORCID + affiliation, in sequence order, handling the corporate-author case), publication date, pages, `doi_data` (the DOI + the landing-page URL), and a `citation_list` from `article_references` when `crossref_deposit_references` is on.
>    Skip sections where `doi_eligible` is false.
> 2. **`CrossrefDepositor`** — posts to the deposit endpoint. **Support both sandbox and production, switched by config, defaulting to sandbox.** Store the batch id, the exact XML sent, and the raw response.
> 3. **`DepositToCrossref` queued job** — retries with backoff. Comment explicitly that Crossref is **idempotent on the DOI**, so a retry updates rather than duplicates, and that nobody should later add an "already deposited?" guard — it would block legitimate metadata corrections.
> 4. **Status polling.** Crossref processes deposits asynchronously; a 200 on the POST is **not** confirmation of registration. Poll the submission report and update each item to `registered` or `failed` with Crossref's actual message.
> 5. **Redeposit** — same code path, new batch id, after a metadata correction.
>
> Credentials from the `journals` table (encrypted), env fallback. **Never log the password**, and never let it into a serialised queue payload.

**Acceptance:** XML validates against the official XSD; a sandbox deposit round-trips; a forced failure records Crossref's real error message; redeposit after an edit updates the record.

---

### Prompt 10 · Deposit log UI

> Build the Registrations screen: deposit history per journal/issue, per-DOI status, Crossref's returned message on failure, and a **Retry** action. The prototype in `jcdms-journal-manager.html` has this UI already (Registrations tab) — port it to Inertia + the Meridian design system.
>
> Add `journal:check-dois` — an artisan command that resolves every registered DOI and reports any that 404. Schedule it. Link rot is the failure mode that destroys a journal's credibility, and it happens silently.

**Acceptance:** a failed deposit can be diagnosed from the UI alone and retried to success.

---

## Phase 5 — Submission & peer review *(only if D2 = in scope)*

### Prompt 11 · Submission pipeline

> Build submissions. The vocabulary is fixed by the frontend — **do not rename anything**:
> ```
> stages  Submitted → Editor check → Peer review → Decision → Production   (0–4)
> status  Draft | Submitted | Under Review | Revisions Requested | Accepted | Rejected
> ```
> Tables per §6 of the architecture doc: `submissions`, `submission_authors`, `submission_files`, `submission_events`.
>
> Back `Submit.tsx`'s 5-step wizard (`Journal → Manuscript → Authors → Declarations → Review`). Read the component — the `Form` type is the exact payload. Requirements:
> - **Drafts persist per step.** The wizard says "nothing goes to an editor until the final step" — honour that: a draft is saved server-side but invisible to editors until submitted.
> - Human-readable reference on submit: `MRDN-2026-0417`.
> - Manuscript upload versioned in `submission_files`; declarations (ethics, competing interests, data availability) recorded with a timestamp — they're a compliance record, not a checkbox.
> - Every transition writes to `submission_events`. **This audit trail is not optional** — editorial decisions get challenged, sometimes years later, and "who assigned that reviewer and when" must be answerable.

**Acceptance:** a draft survives a browser close and resumes at the right step; a submitted manuscript appears in the editor's queue with a reference.

---

### Prompt 12 · Peer review

> Build review rounds, assignments, reports and decisions: `review_rounds`, `review_assignments`, `reviews`, `editorial_decisions`, `reviewer_profiles`.
>
> Back `Dashboard.tsx` — read it for the exact shapes. Reviewer status (`Invited | Accepted | Report submitted | Declined`), recommendations (`Accept | Minor revision | Major revision | Reject`), due dates, and the days-to-first-decision chart (which must now compute from real data, not `DECISION_TIME`).
>
> - Invitation → accept/decline → report, each emailed, each logged.
> - Overdue reminders on a schedule.
> - **Anonymity is a hard requirement.** Under single-blind, a reviewer's identity must never reach an author through any endpoint, serialiser, or eager-load. `comments_to_editor` must never appear in an author-facing response. Write the tests that try to leak it and prove they can't — an accidental leak here is a research-integrity incident, not a bug.
> - An editorial decision closes the round. **On `Accepted`, a Submission becomes an Article** — build that conversion explicitly, carrying authors, abstract, keywords and files across, and leaving the Submission linked via `article_id`.

**Acceptance:** the leak tests pass; an accepted submission converts to a draft Article ready for the publish gate.

---

## Phase 6 — Discovery

### Prompt 13 · Indexing, preservation, hardening

> - **OAI-PMH endpoint** (Dublin Core) so harvesters and DOAJ can pull metadata.
> - **DOAJ export** — the application needs open access, peer review, an ISSN, and a licence. Generate the metadata export it asks for.
> - **Google Scholar validation** — verify a real published article against Scholar's inclusion requirements end to end. This is the payoff for Prompt 6; confirm it landed.
> - **Preservation.** Crossref does **not** store your content — it only resolves identifiers. If the LCC server dies, the DOIs point at nothing. Arrange deposit into a preservation archive (PKP PN, CLOCKSS, or at minimum an offsite, restorable backup of the PDF disk and the `articles` tables).
> - **Metrics** — real view/download counting into `article_metric_daily`, bot-filtered. COUNTER-compliant if you want the numbers to mean anything.
> - Rate-limit and cache the public routes (they change only on publish).
> - Confirm the Crossref password is never logged, serialised, or returned.

---

### Prompt 14 · Go-live runbook

> Produce the deployment runbook: env vars, the Inertia SSR process under supervisor, queue workers, storage disks, backup/restore of the PDF disk and `articles`/`doi_deposits` tables, scheduled jobs (`journal:check-dois`, metrics rollup, reviewer reminders).
>
> Then the switch from placeholders to real values: where the ISSN goes when the British Library assigns it, and the DOI prefix when Crossref issues it. **Verify that changing those two DB rows updates every DOI and every meta tag in the system with no code change.** If anything else needs touching, that's a bug — fix it before go-live, not after.
>
> Finally, state the permanence commitment in plain English for whoever signs off: **LCC is undertaking to keep these landing pages resolving indefinitely.** If the site is ever restructured, 301s are mandatory and the DOI resource URLs must be updated at Crossref by redeposit.

---

## Dependency graph

| # | Prompt | Needs |
|---|---|---|
| 0 | `CLAUDE.md` | — |
| 1 | Recon + SSR assessment | — |
| 2 | Migrations | 1 |
| 3 | Models, DOI, citations | 2 |
| 4 | Seed (real + demo) | 3 |
| 5 | Per-journal RBAC | 3 |
| 6 | **Inertia SSR + citation meta** | 4 |
| 7 | Journal/issue/article admin | 5, 6 |
| 8 | Publish gate | 7 |
| 9 | Crossref deposit | 3 |
| 10 | Deposit log + check-dois | 8, 9 |
| 11 | Submissions | 5 |
| 12 | Peer review | 11 |
| 13 | Discovery + preservation | 10 |
| 14 | Runbook | 13 |

**Prompt 6 is the keystone.** Everything about DOIs depends on it, and it's the one thing the current build gets wrong.
