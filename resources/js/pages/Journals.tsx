import { Head, router } from '@inertiajs/react'
import { ArrowDownUp } from 'lucide-react'
import { IssueCard, JournalCard } from '@/components/Cards'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { toJournalCard } from '@/lib/props'
import type { IssueCard as IssueCardData, Journal, Meta } from '@/lib/props'

type Sort = 'newest' | 'oldest'

const SORTS: { id: Sort; label: string }[] = [
  { id: 'newest', label: 'Newest first' },
  { id: 'oldest', label: 'Oldest first' },
]

type Props = {
  issues: IssueCardData[]
  /** Continuous journals only — they have no issues, so they keep a journal card. */
  journals: Journal[]
  volumes: { number: number; years: string }[]
  journalTitles: string[]
  filters: {
    volume: number | null
    sort: Sort
  }
  meta: Meta
}

/**
 * The journals, browsed ISSUE BY ISSUE.
 *
 * Each card is one printed issue — the journal's name, then "Volume 5 · Number 1 · Summer
 * 2017". It used to be a single journal card with every article from every issue behind it,
 * which hid the volumes entirely. The volume filter and the sort both live in the URL.
 */
export default function Journals({ issues, journals, volumes, journalTitles, filters, meta }: Props) {
  const visit = (next: { volume?: number | null; sort?: Sort }) => {
    const volume = next.volume !== undefined ? next.volume : filters.volume
    const sort = next.sort ?? filters.sort

    const params: Record<string, string> = {}
    if (volume) params.volume = String(volume)
    if (sort !== 'newest') params.sort = sort

    router.get('/journals', params, { preserveState: true, preserveScroll: true, replace: true })
  }

  const single = journalTitles.length === 1 ? journalTitles[0] : null
  const total = issues.length + journals.length

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Journals</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">{single ?? 'All journals'}</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              {single
                ? 'Every issue, volume by volume. Open access and free to read — choose an issue to see its articles, or download the whole issue as a PDF.'
                : 'Every issue of our open-access journals, volume by volume. Choose an issue to see its articles.'}
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {/* Filters */}
        <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          {volumes.length > 1 && (
            <div>
              <h2 className="sr-only">Filter by volume</h2>
              <ul className="flex flex-wrap gap-2">
                <li>
                  <FilterChip active={!filters.volume} onClick={() => visit({ volume: null })}>
                    All volumes
                  </FilterChip>
                </li>
                {volumes.map((v) => (
                  <li key={v.number}>
                    <FilterChip active={filters.volume === v.number} onClick={() => visit({ volume: v.number })}>
                      Volume {v.number}
                      {v.years && <span className="ml-1.5 opacity-70">{v.years}</span>}
                    </FilterChip>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <div className="flex items-center gap-3">
            <ArrowDownUp className="h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
            <label htmlFor="sort" className="shrink-0 text-sm font-medium text-ink-700">
              Sort by
            </label>
            <select
              id="sort"
              value={filters.sort}
              onChange={(e) => visit({ sort: e.target.value as Sort })}
              className="h-11 cursor-pointer rounded-full border border-ink-300 bg-white px-4 pr-9 text-sm
                         text-ink-900 transition-colors duration-200 hover:border-ink-900 focus:border-brand-600"
            >
              {SORTS.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <p aria-live="polite" className="mt-6 text-sm text-ink-600">
          Showing {issues.length} {issues.length === 1 ? 'issue' : 'issues'}
          {filters.volume ? ` in Volume ${filters.volume}` : ''}
          {journals.length > 0 &&
            ` and ${journals.length} continuously published ${journals.length === 1 ? 'journal' : 'journals'}`}
        </p>

        {/* Re-keying on the filter replays the stagger, so a filter change reads as a change. */}
        <RevealGroup
          key={`${filters.volume ?? 'all'}-${filters.sort}`}
          className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
          stagger={0.07}
        >
          {issues.map((issue) => (
            <RevealItem key={issue.key} className="h-full">
              <IssueCard issue={issue} />
            </RevealItem>
          ))}
          {journals.map((j) => (
            <RevealItem key={j.slug} className="h-full">
              <JournalCard journal={toJournalCard(j)} />
            </RevealItem>
          ))}
        </RevealGroup>

        {total === 0 && (
          <p className="mt-16 text-center text-ink-600">
            {filters.volume ? (
              <>
                No published issues in Volume {filters.volume}.{' '}
                <button type="button" onClick={() => visit({ volume: null })} className="link-underline font-semibold">
                  Show all volumes
                </button>
              </>
            ) : (
              'No issues have been published yet.'
            )}
          </p>
        )}
      </div>
    </>
  )
}

function FilterChip({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`inline-flex min-h-[44px] cursor-pointer items-center rounded-full border px-4 text-sm
                  font-medium transition-colors duration-200 ${
                    active
                      ? 'border-brand-700 bg-brand-700 text-white'
                      : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
                  }`}
    >
      {children}
    </button>
  )
}
