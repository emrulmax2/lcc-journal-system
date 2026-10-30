import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { ArrowDownUp, Search, SlidersHorizontal, X } from 'lucide-react'
import { ArticleCard } from '@/components/Cards'
import Pagination from '@/components/Pagination'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatNumber } from '@/lib/format'
import { toArticleCard } from '@/lib/props'
import type { Article, Meta, Paginated } from '@/lib/props'

type Sort = 'newest' | 'oldest' | 'title'

type Filters = {
  q: string
  /** Journal slug, or 'all'. Only offered when there is more than one journal. */
  journal: string
  volume: number | null
  /** Issue NUMBER within `volume`; null when no issue is chosen. */
  issue: number | null
  /** Article type (section name), or 'all'. */
  type: string
  sort: Sort
}

type IssueOption = {
  volume: number
  number: number
  season: string | null
  year: number | null
  /** "Volume 10, Number 2 · Spring 2026" */
  label: string
  /** Matches in this issue, with the OTHER filters applied. */
  count: number
}

type Props = {
  /** Already filtered, sorted and paginated by the server. */
  articles: Paginated<Article>
  journals: { slug: string; title: string }[]
  volumes: { number: number; years: string; count: number }[]
  issues: IssueOption[]
  types: { name: string; count: number }[]
  filters: Filters
  meta: Meta
}

const SORTS: { id: Sort; label: string }[] = [
  { id: 'newest', label: 'Newest first' },
  { id: 'oldest', label: 'Oldest first' },
  { id: 'title', label: 'Title A–Z' },
]

/** Type long enough to have finished the word, not long enough to notice. */
const SEARCH_DEBOUNCE = 300

/**
 * The result count must never change silently under the reader.
 *
 * The prototype held a skeleton for a fixed 250ms on every keystroke, for exactly this
 * reason. Now that the query goes to the server, the real request time replaces the fake
 * one — but a fast response (an empty table, a warm query cache) would otherwise swap the
 * results in so quickly that the count appears to change on its own. So the searching
 * state has a FLOOR, not a fixed duration: as long as the request, and never shorter than
 * this.
 */
const MIN_SEARCHING_MS = 250

const SELECT_CLASS =
  'mt-2 h-11 w-full cursor-pointer rounded-full border border-ink-300 bg-white px-4 pr-9 text-sm text-ink-900 ' +
  'transition-colors duration-200 hover:border-ink-900 focus:border-brand-600'

/**
 * The archive search.
 *
 * Filters: free text, VOLUME, then ISSUE ("Volume 10, Number 2 · Spring 2026"), article
 * type and sort. Every one is applied by the server and lives in the URL, so a filtered view
 * can be shared or bookmarked. Each option shows how many articles it would leave, counted
 * with the other filters applied, so nobody clicks through to an empty page.
 */
