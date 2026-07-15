import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { Search, X } from 'lucide-react'
import { ArticleCard } from '@/components/Cards'
import Pagination from '@/components/Pagination'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatNumber } from '@/lib/format'
import { toArticleCard } from '@/lib/props'
import type { Article, Meta, Paginated } from '@/lib/props'

type Filters = {
  q: string
  journal: string
  type: string
}

type Props = {
  /** Already filtered, sorted and paginated by the server. */
  articles: Paginated<Article>
  journals: { slug: string; title: string }[]
  types: string[]
  filters: Filters
  meta: Meta
}

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

export default function Articles({ articles, journals, types, filters, meta }: Props) {
  const [query, setQuery] = useState(filters.q)
  const [isSearching, setIsSearching] = useState(false)

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

  /**
   * q, journal AND type all round-trip through the URL. `type` used to be local state
   * only, which meant a type-filtered view could not be shared or bookmarked — the link
   * you sent showed the recipient a different result set from the one you were reading.
   */
  const visit = (next: Partial<Filters>) => {
    const merged: Filters = {
      q: next.q !== undefined ? next.q : query,
      journal: next.journal !== undefined ? next.journal : filters.journal,
      type: next.type !== undefined ? next.type : filters.type,
    }

    sentQuery.current = merged.q

    const params: Record<string, string> = {}
    if (merged.q.trim() !== '') params.q = merged.q.trim()
    if (merged.journal !== 'all') params.journal = merged.journal
    if (merged.type !== 'all') params.type = merged.type

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

  const total = articles.meta.total
  const results = articles.data

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
              Every article is open access. Search titles, abstracts, keywords and authors.
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

      <div className="container-page grid gap-10 py-12 lg:grid-cols-[240px_1fr]">
        {/* Filters */}
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <h2 className="font-serif text-lg text-ink-900">Refine</h2>

          <div className="mt-5">
            <label htmlFor="journal-filter" className="block text-sm font-medium text-ink-700">
              Journal
            </label>
            <select
              id="journal-filter"
              value={filters.journal}
              onChange={(e) => applyNow({ journal: e.target.value })}
              className="mt-2 h-11 w-full cursor-pointer rounded-full border border-ink-300 bg-white px-4
                         text-sm text-ink-900 transition-colors duration-200 hover:border-ink-900 focus:border-brand-600"
            >
              <option value="all">All journals</option>
              {journals.map((j) => (
                <option key={j.slug} value={j.slug}>
                  {j.title}
                </option>
              ))}
            </select>
          </div>

          <fieldset className="mt-6">
            <legend className="text-sm font-medium text-ink-700">Article type</legend>
            <div className="mt-2 space-y-1">
              {['all', ...types].map((t) => (
                <label
                  key={t}
                  className="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg px-2
                             text-sm text-ink-700 transition-colors duration-200 hover:bg-ink-50"
                >
                  <input
                    type="radio"
                    name="type"
                    value={t}
                    checked={filters.type === t}
                    onChange={(e) => applyNow({ type: e.target.value })}
                    className="h-4 w-4 cursor-pointer accent-brand-700"
                  />
                  {t === 'all' ? 'All types' : t}
                </label>
              ))}
            </div>
          </fieldset>
        </aside>

        {/* Results */}
        <section aria-label="Search results">
          <p aria-live="polite" className="text-sm text-ink-600">
            {isSearching
              ? 'Searching…'
              : `${formatNumber(total)} ${total === 1 ? 'article' : 'articles'}${
                  filters.q ? ` matching “${filters.q}”` : ''
                }`}
          </p>

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
                key={`${filters.q}-${filters.journal}-${filters.type}-${articles.meta.current_page}`}
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
                Try a broader keyword, or clear the journal and type filters.
              </p>
              <button
                type="button"
                onClick={() => applyNow({ q: '', journal: 'all', type: 'all' })}
                className="btn-secondary mt-6"
              >
                Reset all filters
              </button>
            </div>
          )}
        </section>
      </div>
    </>
  )
}
