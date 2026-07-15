# 02 — Frontend Integration Prompts
### Meridian React/TS → Laravel 12 + Inertia

The frontend is good and stays. This is about connecting it to a real backend and — critically — making the public pages render on the server.

**What changes:** routing (react-router → Inertia), data (`data.ts` → props from controllers), rendering (client-only → SSR).
**What doesn't:** the design system, the components, the motion primitives, Tailwind, the visual language. Don't redesign anything.

---

## The one thing to understand before starting

`src/lib/data.ts` is fictional demo content and says so at the top. Every page currently imports from it. The migration is, in essence:

```ts
// before
import { ARTICLES } from '../lib/data'
export default function Articles() {
  const results = ARTICLES.filter(…)

// after
export default function Articles({ articles, filters }: Props) {
  // filtering happens server-side; results arrive as props
```

Do this page by page, not all at once, and keep `data.ts` around until the last page is converted — it's a useful reference for the exact shapes.

---

## Prompt F1 · Inertia migration + SSR

> Convert the Meridian frontend from a react-router SPA to **Inertia v2 with server-side rendering**, keeping every component and the entire design system intact.
>
> **Why SSR is mandatory, not a nice-to-have:** I fetched the live site at `https://jcdm.lcc.ac.uk/` and the response contained only the `<title>` and meta description — no content whatsoever, because it's `<div id="root"></div>` plus a JS bundle. Google Scholar, Crossref, DOAJ and OAI-PMH harvesters do not execute JavaScript. Our article pages are DOI landing pages: if they're empty to those crawlers, every DOI we register points at nothing readable and the entire DOI programme is wasted money. This prompt exists to fix that.
>
> Steps:
> 1. Install Inertia (Laravel adapter + React adapter) and configure **SSR** (`inertia:start-ssr`, the SSR entry point, and the supervisor config for production).
> 2. Replace `<Routes>`/`<Route>` in `App.tsx` with Inertia pages under `resources/js/Pages/`. Keep the page components themselves unchanged apart from their data source.
> 3. **Page transitions.** `App.tsx` currently wraps routes in `AnimatePresence` keyed on `useLocation().pathname`. Reimplement this against Inertia's router events so the transitions survive. If a faithful reimplementation isn't possible, say so plainly and propose the closest alternative — don't silently drop the animation.
> 4. **Make these SSR-safe** — they touch browser-only APIs and will crash or misbehave in a Node render:
>    - `Reveal.tsx` — `IntersectionObserver`
>    - `Counter.tsx` — animated counters; must render their **final value** in the server HTML, not `0`, or crawlers and no-JS users see zeroes
>    - the parallax hero in `Home.tsx` — scroll listeners
>    - `ImageWithFallback.tsx` — the `onError` swap
>    Guard each so the server render produces correct, static output and the client hydrates without mismatch warnings.
> 5. `<MotionConfig reducedMotion="user">` must survive the move.
> 6. Build Tailwind (3.4, with the `brand`/`ink`/`gold` palette and Newsreader/Inter) through Laravel's Vite pipeline. Do not upgrade Tailwind as part of this task, and do not touch `tailwind.config.js` beyond the content paths.
>
> **Acceptance:** `curl` the homepage and an article page. Both must return fully-rendered HTML — headings, body text, real counter values — with no JavaScript executed. Zero hydration mismatch warnings in the console.

---

## Prompt F2 · Public pages → real data

