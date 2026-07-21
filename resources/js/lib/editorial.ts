/**
 * The Inertia page-prop contract for the EDITORIAL COCKPIT — the submission queue and one
 * manuscript's detail screen.
 *
 * Derived from App\Http\Controllers\Admin\SubmissionController and
 * App\Support\SubmissionPresenter::forEditor.
 *
 * `commentsToEditor` appears in ReviewReportView on purpose: this contract only ever feeds
 * screens gated on `viewAllSubmissions`, and the confidential editor comment is FOR the
 * editor. It must never be lifted into the author-facing Dashboard contract, which has no
 * such field by design (see SubmissionPresenter's header).
 */

/** Enum string values from App\Enums\SubmissionStatus. */
export type SubmissionStatusValue =
  | 'draft'
  | 'submitted'
  | 'under_review'
  | 'revisions_requested'
  | 'accepted'
  | 'rejected'

/** Enum string values from App\Enums\ReviewerStatus. */
export type ReviewerStatusValue =
  | 'invited'
  | 'accepted'
  | 'declined'
  | 'report_submitted'
  | 'withdrawn'

/** Enum string values from App\Enums\Recommendation and DecisionType (they coincide). */
export type DecisionValue = 'accept' | 'minor_revision' | 'major_revision' | 'reject'

/** One row of the triage queue. */
export type EditorSubmissionRow = {
  id: number
  reference: string | null
  title: string
  status: SubmissionStatusValue
  statusLabel: string
  /** 0–4, index into the pipeline. */
  stage: number
  stageLabel: string
  correspondingAuthor: string
  section: string | null
  submittedAt: string | null
  updatedAt: string | null
  /** The latest round, or null if none opened yet. */
  roundNumber: number | null
  reviewerCount: number
  reportsIn: number
  /** Outstanding invitations past their due date, computed against the server clock. */
  overdueCount: number
  decided: boolean
}

export type ReviewReportView = {
  recommendation: DecisionValue
  recommendationLabel: string
  commentsToAuthor: string | null
  /** Editor-only. Confidential FROM the author — never rendered on any author-facing screen. */
  commentsToEditor: string | null
  submittedAt: string | null
}

export type AssignmentView = {
  id: number
  /** "Reviewer 1" when identities are withheld from this viewer. */
  reviewerName: string
  reviewerAffiliation: string
  status: ReviewerStatusValue
  statusLabel: string
  invitedAt: string | null
  dueAt: string
  respondedAt: string | null
  completedAt: string | null
  report: ReviewReportView | null
}

export type ReviewRoundView = {
  id: number
  roundNumber: number
  openedAt: string | null
  closedAt: string | null
  isOpen: boolean
  allReportsIn: boolean
  assignments: AssignmentView[]
}

export type EditorAuthor = {
  name: string
  email: string
  affiliation: string | null
  orcid: string | null
  isCorresponding: boolean
}

export type DecisionView = {
  decision: DecisionValue
  decisionLabel: string
  body: string
  editor: string | null
  roundNumber: number | null
  decidedAt: string | null
}

export type EditorSubmissionDetail = {
  id: number
  reference: string | null
  title: string
  abstract: string | null
  status: SubmissionStatusValue
  statusLabel: string
  stage: number
  stageLabel: string
  keywords: string[]
  section: string | null
  submittedAt: string | null
  authors: EditorAuthor[]
  rounds: ReviewRoundView[]
  decisions: DecisionView[]
}

export type SubmissionFileView = {
  id: number
  version: number
  type: string
  typeLabel: string
  originalName: string
  sizeBytes: number | null
  uploadedAt: string | null
  /** Streams off the private disk through admin.submissions.files.download. */
  downloadHref: string
}

export type TimelineEvent = {
  event: string
  label: string
  /** The person who caused it, or null for a system/scheduled event. */
  actor: string | null
  at: string | null
}

export type ReviewerCandidate = {
  id: number
  name: string
  affiliation: string | null
  expertise: string[]
  available: boolean
  maxConcurrent: number | null
  /** Reviews this person owes anyone, across every journal. */
  currentLoad: number
  /** Already invited in the open round — the invite form disables them. */
  alreadyInRound: boolean
}

export type DecisionState = {
  decided: boolean
  hasOpenRound: boolean
  allReportsIn: boolean
}

export type DiscussionMessage = {
  id: number
  /** Null for an account that was later deleted. */
  author: string | null
  body: string
  at: string | null
}

export type DiscussionThread = {
  id: number
  subject: string
  /** 0–4 pipeline stage, or null for a general thread. */
  stage: number | null
  stageLabel: string | null
  participants: string[]
  messages: DiscussionMessage[]
}

export type ParticipantCandidate = { id: number; name: string }

/** The four decisions the backend accepts. Mirrors App\Enums\DecisionType. */
export const DECISIONS: { value: DecisionValue; label: string }[] = [
  { value: 'accept', label: 'Accept' },
  { value: 'minor_revision', label: 'Minor revision' },
  { value: 'major_revision', label: 'Major revision' },
  { value: 'reject', label: 'Reject' },
]

/** Pill classes keyed by status value — design-system tokens, never stock Tailwind colours. */
export const STATUS_PILL: Record<SubmissionStatusValue, string> = {
  draft: 'bg-ink-100 text-ink-700',
  submitted: 'bg-brand-50 text-brand-800',
  under_review: 'bg-brand-100 text-brand-900',
  revisions_requested: 'bg-gold-50 text-gold-700',
  accepted: 'bg-success-50 text-success-800',
  rejected: 'bg-danger-50 text-danger-800',
}

export const DECISION_PILL: Record<DecisionValue, string> = {
  accept: 'bg-success-50 text-success-800',
  minor_revision: 'bg-brand-50 text-brand-800',
  major_revision: 'bg-gold-50 text-gold-700',
  reject: 'bg-danger-50 text-danger-800',
}

export const REVIEWER_PILL: Record<ReviewerStatusValue, string> = {
  invited: 'bg-ink-100 text-ink-700',
  accepted: 'bg-brand-50 text-brand-800',
  declined: 'bg-danger-50 text-danger-800',
  report_submitted: 'bg-success-50 text-success-800',
  withdrawn: 'bg-ink-100 text-ink-500',
}
