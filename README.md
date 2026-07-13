# Meridian Open Science

An open-access publishing site **and** journal management system, modelled on the feature set of
frontiersin.org. Built with React, Vite, Tailwind and Framer Motion.

> Meridian is a fictional publisher. The branding, journals, articles, authors and metrics are all
> invented — nothing here impersonates Frontiers or any real organisation.

## Run it

```bash
npm install
npm run dev      # http://localhost:5173
npm run build    # typecheck + production build to dist/
npm run preview  # serve the production build
```

## What's in it

**Public site**
- `/` — hero with parallax + search, animated stat counters, author path cards, featured journals,
  the 4-step publishing pipeline, Research Topics, impact band, latest research, newsroom, newsletter
- `/journals` — filter by field, sort by impact factor / volume / decision speed
- `/articles` — live search across titles, abstracts, keywords and authors, with journal and type
  filters kept in the URL so results stay shareable
- `/articles/:slug` — article page with reading-progress bar, drop cap, pull quote, metrics rail

**Journal management system**
- `/submit` — five-step submission wizard (journal → manuscript → authors → declarations → review)
  with per-step validation, a file drop zone, and a submission confirmation with manuscript ID
- `/dashboard` — editorial office: KPI tiles, overdue-review alert, expandable submissions with a
  five-stage peer-review pipeline, reviewer status and recommendations, a "reviews I owe" queue,
  and a decision-time trend chart

## Structure

```
src/
  lib/
    motion.ts     # Framer Motion primitives — durations, eases, variants
    images.ts     # Verified free-image URLs + helpers
    data.ts       # Fictional journals, articles, submissions, reviewers
  components/     # Navbar, Footer, Cards, Reveal, Counter, ImageWithFallback
  pages/          # Home, Journals, Articles, ArticleDetail, Submit, Dashboard, NotFound
design-system/    # The design system this was built against, and why it deviates
                  # from the ui-ux-pro-max generator output
```

## Images

All free, no attribution required, and every URL was checked to return HTTP 200 before use:
Unsplash for photography, randomuser.me for reviewer avatars, picsum.photos as an automatic
fallback (`ImageWithFallback` swaps to it on error, so a dead CDN never leaves a broken image).

## Accessibility and motion

- 4.5:1 contrast floor on all text, including placeholders and footer legal type
- Visible `:focus-visible` rings, 44px minimum touch targets, labels on every input
- Errors sit next to their field and carry an icon, never colour alone
- The chart has a table alternative
- `prefers-reduced-motion` is honoured three ways: a global CSS block, Framer's
  `<MotionConfig reducedMotion="user">`, and `useReducedMotion()` in the parallax and counters
