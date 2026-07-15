import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import {
  AlertTriangle,
  Check,
  ChevronDown,
  Clock,
  FileCheck2,
  Gavel,
  Inbox,
  Lock,
  Plus,
  type LucideIcon,
} from 'lucide-react'
import Counter from '@/components/Counter'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate, formatNumber, isOverdue } from '@/lib/format'
import { easeOut } from '@/lib/motion'
import type { Meta } from '@/lib/props'

/* -------------------------------------------------------------------------- *
 * The editorial-office page-prop contract.
 *
 * Everything on this page used to be a hardcoded module constant — the KPI tiles, the
 * review queue, the editor checklist, the decision-time series and the submissions
 * themselves, all imported from the demo fixture module (now deleted). They are now props,
 * so the page renders whatever
 * the (not yet written) EditorialController hands it, including nothing at all.
 * -------------------------------------------------------------------------- */

export type SubmissionStatus =
  | 'Draft'
  | 'Submitted'
  | 'Under Review'
  | 'Revisions Requested'
  | 'Accepted'
  | 'Rejected'

export type ReviewerStatus = 'Invited' | 'Accepted' | 'Report submitted' | 'Declined'

export type Recommendation = 'Accept' | 'Minor revision' | 'Major revision' | 'Reject'

export type Reviewer = {
  name: string
  affiliation: string
  avatar: string
  status: ReviewerStatus
  /** Absent until the report lands. */
  recommendation?: Recommendation | null
  /** ISO date. Compared against the server's `now`, never the client clock. */
  due: string
}

export type Submission = {
  id: string
  title: string
  journal: string
  status: SubmissionStatus
  submitted: string
  updated: string
  /** Index into PIPELINE_STAGES. Clamped on render — an out-of-range stage is not a crash. */
  stage: number
  correspondingAuthor: string
  type: string | null
  reviewers: Reviewer[]
}

/** A review THIS user owes someone else. */
export type ReviewAssignment = {
  /** The manuscript REFERENCE — what the reviewer was emailed and what they quote back. */
  id: string
  /** The assignment's own id. The Decline and Write-report buttons act on this. */
  assignmentId: number
  status: ReviewerStatus
  /** Invited -> must accept or decline. Accepted -> may write the report. */
  accepted: boolean
  title: string
  journal: string
  due: string
  round: number
}

/**
 * Icons cannot travel through JSON, so the tile's icon is chosen here. The server may name
 * one explicitly; otherwise it is looked up from the label, and failing that a neutral
 * default is used. A KPI never renders without its tile.
 */
export type KpiIcon = 'inbox' | 'gavel' | 'clock' | 'file-check'

export type Kpi = {
  label: string
  value: number
  suffix: string
  tone: 'brand' | 'gold'
  icon?: KpiIcon
}

export type DecisionPoint = { month: string; days: number }

export type ChecklistItem = { label: string; done: boolean }

type Props = {
  kpis: Kpi[]
  submissions: Submission[]
  reviewQueue: ReviewAssignment[]
  decisionTime: DecisionPoint[]
  checklist: ChecklistItem[]
  meta: Meta
}

/** Shared props from HandleInertiaRequests. */
type SharedProps = {
  now: string
  auth: { user: { id: number; name: string; email: string } | null }
}

const PIPELINE_STAGES = [
  'Submitted',
  'Editor check',
  'Peer review',
  'Decision',
  'Production',
] as const

/**
 * The `mounted` gate, exactly as in Layout.tsx.
 *
 * framer-motion serialises the `initial` values straight into the rendered style attribute,
 * so `initial={{ opacity: 0 }}` or `initial={{ pathLength: 0 }}` on the server emits an
 * INVISIBLE element into the SSR HTML — an empty chart, a blank progress bar, a list of
 * transparent rows. Gating on mount means the server and the first client render (the
 * hydration render, which must match it byte for byte) both draw the FINAL state, and the
 * animation is a client-only enhancement layered on afterwards.
 */
function useMounted() {
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])
  return mounted
}

const KPI_ICONS: Record<KpiIcon, LucideIcon> = {
  inbox: Inbox,
  gavel: Gavel,
  clock: Clock,
  'file-check': FileCheck2,
}