> Wire the four public pages to Inertia props from the backend. Delete their `data.ts` imports as you go.
>
> **`Home.tsx`** — stats band, featured journals, research topics, latest research, newsroom. The `STATS` counters must come from real aggregates (or be honestly labelled if they're aspirational — a fabricated "3.9M researchers" on a live LCC site is a credibility problem, not a placeholder).
>
> **`Journals.tsx`** — journals with metrics. Mark `impactFactor` and `citeScore` clearly as **externally sourced** (JCR/Scopus). `acceptanceRate` and `medianDaysToDecision` are computed from our own data.
>
> **`Articles.tsx`** — move filtering server-side. The component currently does `useMemo` over the full array with query + journal + type filters; that doesn't scale past a few dozen articles. Replace with a paginated backend endpoint (FULLTEXT on title/abstract), keeping the URL query params (`?q=`, `?journal=`) so links stay shareable. **Preserve the deliberate "searching" state** — the comment in the source explains it exists so the result count never changes silently under the user. That's good UX; don't lose it to a refactor.
>
> **`ArticleDetail.tsx`** — the DOI landing page, and the most important page in the system. It must:
> - render server-side with the **full `citation_*` + Dublin Core meta set** (see backend Prompt 6 — the correct markup is in `jcdms-site.zip`)
> - make the **Cite** button work: Harvard, BibTeX, RIS from the backend `CitationFormatter`
> - make **Download PDF** hit the real, stable PDF route
> - show authors with affiliations and **ORCID links** where present
> - show real view/download metrics
> - display the DOI as a resolvable `https://doi.org/…` link, not plain text as it is now
>
> Keep the drop caps and pull quotes — they're in the design system as the article-page signature.

---

## Prompt F3 · Auth, dashboard, submission wizard

> **Auth.** Add login via the hub's existing Google SSO (Socialite). `/dashboard` and `/submit` are behind it. Roles are **per journal** — the same user may be an editor on one journal and a reviewer on another, so the dashboard must render from the user's roles *in the current journal context*, not a global role.
>
> **`Dashboard.tsx`** — currently reads `SUBMISSIONS` and `DECISION_TIME` from `data.ts`. Wire to real endpoints:
> - submissions and reviews tabs, scoped to what this user may actually see
> - the reviewer accordion, with real assignment statuses and recommendations
> - the days-to-first-decision chart, computed from real decisions (keep the table alternative — it's in the design system's pre-delivery checklist, and it's an accessibility requirement, not a nicety)
> - the editor checklist, driven by real submission state
>
> **`Submit.tsx`** — the 5-step wizard (`Journal → Manuscript → Authors → Declarations → Review`). Back it properly:
> - **persist the draft server-side on every step transition.** The UI promises "nothing goes to an editor until the final step" — keep that promise, but a lost draft after four steps of typing is the single most infuriating thing an academic submission system can do to a person. Save early, save often, resume where they left off.
> - real file upload with progress, size and MIME validation, and a clear error if it fails
> - keep the per-step validation exactly as it is — the source comment ("so users aren't shouted at about fields they haven't reached") is the right instinct
> - on submit, show the real reference (`MRDN-2026-0417`) and route to the dashboard
>
> **Anonymity.** If review is single-blind, a reviewer's identity must never reach an author — not through a prop, not through an eager-loaded relation, not through an error message. Check every serialiser feeding the author-facing dashboard.

---

## Prompt F4 · Extend the design system to the admin

> The management screens (journals, issues, articles, publish, deposit log, settings, users) don't exist in the frontend yet. Build them **inside the Meridian design system** — the admin and the public site are the same product and must look it.
>
> Use what's already there: `.btn-primary` / `.btn-secondary` / `.btn-ghost`, `.card`, `.input`, `.eyebrow`, `.container-page`, `shadow-card` / `shadow-lift`, the `dropdown/sticky/overlay/modal` z-scale, Lucide icons, Newsreader for headings and Inter for UI. Add tokens only if something genuinely isn't expressible with what exists — and say so if you do.
>
> The screens and their behaviour are already prototyped in `jcdms-journal-manager.html`. **Port the logic and the information architecture; discard its visual language** (that prototype used LCC navy/gold and Fraunces — superseded by Meridian). Screens:
> - Dashboard, Issues (volume-grouped archive), Issue detail with article reordering
> - Article editor: metadata, authors repeater with ORCID, keywords, PDF upload, references repeater
> - **The publish gate** — pre-flight errors shown as a complete list (never one at a time), and an unmistakable confirmation that this makes URLs permanent
> - Registrations — the Crossref deposit log with per-DOI status and Retry
> - Settings — masthead, sections, DOI/Crossref, licensing
> - Users & roles — per-journal
>
> Respect the design system's anti-patterns list: no scale-on-hover in grids, no colour as the sole carrier of status (deposit failures need an icon and a label, not just red), no contrast below 4.5:1, visible focus states throughout.

---

## Prompt F5 · Frontend hardening

> - **Types.** Generate TypeScript interfaces from the backend resources so the frontend can't drift from the API. No `any`. `tsc --noEmit` is already the `lint` script — keep it green.
> - **Loading and error states.** The prototype has none, because it had no network. Every fetch needs a skeleton and a real error state. The design system permits spinners and skeletons only — no decorative animation.
> - **Accessibility regression check** against the pre-delivery checklist in `MASTER.md`: 4.5:1 contrast including placeholders, visible focus rings, `prefers-reduced-motion`, charts with table alternatives, no content behind the sticky navbar, no horizontal scroll at 375px.
> - **Bundle.** The current build is a 392KB single JS chunk. Under Inertia, code-split per page; the public reading pages should not ship the dashboard and submission wizard to anonymous readers.
> - **Images.** `images.ts` points at Unsplash and randomuser.me. Fine for a demo; **not** fine for a live LCC journal — replace with real assets on LCC storage before launch. `ImageWithFallback` stays.

---

## Sequence

| # | Prompt | Needs |
|---|---|---|
| F1 | Inertia + SSR | Backend 1–5 |
| F2 | Public pages → real data | F1, Backend 6 |
| F3 | Auth, dashboard, wizard | F2, Backend 11–12 |
| F4 | Admin screens | F2, Backend 7–10 |
| F5 | Hardening | F4 |

**F1 is the keystone.** Until `curl` on an article page returns real HTML with citation meta tags, the DOI work has nothing to point at.
