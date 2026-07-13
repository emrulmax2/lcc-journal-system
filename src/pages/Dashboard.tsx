import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import {
  AlertTriangle,
  ChevronDown,
  Clock,
  FileCheck2,
  Gavel,
  Inbox,
  Plus,
} from 'lucide-react'
import Counter from '@/components/Counter'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate } from '@/components/Cards'
import {
  DECISION_TIME,
  PIPELINE_STAGES,
  SUBMISSIONS,
  type Reviewer,
  type Submission,
  type SubmissionStatus,
} from '@/lib/data'
import { easeOut, staggerParent } from '@/lib/motion'

const KPIS = [
  { icon: Inbox, label: 'Active submissions', value: 4, suffix: '', tone: 'brand' as const },
  { icon: Gavel, label: 'Awaiting your decision', value: 1, suffix: '', tone: 'gold' as const },
  { icon: Clock, label: 'Median days to decision', value: 51, suffix: '', tone: 'brand' as const },
  { icon: FileCheck2, label: 'Accepted this year', value: 12, suffix: '', tone: 'brand' as const },
]

export default function Dashboard() {
  const [tab, setTab] = useState<'submissions' | 'reviews'>('submissions')
  const [expanded, setExpanded] = useState<string | null>(SUBMISSIONS[0].id)

  const overdue = useMemo(
    () =>
      SUBMISSIONS.flatMap((s) => s.reviewers).filter(
        (r) => r.status !== 'Report submitted' && new Date(r.due) < new Date('2026-07-13'),
      ).length,
    [],
  )

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div>
              <p className="eyebrow">Editorial office</p>
              <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Welcome back, Dr Okonkwo</h1>
              <p className="mt-4 max-w-prose text-ink-600">
                Everything you have in flight — as an author, a reviewer and a handling editor.
              </p>
            </div>
            <Link to="/submit" className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New submission
            </Link>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {/* KPI tiles */}
        <RevealGroup className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" stagger={0.08}>
          {KPIS.map(({ icon: Icon, ...k }) => (
            <RevealItem key={k.label}>
              <div className="card p-5">
                <span
                  className={`inline-flex h-10 w-10 items-center justify-center rounded-lg ${
                    k.tone === 'gold' ? 'bg-gold-50 text-gold-700' : 'bg-brand-50 text-brand-700'
                  }`}
                >
                  <Icon className="h-5 w-5" aria-hidden="true" />
                </span>
                <p className="mt-4 font-serif text-3xl text-ink-900">
                  <Counter value={k.value} suffix={k.suffix} />
                </p>
                <p className="mt-1 text-sm text-ink-600">{k.label}</p>
              </div>
            </RevealItem>
          ))}
        </RevealGroup>

        {overdue > 0 && (
          <Reveal className="mt-6">
            <div
              role="status"
              className="flex items-start gap-3 rounded-xl border border-gold-500/40 bg-gold-50 p-4"
            >
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-gold-700" aria-hidden="true" />
              <p className="text-sm text-ink-800">
                <span className="font-semibold">{overdue} reviewer report is overdue.</span> Send a
                reminder, or invite a replacement reviewer from the suggested pool.
              </p>
            </div>
          </Reveal>
        )}

        <div className="mt-10 grid gap-8 lg:grid-cols-[1fr_340px]">
          <section>
            {/* Segmented pill control — the selected pill slides between the segments. */}
            <div
              role="tablist"
              aria-label="Dashboard views"
              className="inline-flex rounded-full border border-ink-200 bg-white p-1"
            >
              {(
                [
                  { id: 'submissions', label: 'My submissions' },
                  { id: 'reviews', label: 'Reviews I owe' },
                ] as const
              ).map((t) => (
                <button
                  key={t.id}
                  role="tab"
                  id={`tab-${t.id}`}
                  aria-selected={tab === t.id}
                  aria-controls={`panel-${t.id}`}
                  onClick={() => setTab(t.id)}
                  className={`relative cursor-pointer rounded-full px-5 py-2.5 text-sm font-semibold
                              transition-colors duration-200 ${
                                tab === t.id ? 'text-white' : 'text-ink-600 hover:text-ink-900'
                              }`}
                >
                  {/* Shared layoutId slides the filled pill from one segment to the other.
                      The label is lifted above it explicitly — the button itself has no
                      stacking context, so a negative z-index would hide the pill behind
                      the container's white background. */}
                  {tab === t.id && (
                    <motion.span
                      layoutId="tab-pill"
                      className="absolute inset-0 rounded-full bg-ink-900"
                      transition={{ duration: 0.3, ease: easeOut }}
                    />
                  )}
                  <span className="relative">{t.label}</span>
                </button>
              ))}
            </div>

            {tab === 'submissions' ? (
              <div
                role="tabpanel"
                id="panel-submissions"
                aria-labelledby="tab-submissions"
                className="mt-6"
              >
                <motion.ul
                  variants={staggerParent(0.07)}
                  initial="hidden"
                  animate="visible"
                  className="space-y-4"
                >
                  {SUBMISSIONS.map((s) => (
                    <SubmissionRow
                      key={s.id}
                      submission={s}
                      open={expanded === s.id}
                      onToggle={() => setExpanded(expanded === s.id ? null : s.id)}
                    />
                  ))}
                </motion.ul>
              </div>
            ) : (
              <div
                role="tabpanel"
                id="panel-reviews"
                aria-labelledby="tab-reviews"
                className="mt-6"
              >
                <ReviewQueue />
              </div>
            )}
          </section>

          <aside className="space-y-6">
            <DecisionTimeChart />

            <div className="card p-6">
              <h2 className="font-serif text-lg">Editor checklist</h2>
              <ul className="mt-4 space-y-3">
                {[
                  { label: 'Scope and integrity check', done: true },
                  { label: 'Reviewer invitations sent', done: true },
                  { label: 'Two reports received', done: false },
                  { label: 'Decision recorded', done: false },
                ].map((c) => (
                  <li key={c.label} className="flex items-center gap-3 text-sm">
                    <span
                      className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] ${
                        c.done
                          ? 'bg-brand-700 text-white'
                          : 'border-2 border-ink-300 text-transparent'
                      }`}
                    >
                      ✓
                    </span>
                    <span className={c.done ? 'text-ink-500 line-through' : 'text-ink-800'}>
                      {c.label}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          </aside>
        </div>
      </div>
    </>
  )
}

/* ----------------------------- Submission row ---------------------------- */

const STATUS_STYLE: Record<SubmissionStatus, string> = {
  Draft: 'bg-ink-100 text-ink-700',
  Submitted: 'bg-brand-50 text-brand-800',
  'Under Review': 'bg-brand-100 text-brand-900',
  'Revisions Requested': 'bg-gold-50 text-gold-700',
  Accepted: 'bg-emerald-50 text-emerald-800',
  Rejected: 'bg-red-50 text-red-800',
}

function SubmissionRow({
  submission,
  open,
  onToggle,
}: {
  submission: Submission
  open: boolean
  onToggle: () => void
}) {
  const panelId = `sub-panel-${submission.id}`

  return (
    <motion.li variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0 } }}>
      <div className="card overflow-hidden">
        <button
          type="button"
          onClick={onToggle}
          aria-expanded={open}
          aria-controls={panelId}
          className="flex w-full cursor-pointer items-start gap-4 p-5 text-left transition-colors duration-200 hover:bg-ink-50"
        >
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <span className="font-mono text-xs text-ink-500">{submission.id}</span>
              <span
                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                  STATUS_STYLE[submission.status]
                }`}
              >
                {submission.status}
              </span>
            </div>

            <h3 className="mt-2 font-serif text-lg leading-snug text-ink-900">
              {submission.title}
            </h3>
            <p className="mt-1 text-sm text-ink-600">
              {submission.journal} · updated {formatDate(submission.updated)}
            </p>

            <Pipeline stage={submission.stage} />
          </div>

          <ChevronDown
            className={`mt-1 h-5 w-5 shrink-0 text-ink-500 transition-transform duration-200 ${
              open ? 'rotate-180' : ''
            }`}
            aria-hidden="true"
          />
        </button>

        {/* Height animation is the exception to the transform-only rule: an accordion
            genuinely needs it, and `height: auto` keeps it correct at any content size. */}
        <AnimatePresence initial={false}>
          {open && (
            <motion.div
              id={panelId}
              key="content"
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.28, ease: easeOut }}
              className="overflow-hidden"
            >
              <div className="border-t border-ink-200 bg-ink-50 p-5">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-ink-500">
                  Peer review
                </h4>

                {submission.reviewers.length === 0 ? (
                  <p className="mt-3 text-sm text-ink-600">
                    This manuscript is still a draft. Finish it in the{' '}
                    <Link to="/submit" className="link-underline font-medium">
                      submission form
                    </Link>
                    .
                  </p>
                ) : (
                  <ul className="mt-3 space-y-3">
                    {submission.reviewers.map((r) => (
                      <ReviewerRow key={r.name} reviewer={r} />
                    ))}
                  </ul>
                )}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </motion.li>
  )
}

