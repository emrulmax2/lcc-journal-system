# OJS parity — status & remaining work

Tracks how Meridian compares to Open Journal Systems (OJS 3.x), what has been built, and what
remains. Companion to the roadmap the work was planned against.

## Delivered

| Phase | Feature | Notes |
|---|---|---|
| 1 | **Editor workflow cockpit** | Submission queue, editor detail screen, private manuscript download, reviewer-assignment UI, decision UI, audit timeline. Reuses the existing audited Actions. `tests/Feature/EditorCockpitTest.php`. |
| 1 | **Per-stage discussion threads** | Internal, append-only editorial forum on each manuscript; editors-only participants (keeps single-blind intact). |
| 2 | **Editorial email** | Queued, after-commit: submission receipt, reviewer invitation, reviewer reminder (scheduled `reviews:remind`), decline-notice to editors, decision letter. `tests/Feature/EditorialNotificationsTest.php`. |
| 3 | **HTML full text** | Crawlable, server-rendered `/articles/{slug}.html` + `citation_fulltext_html_url`; XSS-safe (MarkdownRenderer). `tests/Feature/FullTextHtmlTest.php`. |
| 4 | **i18n foundation** | Locale resolution middleware, `lang/` files (en/es/fr), Inertia-shared translations, `t()` helper, `<LocaleSwitcher>`, per-user preference, `<html lang>`. `tests/Feature/LocalizationTest.php`. |
| 5 | **Revision loop (G2)** | Authors upload a revised manuscript after a revise-and-resubmit decision; versioned, editors notified. |
| 5 | **Reviewer withdraw (G3)** | Editors withdraw an outstanding invitation to bring in a replacement; audited. `tests/Feature/EditorialLifecycleTest.php`. |
| 6 | **Atom feeds + sitemap** | `/feed`, `/journals/{slug}/feed`, feed autodiscovery `<link>`, journal landing pages in the sitemap. `tests/Feature/FeedTest.php`. |

## Remaining — and why each is not a code-only task

These need an external account, credential, service membership, or a spec large enough to be
its own project. Each is scaffolded conceptually below rather than half-built, because a fake
integration (especially payments) is worse than none.

### DataCite deposit
Mirror the existing Crossref pipeline (`app/Services/Crossref/*`, `Jobs/DepositToCrossref`):
a `DataCiteXmlBuilder` (schema 4.x) + `DataCiteDepositor` + queued job + `config/datacite.php`.
**Needs:** a DataCite member account and repository credentials. Wire the same
decoupled-from-publish, idempotent-on-DOI design the Crossref path already proves.

### ROR affiliation IDs
Add `ror_id` to `article_authors`/`submission_authors`, surface on the author forms, and emit
it inside the Crossref (and DataCite) `<affiliations>` and the JATS. Purely internal, but
touches the frozen-on-publish Crossref XML — treat as a careful, well-tested change.

### Plagiarism / similarity (iThenticate / Crossref Similarity Check)
A review-stage hook that submits the manuscript file and stores the returned similarity score
on the submission, shown on the cockpit detail. **Needs:** an iThenticate/Turnitin API account.

### APC / payments
Author-fee records + a gateway (Stripe/PayPal) + webhook reconciliation. **Needs:** a merchant
account and PCI-aware handling. **Do not** build a superficial version — money paths must be
real or absent. An APC content page already exists; this is the transactional layer.

### COUNTER R5 statistics
The current `TrackArticleView` is a bot-filtered floor, explicitly not COUNTER. R5 compliance
is a sizeable spec (report types, double-click filtering, robots list, SUSHI). Its own project.

### Preservation (PKP PN / LOCKSS / CLOCKSS)
A LOCKSS manifest page + membership registration. **Needs:** network membership and the
harvest agreement; mostly ops, not code.

### Native XML import/export & QuickSubmit
DOAJ export already exists. A full OJS-native XML round-trip and back-issue QuickSubmit importer
are large; scope them separately.

### Structured review forms & full copyediting file-exchange (Phase 5 depth)
Reviews today carry a recommendation + free-text comments (author-facing and confidential).
Configurable per-journal review questionnaires, and a copyeditor↔author↔layout file-exchange
sub-workflow for stages 3–4, are each substantial features beyond the revision loop already
shipped.
