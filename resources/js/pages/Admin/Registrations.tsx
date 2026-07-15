import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  AlertTriangle,
  Code2,
  FileText,
  Info,
  RefreshCw,
  RotateCw,
} from 'lucide-react'
import { AdminShell, Banner, EmptyState, Panel, Spinner } from '@/components/admin/Shell'
import { DepositBadge, DepositItemBadge, EndpointBadge } from '@/components/admin/Status'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate, formatNumber } from '@/lib/format'
import type { AdminPage, DepositItemStatus, DepositStatus } from '@/lib/admin'
import type { PaginationLink } from '@/lib/props'

type DepositItem = {
  id: number
  doi: string
  status: DepositItemStatus
  statusLabel: string
  title: string | null
  articleId: number
  /** Crossref's ACTUAL words. Credentials it echoes back are struck out server-side. */
  message: string | null
}

type Deposit = {
  id: number
  batchId: string
  endpoint: string
  isProduction: boolean
  status: DepositStatus
  statusLabel: string
  isRetryable: boolean
  attempts: number
  issue: { id: number; label: string } | null
  submittedAt: string | null
  completedAt: string | null
  submittedBy: string | null
  error: string | null
  hasPayload: boolean
  items: DepositItem[]
}

type Props = AdminPage<{
  deposits: { data: Deposit[]; links: PaginationLink[]; total: number }
}>

/**
 * The Crossref deposit log.
 *
 * WHY RETRY HAS NO GUARD. Crossref is idempotent on the DOI: redepositing UPDATES the record
 * rather than duplicating it. So retry is always safe — and an "already deposited?" check
 * would block the only mechanism there is for correcting metadata on a published article,
 * which is the one thing that cannot be fixed any other way.
 *
 * The retry reuses the SAME deposit row and batch id, so this stays one deposit with N
 * attempts rather than N deposits — and Crossref's submission report, which is keyed on the
 * batch id, keeps pointing at it.
 */
export default function Registrations({ deposits, journal, can, journals, meta }: Props) {
  const [retrying, setRetrying] = useState<number | null>(null)

  const retry = (id: number) => {
    setRetrying(id)

    router.post(
      `/admin/deposits/${id}/retry`,
      {},
      { preserveScroll: true, onFinish: () => setRetrying(null) },
    )
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={journal.abbreviation ?? 'Journal'}
        title="DOI registration"
        description="Every deposit sent to Crossref, what it contained, and what Crossref said back."
      >
        {!journal.canMintDois && (
          <div className="mb-8">
            <Banner
              tone="gold"
              icon={AlertTriangle}
              title="Crossref has not issued a prefix; DOIs cannot be registered yet"
            >
              This is not a fault. Articles publish and their URLs are permanent regardless — the
              deposit is made once a prefix exists, and nothing here can mint one in the meantime.
            </Banner>
          </div>
        )}

        {deposits.data.length === 0 ? (
          <EmptyState
            icon={FileText}
            title="No deposits yet"
            body="A deposit is created when an article or an issue is published — outside the publish transaction, so a Crossref outage can never roll back a publication or take the public pages down."
          />
        ) : (
          <RevealGroup className="space-y-6" stagger={0.06}>
            {deposits.data.map((deposit) => (
              <RevealItem key={deposit.id}>
                <Panel
                  title={deposit.issue ? deposit.issue.label : 'Article deposit'}
                  description={`Batch ${deposit.batchId}`}
                  actions={
                    <>
                      <DepositBadge status={deposit.status} label={deposit.statusLabel} />

                      {deposit.hasPayload && (
                        <a
                          href={`/admin/deposits/${deposit.id}/xml`}
                          target="_blank"
                          rel="noreferrer"
                          className="btn-ghost"
                        >
                          <Code2 className="h-4 w-4" aria-hidden="true" />
                          View XML sent
                        </a>
                      )}

                      {can.depositDois && deposit.isRetryable && (
                        <button
                          type="button"
                          onClick={() => retry(deposit.id)}
                          disabled={retrying === deposit.id}
                          className="btn-primary"
                        >
                          {retrying === deposit.id ? (
                            <Spinner />
                          ) : (
                            <RotateCw className="h-4 w-4" aria-hidden="true" />
                          )}
                          {retrying === deposit.id ? 'Queueing…' : 'Retry'}
                        </button>
                      )}
                    </>
                  }
                >
                  <div className="flex flex-wrap items-center gap-3 text-sm text-ink-600">
                    {/* THE ENDPOINT. Loud, in words. A sandbox deposit registers nothing, and
                        it is otherwise indistinguishable from a production one. */}
                    <EndpointBadge endpoint={deposit.endpoint} />

                    <span className="inline-flex items-center gap-1.5">
                      <RefreshCw className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
                      {formatNumber(deposit.attempts)}{' '}
                      {deposit.attempts === 1 ? 'attempt' : 'attempts'}
                    </span>

                    {deposit.submittedAt && <span>sent {formatDate(deposit.submittedAt)}</span>}
                    {deposit.submittedBy && <span>by {deposit.submittedBy}</span>}
                  </div>

                  {deposit.status === 'submitted' && (
                    <p className="mt-4 flex items-start gap-2 rounded-lg border border-brand-300 bg-brand-50 p-3 text-sm text-ink-800">
                      <Info className="mt-0.5 h-4 w-4 shrink-0 text-brand-700" aria-hidden="true" />
                      <span>
                        Crossref has <strong className="font-semibold">accepted the file for processing</strong>.
                        That is not registration: it processes deposits asynchronously, and it can
                        accept a batch while rejecting records inside it. Nothing is registered until
                        its submission report says so, per DOI.
                      </span>
                    </p>
                  )}

                  {deposit.error && (
                    <div
                      role="alert"
                      className="mt-4 flex items-start gap-2 rounded-lg border border-danger-600/40 bg-danger-50 p-3"
                    >
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-danger-700" aria-hidden="true" />
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-ink-900">Crossref said:</p>
                        <p className="mt-1 break-words font-mono text-xs text-ink-800">
                          {deposit.error}
                        </p>
                      </div>
                    </div>
                  )}

                  {deposit.items.length > 0 && (
                    <ul className="mt-5 space-y-2">
                      {deposit.items.map((item) => (
                        <li
                          key={item.id}
                          className="rounded-lg border border-ink-200 p-3 transition-colors duration-200 hover:border-ink-300"
                        >
                          <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                              <Link
                                href={`/admin/articles/${item.articleId}/edit`}
                                className="block truncate text-sm font-medium text-ink-900 transition-colors duration-200 hover:text-brand-800"
                              >
                                {item.title ?? 'Article'}
                              </Link>
                              <span className="mt-0.5 block break-all font-mono text-xs text-ink-600">
                                {item.doi}
                              </span>
                            </div>
                            <DepositItemBadge status={item.status} label={item.statusLabel} />
                          </div>

                          {item.message && (
                            <p className="mt-2 break-words rounded-md bg-ink-50 p-2 font-mono text-xs text-ink-700">
                              {item.message}
                            </p>
                          )}
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>
              </RevealItem>
            ))}
          </RevealGroup>
        )}
      </AdminShell>
    </>
  )
}