function Pipeline({ stage }: { stage: number }) {
  return (
    <div className="mt-4">
      <ol className="flex items-center gap-1.5">
        {PIPELINE_STAGES.map((label, i) => {
          const done = i <= stage
          return (
            <li key={label} className="flex-1">
              <motion.span
                initial={{ scaleX: 0 }}
                animate={{ scaleX: 1 }}
                transition={{ duration: 0.4, ease: easeOut, delay: i * 0.06 }}
                className={`block h-1.5 origin-left rounded-full ${
                  done ? 'bg-brand-600' : 'bg-ink-200'
                }`}
              />
              <span
                className={`mt-1.5 hidden text-[10px] sm:block ${
                  i === stage ? 'font-semibold text-brand-800' : 'text-ink-500'
                }`}
              >
                {label}
              </span>
            </li>
          )
        })}
      </ol>
      <p className="sr-only">
        Currently at stage {stage + 1} of {PIPELINE_STAGES.length}: {PIPELINE_STAGES[stage]}
      </p>
    </div>
  )
}

const REC_STYLE: Record<string, string> = {
  Accept: 'bg-emerald-50 text-emerald-800',
  'Minor revision': 'bg-brand-50 text-brand-800',
  'Major revision': 'bg-gold-50 text-gold-700',
  Reject: 'bg-red-50 text-red-800',
}

