# Meridian Open Science

Open-access publishing platform + journal management system for London Churchill College.
Laravel 12 · PHP 8.2 · MySQL · Inertia v2 + React 18 + TS + Tailwind 3.4 · Spatie permissions.

Launch journal: **JCD&MS** — Journal of Contemporary Development & Management Studies.

## Non-negotiables

1. **PUBLIC PAGES ARE SERVER-RENDERED.** Google Scholar, Crossref, DOAJ and OAI-PMH
   harvesters do not execute JavaScript. An article landing page that renders
   client-side is invisible to them, and every DOI pointing at it is wasted money.
   Acceptance test for any public page: `curl` it and see the content and the
   `citation_*` meta tags in the raw HTML.

2. **CITATION META TAGS ARE RENDERED BY BLADE, NOT REACT.** This is deliberate and
   load-bearing. Inertia SSR depends on a Node process; when that process dies,
   Inertia *silently* falls back to client-side rendering — humans see a working
   site, crawlers see nothing, and nobody notices for months. So the `citation_*`
   and Dublin Core tags are emitted by PHP from controller data in
   `resources/views/app.blade.php`. They are present whether or not Node is alive.
   Do not move them into an Inertia `<Head>`.

3. **URLS AND DOIS ARE PERMANENT.** On publish, an article's `slug`, `sequence` and
   `doi_suffix` are FROZEN. Editing the title later must NOT regenerate the slug.
   Enforced in a model observer AND a policy. A changed suffix is a dead DOI, and
   there is no undo.

4. **CROSSREF DEPOSITS ARE IDEMPOTENT ON THE DOI.** Redepositing UPDATES the record.
   Retry is always safe. Never add an "already deposited?" guard — it would block
   legitimate metadata corrections.

5. **DEPOSIT IS DECOUPLED FROM PUBLISH.** If Crossref is unreachable, pages still go
   live; the deposit retries later from the admin. Never wrap the Crossref call in
   the publish transaction. The public site must never depend on Crossref being up.

6. **TWO PUBLICATION MODELS.** `journals.publication_model` is `continuous` or
   `issue_based`. `articles.issue_id` is NULLABLE. JCD&MS is issue_based (Vol 10,
   No 2, pp. 8–106). Don't force one model on the other.

7. **ROLES ARE PER-JOURNAL.** Someone edits Journal A and reviews for Journal B.
   Spatie teams, team = journal. A global role cannot express this.

8. **utf8mb4 EVERYWHERE.** The dev MySQL server's default charset is `latin1`.
   Author names carry diacritics (Papé, Ramírez, Sørensen) and titles carry
   en-dashes and curly quotes. A latin1 column corrupts real data silently.

## Environment

- Dev: WAMP, PHP 8.2.27, **MySQL 5.7.31** (no CTEs, no window functions — keep SQL portable)
- Prod: **cPanel account** on a dedicated server. See `docs/DEPLOYMENT.md` for how the
  Node SSR process is kept alive there; it is the one genuinely fragile moving part.

## Placeholders (deliberate — not bugs)

`issn_online`, `issn_print` and `doi_prefix` are **NULL** until the British Library and
Crossref issue them. They live on the `journals` row. Nothing else in the codebase may
hardcode them — if changing one DB row does not update every DOI in the app, that's a bug.

The prototype used `0000-0000` and `10.xxxx` as visible placeholders. We store NULL
instead, so that an un-issued prefix is *impossible to deposit* rather than depositing
garbage.

## Vocabulary (from the frontend — do not rename)

```
Pipeline stages : Submitted → Editor check → Peer review → Decision → Production  (0-4)
Submission      : Draft | Submitted | Under Review | Revisions Requested | Accepted | Rejected
Reviewer        : Invited | Accepted | Report submitted | Declined
Recommendation  : Accept | Minor revision | Major revision | Reject
```

## Design system

`design-system/meridian-open-science/MASTER.md` is authoritative. Trust teal (`brand-700`)
+ authority navy (`ink-900`) + gold accent. Newsreader (serif headings) + Inter (sans UI).
Lucide icons only. The admin is the same product as the public site and must look it.

Status is never carried by colour alone — deposit failures need an icon and a label.
Use the `success`/`danger` tokens, not stock Tailwind `emerald`/`red`.