export default function Articles({ articles, journals, volumes, issues, types, filters, meta }: Props) {
  const [query, setQuery] = useState(filters.q)
  const [isSearching, setIsSearching] = useState(false)
  /** The filter panel is a disclosure on small screens; always open on large ones. */
  const [panelOpen, setPanelOpen] = useState(false)

  const debounceTimer = useRef<number | null>(null)
  const floorTimer = useRef<number | null>(null)
  /** The last `q` WE sent. Distinguishes our own echo from a real navigation. */
  const sentQuery = useRef(filters.q)

  /**
   * Adopt the server's `q` on back/forward — but not when it is merely the echo of what
   * we just typed. Without that guard, the reply to "cor" would overwrite "coral" in the
   * box while the reader is still typing it.
   */
  useEffect(() => {
    if (filters.q !== sentQuery.current) {
      sentQuery.current = filters.q
      setQuery(filters.q)
    }
  }, [filters.q])

  useEffect(
    () => () => {
      if (debounceTimer.current !== null) window.clearTimeout(debounceTimer.current)
      if (floorTimer.current !== null) window.clearTimeout(floorTimer.current)
    },
    [],
  )

  /** Every filter round-trips through the URL. */
  const visit = (next: Partial<Filters>) => {
    const merged: Filters = { ...filters, q: query, ...next }
    if (merged.volume === null) merged.issue = null

    sentQuery.current = merged.q

    const params: Record<string, string> = {}
    if (merged.q.trim() !== '') params.q = merged.q.trim()
    if (merged.journal !== 'all') params.journal = merged.journal
    if (merged.volume !== null) params.volume = String(merged.volume)
    if (merged.volume !== null && merged.issue !== null) params.issue = String(merged.issue)
    if (merged.type !== 'all') params.type = merged.type
    if (merged.sort !== 'newest') params.sort = merged.sort

    const startedAt = Date.now()
    setIsSearching(true)

    router.get('/articles', params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => {
        const remaining = Math.max(0, MIN_SEARCHING_MS - (Date.now() - startedAt))
        if (floorTimer.current !== null) window.clearTimeout(floorTimer.current)
        floorTimer.current = window.setTimeout(() => setIsSearching(false), remaining)
      },
    })
  }

  const onQueryChange = (value: string) => {
    setQuery(value)
    if (debounceTimer.current !== null) window.clearTimeout(debounceTimer.current)
    debounceTimer.current = window.setTimeout(() => visit({ q: value }), SEARCH_DEBOUNCE)
  }

  const applyNow = (next: Partial<Filters>) => {
    if (debounceTimer.current !== null) window.clearTimeout(debounceTimer.current)
    if (next.q !== undefined) setQuery(next.q)
    visit(next)
  }

  const clearAll = () => applyNow({ q: '', journal: 'all', volume: null, issue: null, type: 'all' })

  const total = articles.meta.total
  const results = articles.data

  // The issue list follows the chosen volume; with no volume chosen it lists every issue.
  const issueChoices = filters.volume === null ? issues : issues.filter((i) => i.volume === filters.volume)
  const selectedIssue =
    filters.volume !== null && filters.issue !== null
      ? issues.find((i) => i.volume === filters.volume && i.number === filters.issue) ?? null
      : null

  // The active filters, each removable on its own.
  const chips: { key: string; label: string; clear: Partial<Filters> }[] = []
  if (filters.q) chips.push({ key: 'q', label: `“${filters.q}”`, clear: { q: '' } })
  if (filters.journal !== 'all') {
    const j = journals.find((x) => x.slug === filters.journal)
    chips.push({ key: 'journal', label: j?.title ?? filters.journal, clear: { journal: 'all' } })
  }
  if (selectedIssue) {
    chips.push({ key: 'issue', label: selectedIssue.label, clear: { volume: null, issue: null } })
  } else if (filters.volume !== null) {
    chips.push({ key: 'volume', label: `Volume ${filters.volume}`, clear: { volume: null, issue: null } })
  }
  if (filters.type !== 'all') chips.push({ key: 'type', label: filters.type, clear: { type: 'all' } })
  const activeCount = chips.length

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Articles</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Search the archive</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Every article is open access. Search titles, abstracts, keywords and authors — or browse
              by volume and issue.
            </p>
          </Reveal>

          <Reveal delay={0.05} className="mt-8">
            <form
              onSubmit={(e) => {
                e.preventDefault()
                applyNow({ q: query })
              }}
              role="search"
              className="max-w-2xl"
            >
              <label htmlFor="archive-search" className="sr-only">
                Search articles
              </label>
              <div className="relative">
                <Search
                  className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500"
                  aria-hidden="true"
                />
                <input
                  id="archive-search"
                  type="search"
                  name="q"
                  value={query}
                  onChange={(e) => onQueryChange(e.target.value)}
                  placeholder="Search titles, abstracts, keywords and authors"
                  className="h-14 w-full rounded-full border border-ink-300 bg-white pl-12 pr-14 text-base
                             text-ink-900 placeholder:text-ink-500 transition-colors duration-200 focus:border-brand-600"
                />
                {query !== '' && (
                  <button
                    type="button"
                    onClick={() => applyNow({ q: '' })}
                    aria-label="Clear search"
                    className="absolute right-2.5 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 cursor-pointer
                               items-center justify-center rounded-full text-ink-500 transition-colors
                               duration-200 hover:bg-ink-100 hover:text-ink-900"
                  >
                    <X className="h-5 w-5" />
                  </button>
                )}
              </div>
            </form>
          </Reveal>
        </div>
      </header>

      <div className="container-page grid grid-cols-1 gap-10 py-12 lg:grid-cols-[272px_minmax(0,1fr)]">
        {/* Filters */}
        <aside className="lg:sticky lg:top-24 lg:self-start" aria-label="Filters">
          <div className="flex items-center justify-between gap-3">
            <h2 className="font-serif text-lg text-ink-900">Refine</h2>
            {activeCount > 0 && (
              <button type="button" onClick={clearAll} className="link-underline cursor-pointer text-sm font-medium">
                Clear all
              </button>
            )}
          </div>

          {/* Small screens: the panel is a disclosure, so the results are not pushed off-screen. */}
          <button
            type="button"
            onClick={() => setPanelOpen((o) => !o)}
            aria-expanded={panelOpen}
            aria-controls="filter-panel"
            className="mt-4 inline-flex min-h-[44px] w-full cursor-pointer items-center justify-between rounded-full
                       border border-ink-300 bg-white px-4 text-sm font-medium text-ink-900 lg:hidden"
          >
            <span className="inline-flex items-center gap-2">
              <SlidersHorizontal className="h-4 w-4 text-ink-500" aria-hidden="true" />
              {panelOpen ? 'Hide filters' : 'Show filters'}
            </span>
            {activeCount > 0 && (
              <span className="rounded-full bg-brand-700 px-2 py-0.5 text-xs font-semibold text-white">
                {activeCount}
              </span>
            )}
          </button>

          <div id="filter-panel" className={`${panelOpen ? 'block' : 'hidden'} lg:block`}>
            {journals.length > 1 && (
              <div className="mt-5">
                <label htmlFor="journal-filter" className="block text-sm font-medium text-ink-700">
                  Journal
                </label>
                <select
                  id="journal-filter"
                  value={filters.journal}
                  onChange={(e) => applyNow({ journal: e.target.value, volume: null, issue: null })}
                  className={SELECT_CLASS}
                >
                  <option value="all">All journals</option>
                  {journals.map((j) => (
                    <option key={j.slug} value={j.slug}>
                      {j.title}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {volumes.length > 0 && (
              <>
                <div className="mt-5">
                  <label htmlFor="volume-filter" className="block text-sm font-medium text-ink-700">
                    Volume
                  </label>
                  <select
                    id="volume-filter"
                    value={filters.volume ?? 'all'}
                    onChange={(e) =>
                      applyNow({
                        volume: e.target.value === 'all' ? null : Number(e.target.value),
                        issue: null,
                      })
                    }
                    className={SELECT_CLASS}
                  >
                    <option value="all">All volumes</option>
                    {volumes.map((v) => (
                      <option
                        key={v.number}
                        value={v.number}
                        disabled={v.count === 0 && filters.volume !== v.number}
                      >
                        Volume {v.number}
                        {v.years ? ` · ${v.years}` : ''} ({v.count})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="mt-5">
                  <label htmlFor="issue-filter" className="block text-sm font-medium text-ink-700">
                    Issue
                  </label>
                  <select
                    id="issue-filter"
                    value={selectedIssue ? `${selectedIssue.volume}-${selectedIssue.number}` : 'all'}
                    onChange={(e) => {
                      if (e.target.value === 'all') {
                        applyNow({ issue: null })
                        return
                      }
                      const [volume, number] = e.target.value.split('-').map(Number)
                      applyNow({ volume, issue: number })
                    }}
                    className={SELECT_CLASS}
                  >
                    <option value="all">
                      {filters.volume === null ? 'All issues' : `All issues in Volume ${filters.volume}`}
                    </option>
                    {issueChoices.map((i) => {
                      const value = `${i.volume}-${i.number}`
                      const selected = selectedIssue !== null && value === `${selectedIssue.volume}-${selectedIssue.number}`
                      // With a volume chosen, the volume is already in the box above — the
                      // short form fits the sidebar instead of being cut off mid-season.
                      const text =
                        filters.volume === null
                          ? i.label
                          : [`Number ${i.number}`, i.season].filter(Boolean).join(' · ')
                      return (
                        <option key={value} value={value} disabled={i.count === 0 && !selected}>
                          {text} ({i.count})
                        </option>
                      )
                    })}
                  </select>
                </div>
              </>
            )}

            <fieldset className="mt-6">
              <legend className="text-sm font-medium text-ink-700">Article type</legend>
              <div className="mt-2 space-y-1">
                {[{ name: 'all', count: null as number | null }, ...types].map((t) => {
                  const selected = filters.type === t.name
                  const empty = t.count === 0 && !selected
                  return (
                    <label
                      key={t.name}
                      className={`flex min-h-[44px] items-center gap-3 rounded-lg px-2 text-sm transition-colors duration-200 ${
                        empty ? 'cursor-not-allowed text-ink-400' : 'cursor-pointer text-ink-700 hover:bg-ink-50'
                      }`}
                    >
                      <input
                        type="radio"
                        name="type"
                        value={t.name}
                        checked={selected}
                        disabled={empty}
                        onChange={(e) => applyNow({ type: e.target.value })}
                        className="h-4 w-4 cursor-pointer accent-brand-700 disabled:cursor-not-allowed"
                      />
                      <span className="flex-1">{t.name === 'all' ? 'All types' : t.name}</span>
                      {t.count !== null && (
                        <span className="tabular-nums text-xs text-ink-500">{formatNumber(t.count)}</span>
                      )}
                    </label>
                  )
                })}
              </div>
            </fieldset>
          </div>
        </aside>

        {/* Results */}
        <section aria-label="Search results" className="min-w-0">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p aria-live="polite" className="text-sm text-ink-600">
              {isSearching
                ? 'Searching…'
                : `${formatNumber(total)} ${total === 1 ? 'article' : 'articles'}${
                    selectedIssue ? ` in ${selectedIssue.label}` : filters.volume !== null ? ` in Volume ${filters.volume}` : ''
                  }`}
            </p>

            <div className="flex items-center gap-2">
              <ArrowDownUp className="h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
              <label htmlFor="sort" className="text-sm font-medium text-ink-700">
                Sort
              </label>
              <select
                id="sort"
                value={filters.sort}
                onChange={(e) => applyNow({ sort: e.target.value as Sort })}
                className="h-10 cursor-pointer rounded-full border border-ink-300 bg-white px-3 pr-8 text-sm text-ink-900
                           transition-colors duration-200 hover:border-ink-900 focus:border-brand-600"
              >
                {SORTS.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {chips.length > 0 && (
            <ul className="mt-4 flex flex-wrap items-center gap-2" aria-label="Active filters">
              {chips.map((c) => (
                <li key={c.key}>
                  <button
                    type="button"
                    onClick={() => applyNow(c.clear)}
                    className="inline-flex min-h-[36px] cursor-pointer items-center gap-1.5 rounded-full border border-brand-200
                               bg-brand-50 pl-3 pr-2 text-sm font-medium text-brand-800 transition-colors duration-200
                               hover:border-brand-700"
                  >
                    {c.label}
                    <X className="h-3.5 w-3.5" aria-hidden="true" />
                    <span className="sr-only">Remove this filter</span>
                  </button>
                </li>
              ))}
              {chips.length > 1 && (
                <li>
                  <button type="button" onClick={clearAll} className="link-underline cursor-pointer px-1 text-sm">
                    Clear all
                  </button>
                </li>
              )}
            </ul>
          )}

          {isSearching ? (
            <motion.div
              // Decorative, and client-only — `isSearching` is false on the server, so no
              // opacity:0 is ever serialised into the SSR HTML.
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="mt-5 space-y-5"
            >
              {[0, 1, 2].map((i) => (
                <div key={i} className="card flex gap-4 p-5">
                  <div className="hidden h-28 w-28 shrink-0 animate-pulse rounded-lg bg-ink-100 sm:block" />
                  <div className="flex-1 space-y-3 py-1">
                    <div className="h-3 w-24 animate-pulse rounded bg-ink-100" />
                    <div className="h-4 w-4/5 animate-pulse rounded bg-ink-100" />
                    <div className="h-4 w-3/5 animate-pulse rounded bg-ink-100" />
                    <div className="h-3 w-2/5 animate-pulse rounded bg-ink-100" />
                  </div>
                </div>
              ))}
            </motion.div>
          ) : results.length > 0 ? (
            <>
              <RevealGroup
                key={`${filters.q}-${filters.journal}-${filters.volume}-${filters.issue}-${filters.type}-${filters.sort}-${articles.meta.current_page}`}
                className="mt-5 space-y-5"
                stagger={0.06}
              >
                {results.map((a) => (
                  <RevealItem key={a.slug}>
                    <ArticleCard article={toArticleCard(a)} />
                  </RevealItem>
                ))}
              </RevealGroup>

              <Pagination links={articles.meta.links} />
            </>
          ) : (
            <div className="card mt-5 p-12 text-center">
              <p className="font-serif text-xl text-ink-900">No articles match those filters</p>
              <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">
                Try a broader keyword, or remove one of the filters above.
              </p>
              <button type="button" onClick={clearAll} className="btn-secondary mt-6">
                Reset all filters
              </button>
            </div>
          )}
        </section>
      </div>
    </>
  )
}