function ReviewerRow({ reviewer }: { reviewer: Reviewer }) {
  const late = reviewer.status !== 'Report submitted' && new Date(reviewer.due) < new Date('2026-07-13')

  return (
    <li className="flex flex-wrap items-center gap-3 rounded-lg border border-ink-200 bg-white p-3">
      <img
        src={reviewer.avatar}
        alt=""
        width={40}
        height={40}
        loading="lazy"
        className="h-10 w-10 shrink-0 rounded-full object-cover"
      />
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-ink-900">{reviewer.name}</p>
        <p className="truncate text-xs text-ink-600">{reviewer.affiliation}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {reviewer.recommendation && (
          <span
            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
              REC_STYLE[reviewer.recommendation]
            }`}
          >
            {reviewer.recommendation}
          </span>
        )}
        <span className="rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-medium text-ink-700">
          {reviewer.status}
        </span>
        <span
          className={`inline-flex items-center gap-1 text-xs ${
            late ? 'font-semibold text-gold-700' : 'text-ink-500'
          }`}
        >
          {late && <AlertTriangle className="h-3.5 w-3.5" aria-hidden="true" />}
          {late ? 'Overdue' : 'Due'} {formatDate(reviewer.due)}
        </span>
      </div>
    </li>
  )
}

/* ------------------------------ Review queue ----------------------------- */

function ReviewQueue() {
  const queue = [
    {
      id: 'MRDN-2026-0431',
      title: 'Bayesian calibration of ice-sheet retreat under RCP8.5',
      journal: 'Meridian Climate Systems',
      due: '2026-07-18',
      round: 1,
    },
    {
      id: 'MRDN-2026-0409',
      title: 'Sparse attention improves protein contact-map prediction',
      journal: 'Meridian AI & Engineering',
      due: '2026-07-25',
      round: 2,
    },
  ]

  return (
    <motion.ul
      variants={staggerParent(0.08)}
      initial="hidden"
      animate="visible"
      className="space-y-4"
    >
      {queue.map((q) => (
        <motion.li
          key={q.id}
          variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0 } }}
        >
          <div className="card flex flex-wrap items-center justify-between gap-4 p-5">
            <div className="min-w-0">
              <span className="font-mono text-xs text-ink-500">{q.id}</span>
              <h3 className="mt-1.5 font-serif text-lg leading-snug text-ink-900">{q.title}</h3>
              <p className="mt-1 text-sm text-ink-600">
                {q.journal} · review round {q.round} · due {formatDate(q.due)}
              </p>
            </div>
            <div className="flex gap-2">
              <button type="button" className="btn-secondary">
                Decline
              </button>
              <button type="button" className="btn-primary">
                Write report
              </button>
            </div>
          </div>
        </motion.li>
      ))}
    </motion.ul>
  )
}

/* --------------------------- Decision-time chart -------------------------- */

/**
 * Days-to-first-decision over time — a trend over an ordered interval, so a line.
 * The line draws itself in; the data is also exposed as a table for screen readers
 * and anyone who wants the numbers rather than the shape.
 */
function DecisionTimeChart() {
  const w = 280
  const h = 120
  const pad = 8

  const values = DECISION_TIME.map((d) => d.days)
  const min = Math.min(...values) - 6
  const max = Math.max(...values) + 6

  const x = (i: number) => pad + (i / (DECISION_TIME.length - 1)) * (w - pad * 2)
  const y = (v: number) => pad + (1 - (v - min) / (max - min)) * (h - pad * 2)

  const line = DECISION_TIME.map((d, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(d.days)}`).join(' ')
  const area = `${line} L ${x(DECISION_TIME.length - 1)} ${h - pad} L ${x(0)} ${h - pad} Z`

  const first = DECISION_TIME[0].days
  const last = DECISION_TIME[DECISION_TIME.length - 1].days
  const change = Math.round(((last - first) / first) * 100)

  return (
    <div className="card p-6">
      <h2 className="font-serif text-lg">Days to first decision</h2>
      <p className="mt-1 text-sm text-ink-600">
        <span className="font-semibold text-brand-800">{change}%</span> since January
      </p>

      <svg
        viewBox={`0 0 ${w} ${h}`}
        className="mt-4 w-full"
        role="img"
        aria-label={`Line chart: median days to first decision fell from ${first} in January to ${last} in July.`}
      >
        <defs>
          <linearGradient id="decisionFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#0F766E" stopOpacity="0.18" />
            <stop offset="100%" stopColor="#0F766E" stopOpacity="0" />
          </linearGradient>
        </defs>

        <motion.path
          d={area}
          fill="url(#decisionFill)"
          initial={{ opacity: 0 }}
          whileInView={{ opacity: 1 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6, delay: 0.5 }}
        />
        <motion.path
          d={line}
          fill="none"
          stroke="#0F766E"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          initial={{ pathLength: 0 }}
          whileInView={{ pathLength: 1 }}
          viewport={{ once: true }}
          transition={{ duration: 1.1, ease: easeOut }}
        />
        {DECISION_TIME.map((d, i) => (
          <motion.circle
            key={d.month}
            cx={x(i)}
            cy={y(d.days)}
            r="3"
            fill="#fff"
            stroke="#0F766E"
            strokeWidth="2"
            initial={{ opacity: 0, scale: 0 }}
            whileInView={{ opacity: 1, scale: 1 }}
            viewport={{ once: true }}
            transition={{ delay: 0.3 + i * 0.09, duration: 0.25 }}
          />
        ))}
      </svg>

      <div className="mt-2 flex justify-between text-[11px] text-ink-500">
        <span>{DECISION_TIME[0].month}</span>
        <span>{DECISION_TIME[DECISION_TIME.length - 1].month}</span>
      </div>

      {/* Table alternative — the chart is never the only way to get the numbers. */}
      <details className="mt-4">
        <summary className="cursor-pointer text-sm font-medium text-brand-800 transition-colors duration-200 hover:text-brand-900">
          View as table
        </summary>
        <table className="mt-3 w-full text-left text-sm">
          <caption className="sr-only">Median days to first decision by month</caption>
          <thead>
            <tr className="border-b border-ink-200">
              <th scope="col" className="py-2 font-medium text-ink-600">
                Month
              </th>
              <th scope="col" className="py-2 text-right font-medium text-ink-600">
                Days
              </th>
            </tr>
          </thead>
          <tbody>
            {DECISION_TIME.map((d) => (
              <tr key={d.month} className="border-b border-ink-100 last:border-0">
                <th scope="row" className="py-2 font-normal text-ink-800">
                  {d.month}
                </th>
                <td className="py-2 text-right tabular-nums text-ink-900">{d.days}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </div>
  )
}
