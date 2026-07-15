import { Head, router } from '@inertiajs/react'
import { SlidersHorizontal } from 'lucide-react'
import { JournalCard } from '@/components/Cards'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { toJournalCard } from '@/lib/props'
import type { Journal, Meta } from '@/lib/props'

type Sort = 'impact' | 'articles' | 'speed'

const SORTS: { id: Sort; label: string }[] = [
  { id: 'impact', label: 'Impact factor' },
  { id: 'articles', label: 'Articles published' },
  { id: 'speed', label: 'Fastest decision' },
]

type Props = {
  journals: Journal[]
  fields: string[]
  filters: {
    field: string | null
    sort: string
  }
  meta: Meta
}

export default function Journals({ journals, fields, filters, meta }: Props) {
  const activeField = filters.field
  const sort = filters.sort

  /**
   * Filtering and sorting are the SERVER's job now, and both live in the URL.
   *
   * `sort` used to be local component state, so a sorted view could not be shared,
   * bookmarked or reached with the back button — the link you sent a colleague showed
   * them a different order from the one you were looking at.
   */
  const visit = (next: { field?: string | null; sort?: string }) => {
    const field = next.field !== undefined ? next.field : activeField
    const nextSort = next.sort !== undefined ? next.sort : sort

    const params: Record<string, string> = {}
    if (field) params.field = field
    if (nextSort) params.sort = nextSort

    router.get('/journals', params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Journals</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">All journals</h1>
            {/* Was "Six open-access journals…" — a count written into the page, which was
                the number of journals in the deleted fixture and not the number in the
                database. The count is stated below, from the data. */}
            <p className="mt-4 max-w-prose text-ink-600">
              Every journal is open access and led by a specialist editorial board. Filter by field,
              or sort by the metric that matters to you.
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {/* Filters */}
        <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="sr-only">Filter by field</h2>
            <ul className="flex flex-wrap gap-2">
              <li>
                <FilterChip active={!activeField} onClick={() => visit({ field: null })}>
                  All fields
                </FilterChip>
              </li>
              {fields.map((f) => (
                <li key={f}>
                  <FilterChip active={activeField === f} onClick={() => visit({ field: f })}>
                    {f}
                  </FilterChip>
                </li>
              ))}
            </ul>
          </div>

          <div className="flex items-center gap-3">
            <SlidersHorizontal className="h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
            <label htmlFor="sort" className="shrink-0 text-sm font-medium text-ink-700">
              Sort by
            </label>
            <select
              id="sort"
              value={sort}
              onChange={(e) => visit({ sort: e.target.value })}
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
          Showing {journals.length} {journals.length === 1 ? 'journal' : 'journals'}
          {activeField ? ` in ${activeField}` : ''}
        </p>

        {/* Re-keying on the filter replays the stagger, so a filter change reads as a change. */}
        <RevealGroup
          key={`${activeField ?? 'all'}-${sort}`}
          className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
          stagger={0.07}
        >
          {journals.map((j) => (
            <RevealItem key={j.slug} className="h-full">
              <JournalCard journal={toJournalCard(j)} />
            </RevealItem>
          ))}
        </RevealGroup>

        {journals.length === 0 && (
          <p className="mt-16 text-center text-ink-600">
            No journals in this field yet.{' '}
            <button
              type="button"
              onClick={() => visit({ field: null })}
              className="link-underline font-semibold"
            >
              Clear the filter
            </button>
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
