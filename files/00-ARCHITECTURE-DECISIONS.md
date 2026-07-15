# 00 — Architecture, Findings & Decisions
### Meridian Open Science → Laravel 12 + MySQL

Written after reviewing the `work.tar` production build, the `postcss_config.tar` source, the design system at `design-system/meridian-open-science/MASTER.md`, and the live site at **https://jcdm.lcc.ac.uk/**.

This supersedes the earlier `jcdms-laravel-build-prompts.md`. That plan assumed a single journal (JCD&MS) with no peer review. The frontend you've built is a different, larger thing.

---

## 1. What you have actually built

**Meridian Open Science** — an open-access publishing platform *plus* a journal management system. The design system states the reference point explicitly: frontiersin.org, "its feature set and information architecture, not its branding."

| | |
|---|---|
| **Stack** | Vite 5 · React 18.3 · TypeScript 5.6 · **Tailwind 3.4** · react-router-dom 6.28 · framer-motion 11 · lucide-react |
| **Design** | Trust teal (`brand-700` `#0F766E`) + authority slate (`ink-900` `#0F172A`) + amber accent (`gold-600`) |
| **Type** | Newsreader (serif, headings) + Inter (sans, UI/body) — "News Editorial" pairing |
| **Style** | Editorial Grid / Magazine + Swiss rigour |
| **Routes** | `/` · `/journals` · `/articles` · `/articles/:slug` · `/submit` · `/dashboard` · `*` |
| **Content** | 6 journals, 6 articles, 6 news items, 3 research topics, 4 submissions — all fictional demo data (`data.ts` says so) |

It is a genuinely good frontend: WCAG-conscious, reduced-motion aware, a documented z-index scale, charts with table alternatives, a real design system with reasoned deviations. Nothing below is a criticism of the design work.

---

## 2. 🔴 Critical finding — the site is invisible to the crawlers that matter

**I fetched `https://jcdm.lcc.ac.uk/` and got back the title and meta description. Nothing else. No content at all.**

The cause is in `.htaccess` and `index.html`: this is a client-rendered SPA. The server has exactly one HTML file on disk, containing `<div id="root"></div>`. Everything else is assembled by JavaScript in the browser.

I confirmed there is **no SSR, no prerendering, no `react-helmet`, and no meta-tag management anywhere in the source**. `ArticleDetail.tsx` prints the DOI as visible text (line 68) but emits **zero** `citation_*` meta tags.

Why this is fatal for the DOI project specifically:

- **Google Scholar does not execute JavaScript.** It reads `citation_*` meta tags from the raw HTML response. It will index nothing.
- **Crossref, DOAJ, and OAI-PMH harvesters** don't run JS either.
- A DOI resolves to a landing page. If that landing page is empty to every machine that reads it, **the DOI does the one job it exists to do — and fails at it.**

You would be paying Crossref to register permanent identifiers that point at pages no index can read. This must be fixed before a single DOI is deposited.

### The fix — three options

| Option | Keeps your React work | Ops cost | Verdict |
|---|---|---|---|
| **A. Inertia v2 + SSR** | ✅ All 8 pages, motion system, design system | Node SSR process under supervisor | **Recommended** |
| **B. Blade for public pages, SPA for the app** | ⚠️ Public pages re-implemented in Blade (same Tailwind build, so identical look; loses Framer Motion on those pages) | None | Solid fallback |
| **C. Build-time prerender** | ✅ | Rebuild on every publish | Only viable at low publication volume |

**Recommendation: A.** Inertia lets Laravel own routing, auth and data while your existing `.tsx` components do the rendering; its SSR mode outputs fully-formed HTML, and `<Head>` injects the citation meta server-side. You keep everything you've built. The conversion is mechanical: react-router `<Route>` → Inertia pages, and props arrive from controllers instead of `data.ts`.

Take **B** if you can't run a Node process in production. The split is clean, because the SEO-critical surface and the interactive surface don't overlap: `/`, `/journals`, `/articles`, `/articles/:slug` are read-only and must be crawlable → Blade. `/submit` and `/dashboard` are behind login and need no SEO at all → keep the SPA exactly as it is.

**Whatever you choose, the acceptance test is the same:** `curl https://…/articles/{slug}` must return HTML containing the full `citation_*` meta set. If it doesn't, the DOI programme doesn't work.

---

## 3. 🟠 Scope finding — peer review is now in scope

My earlier plan explicitly excluded submission and peer review ("it happens over email"). **Your frontend has both**, fully designed:

- **`Submit.tsx`** — a 5-step wizard: `Journal → Manuscript → Authors → Declarations → Review`, with per-step validation, an authors repeater, file upload, and three declarations (ethics, competing interests, data availability).
- **`Dashboard.tsx`** — an editorial office: submissions and reviews tabs, an accordion of reviewer rows with recommendations, a days-to-first-decision chart, and an editor checklist.
- **`data.ts`** — a complete workflow vocabulary:

