import { useMemo, useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import {
  AlertTriangle,
  ArrowLeft,
  Check,
  Clock,
  Download,
  FileText,
  Gavel,
  Lock,
  Mail,
  MessageSquare,
  Plus,
  Send,
  UserPlus,
  Users,
} from 'lucide-react'
import { AdminShell, EmptyState, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import { formatDate, formatLongDate, isOverdue } from '@/lib/format'
import { formatBytes, submissionsHref, type AdminPage } from '@/lib/admin'
import {
  DECISION_PILL,
  DECISIONS,
  REVIEWER_PILL,
  STATUS_PILL,
  type AssignmentView,
  type DecisionState,
  type DecisionValue,
  type DiscussionThread,
  type EditorSubmissionDetail,
  type ParticipantCandidate,
  type ReviewRoundView,
  type ReviewerCandidate,
  type SubmissionFileView,
  type TimelineEvent,
} from '@/lib/editorial'

/** The five pipeline stages, for the discussion thread's optional stage tag. */
const PIPELINE_STAGES = ['Submitted', 'Editor check', 'Peer review', 'Decision', 'Production']

type Props = AdminPage<{
  submission: EditorSubmissionDetail
  files: SubmissionFileView[]
  timeline: TimelineEvent[]
  reviewerPool: ReviewerCandidate[]
  decisionState: DecisionState
  discussions: DiscussionThread[]
  participantCandidates: ParticipantCandidate[]
}>

type SharedProps = { now: string }

/**
 * The editorial cockpit's detail screen — one manuscript, everything an editor acts on.
 *
 * EDITOR-ONLY (gated on viewAllSubmissions), so it may show confidential reviewer comments
 * and — to those who may assign reviewers — reviewer identities. The mutations do NOT live
 * here: the invite and decision forms POST to the existing audited endpoints, which open
 * rounds, enforce the anonymity boundary and write the event trail.
 */
export default function SubmissionDetail({
  submission,
  files,
  timeline,
  reviewerPool,
  decisionState,
  discussions,
  participantCandidates,
  journal,
  can,
  journals,
  meta,
}: Props) {
  const { now } = usePage<SharedProps>().props

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={submission.reference ?? 'Manuscript'}
        title={submission.title}
        description={`${submission.statusLabel} · ${submission.stageLabel}${
          submission.section ? ` · ${submission.section}` : ''
        }`}
        actions={
          <Link href={submissionsHref(journal.id)} className="btn-ghost">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            Back to queue
          </Link>
        }
      >
        <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
          <div className="space-y-8">
            <Abstract submission={submission} />
            <Authors submission={submission} />
            <Files files={files} />
            <Rounds rounds={submission.rounds} now={now} canAssign={can.assignReviewers} />

            {can.assignReviewers && !decisionState.decided && (
              <InviteReviewer submissionId={submission.id} pool={reviewerPool} />
            )}

            <Decisions
              submission={submission}
              decisionState={decisionState}
              canDecide={can.recordDecision}
            />

            <Discussions
              submissionId={submission.id}
              discussions={discussions}
              candidates={participantCandidates}
            />
          </div>

          <aside className="space-y-6">
            <StatusCard submission={submission} decisionState={decisionState} />
            <Timeline events={timeline} />
          </aside>
        </div>
      </AdminShell>
    </>
  )
}

/* --------------------------------- Abstract -------------------------------- */

function Abstract({ submission }: { submission: EditorSubmissionDetail }) {
  return (
    <Panel title="Abstract">
      {submission.abstract ? (
        <p className="whitespace-pre-line text-sm leading-relaxed text-ink-800">
          {submission.abstract}
        </p>
      ) : (
        <p className="text-sm text-ink-600">No abstract was provided.</p>
      )}

      {submission.keywords.length > 0 && (
        <div className="mt-5 flex flex-wrap gap-2">
          {submission.keywords.map((k) => (
            <span
              key={k}
              className="rounded-full bg-ink-100 px-3 py-1 text-xs font-medium text-ink-700"
            >
              {k}
            </span>
          ))}
        </div>
      )}
    </Panel>
  )
}

/* ---------------------------------- Authors -------------------------------- */

function Authors({ submission }: { submission: EditorSubmissionDetail }) {
  return (
    <Panel title="Authors" description="As entered by the corresponding author at submission.">
      <ul className="space-y-3">
        {submission.authors.map((a, i) => (
          <li
            key={`${a.email}-${i}`}
            className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-ink-200 bg-white p-3"
          >
            <div className="min-w-0">
              <p className="flex items-center gap-2 text-sm font-semibold text-ink-900">
                {a.name}
                {a.isCorresponding && (
                  <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800">
                    <Mail className="h-3 w-3" aria-hidden="true" />
                    Corresponding
                  </span>
                )}
              </p>
              <p className="mt-0.5 text-xs text-ink-600">
                {a.affiliation || 'No affiliation given'}
              </p>
            </div>
            <div className="text-right text-xs text-ink-600">
              <p className="truncate">{a.email}</p>
              {a.orcid && (
                <a
                  href={`https://orcid.org/${a.orcid}`}
                  target="_blank"
                  rel="noreferrer"
                  className="font-mono text-brand-800 hover:text-brand-900"
                >
                  {a.orcid}
                </a>
              )}
            </div>
          </li>
        ))}
      </ul>
    </Panel>
  )
}

/* ----------------------------------- Files --------------------------------- */

function Files({ files }: { files: SubmissionFileView[] }) {
  return (
    <Panel title="Files" description="Every version, oldest first. Downloads stream privately — these files have no public URL.">
      {files.length === 0 ? (
        <p className="text-sm text-ink-600">No files were attached.</p>
      ) : (
        <ul className="divide-y divide-ink-200">
          {files.map((f) => (
            <li key={f.id} className="flex items-center gap-3 py-3">
              <FileText className="h-5 w-5 shrink-0 text-ink-500" aria-hidden="true" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-ink-900">{f.originalName}</p>
                <p className="text-xs text-ink-600">
                  {f.typeLabel} · v{f.version} · {formatBytes(f.sizeBytes)} · {formatDate(f.uploadedAt)}
                </p>
              </div>
              {/* Native anchor, not an Inertia visit — this is a file stream, not a page. */}
              <a href={f.downloadHref} className="btn-secondary shrink-0">
                <Download className="h-4 w-4" aria-hidden="true" />
                Download
              </a>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}

/* ---------------------------------- Rounds --------------------------------- */

function Rounds({
  rounds,
  now,
  canAssign,
}: {
  rounds: ReviewRoundView[]
  now: string
  canAssign: boolean
}) {
  if (rounds.length === 0) {
    return (
      <Panel title="Peer review">
        <EmptyState
          icon={Users}
          title="No reviewers invited yet"
          body="Invite a reviewer below to open the first round. The manuscript moves to Under review the moment you do."
        />
      </Panel>
    )
  }

  return (
    <Panel title="Peer review">
      <div className="space-y-6">
        {rounds.map((round) => (
          <div key={round.id}>
            <div className="mb-3 flex items-center gap-2">
              <h3 className="font-serif text-lg text-ink-900">Round {round.roundNumber}</h3>
              <span
                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                  round.isOpen ? 'bg-brand-50 text-brand-800' : 'bg-ink-100 text-ink-700'
                }`}
              >
                {round.isOpen ? 'Open' : 'Closed'}
              </span>
              {round.isOpen && round.allReportsIn && (
                <span className="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-800">
                  <Check className="h-3 w-3" aria-hidden="true" />
                  All reports in
                </span>
              )}
            </div>

            <ul className="space-y-3">
              {round.assignments.map((a) => (
                <AssignmentCard key={a.id} assignment={a} now={now} canAssign={canAssign} />
              ))}
            </ul>
          </div>
        ))}
      </div>
    </Panel>
  )
}

function AssignmentCard({
  assignment,
  now,
  canAssign,
}: {
  assignment: AssignmentView
  now: string
  canAssign: boolean
}) {
  const [withdrawing, setWithdrawing] = useState(false)
  const outstanding = assignment.status === 'invited' || assignment.status === 'accepted'
  const late =
    assignment.status !== 'report_submitted' &&
    assignment.status !== 'declined' &&
    isOverdue(assignment.dueAt, now)

  const withdraw = () => {
    setWithdrawing(true)
    router.post(
      `/reviews/${assignment.id}/withdraw`,
      {},
      { preserveScroll: true, onFinish: () => setWithdrawing(false) },
    )
  }

  return (
    <li className="rounded-lg border border-ink-200 bg-white p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-semibold text-ink-900">{assignment.reviewerName}</p>
          <p className="mt-0.5 text-xs text-ink-600">{assignment.reviewerAffiliation}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {assignment.report && (
            <span
              className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                DECISION_PILL[assignment.report.recommendation]
              }`}
            >
              {assignment.report.recommendationLabel}
            </span>
          )}
          <span
            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
              REVIEWER_PILL[assignment.status]
            }`}
          >
            {assignment.statusLabel}
          </span>
          <span
            className={`inline-flex items-center gap-1 text-xs ${
              late ? 'font-semibold text-gold-700' : 'text-ink-500'
            }`}
          >
            {late ? (
              <AlertTriangle className="h-3.5 w-3.5" aria-hidden="true" />
            ) : (
              <Clock className="h-3.5 w-3.5" aria-hidden="true" />
            )}
            {late ? 'Overdue' : 'Due'} {formatDate(assignment.dueAt)}
          </span>

          {canAssign && outstanding && (
            <button
              type="button"
              onClick={withdraw}
              disabled={withdrawing}
              className="text-xs font-semibold text-danger-700 hover:text-danger-800 disabled:opacity-50"
            >
              {withdrawing ? 'Withdrawing…' : 'Withdraw'}
            </button>
          )}
        </div>
      </div>

      {assignment.report && (
        <div className="mt-3 space-y-3 border-t border-ink-100 pt-3">
          {assignment.report.commentsToAuthor && (
            <div>
              <p className="text-xs font-semibold uppercase tracking-wider text-ink-500">
                Comments to the author
              </p>
              <p className="mt-1 whitespace-pre-line text-sm text-ink-800">
                {assignment.report.commentsToAuthor}
              </p>
            </div>
          )}

          {assignment.report.commentsToEditor && (
            <div className="rounded-lg bg-gold-50 p-3">
              <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gold-700">
                <Lock className="h-3.5 w-3.5" aria-hidden="true" />
                Confidential — editors only
              </p>
              <p className="mt-1 whitespace-pre-line text-sm text-ink-800">
                {assignment.report.commentsToEditor}
              </p>
            </div>
          )}
        </div>
      )}
    </li>
  )
}

/* ------------------------------ Invite reviewer ---------------------------- */

function InviteReviewer({
  submissionId,
  pool,
}: {
  submissionId: number
  pool: ReviewerCandidate[]
}) {
  const [reviewerId, setReviewerId] = useState('')
  const [dueAt, setDueAt] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!reviewerId) return

    setSubmitting(true)
    router.post(
      `/submissions/${submissionId}/reviewers`,
      { reviewer_id: Number(reviewerId), due_at: dueAt || null },
      {
        preserveScroll: true,
        onError: (e) => setErrors(e as Record<string, string>),
        onSuccess: () => {
          setReviewerId('')
          setDueAt('')
          setErrors({})
        },
        onFinish: () => setSubmitting(false),
      },
    )
  }

  return (
    <Panel
      title="Invite a reviewer"
      description="From this journal's reviewer pool. The default deadline is three weeks; override it if you need to."
    >
      {pool.length === 0 ? (
        <p className="text-sm text-ink-600">
          No reviewers are assigned to this journal yet. Add people with the{' '}
          <span className="font-mono text-xs">reviewer</span> role on the People screen first.
        </p>
      ) : (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
          <div>
            <label htmlFor="invite-reviewer" className="block text-sm font-medium text-ink-800">
              Reviewer
            </label>
            <select
              id="invite-reviewer"
              value={reviewerId}
              onChange={(e) => setReviewerId(e.target.value)}
              className={`${SELECT} mt-1.5`}
              required
            >
              <option value="">— choose —</option>
              {pool.map((r) => (
                <option key={r.id} value={r.id} disabled={r.alreadyInRound}>
                  {r.name}
                  {r.affiliation ? ` — ${r.affiliation}` : ''}
                  {r.alreadyInRound ? ' (already in this round)' : ''}
                  {!r.available ? ' (unavailable)' : ''}
                  {r.currentLoad > 0
                    ? ` · ${r.currentLoad} in progress${
                        r.maxConcurrent ? `/${r.maxConcurrent}` : ''
                      }`
                    : ''}
                </option>
              ))}
            </select>
            {errors.reviewer_id && (
              <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
                {errors.reviewer_id}
              </p>
            )}
            {errors.reviewer && (
              <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
                {errors.reviewer}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="invite-due" className="block text-sm font-medium text-ink-800">
              Due date
            </label>
            <input
              id="invite-due"
              type="date"
              value={dueAt}
              onChange={(e) => setDueAt(e.target.value)}
              className={`${SELECT} mt-1.5`}
            />
            {errors.due_at && (
              <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
                {errors.due_at}
              </p>
            )}
          </div>

          <button type="submit" disabled={submitting || !reviewerId} className="btn-primary">
            {submitting ? <Spinner /> : <UserPlus className="h-4 w-4" aria-hidden="true" />}
            Invite
          </button>
        </form>
      )}
    </Panel>
  )
}

/* --------------------------------- Decision -------------------------------- */

function Decisions({
  submission,
  decisionState,
  canDecide,
}: {
  submission: EditorSubmissionDetail
  decisionState: DecisionState
  canDecide: boolean
}) {
  return (
    <Panel title="Editorial decision">
      {submission.decisions.length > 0 && (
        <ul className="mb-6 space-y-4">
          {submission.decisions.map((d, i) => (
            <li key={i} className="rounded-lg border border-ink-200 bg-white p-4">
              <div className="flex flex-wrap items-center gap-2">
                <span
                  className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                    DECISION_PILL[d.decision]
                  }`}
                >
                  {d.decisionLabel}
                </span>
                <span className="text-xs text-ink-600">
                  {d.editor ? `${d.editor} · ` : ''}
                  {d.roundNumber ? `round ${d.roundNumber} · ` : ''}
                  {formatLongDate(d.decidedAt)}
                </span>
              </div>
              <p className="mt-2 whitespace-pre-line text-sm text-ink-800">{d.body}</p>
            </li>
          ))}
        </ul>
      )}

      {decisionState.decided ? (
        <p className="flex items-start gap-2 rounded-lg bg-ink-50 p-3 text-sm text-ink-700">
          <Check className="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
          This manuscript has reached a terminal decision. On acceptance it becomes a draft
          article, which still has to pass the publish gate before it gets a URL and a DOI.
        </p>
      ) : canDecide ? (
        <DecisionForm submissionId={submission.id} hasOpenRound={decisionState.hasOpenRound} />
      ) : (
        <p className="text-sm text-ink-600">
          You may read this manuscript but not decide it. A handling editor records the decision.
        </p>
      )}
    </Panel>
  )
}

