import { Head, Link, router } from '@inertiajs/react'
import { AlertTriangle, ChevronRight, Clock, Inbox, Users } from 'lucide-react'
import { AdminShell, EmptyState, SELECT } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate } from '@/lib/format'
import { submissionHref, type AdminPage } from '@/lib/admin'
import { STATUS_PILL, type EditorSubmissionRow } from '@/lib/editorial'

type Option = { value: string | number; label: string }

type Props = AdminPage<{
  submissions: EditorSubmissionRow[]
  filters: { status: string | null; stage: number | null; overdue: boolean }
  statusOptions: Option[]
  stageOptions: Option[]
}>

/**
 * The editorial triage queue.
 *
 * Read-only: every row links to the manuscript's detail screen, where inviting a reviewer
 * and recording a decision actually happen. The backend already excludes drafts
 * (Submission::visibleToEditors) — nothing reaches an editor before its author sends it.
 */
export default function AdminSubmissions({
  submissions,
  filters,
  statusOptions,
  stageOptions,
  journal,
  can,
  journals,
  meta,
}: Props) {
  // Filtering is a GET round-trip so the URL is shareable and the back button works. `replace`
  // keeps the history from filling with every dropdown change.
  const apply = (patch: Partial<{ status: string; stage: string; overdue: boolean }>) => {
    const next = {
      status: filters.status ?? '',
      stage: filters.stage != null ? String(filters.stage) : '',
      overdue: filters.overdue,
      ...patch,
    }

    router.get(`/admin/journals/${journal.id}/submissions`, next, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }

  const overdueTotal = submissions.reduce((sum, s) => sum + s.overdueCount, 0)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={journal.abbreviation ?? 'Journal'}
        title="Submissions"
        description="Manuscripts in peer review, and where each one is. Open one to invite reviewers or record a decision."
      >
        {/* Filter bar */}
        <div className="mb-6 flex flex-wrap items-end gap-4">
          <div>
            <label htmlFor="filter-status" className="block text-sm font-medium text-ink-800">
              Status
            </label>
            <select
              id="filter-status"
              value={filters.status ?? ''}
              onChange={(e) => apply({ status: e.target.value })}
              className={`${SELECT} mt-1.5 w-48`}
            >
              <option value="">All statuses</option>
              {statusOptions.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="filter-stage" className="block text-sm font-medium text-ink-800">
              Stage
            </label>
            <select
              id="filter-stage"
              value={filters.stage != null ? String(filters.stage) : ''}
              onChange={(e) => apply({ stage: e.target.value })}
              className={`${SELECT} mt-1.5 w-48`}
            >
              <option value="">All stages</option>
              {stageOptions.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>

          <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-ink-300 bg-white px-3.5 py-2.5 text-sm text-ink-800 hover:border-ink-400">
            <input
              type="checkbox"
              checked={filters.overdue}
              onChange={(e) => apply({ overdue: e.target.checked })}
              className="h-4 w-4 cursor-pointer accent-brand-700"
            />
            Overdue reviews only
          </label>

          {(filters.status || filters.stage != null || filters.overdue) && (
            <Link
              href={`/admin/journals/${journal.id}/submissions`}
              className="text-sm font-medium text-brand-800 hover:text-brand-900"
            >
              Clear
            </Link>
          )}
        </div>

        {overdueTotal > 0 && (
          <div
            role="status"
            className="mb-6 flex items-start gap-3 rounded-xl border border-gold-500/40 bg-gold-50 p-4"
          >
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-gold-700" aria-hidden="true" />
            <p className="text-sm text-ink-800">
              <span className="font-semibold">
                {overdueTotal === 1
                  ? '1 reviewer report is overdue'
                  : `${overdueTotal} reviewer reports are overdue`}
              </span>{' '}
              across this queue. Open the manuscript to see which reviewer, and how late.
            </p>
          </div>
        )}

        {submissions.length === 0 ? (
          <EmptyState
            icon={Inbox}
            title="Nothing in the queue"
            body="Submitted manuscripts appear here the moment their authors send them. Drafts never do — nothing reaches an editor early."
          />
        ) : (
          <RevealGroup as="ul" className="space-y-3" stagger={0.05}>
            {submissions.map((s) => (
              <RevealItem as="li" key={s.id}>
                <Link
                  href={submissionHref(s.id)}
                  className="card flex items-center gap-4 p-5 transition-colors duration-200 hover:bg-ink-50"
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-mono text-xs text-ink-500">{s.reference ?? '—'}</span>
                      <span
                        className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                          STATUS_PILL[s.status] ?? 'bg-ink-100 text-ink-700'
                        }`}
                      >
                        {s.statusLabel}
                      </span>
                      <span className="rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-medium text-ink-700">
                        {s.stageLabel}
                      </span>
                    </div>

                    <h3 className="mt-2 truncate font-serif text-lg text-ink-900">{s.title}</h3>
                    <p className="mt-0.5 truncate text-sm text-ink-600">
                      {s.correspondingAuthor}
                      {s.section ? ` · ${s.section}` : ''} · updated {formatDate(s.updatedAt)}
                    </p>
                  </div>

                  <div className="hidden shrink-0 flex-col items-end gap-1 text-xs text-ink-600 sm:flex">
                    {s.reviewerCount > 0 && (
                      <span className="inline-flex items-center gap-1">
                        <Users className="h-3.5 w-3.5" aria-hidden="true" />
                        {s.reportsIn}/{s.reviewerCount} reports
                        {s.roundNumber ? ` · round ${s.roundNumber}` : ''}
                      </span>
                    )}
                    {s.overdueCount > 0 && (
                      <span className="inline-flex items-center gap-1 font-semibold text-gold-700">
                        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
                        {s.overdueCount} overdue
                      </span>
                    )}
                  </div>

                  <ChevronRight className="h-5 w-5 shrink-0 text-ink-400" aria-hidden="true" />
                </Link>
              </RevealItem>
            ))}
          </RevealGroup>
        )}
      </AdminShell>
    </>
  )
}