```ts
PIPELINE_STAGES  = Submitted → Editor check → Peer review → Decision → Production   // stage 0–4
SubmissionStatus = Draft | Submitted | Under Review | Revisions Requested | Accepted | Rejected
Reviewer.status  = Invited | Accepted | Report submitted | Declined
recommendation   = Accept | Minor revision | Major revision | Reject
```

This roughly doubles the backend. It's the right call if LCC wants review to run through software — but it must be a conscious decision, not something that arrives by accident because the UI exists.

---

## 4. 🟠 Model finding — multi-journal, and no volumes or issues

The frontend models **six journals** with per-journal metrics (impact factor, CiteScore, acceptance rate, median days to decision, article and editor counts) grouped by subject **field**.

More significantly: **there is no volume or issue concept anywhere in the frontend.** An article has a `date` and a `journal`, and nothing else. That's a **continuous publication** model — the Frontiers pattern, and a legitimate one.

But **JCD&MS publishes in volumes and issues** (Vol 10, No 2, Spring 2026, pp. 8–106). Those are real, printed, and paginated.

The data model below supports both, via a `publication_model` flag on the journal (`continuous` | `issue_based`). Articles have a nullable `issue_id`. Don't force one model on the other — you'll regret it either way.

---

## 5. 🔵 Decisions needed from you before Prompt 1

These are strategy, not engineering. I can't make them for you.

**D1 — What is Meridian, and what is JCD&MS?**
The domain is `jcdm.lcc.ac.uk`, so this is LCC's. But the frontend is branded "Meridian Open Science" as a multi-journal platform with fictional life-sciences journals. Three readings:
- (a) Meridian is the **platform**; JCD&MS is its first (perhaps only) journal → keep the multi-journal schema, seed one real journal, drop the demo content.
- (b) Meridian is a **prototype skin** for JCD&MS and the branding will become LCC's → single journal, but keep the schema multi-journal anyway; it costs nothing now and saves a migration later.
- (c) LCC genuinely intends to host **multiple journals** → build for it properly, including per-journal roles.

Everything else follows from this. My assumption below is **(a)/(b)** — build multi-journal, launch with one.

**D2 — Peer review: in or out for v1?**
In = the full pipeline (§3). Out = the publish-and-register system from the earlier plan, and `/submit` and `/dashboard` become phase 2. Halving v1 is a legitimate choice; shipping a submission wizard that emails a PDF to an editor is not.

**D3 — The brand.**
My earlier prototypes (`jcdms-journal-manager.html`, `jcdms-site.zip`) use LCC navy/gold with Fraunces. Meridian uses teal/slate with Newsreader/Inter. These are two different visual products. **Meridian wins** — it's the real, considered design system. My prototypes are now superseded as UI, but they remain the specification for the *domain logic*: DOI suffix generation, Crossref deposit, the publish gate, citation formats, and the landing-page meta tag set all carry over unchanged.

**D4 — DOI suffix pattern, and URL permanence.**
Your demo data uses `10.48219/mrdn.2026.00412` — a year-based running sequence that doesn't encode volume or issue. My plan used `10.xxxx/jcdms.v10i2.001`. Both are valid; pick one per journal and store the pattern on the journal row.