function DecisionForm({
  submissionId,
  hasOpenRound,
}: {
  submissionId: number
  hasOpenRound: boolean
}) {
  const [decision, setDecision] = useState<DecisionValue>('minor_revision')
  const [body, setBody] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const submit = () => {
    setSubmitting(true)
    router.post(
      `/submissions/${submissionId}/decision`,
      { decision, body },
      {
        preserveScroll: true,
        onError: (e) => setErrors(e as Record<string, string>),
        onSuccess: () => {
          setBody('')
          setErrors({})
        },
        onFinish: () => setSubmitting(false),
      },
    )
  }

  return (
    <div>
      {!hasOpenRound && (
        <p className="mb-4 flex items-start gap-2 rounded-lg border border-gold-500/40 bg-gold-50 p-3 text-sm text-ink-800">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-gold-700" aria-hidden="true" />
          No review round is open. Deciding now is a desk decision, recorded without reviewer
          reports.
        </p>
      )}

      <fieldset>
        <legend className="text-sm font-semibold text-ink-900">Decision</legend>
        <div className="mt-2 grid gap-2 sm:grid-cols-2">
          {DECISIONS.map((d) => (
            <label
              key={d.value}
              className={`flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm transition-colors duration-200 ${
                decision === d.value
                  ? 'border-brand-600 bg-brand-50 text-brand-900'
                  : 'border-ink-300 hover:border-ink-400'
              }`}
            >
              <input
                type="radio"
                name="decision"
                value={d.value}
                checked={decision === d.value}
                onChange={() => setDecision(d.value)}
                className="cursor-pointer"
              />
              {d.label}
            </label>
          ))}
        </div>
      </fieldset>

      <div className="mt-5">
        <label htmlFor="decision-body" className="text-sm font-semibold text-ink-900">
          Letter to the author
        </label>
        <p className="mt-1 text-xs text-ink-600">
          Required. A decision with no reasoning is not one the author can act on — and it is
          the first thing asked for when a decision is challenged.
        </p>
        <textarea
          id="decision-body"
          rows={8}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          className="mt-2 w-full rounded-lg border border-ink-300 p-3 text-sm text-ink-900 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
        />
        {errors.body && (
          <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
            {errors.body}
          </p>
        )}
        {errors.decision && (
          <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
            {errors.decision}
          </p>
        )}
      </div>

      <div className="mt-5 flex justify-end">
        <button
          type="button"
          onClick={submit}
          disabled={submitting || body.trim() === ''}
          className="btn-primary"
        >
          {submitting ? <Spinner /> : <Gavel className="h-4 w-4" aria-hidden="true" />}
          Record decision
        </button>
      </div>
    </div>
  )
}

