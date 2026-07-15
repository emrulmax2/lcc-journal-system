import { useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { BookOpen, Lock, Pencil, Plus, X } from 'lucide-react'
import { AdminShell, EmptyState, Field, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import { IssueBadge } from '@/components/admin/Status'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate, formatNumber } from '@/lib/format'
import { issueHref, type AdminPage, type IssueStatus } from '@/lib/admin'

type Issue = {
  id: number
  number: number
  season: string | null
  status: IssueStatus
  statusLabel: string
  publicationDate: string | null
  articles: number
  isPublished: boolean
}

type Volume = {
  id: number
  number: number
  year: number
  issues: Issue[]
}

type Props = AdminPage<{ volumes: Volume[] }>

/**
 * The volume-grouped archive. Only ever rendered for an ISSUE-BASED journal — a continuous
 * one 404s on this route, and its tab does not exist.
 *
 * A PUBLISHED ISSUE IS READ-ONLY, and the UI says WHY, with a padlock and a sentence, before
 * an editor discovers it by clicking a button that refuses them. IssuePolicy is what actually
 * enforces it.
 */
export default function AdminIssues({ volumes, journal, can, journals, meta }: Props) {
  const [creatingVolume, setCreatingVolume] = useState(false)
  const [editingVolume, setEditingVolume] = useState<number | null>(null)
  const [creatingIssueIn, setCreatingIssueIn] = useState<number | null>(null)
  const [editingIssue, setEditingIssue] = useState<number | null>(null)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={journal.abbreviation ?? 'Journal'}
        title="Issues"
        description="Volumes, issues and their running order. A published issue is permanent: its articles carry live DOIs and printed page numbers."
        actions={
          can.manageIssues && (
            <button
              type="button"
              onClick={() => setCreatingVolume((v) => !v)}
              className="btn-primary"
            >
              <Plus className="h-4 w-4" aria-hidden="true" />
              New volume
            </button>
          )
        }
      >
        {creatingVolume && (
          <div className="mb-8">
            <VolumeForm
              journalId={journal.id}
              onDone={() => setCreatingVolume(false)}
              onCancel={() => setCreatingVolume(false)}
            />
          </div>
        )}

        {volumes.length === 0 ? (
          <EmptyState
            icon={BookOpen}
            title="No volumes yet"
            body="An issue-based journal collects its articles into numbered, paginated issues, inside a volume. The volume number renders into every DOI this journal mints."
          >
            {can.manageIssues && (
              <button type="button" onClick={() => setCreatingVolume(true)} className="btn-primary">
                <Plus className="h-4 w-4" aria-hidden="true" />
                Create the first volume
              </button>
            )}
          </EmptyState>
        ) : (
          <RevealGroup className="space-y-8" stagger={0.06}>
            {volumes.map((volume) => (
              <RevealItem key={volume.id}>
                <Panel
                  title={`Volume ${formatNumber(volume.number)}`}
                  description={`${volume.year} · ${volume.issues.length} ${
                    volume.issues.length === 1 ? 'issue' : 'issues'
                  }`}
                  actions={
                    can.manageIssues && (
                      <>
                        <button
                          type="button"
                          onClick={() =>
                            setEditingVolume(editingVolume === volume.id ? null : volume.id)
                          }
                          className="btn-ghost"
                        >
                          <Pencil className="h-4 w-4" aria-hidden="true" />
                          Edit volume
                        </button>
                        <button
                          type="button"
                          onClick={() =>
                            setCreatingIssueIn(creatingIssueIn === volume.id ? null : volume.id)
                          }
                          className="btn-secondary"
                        >
                          <Plus className="h-4 w-4" aria-hidden="true" />
                          New issue
                        </button>
                      </>
                    )
                  }
                >
                  {editingVolume === volume.id && (
                    <div className="mb-6">
                      <VolumeForm
                        journalId={journal.id}
                        volume={volume}
                        onDone={() => setEditingVolume(null)}
                        onCancel={() => setEditingVolume(null)}
                      />
                    </div>
                  )}

                  {creatingIssueIn === volume.id && (
                    <div className="mb-6">
                      <IssueForm
                        journalId={journal.id}
                        volumeId={volume.id}
                        onDone={() => setCreatingIssueIn(null)}
                        onCancel={() => setCreatingIssueIn(null)}
                      />
                    </div>
                  )}

                  {volume.issues.length === 0 ? (
                    <p className="text-sm text-ink-600">
                      This volume has no issues yet.
                    </p>
                  ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                      {volume.issues.map((issue) => (
                        <li key={issue.id}>
                          {editingIssue === issue.id ? (
                            <IssueForm
                              journalId={journal.id}
                              volumeId={volume.id}
                              issue={issue}
                              onDone={() => setEditingIssue(null)}
                              onCancel={() => setEditingIssue(null)}
                            />
                          ) : (
                            <div className="rounded-lg border border-ink-200 p-4 transition-colors duration-200 hover:border-ink-300">
                              <div className="flex flex-wrap items-center justify-between gap-3">
                                <Link
                                  href={issueHref(issue.id)}
                                  className="cursor-pointer font-serif text-lg text-ink-900 transition-colors duration-200 hover:text-brand-800"
                                >
                                  Issue {formatNumber(issue.number)}
                                  {issue.season ? ` — ${issue.season}` : ''}
                                </Link>
                                <IssueBadge status={issue.status} label={issue.statusLabel} />
                              </div>

                              <p className="mt-1.5 text-sm text-ink-600">
                                {formatNumber(issue.articles)}{' '}
                                {issue.articles === 1 ? 'article' : 'articles'}
                                {issue.publicationDate
                                  ? ` · published ${formatDate(issue.publicationDate)}`
                                  : ''}
                              </p>

                              {/* WHY it is read-only. Icon + words, never colour alone. */}
                              {issue.isPublished ? (
                                <p className="mt-3 flex items-start gap-2 rounded-md bg-ink-50 p-2.5 text-xs text-ink-700">
                                  <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                                  <span>
                                    Read-only. Its articles carry live DOIs and printed page
                                    numbers — reordering or renumbering would invalidate every
                                    citation already made to them.
                                  </span>
                                </p>
                              ) : (
                                can.manageIssues && (
                                  <button
                                    type="button"
                                    onClick={() => setEditingIssue(issue.id)}
                                    className="btn-ghost mt-2 px-3 py-1.5 text-xs"
                                  >
                                    <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                                    Edit
                                  </button>
                                )
                              )}
                            </div>
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

/* -------------------------------- Volume form ------------------------------ */

function VolumeForm({
  journalId,
  volume,
  onDone,
  onCancel,
}: {
  journalId: number
  volume?: Volume
  onDone: () => void
  onCancel: () => void
}) {
  const form = useForm({
    number: volume?.number ?? '',
    year: volume?.year ?? new Date().getUTCFullYear(),
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    const options = { preserveScroll: true, onSuccess: onDone }

    if (volume) {
      form.put(`/admin/volumes/${volume.id}`, options)
    } else {
      form.post(`/admin/journals/${journalId}/volumes`, options)
    }
  }

  return (
    <form
      onSubmit={submit}
      className="rounded-lg border border-brand-300 bg-brand-50/40 p-4"
      aria-label={volume ? 'Edit volume' : 'New volume'}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_1fr_auto]">
        <Field label="Volume number" htmlFor="volume-number" error={form.errors.number}>
          <input
            id="volume-number"
            type="number"
            min={1}
            value={form.data.number}
            onChange={(e) => form.setData('number', e.target.value)}
            className={INPUT}
            required
          />
        </Field>

        <Field label="Year" htmlFor="volume-year" error={form.errors.year}>
          <input
            id="volume-year"
            type="number"
            min={1900}
            max={2100}
            value={form.data.year}
            onChange={(e) => form.setData('year', Number(e.target.value))}
            className={INPUT}
            required
          />
        </Field>

        <div className="flex items-end gap-2">
          <button type="submit" disabled={form.processing} className="btn-primary">
            {form.processing ? <Spinner /> : null}
            {volume ? 'Save' : 'Create'}
          </button>
          <button type="button" onClick={onCancel} className="btn-ghost">
            <X className="h-4 w-4" aria-hidden="true" />
            Cancel
          </button>
        </div>
      </div>
    </form>
  )
}

/* --------------------------------- Issue form ------------------------------ */

function IssueForm({
  journalId,
  volumeId,
  issue,
  onDone,
  onCancel,
}: {
  journalId: number
  volumeId: number
  issue?: Issue
  onDone: () => void
  onCancel: () => void
}) {
  const form = useForm({
    volume_id: volumeId,
    number: issue?.number ?? '',
    season: issue?.season ?? '',
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    const options = { preserveScroll: true, onSuccess: onDone }

    if (issue) {
      form.put(`/admin/issues/${issue.id}`, options)
    } else {
      form.post(`/admin/journals/${journalId}/issues`, options)
    }
  }

  return (
    <form
      onSubmit={submit}
      className="rounded-lg border border-brand-300 bg-brand-50/40 p-4"
      aria-label={issue ? 'Edit issue' : 'New issue'}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Issue number" htmlFor={`issue-number-${issue?.id ?? 'new'}`} error={form.errors.number}>
          <input
            id={`issue-number-${issue?.id ?? 'new'}`}
            type="number"
            min={1}
            value={form.data.number}
            onChange={(e) => form.setData('number', e.target.value)}
            className={INPUT}
            required
          />
        </Field>

        <Field
          label="Season"
          htmlFor={`issue-season-${issue?.id ?? 'new'}`}
          hint="Optional — e.g. “Spring 2026”."
          error={form.errors.season}
        >
          <input
            id={`issue-season-${issue?.id ?? 'new'}`}
            type="text"
            value={form.data.season ?? ''}
            onChange={(e) => form.setData('season', e.target.value)}
            className={INPUT}
          />
        </Field>
      </div>

      <div className="mt-4 flex gap-2">
        <button type="submit" disabled={form.processing} className="btn-primary">
          {form.processing ? <Spinner /> : null}
          {issue ? 'Save issue' : 'Create issue'}
        </button>
        <button type="button" onClick={onCancel} className="btn-ghost">
          <X className="h-4 w-4" aria-hidden="true" />
          Cancel
        </button>
      </div>
    </form>
  )
}
