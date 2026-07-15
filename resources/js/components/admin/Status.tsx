import {
  Ban,
  CheckCircle2,
  Clock,
  FlaskConical,
  Globe2,
  Hourglass,
  Lock,
  PencilLine,
  Upload,
  XCircle,
  type LucideIcon,
} from 'lucide-react'
import type {
  ArticleStatus,
  DepositItemStatus,
  DepositStatus,
  IssueStatus,
} from '@/lib/admin'

/**
 * STATUS IS NEVER CARRIED BY COLOUR ALONE.
 *
 * Every badge here is an icon AND a word. That is the design system's rule, and on this
 * screen in particular it is not decoration: "failed" and "registered" differ by one word
 * and one hue, one of them means a DOI resolves and the other means it does not, and about
 * one man in twelve cannot tell the two hues apart.
 *
 * The tokens are the design system's own — `success`, `danger`, `gold` — never stock
 * emerald/red, which sit outside it and could never be restyled centrally.
 */

type Look = { icon: LucideIcon; className: string }

const NEUTRAL: Look = { icon: PencilLine, className: 'bg-ink-100 text-ink-700' }

const ARTICLE: Record<ArticleStatus, Look> = {
  draft: { icon: PencilLine, className: 'bg-ink-100 text-ink-700' },
  published: { icon: CheckCircle2, className: 'bg-success-50 text-success-800' },
  // Withdrawn is not deleted: the landing page keeps resolving, with a notice, or the DOI dies.
  withdrawn: { icon: Ban, className: 'bg-gold-50 text-gold-700' },
}

const ISSUE: Record<IssueStatus, Look> = {
  draft: { icon: PencilLine, className: 'bg-ink-100 text-ink-700' },
  // The padlock is the point: a published issue is READ-ONLY, and the icon says why before
  // the editor discovers it by having a button refuse them.
  published: { icon: Lock, className: 'bg-success-50 text-success-800' },
}

const DEPOSIT: Record<DepositStatus, Look> = {
  queued: { icon: Clock, className: 'bg-ink-100 text-ink-700' },
  depositing: { icon: Upload, className: 'bg-brand-50 text-brand-800' },
  // "Awaiting Crossref" — accepted for processing, NOT registered. A 200 on the POST says
  // only that the XML was received.
  submitted: { icon: Hourglass, className: 'bg-brand-50 text-brand-800' },
  registered: { icon: CheckCircle2, className: 'bg-success-50 text-success-800' },
  failed: { icon: XCircle, className: 'bg-danger-50 text-danger-800' },
}

const ITEM: Record<DepositItemStatus, Look> = {
  pending: { icon: Hourglass, className: 'bg-ink-100 text-ink-700' },
  registered: { icon: CheckCircle2, className: 'bg-success-50 text-success-800' },
  failed: { icon: XCircle, className: 'bg-danger-50 text-danger-800' },
}

function Badge({ look, label }: { look: Look; label: string }) {
  const Icon = look.icon

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${look.className}`}
    >
      <Icon className="h-3.5 w-3.5" aria-hidden="true" />
      {label}
    </span>
  )
}

export function ArticleBadge({ status, label }: { status: ArticleStatus; label: string }) {
  return <Badge look={ARTICLE[status] ?? NEUTRAL} label={label} />
}

export function IssueBadge({ status, label }: { status: IssueStatus; label: string }) {
  return <Badge look={ISSUE[status] ?? NEUTRAL} label={label} />
}

export function DepositBadge({ status, label }: { status: DepositStatus; label: string }) {
  return <Badge look={DEPOSIT[status] ?? NEUTRAL} label={label} />
}

export function DepositItemBadge({ status, label }: { status: DepositItemStatus; label: string }) {
  return <Badge look={ITEM[status] ?? NEUTRAL} label={label} />
}

/**
 * THE ENDPOINT. The single most consequential fact in the deposit log.
 *
 * A sandbox deposit and a production one look identical in every other respect, and a
 * journal that believes it registered fifty DOIs against test.crossref.org has fifty DOIs
 * that resolve nowhere. So this is loud, and it is a word plus an icon, not a hue.
 */
export function EndpointBadge({ endpoint }: { endpoint: string }) {
  const isProduction = endpoint === 'production'

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${
        isProduction
          ? 'border-danger-600/40 bg-danger-50 text-danger-800'
          : 'border-ink-300 bg-ink-50 text-ink-700'
      }`}
    >
      {isProduction ? (
        <Globe2 className="h-3.5 w-3.5" aria-hidden="true" />
      ) : (
        <FlaskConical className="h-3.5 w-3.5" aria-hidden="true" />
      )}
      {isProduction ? 'Production — live DOIs' : 'Sandbox — nothing is registered'}
    </span>
  )
}