/* ------------------------------- Discussions ------------------------------- */

function Discussions({
  submissionId,
  discussions,
  candidates,
}: {
  submissionId: number
  discussions: DiscussionThread[]
  candidates: ParticipantCandidate[]
}) {
  const [starting, setStarting] = useState(false)

  return (
    <Panel
      title="Editorial discussion"
      description="Internal to the editorial team. The author never sees any of this."
      actions={
        <button type="button" onClick={() => setStarting((s) => !s)} className="btn-secondary">
          <Plus className="h-4 w-4" aria-hidden="true" />
          New thread
        </button>
      }
    >
      {starting && (
        <div className="mb-6">
          <NewThread
            submissionId={submissionId}
            candidates={candidates}
            onDone={() => setStarting(false)}
          />
        </div>
      )}

      {discussions.length === 0 ? (
        <p className="text-sm text-ink-600">
          No discussions yet. Start one to talk a manuscript over with the rest of the team.
        </p>
      ) : (
        <div className="space-y-5">
          {discussions.map((d) => (
            <Thread key={d.id} thread={d} />
          ))}
        </div>
      )}
    </Panel>
  )
}

function Thread({ thread }: { thread: DiscussionThread }) {
  return (
    <div className="rounded-lg border border-ink-200 bg-white p-4">
      <div className="flex flex-wrap items-center gap-2">
        <MessageSquare className="h-4 w-4 text-ink-500" aria-hidden="true" />
        <h3 className="font-semibold text-ink-900">{thread.subject}</h3>
        {thread.stageLabel && (
          <span className="rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-medium text-ink-700">
            {thread.stageLabel}
          </span>
        )}
      </div>

      {thread.participants.length > 0 && (
        <p className="mt-1 text-xs text-ink-500">{thread.participants.join(', ')}</p>
      )}

      <ul className="mt-3 space-y-3">
        {thread.messages.map((m) => (
          <li key={m.id} className="rounded-lg bg-ink-50 p-3">
            <p className="text-xs font-medium text-ink-700">
              {m.author ?? 'Unknown'} · {formatDate(m.at)}
            </p>
            <p className="mt-1 whitespace-pre-line text-sm text-ink-800">{m.body}</p>
          </li>
        ))}
      </ul>

      <Reply threadId={thread.id} />
    </div>
  )
}