⚠️ But note the conflict this creates with your URLs. Your article route is `/articles/:slug` — **a title slug**. A DOI is a permanent promise; a slug derived from a title is not, because titles get corrected. **If you keep slug URLs, the slug must be frozen at publication and never regenerated, even if the title is edited.** Enforce it in the model. (My earlier plan sidestepped this by putting the sequence in the URL. Slugs are friendlier; they're just more dangerous, and the danger is entirely manageable if you freeze them.)

---

## 6. The reconciled data model

Combining your frontend's shapes with what DOI/Crossref compliance requires.

### Platform & journals
```
fields                  id, name, slug                          -- Health & Medicine, Neuroscience, …
journals                id, slug, title, field_id, description, cover_path,
                        issn_online, issn_print, doi_prefix, doi_suffix_pattern,
                        publication_model (continuous|issue_based),
                        open_access, publisher, principal_editor, contact_email,
                        aims_and_scope, license, license_holder,
                        crossref_username, crossref_password (encrypted),
                        crossref_deposit_references
journal_metrics         journal_id, impact_factor, cite_score, acceptance_rate,
                        median_days_to_decision, article_count, editor_count, computed_at
journal_sections        journal_id, name, sequence, is_active, doi_eligible
                        -- Original Research, Review, Methods, Perspective (per journal)
volumes                 journal_id, number, year                -- issue_based journals only
issues                  volume_id, number, season, publication_date, status, cover_path
```
`impact_factor` and `cite_score` come from **external** sources (JCR, Scopus) — they're entered, not computed. `acceptance_rate`, `median_days_to_decision`, `article_count` and `editor_count` **are** computable from your own data; compute them on a schedule rather than letting an editor type a flattering number.

### Content
```
articles                journal_id, issue_id (NULLABLE — continuous publication),
                        journal_section_id, slug (FROZEN on publish), sequence (nullable),
                        title, abstract, keywords (json), first_page, last_page (nullable),
                        corporate_author (nullable), doi_suffix (UNIQUE), status,
                        published_at, views_count, citations_count
article_authors         article_id, given_name, family_name, affiliation, orcid,
                        email, is_corresponding, sequence
article_references      article_id, ordinal, raw_text, doi          -- deposited for Cited-by
article_files           article_id, type (pdf|xml|supplementary|dataset), path, label
article_metric_daily    article_id, date, views, downloads          -- powers "Views & downloads"
```

### Editorial workflow (new)
```
submissions             reference (MRDN-2026-0417), journal_id, journal_section_id,
                        corresponding_author_id, title, abstract, keywords,
                        status, stage (0–4), funding, ethics_declared,
                        conflicts_declared, data_available,
                        submitted_at, article_id (nullable — set on acceptance)
submission_authors      submission_id, name, email, affiliation, orcid, is_corresponding, sequence
submission_files        submission_id, version, type (manuscript|cover_letter|figure|
                        supplementary|revision), path, uploaded_by
review_rounds           submission_id, round_number, opened_at, closed_at
review_assignments      review_round_id, reviewer_id, status (invited|accepted|declined|
                        report_submitted), invited_at, due_at, responded_at, completed_at
reviews                 review_assignment_id, recommendation, comments_to_author,
                        comments_to_editor, submitted_at
editorial_decisions     submission_id, review_round_id, editor_id, decision, body, decided_at
submission_events       submission_id, user_id, event, payload, created_at   -- audit trail
reviewer_profiles       user_id, affiliation, orcid, expertise (json), available
```
**On acceptance, a Submission becomes an Article.** That single join is the seam between the two halves of the system.

### DOI registration
```
doi_deposits            issue_id (nullable), journal_id, batch_id (uuid), payload_path,
                        status, crossref_submission_id, response_body,
                        submitted_by, submitted_at, completed_at
doi_deposit_items       doi_deposit_id, article_id, doi, status, message
```
For continuous-publication journals, deposits are per-article rather than per-issue — hence the nullable `issue_id`.

### Editorial content
```
news_items              slug, title, category, excerpt, body, photo_path, published_at
research_topics         slug, title, description, photo_path, deadline, journal_id (nullable)
research_topic_editors  research_topic_id, user_id
```

### Roles — must be **per journal**
A person can edit Journal A and review for Journal B. A single global `journal-editor` role can't express that. Use Spatie's **teams** feature (team = journal), or a `journal_user` pivot carrying the role.

```
site-admin  ·  publisher-admin  ·  journal-editor  ·  section-editor
production  ·  reviewer  ·  author
```
`journal.publish` remains the high-privilege gate: it makes URLs permanent and spends money at Crossref.

---

## 7. What carries over unchanged from the earlier plan

Not wasted work — this is still the spec, and the prototypes remain the reference implementation:

- The **DOI suffix generator** lives in exactly one place; every view, export and XML deposit reads from `Article::doi()`.
- **Immutability after publish** — sequence, slug and DOI suffix are frozen. A changed suffix is a dead DOI.
- **Crossref deposits are idempotent on the DOI** — a retry updates rather than duplicates. Never add an "already deposited?" guard.
- **Deposit is decoupled from publish** — if Crossref is down, the pages still go live and the deposit retries later. Never wrap the deposit in the publish transaction.
- The full **Highwire + Dublin Core meta tag set**, and the rule that `citation_pdf_url` and `citation_abstract_html_url` must exactly match the real routes.
- **Harvard / BibTeX / RIS** citation formats, including the corporate-author case.
- **`journal:check-dois`** — scheduled link-rot detection.

---

## 8. Build order

```
Phase 0   Decisions D1–D4 · ISSN application · Crossref membership
Phase 1   Foundation — schema, models, DOI logic, RBAC, seed
Phase 2   Public reading surface — SSR/Inertia, article landing pages, citation meta   ← unblocks DOIs
Phase 3   Editorial management — journals, issues, articles, publish gate
Phase 4   Crossref deposit — XML, API, deposit log, retry
Phase 5   Submission & peer review — wizard, pipeline, reviewers, decisions    (only if D2 = in)
Phase 6   Discovery — DOAJ, Scholar validation, OAI-PMH, metrics, preservation
```

**Phase 2 before Phase 4.** There is no point registering a DOI until the page it points at is one a crawler can read.
