import { useMemo, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  ArrowDown,
  ArrowUp,
  FileText,
  FileWarning,
  GripVertical,
  Lock,
  Pencil,
  Plus,
  Undo2,
} from 'lucide-react'
import { AdminShell, EmptyState, Panel, Spinner } from '@/components/admin/Shell'
import { PublishButton } from '@/components/admin/PublishButton'
import { ArticleBadge, IssueBadge } from '@/components/admin/Status'
import { formatDate, formatNumber } from '@/lib/format'
import {
  editArticleHref,
  newArticleHref,
  type AdminPage,
  type ArticleStatus,
  type IssueStatus,
} from '@/lib/admin'

type ArticleRow = {
  id: number
  title: string
  slug: string
  sequence: number | null
  status: ArticleStatus
  statusLabel: string
  isPublished: boolean
  isFrozen: boolean
  section: string | null
  pages: string | null
  hasPdf: boolean
  authors: string[]
  /** The suffix the ONE generator would mint. NULL when it cannot be derived — never a guess. */
  doiSuffix: string | null
  doi: string | null
  suffixFrozen: boolean
}

type Issue = {
  id: number
  number: number
  season: string | null
  status: IssueStatus
  statusLabel: string
  publicationDate: string | null
  isPublished: boolean
  volume: { id: number; number: number; year: number }
}

type Props = AdminPage<{ issue: Issue; articles: ArticleRow[] }>

/**
 * The running order of an issue.
 *
 * REORDERING IS BLOCKED ON A PUBLISHED ISSUE. The DOI suffix is derived from an article's
 * position ({journal}.v{volume}i{issue}.{seq}), so moving article 3 to position 2 changes
 * which paper a minted, cited, permanently-resolving identifier refers to. IssuePolicy
 * refuses the endpoint; here the controls are simply not rendered, and the reason is on
 * screen with a padlock rather than left for the editor to discover.
 */