const KPI_ICON_BY_LABEL: Record<string, LucideIcon> = {
  'Active submissions': Inbox,
  'Awaiting your decision': Gavel,
  'Median days to decision': Clock,
  'Accepted this year': FileCheck2,
}

function kpiIcon(kpi: Kpi): LucideIcon {
  return (kpi.icon && KPI_ICONS[kpi.icon]) || KPI_ICON_BY_LABEL[kpi.label] || Inbox
}

export default function Dashboard({
  kpis,
  submissions,
  reviewQueue,
  decisionTime,
  checklist,
  meta,
}: Props) {
  // The server's clock. Deriving "today" on both sides guarantees a hydration mismatch the
  // moment a render straddles midnight, and the page previously compared against a literal
  // `new Date('2026-07-13')` — an "overdue" that was frozen in the past for ever.
  const { now, auth } = usePage<SharedProps>().props

  const [tab, setTab] = useState<'submissions' | 'reviews'>('submissions')

  // SUBMISSIONS[0].id threw on an empty list — the dashboard of a brand-new author, which
  // is the very first dashboard anyone sees.
  const [expanded, setExpanded] = useState<string | null>(submissions[0]?.id ?? null)

  const overdue = useMemo(
    () =>
      submissions
        .flatMap((s) => s.reviewers)
        .filter((r) => r.status !== 'Report submitted' && isOverdue(r.due, now)).length,
    [submissions, now],
  )

  const name = auth?.user?.name

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div>
              <p className="eyebrow">Editorial office</p>
              <h1 className="mt-3 font-serif text-4xl sm:text-5xl">
                {name ? `Welcome back, ${name}` : 'Welcome back'}
              </h1>
              <p className="mt-4 max-w-prose text-ink-600">
                Everything you have in flight — as an author, a reviewer and a handling editor.
              </p>
            </div>
            <Link href="/submit" className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden="true" />
              New submission
            </Link>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {/* KPI tiles */}
        {kpis.length > 0 && (
          <RevealGroup className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" stagger={0.08}>
            {kpis.map((k) => {
              const Icon = kpiIcon(k)
              return (
                <RevealItem key={k.label}>
                  <div className="card p-5">
                    <span
                      className={`inline-flex h-10 w-10 items-center justify-center rounded-lg ${
                        k.tone === 'gold'
                          ? 'bg-gold-50 text-gold-700'
                          : 'bg-brand-50 text-brand-700'
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
              )
            })}
          </RevealGroup>
        )}

        {overdue > 0 && (
          <Reveal className="mt-6">
            <div
              role="status"
              className="flex items-start gap-3 rounded-xl border border-gold-500/40 bg-gold-50 p-4"
            >
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-gold-700" aria-hidden="true" />
              <p className="text-sm text-ink-800">
                <span className="font-semibold">
                  {overdue === 1
                    ? '1 reviewer report is overdue.'
                    : `${formatNumber(overdue)} reviewer reports are overdue.`}
                </span>{' '}
                {/*
                  Was: "Send a reminder, or invite a replacement reviewer from the suggested
                  pool." There is no reminder button and no reviewer pool anywhere in this
                  application. Telling an editor to use a control that does not exist sends
                  them hunting for it, and then makes them distrust the rest of the page.
                */}
                Open the submission to see which reviewer, and how late.
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
                  type="button"
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
                {submissions.length === 0 ? (
                  <EmptyState
                    icon={Inbox}
                    title="Nothing submitted yet"
                    body="Your manuscripts appear here the moment you send them to an editor — with the peer-review pipeline, the reviewers and every decision attached."
                  >
                    <Link href="/submit" className="btn-primary mt-6">
                      <Plus className="h-4 w-4" aria-hidden="true" />
                      Start a submission
                    </Link>
                  </EmptyState>
                ) : (
                  /* RevealGroup, not a framer stagger with initial="hidden": the latter
                     serialised opacity:0 onto every row, so the entire submissions list
                     was transparent in the server-rendered HTML. */
                  <RevealGroup as="ul" className="space-y-4" stagger={0.07}>
                    {submissions.map((s) => (
                      <SubmissionRow
                        key={s.id}
                        submission={s}
                        open={expanded === s.id}
                        onToggle={() => setExpanded(expanded === s.id ? null : s.id)}
                      />
                    ))}
                  </RevealGroup>
                )}
              </div>
            ) : (
              <div role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" className="mt-6">
                <ReviewQueue queue={reviewQueue} now={now} />
              </div>
            )}
          </section>

          <aside className="space-y-6">
            <DecisionTimeChart points={decisionTime} />

            <div className="card p-6">
              <h2 className="font-serif text-lg">Editor checklist</h2>

              {checklist.length === 0 ? (
                <p className="mt-4 text-sm text-ink-600">
                  Nothing to check off — no manuscript is currently with you.
                </p>
              ) : (
                <ul className="mt-4 space-y-3">
                  {checklist.map((c) => (
                    <li key={c.label} className="flex items-center gap-3 text-sm">
                      {/* The state is carried by the tick, the strikethrough AND the
                          announced text — never by colour alone. */}
                      <span
                        className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${
                          c.done
                            ? 'bg-brand-700 text-white'
                            : 'border-2 border-ink-300 text-transparent'
                        }`}
                      >
                        {c.done && <Check className="h-3 w-3" aria-hidden="true" />}
                      </span>
                      <span className={c.done ? 'text-ink-500 line-through' : 'text-ink-800'}>
                        {c.label}
                        <span className="sr-only">{c.done ? ' (done)' : ' (outstanding)'}</span>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </aside>
        </div>
      </div>
    </>
  )
}

/* ------------------------------ Empty states ----------------------------- */

function EmptyState({
  icon: Icon,
  title,
  body,
  children,
}: {
  icon: LucideIcon
  title: string
  body: string
  children?: ReactNode
}) {
  return (
    <div className="card p-12 text-center">
      <span className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-500">
        <Icon className="h-6 w-6" aria-hidden="true" />
      </span>
      <p className="mt-4 font-serif text-xl text-ink-900">{title}</p>
      <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">{body}</p>
      {children}
    </div>
  )
}

/* ----------------------------- Submission row ---------------------------- */

const STATUS_STYLE: Record<SubmissionStatus, string> = {
  Draft: 'bg-ink-100 text-ink-700',
  Submitted: 'bg-brand-50 text-brand-800',
  'Under Review': 'bg-brand-100 text-brand-900',
  'Revisions Requested': 'bg-gold-50 text-gold-700',
  // success-*/danger-* are design-system tokens. Stock emerald/red sit outside it and
  // could never be restyled centrally.
  Accepted: 'bg-success-50 text-success-800',
  Rejected: 'bg-danger-50 text-danger-800',
}

const NEUTRAL_PILL = 'bg-ink-100 text-ink-700'

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
    <RevealItem as="li">
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
                  STATUS_STYLE[submission.status] ?? NEUTRAL_PILL
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
            genuinely needs it, and `height: auto` keeps it correct at any content size.
            `AnimatePresence initial={false}` is what keeps the row that is open on first
            paint from being served with height:0 — it renders open, and only later
            expansions animate. */}
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
                    {submission.status === 'Draft' ? (
                      <>
                        This manuscript is still a draft. Finish it in the{' '}
                        <Link href="/submit" className="link-underline font-medium">
                          submission form
                        </Link>
                        .
                      </>
                    ) : (
                      'No reviewers have been invited yet. The handling editor is still assessing scope.'
                    )}
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
    </RevealItem>
  )
}

function Pipeline({ stage }: { stage: number }) {
  const mounted = useMounted()

  // A stage index the server does not agree with must not index off the end of the array.
  const current = Math.min(Math.max(stage, 0), PIPELINE_STAGES.length - 1)

  return (
    <div className="mt-4">
      <ol className="flex items-center gap-1.5">
        {PIPELINE_STAGES.map((label, i) => {
          const done = i <= current
          return (
            <li key={label} className="flex-1">
              <motion.span
                // Ungated, `initial={{ scaleX: 0 }}` is serialised as transform:scaleX(0):
                // every pipeline bar is drawn at zero width in the server HTML.
                initial={mounted ? { scaleX: 0 } : false}
                animate={{ scaleX: 1 }}
                transition={{ duration: 0.4, ease: easeOut, delay: i * 0.06 }}
                className={`block h-1.5 origin-left rounded-full ${
                  done ? 'bg-brand-600' : 'bg-ink-200'
                }`}
              />
              <span
                className={`mt-1.5 hidden text-[10px] sm:block ${
                  i === current ? 'font-semibold text-brand-800' : 'text-ink-500'
                }`}
              >
                {label}
              </span>
            </li>
          )
        })}
      </ol>
      <p className="sr-only">
        Currently at stage {current + 1} of {PIPELINE_STAGES.length}: {PIPELINE_STAGES[current]}
      </p>
    </div>
  )
}

const REC_STYLE: Record<Recommendation, string> = {
  Accept: 'bg-success-50 text-success-800',
  'Minor revision': 'bg-brand-50 text-brand-800',
  'Major revision': 'bg-gold-50 text-gold-700',
  Reject: 'bg-danger-50 text-danger-800',
}

function ReviewerRow({ reviewer }: { reviewer: Reviewer }) {
  const { now } = usePage<SharedProps>().props
  const late = reviewer.status !== 'Report submitted' && isOverdue(reviewer.due, now)

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
              REC_STYLE[reviewer.recommendation] ?? NEUTRAL_PILL
            }`}
          >
            {reviewer.recommendation}
          </span>
        )}
        <span className="rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-medium text-ink-700">
          {reviewer.status}
        </span>
        {/* "Overdue" is a word and an icon, not just a colour. */}
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

/** The four recommendations the backend accepts. Mirrors App\Enums\Recommendation. */
const RECOMMENDATIONS: Recommendation[] = [
  'Accept',
  'Minor revision',
  'Major revision',
  'Reject',
]

function ReviewQueue({ queue, now }: { queue: ReviewAssignment[]; now: string }) {
  const [busy, setBusy] = useState<number | null>(null)
  const [reporting, setReporting] = useState<ReviewAssignment | null>(null)

  const respond = (assignmentId: number, action: 'accept' | 'decline') => {
    setBusy(assignmentId)
    router.post(
      `/reviews/${assignmentId}/${action}`,
      {},
      { preserveScroll: true, onFinish: () => setBusy(null) },
    )
  }

  if (queue.length === 0) {
    return (
      <EmptyState
        icon={Gavel}
        title="No reviews outstanding"
        body="When an editor invites you to review a manuscript, the invitation and its deadline appear here."
      />
    )
  }

  return (
    <RevealGroup as="ul" className="space-y-4" stagger={0.08}>
      {queue.map((q) => {
        const late = isOverdue(q.due, now)
        return (
          <RevealItem as="li" key={q.id}>
            <div className="card flex flex-wrap items-center justify-between gap-4 p-5">
              <div className="min-w-0">
                <span className="font-mono text-xs text-ink-500">{q.id}</span>
                <h3 className="mt-1.5 font-serif text-lg leading-snug text-ink-900">{q.title}</h3>
                <p className="mt-1 text-sm text-ink-600">
                  {q.journal} · review round {formatNumber(q.round)} ·{' '}
                  <span className={late ? 'font-semibold text-gold-700' : undefined}>
                    {late ? 'overdue since' : 'due'} {formatDate(q.due)}
                  </span>
                </p>
              </div>
              {/*
                These two buttons shipped INERT — no onClick at all — while the routes they
                needed existed the whole time. They are the only two controls a reviewer is
                actually asked to use.

                An invitation must be accepted before a report can be written, so the
                controls change with the assignment's state rather than offering an action
                the server would refuse.
              */}
              <div className="flex shrink-0 gap-2">
                {!q.accepted && (
                  <button
                    type="button"
                    disabled={busy === q.assignmentId}
                    onClick={() => respond(q.assignmentId, 'decline')}
                    className="btn-secondary cursor-pointer"
                  >
                    Decline
                  </button>
                )}

                {q.accepted ? (
                  <button
                    type="button"
                    onClick={() => setReporting(q)}
                    className="btn-primary cursor-pointer"
                  >
                    Write report
                  </button>
                ) : (
                  <button
                    type="button"
                    disabled={busy === q.assignmentId}
                    onClick={() => respond(q.assignmentId, 'accept')}
                    className="btn-primary cursor-pointer"
                  >
                    {busy === q.assignmentId ? 'Working…' : 'Accept review'}
                  </button>
                )}
              </div>
            </div>
          </RevealItem>
        )
      })}

      {reporting && (
        <ReviewReportDialog assignment={reporting} onClose={() => setReporting(null)} />
      )}
    </RevealGroup>
  )
}

/**
 * The review report form. There was no such component anywhere in the app.
 *
 * `comments_to_editor` is confidential and is NEVER shown to the author — the field says
 * so, because a reviewer who does not know that will either self-censor or say something
 * they believe is private when it is not. The backend enforces it (SubmissionPresenter);
 * this label is what makes it usable.
 */
function ReviewReportDialog({
  assignment,
  onClose,
}: {
  assignment: ReviewAssignment
  onClose: () => void
}) {
  const [recommendation, setRecommendation] = useState<Recommendation>('Minor revision')
  const [toAuthor, setToAuthor] = useState('')
  const [toEditor, setToEditor] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const submit = () => {
    setSubmitting(true)
    router.post(
      `/reviews/${assignment.assignmentId}/report`,
      { recommendation, comments_to_author: toAuthor, comments_to_editor: toEditor },
      {
        preserveScroll: true,
        onError: (e) => setErrors(e as Record<string, string>),
        onSuccess: onClose,
        onFinish: () => setSubmitting(false),
      },
    )
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="report-title"
      className="fixed inset-0 z-modal flex items-center justify-center bg-ink-950/50 p-4"
    >
      <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-lift">
        <h2 id="report-title" className="font-serif text-2xl text-ink-900">
          Review report
        </h2>
        <p className="mt-1 text-sm text-ink-600">
          {assignment.title} · {assignment.journal}
        </p>

        <fieldset className="mt-6">
          <legend className="text-sm font-semibold text-ink-900">Recommendation</legend>
          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            {RECOMMENDATIONS.map((r) => (
              <label
                key={r}
                className={`flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm transition-colors duration-200 ${
                  recommendation === r
                    ? 'border-brand-600 bg-brand-50 text-brand-900'
                    : 'border-ink-300 hover:border-ink-400'
                }`}
              >
                <input
                  type="radio"
                  name="recommendation"
                  value={r}
                  checked={recommendation === r}
                  onChange={() => setRecommendation(r)}
                  className="cursor-pointer"
                />
                {r}
              </label>
            ))}
          </div>
        </fieldset>

        <div className="mt-6">
          <label htmlFor="to-author" className="text-sm font-semibold text-ink-900">
            Comments to the author
          </label>
          <p className="mt-1 text-xs text-ink-600">
            Sent to the author in full, with your name removed.
          </p>
          <textarea
            id="to-author"
            rows={7}
            value={toAuthor}
            onChange={(e) => setToAuthor(e.target.value)}
            className="mt-2 w-full rounded-lg border border-ink-300 p-3 text-sm text-ink-900 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
          />
          {errors.comments_to_author && (
            <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
              {errors.comments_to_author}
            </p>
          )}
        </div>

        <div className="mt-5">
          <label htmlFor="to-editor" className="text-sm font-semibold text-ink-900">
            Confidential comments to the editor{' '}
            <span className="font-normal text-ink-600">(optional)</span>
          </label>
          <p className="mt-1 flex items-start gap-1.5 text-xs text-ink-600">
            <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            The author never sees this — not in any report, response or error message.
          </p>
          <textarea
            id="to-editor"
            rows={4}
            value={toEditor}
            onChange={(e) => setToEditor(e.target.value)}
            className="mt-2 w-full rounded-lg border border-ink-300 p-3 text-sm text-ink-900 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
          />
        </div>

        <div className="mt-7 flex justify-end gap-3">
          <button type="button" onClick={onClose} className="btn-secondary cursor-pointer">
            Cancel
          </button>
          <button
            type="button"
            onClick={submit}
            disabled={submitting || toAuthor.trim() === ''}
            className="btn-primary cursor-pointer"
          >
            {submitting ? 'Submitting…' : 'Submit report'}
          </button>
        </div>
      </div>
    </div>
  )
}

/* --------------------------- Decision-time chart -------------------------- */

/**
 * Days-to-first-decision over time — a trend over an ordered interval, so a line.
 *
 * The line draws itself in ON THE CLIENT ONLY. `initial={{ pathLength: 0 }}` is serialised
 * by framer into stroke-dasharray/stroke-dashoffset, so the server-rendered chart was an
 * empty box: the data was in the HTML and none of it was drawn. Everything below is gated
 * on `mounted`, so the server and the hydration render both emit the finished chart.
 *
 * The table alternative is not a nicety — no chart on this site is the only way to reach
 * its numbers.
 */
function DecisionTimeChart({ points }: { points: DecisionPoint[] }) {
  const mounted = useMounted()

  if (points.length === 0) {
    return (
      <div className="card p-6">
        <h2 className="font-serif text-lg">Days to first decision</h2>
        <p className="mt-2 text-sm text-ink-600">
          Not enough decisions yet to plot a trend. This chart appears once the first
          manuscripts have been through review.
        </p>
      </div>
    )
  }

  const w = 280
  const h = 120
  const pad = 8

  const values = points.map((d) => d.days)
  const min = Math.min(...values) - 6
  const max = Math.max(...values) + 6
  // A flat series would otherwise divide by zero and render every point at NaN.
  const range = max - min || 1

  const lastIndex = points.length - 1

  const x = (i: number) => pad + (lastIndex === 0 ? 0.5 : i / lastIndex) * (w - pad * 2)
  const y = (v: number) => pad + (1 - (v - min) / range) * (h - pad * 2)

  const line = points.map((d, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(d.days)}`).join(' ')
  const area = `${line} L ${x(lastIndex)} ${h - pad} L ${x(0)} ${h - pad} Z`

  const first = points[0]
  const last = points[lastIndex]
  // "% since January" was hardcoded, as was the divisor. Both come from the data now.
  const change = first.days === 0 ? 0 : Math.round(((last.days - first.days) / first.days) * 100)

  return (
    <div className="card p-6">
      <h2 className="font-serif text-lg">Days to first decision</h2>
      <p className="mt-1 text-sm text-ink-600">
        <span className="font-semibold text-brand-800">
          {change > 0 ? '+' : ''}
          {formatNumber(change)}%
        </span>{' '}
        since {first.month}
      </p>

      <svg
        viewBox={`0 0 ${w} ${h}`}
        className="mt-4 w-full"
        role="img"
        aria-label={`Line chart: median days to first decision went from ${formatNumber(
          first.days,
        )} in ${first.month} to ${formatNumber(last.days)} in ${last.month}.`}
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
          initial={mounted ? { opacity: 0 } : false}
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
          initial={mounted ? { pathLength: 0 } : false}
          whileInView={{ pathLength: 1 }}
          viewport={{ once: true }}
          transition={{ duration: 1.1, ease: easeOut }}
        />
        {points.map((d, i) => (
          <motion.circle
            key={d.month}
            cx={x(i)}
            cy={y(d.days)}
            r="3"
            fill="#fff"
            stroke="#0F766E"
            strokeWidth="2"
            initial={mounted ? { opacity: 0, scale: 0 } : false}
            whileInView={{ opacity: 1, scale: 1 }}
            viewport={{ once: true }}
            transition={{ delay: 0.3 + i * 0.09, duration: 0.25 }}
          />
        ))}
      </svg>

      <div className="mt-2 flex justify-between text-[11px] text-ink-500">
        <span>{first.month}</span>
        <span>{last.month}</span>
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
            {points.map((d) => (
              <tr key={d.month} className="border-b border-ink-100 last:border-0">
                <th scope="row" className="py-2 font-normal text-ink-800">
                  {d.month}
                </th>
                <td className="py-2 text-right tabular-nums text-ink-900">
                  {formatNumber(d.days)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </div>
  )
}
