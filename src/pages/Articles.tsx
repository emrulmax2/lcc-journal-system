import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import { Search, X } from 'lucide-react'
import { ArticleCard } from '@/components/Cards'
import { Reveal } from '@/components/Reveal'
import { ARTICLES, JOURNALS } from '@/lib/data'
import { staggerParent } from '@/lib/motion'

const TYPES = ['Original Research', 'Review', 'Methods', 'Perspective'] as const

export default function Articles() {
  const [params, setParams] = useSearchParams()
  const [query, setQuery] = useState(params.get('q') ?? '')
  const [journal, setJournal] = useState(params.get('journal') ?? 'all')
  const [type, setType] = useState<string>('all')
  const [searching, setSearching] = useState(false)

  // Keep the URL as the source of truth so results stay shareable and back-button-able.
  useEffect(() => {
    const next: Record<string, string> = {}
    if (query.trim()) next.q = query.trim()
    if (journal !== 'all') next.journal = journal
    setParams(next, { replace: true })
  }, [query, journal, setParams])

  // Show a brief searching state so the result count never changes silently under the user.
  useEffect(() => {
    if (!query) return
    setSearching(true)
    const t = window.setTimeout(() => setSearching(false), 250)
    return () => window.clearTimeout(t)
  }, [query])

  const results = useMemo(() => {
    const q = query.trim().toLowerCase()
    return ARTICLES.filter((a) => {
      const matchesQuery =
        !q ||
        a.title.toLowerCase().includes(q) ||
        a.abstract.toLowerCase().includes(q) ||
        a.keywords.some((k) => k.toLowerCase().includes(q)) ||
        a.authors.some((n) => n.toLowerCase().includes(q))
      const matchesJournal = journal === 'all' || a.journalSlug === journal
      const matchesType = type === 'all' || a.type === type
      return matchesQuery && matchesJournal && matchesType
    })
  }, [query, journal, type])

  return (
    <>
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
            <form onSubmit={(e) => e.preventDefault()} role="search" className="max-w-2xl">
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
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="e.g. coral bleaching, mRNA, permafrost"
                  className="h-14 w-full rounded-full border border-ink-300 bg-white pl-12 pr-14 text-base
                             text-ink-900 placeholder:text-ink-500 transition-colors duration-200 focus:border-brand-600"
                />
                {query && (
                  <button
                    type="button"
                    onClick={() => setQuery('')}
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
              value={journal}
              onChange={(e) => setJournal(e.target.value)}
              className="mt-2 h-11 w-full cursor-pointer rounded-full border border-ink-300 bg-white px-4
                         text-sm text-ink-900 transition-colors duration-200 hover:border-ink-900 focus:border-brand-600"
            >
              <option value="all">All journals</option>
              {JOURNALS.map((j) => (
                <option key={j.slug} value={j.slug}>
                  {j.title}
                </option>
              ))}
            </select>
          </div>

          <fieldset className="mt-6">
            <legend className="text-sm font-medium text-ink-700">Article type</legend>
            <div className="mt-2 space-y-1">
              {['all', ...TYPES].map((t) => (
                <label
                  key={t}
                  className="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg px-2
                             text-sm text-ink-700 transition-colors duration-200 hover:bg-ink-50"
                >
                  <input
                    type="radio"
                    name="type"
                    value={t}
                    checked={type === t}
                    onChange={(e) => setType(e.target.value)}
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
            {searching
              ? 'Searching…'
              : `${results.length} ${results.length === 1 ? 'article' : 'articles'}${
                  query ? ` matching “${query}”` : ''
                }`}
          </p>

          <AnimatePresence mode="wait">
            {searching ? (
              <motion.div
                key="skeleton"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
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
              <motion.div
                key={`${query}-${journal}-${type}`}
                variants={staggerParent(0.06)}
                initial="hidden"
                animate="visible"
                className="mt-5 space-y-5"
              >
                {results.map((a) => (
                  <ArticleCard key={a.slug} article={a} />
                ))}
              </motion.div>
            ) : (
              <motion.div
                key="empty"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                className="card mt-5 p-12 text-center"
              >
                <p className="font-serif text-xl text-ink-900">No articles match those filters</p>
                <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">
                  Try a broader keyword, or clear the journal and type filters.
                </p>
                <button
                  type="button"
                  onClick={() => {
                    setQuery('')
                    setJournal('all')
                    setType('all')
                  }}
                  className="btn-secondary mt-6"
                >
                  Reset all filters
                </button>
              </motion.div>
            )}
          </AnimatePresence>
        </section>
      </div>
    </>
  )
}