export default function AdminIssueDetail({ issue, articles, journal, can, journals, meta }: Props) {
  const [order, setOrder] = useState<number[]>(articles.map((a) => a.id))
  const [saving, setSaving] = useState(false)

  const byId = useMemo(
    () => new Map(articles.map((article) => [article.id, article])),
    [articles],
  )

  const original = useMemo(() => articles.map((a) => a.id), [articles])
  const dirty = order.some((id, i) => id !== original[i])

  const reorderable = can.manageArticles && !issue.isPublished

  const move = (index: number, delta: number) => {
    const next = [...order]
    const target = index + delta
    if (target < 0 || target >= next.length) return
    ;[next[index], next[target]] = [next[target], next[index]]
    setOrder(next)
  }

  const dropOn = (fromId: number, toIndex: number) => {
    const from = order.indexOf(fromId)
    if (from === -1 || from === toIndex) return
    const next = [...order]
    next.splice(from, 1)
    next.splice(toIndex, 0, fromId)
    setOrder(next)
  }

  const save = () => {
    setSaving(true)

    router.post(
      `/admin/issues/${issue.id}/reorder`,
      { order },
      {
        preserveScroll: true,
        onFinish: () => setSaving(false),
      },
    )
  }

  const label = `Vol ${issue.volume.number}, No ${issue.number}`

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={`${journal.abbreviation ?? journal.title} · ${issue.volume.year}`}
        title={label}
        description={
          issue.season
            ? `${issue.season}. ${articles.length} ${articles.length === 1 ? 'article' : 'articles'}.`
            : `${articles.length} ${articles.length === 1 ? 'article' : 'articles'}.`
        }
        actions={
          <>
            <IssueBadge status={issue.status} label={issue.statusLabel} />

            {can.manageArticles && !issue.isPublished && (
              <Link href={newArticleHref(journal.id)} className="btn-secondary">
                <Plus className="h-4 w-4" aria-hidden="true" />
                New article
              </Link>
            )}

            {can.publish && !issue.isPublished && articles.length > 0 && (
              <PublishButton
                url={`/admin/issues/${issue.id}/publish`}
                label="Publish issue"
                heading={`Publish ${label}?`}
                summary={`All ${articles.length} ${
                  articles.length === 1 ? 'article' : 'articles'
                } go live together, and the issue becomes read-only for ever.`}
                canMintDois={journal.canMintDois}
              />
            )}
          </>
        }
      >
        {issue.isPublished && (
          <div className="mb-8 flex items-start gap-3 rounded-xl border border-ink-200 bg-ink-50 p-4">
            <Lock className="mt-0.5 h-5 w-5 shrink-0 text-ink-500" aria-hidden="true" />
            <p className="text-sm text-ink-800">
              <span className="font-semibold text-ink-900">
                This issue is published and is now read-only.
              </span>{' '}
              Its articles carry live DOIs and printed page numbers. Adding an article shifts
              pagination, removing one orphans a DOI, and reordering changes which article a
              sequence-derived DOI refers to — each silently invalidates every citation already
              made to the issue.
              {issue.publicationDate && (
                <> Published {formatDate(issue.publicationDate)}.</>
              )}
            </p>
          </div>
        )}

        <Panel
          title="Running order"
          description={
            reorderable
              ? 'Drag a row, or use the arrows. The position is what the DOI suffix is derived from, so the order is part of the identifier — not a display preference.'
              : 'The order is fixed.'
          }
          actions={
            reorderable &&
            dirty && (
              <>
                <button
                  type="button"
                  onClick={() => setOrder(original)}
                  className="btn-ghost"
                  disabled={saving}
                >
                  <Undo2 className="h-4 w-4" aria-hidden="true" />
                  Reset
                </button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                  {saving ? <Spinner /> : null}
                  {saving ? 'Saving…' : 'Save order'}
                </button>
              </>
            )
          }
        >
          {articles.length === 0 ? (
            <EmptyState
              icon={FileText}
              title="No articles in this issue"
              body="An issue with no articles cannot be published. Create an article and place it in this issue."
            >
              {can.manageArticles && (
                <Link href={newArticleHref(journal.id)} className="btn-primary">
                  <Plus className="h-4 w-4" aria-hidden="true" />
                  New article
                </Link>
              )}
            </EmptyState>
          ) : (
            <ol className="space-y-3">
              {order.map((id, index) => {
                const article = byId.get(id)
                if (!article) return null

                return (
                  <li
                    key={id}
                    draggable={reorderable}
                    onDragStart={(e) => e.dataTransfer.setData('text/plain', String(id))}
                    onDragOver={(e) => reorderable && e.preventDefault()}
                    onDrop={(e) => {
                      if (!reorderable) return
                      e.preventDefault()
                      const dragged = Number(e.dataTransfer.getData('text/plain'))
                      if (!Number.isNaN(dragged)) dropOn(dragged, index)
                    }}
                    className="rounded-lg border border-ink-200 bg-white p-4 transition-colors duration-200 hover:border-ink-300"
                  >
                    <div className="flex items-start gap-3">
                      {reorderable && (
                        <span
                          className="mt-1 cursor-grab text-ink-400 active:cursor-grabbing"
                          aria-hidden="true"
                        >
                          <GripVertical className="h-5 w-5" />
                        </span>
                      )}

                      <span className="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-100 font-mono text-xs font-semibold text-ink-700">
                        {formatNumber(index + 1)}
                      </span>

                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <ArticleBadge status={article.status} label={article.statusLabel} />
                          {article.section && (
                            <span className="text-xs text-ink-500">{article.section}</span>
                          )}
                          {!article.hasPdf && (
                            /* Icon + words: an advertised citation_pdf_url that 404s downgrades
                               the whole journal, so this cannot be a colour cue alone. */
                            <span className="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2 py-0.5 text-[11px] font-semibold text-gold-700">
                              <FileWarning className="h-3.5 w-3.5" aria-hidden="true" />
                              No PDF
                            </span>
                          )}
                        </div>

                        <h3 className="mt-1.5 font-serif text-lg leading-snug text-ink-900">
                          {article.title}
                        </h3>

                        <p className="mt-1 truncate text-sm text-ink-600">
                          {article.authors.length > 0 ? article.authors.join(', ') : 'No author yet'}
                          {article.pages ? ` · pp. ${article.pages}` : ''}
                        </p>

                        <p className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                          <span className="text-ink-500">DOI suffix</span>
                          {article.doiSuffix ? (
                            <span className="font-mono text-ink-900">{article.doiSuffix}</span>
                          ) : (
                            <span className="text-ink-500">
                              — not derivable until the article has a position
                            </span>
                          )}
                          {article.suffixFrozen && (
                            <span className="inline-flex items-center gap-1 text-ink-500">
                              <Lock className="h-3 w-3" aria-hidden="true" />
                              frozen at publication
                            </span>
                          )}
                        </p>
                      </div>

                      <div className="flex shrink-0 flex-col items-end gap-2">
                        {can.manageArticles && (
                          <Link
                            href={editArticleHref(article.id)}
                            className="btn-ghost px-3 py-1.5 text-xs"
                          >
                            <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                            Edit
                          </Link>
                        )}

                        {reorderable && (
                          <div className="flex gap-1">
                            <button
                              type="button"
                              onClick={() => move(index, -1)}
                              disabled={index === 0}
                              aria-label={`Move “${article.title}” up`}
                              className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border
                                         border-ink-300 text-ink-700 transition-colors duration-200
                                         hover:border-ink-900 hover:bg-ink-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                              <ArrowUp className="h-4 w-4" aria-hidden="true" />
                            </button>
                            <button
                              type="button"
                              onClick={() => move(index, 1)}
                              disabled={index === order.length - 1}
                              aria-label={`Move “${article.title}” down`}
                              className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border
                                         border-ink-300 text-ink-700 transition-colors duration-200
                                         hover:border-ink-900 hover:bg-ink-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                              <ArrowDown className="h-4 w-4" aria-hidden="true" />
                            </button>
                          </div>
                        )}
                      </div>
                    </div>
                  </li>
                )
              })}
            </ol>
          )}
        </Panel>
      </AdminShell>
    </>
  )
}