function NewThread({
  submissionId,
  candidates,
  onDone,
}: {
  submissionId: number
  candidates: ParticipantCandidate[]
  onDone: () => void
}) {
  const [subject, setSubject] = useState('')
  const [stage, setStage] = useState('')
  const [body, setBody] = useState('')
  const [participants, setParticipants] = useState<number[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    router.post(
      `/submissions/${submissionId}/discussions`,
      { subject, stage: stage === '' ? null : Number(stage), body, participants },
      {
        preserveScroll: true,
        onError: (e) => setErrors(e as Record<string, string>),
        onSuccess: onDone,
        onFinish: () => setSubmitting(false),
      },
    )
  }

  return (
    <form onSubmit={submit} className="rounded-lg border border-ink-200 bg-ink-50 p-4">
      <div className="grid gap-4 sm:grid-cols-[1fr_200px]">
        <div>
          <label htmlFor="thread-subject" className="block text-sm font-medium text-ink-800">
            Subject
          </label>
          <input
            id="thread-subject"
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
            className={`${SELECT} mt-1.5`}
            required
          />
          {errors.subject && (
            <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
              {errors.subject}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="thread-stage" className="block text-sm font-medium text-ink-800">
            Stage (optional)
          </label>
          <select
            id="thread-stage"
            value={stage}
            onChange={(e) => setStage(e.target.value)}
            className={`${SELECT} mt-1.5`}
          >
            <option value="">General</option>
            {PIPELINE_STAGES.map((label, i) => (
              <option key={label} value={i}>
                {label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {candidates.length > 0 && (
        <fieldset className="mt-4">
          <legend className="text-sm font-medium text-ink-800">Include (optional)</legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {candidates.map((c) => {
              const on = participants.includes(c.id)
              return (
                <label
                  key={c.id}
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors duration-200 ${
                    on
                      ? 'border-brand-700 bg-brand-700 text-white'
                      : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={on}
                    onChange={(e) =>
                      setParticipants(
                        e.target.checked
                          ? [...participants, c.id]
                          : participants.filter((id) => id !== c.id),
                      )
                    }
                    className="h-3.5 w-3.5 cursor-pointer accent-white"
                  />
                  {c.name}
                </label>
              )
            })}
          </div>
        </fieldset>
      )}

      <div className="mt-4">
        <label htmlFor="thread-body" className="block text-sm font-medium text-ink-800">
          Message
        </label>
        <textarea
          id="thread-body"
          rows={4}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          className="mt-1.5 w-full rounded-lg border border-ink-300 p-3 text-sm text-ink-900 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
          required
        />
        {errors.body && (
          <p role="alert" className="mt-1.5 text-sm font-medium text-danger-700">
            {errors.body}
          </p>
        )}
      </div>

      <div className="mt-4 flex justify-end gap-3">
        <button type="button" onClick={onDone} className="btn-secondary">
          Cancel
        </button>
        <button
          type="submit"
          disabled={submitting || subject.trim() === '' || body.trim() === ''}
          className="btn-primary"
        >
          {submitting ? <Spinner /> : <Send className="h-4 w-4" aria-hidden="true" />}
          Start thread
        </button>
      </div>
    </form>
  )
}

function Reply({ threadId }: { threadId: number }) {
  const [body, setBody] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (body.trim() === '') return
    setSubmitting(true)
    router.post(
      `/discussions/${threadId}/messages`,
      { body },
      {
        preserveScroll: true,
        onSuccess: () => setBody(''),
        onFinish: () => setSubmitting(false),
      },
    )
  }

  return (
    <form onSubmit={submit} className="mt-3 flex items-start gap-2">
      <textarea
        rows={1}
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder="Reply…"
        className="min-h-[2.75rem] flex-1 rounded-lg border border-ink-300 p-2.5 text-sm text-ink-900 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
      />
      <button type="submit" disabled={submitting || body.trim() === ''} className="btn-secondary">
        {submitting ? <Spinner /> : <Send className="h-4 w-4" aria-hidden="true" />}
        Reply
      </button>
    </form>
  )
}

/* -------------------------------- Status card ------------------------------ */

function StatusCard({
  submission,
  decisionState,
}: {
  submission: EditorSubmissionDetail
  decisionState: DecisionState
}) {
  const reviewerTotals = useMemo(() => {
    const all = submission.rounds.flatMap((r) => r.assignments)
    return {
      total: all.length,
      reports: all.filter((a) => a.status === 'report_submitted').length,
    }
  }, [submission.rounds])

  return (
    <div className="card p-6">
      <h2 className="font-serif text-lg text-ink-900">At a glance</h2>
      <dl className="mt-4 space-y-3 text-sm">
        <Row label="Status">
          <span
            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
              STATUS_PILL[submission.status] ?? 'bg-ink-100 text-ink-700'
            }`}
          >
            {submission.statusLabel}
          </span>
        </Row>
        <Row label="Stage">{submission.stageLabel}</Row>
        <Row label="Submitted">{formatDate(submission.submittedAt)}</Row>
        <Row label="Reviewers">
          {reviewerTotals.total === 0
            ? 'None invited'
            : `${reviewerTotals.reports}/${reviewerTotals.total} reports in`}
        </Row>
        <Row label="Rounds">{submission.rounds.length || '—'}</Row>
        <Row label="Decision">
          {decisionState.decided ? submission.statusLabel : 'Pending'}
        </Row>
      </dl>
    </div>
  )
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <dt className="text-ink-600">{label}</dt>
      <dd className="text-right font-medium text-ink-900">{children}</dd>
    </div>
  )
}

/* --------------------------------- Timeline -------------------------------- */

function Timeline({ events }: { events: TimelineEvent[] }) {
  return (
    <div className="card p-6">
      <h2 className="font-serif text-lg text-ink-900">Audit trail</h2>
      {events.length === 0 ? (
        <p className="mt-3 text-sm text-ink-600">Nothing recorded yet.</p>
      ) : (
        <ol className="mt-4 space-y-4">
          {events.map((e, i) => (
            <li key={i} className="flex gap-3">
              <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true" />
              <div className="min-w-0">
                <p className="text-sm font-medium text-ink-900">{e.label}</p>
                <p className="text-xs text-ink-600">
                  {e.actor ?? 'System'} · {formatDate(e.at)}
                </p>
              </div>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
