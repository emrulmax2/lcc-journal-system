import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { motion } from 'framer-motion'
import { SlidersHorizontal } from 'lucide-react'
import { JournalCard } from '@/components/Cards'
import { Reveal } from '@/components/Reveal'
import { FIELDS, JOURNALS, type Field } from '@/lib/data'
import { staggerParent } from '@/lib/motion'

type Sort = 'impact' | 'articles' | 'speed'

const SORTS: { id: Sort; label: string }[] = [
  { id: 'impact', label: 'Impact factor' },
  { id: 'articles', label: 'Articles published' },
  { id: 'speed', label: 'Fastest decision' },
]

export default function Journals() {
  const [params, setParams] = useSearchParams()
  const activeField = (params.get('field') as Field | null) ?? null
  const [sort, setSort] = useState<Sort>('impact')

  const journals = useMemo(() => {
    const list = activeField ? JOURNALS.filter((j) => j.field === activeField) : [...JOURNALS]
    return list.sort((a, b) => {
      if (sort === 'impact') return b.impactFactor - a.impactFactor
      if (sort === 'articles') return b.articles - a.articles
      return a.medianDaysToDecision - b.medianDaysToDecision
    })
  }, [activeField, sort])

  const setField = (field: Field | null) => {
    if (field) setParams({ field })
    else setParams({})
  }

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Journals</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">All journals</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Six open-access journals, each led by a specialist editorial board. Filter by field,
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
                <FilterChip active={!activeField} onClick={() => setField(null)}>
                  All fields
                </FilterChip>
              </li>
              {FIELDS.map((f) => (
                <li key={f}>
                  <FilterChip active={activeField === f} onClick={() => setField(f)}>
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
              onChange={(e) => setSort(e.target.value as Sort)}
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
        <motion.div
          key={`${activeField ?? 'all'}-${sort}`}
          variants={staggerParent(0.07)}
          initial="hidden"
          animate="visible"
          className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
        >
          {journals.map((j) => (
            <JournalCard key={j.slug} journal={j} />
          ))}
        </motion.div>

        {journals.length === 0 && (
          <p className="mt-16 text-center text-ink-600">
            No journals in this field yet.{' '}
            <button
              type="button"
              onClick={() => setField(null)}
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
